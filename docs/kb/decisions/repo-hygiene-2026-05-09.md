---
title: Repo hygiene — productie-checkout residu
type: decision
date: 2026-05-09
status: implemented
project: havuncore (cross-project)
---

# ADR — Repo hygiene cross-project

## Status

**Geïmplementeerd 2026-05-09** in 5 commits + cross-project rollout.

Aanleiding: HavunCore-webapp Project Status toonde "2 uncommitted files" voor HavunAdmin op productie. Lokale fix bleek effectloos — webapp kijkt naar `/var/www/{project}` op Hetzner, niet naar `D:\GitHub\`.

**Implementatie-trail:**

- Bonus-fix `havuncore-webapp@63ac38f` — PWA detecteert geen-git pads als red ipv silent green
- Laag 1 (cross-project `*env.bak*`): 8 commits over 8 repos, server-pulls op 7 checkouts
- Laag 2 (archief): 8 backup-files verplaatst naar `/var/backups/havun-env/{project}/` op server
- Laag 4 (KB-policy): `docs/kb/reference/repo-hygiene-policy.md`
- Laag 3 (`qv:scan --only=residu`): commits `havuncore@9e2b8c0` + simplify-pass `havuncore@be1e52b`. 102 tests passed; live run rapporteert 4 drift findings cross-project.

## Context

Het Havun-platform heeft drie zelfwerkende systemen:

| Systeem | Dekking |
|---------|---------|
| AutoFix | Runtime-errors → Claude-analyse + fix |
| K&V (`qv:scan`) | Composer/npm/SSL-veroudering cross-project |
| KB-indexer | Documentatie-versheid |

**Wat ontbreekt:** detectie + cleanup van **deployment-residu** in productie-checkouts (`.env.bak*`, build-uploads, storage-`.gitignore` drift, etc.). Deze rommel verschijnt wel in de Project Status PWA, maar zonder context en zonder cleanup-pad.

## Scope-meting (server, 2026-05-09)

Scan over 8 PWA-projecten op Hetzner:

| Project | Uncommitted | `.env.bak*` | Andere residu |
|---------|-------------|-------------|---------------|
| HavunCore | 3 docs (M) | 1 | – |
| Herdenkingsportaal | M+?? | 2 | `public/fonts/` |
| HavunAdmin | 2 (`.env.bak*`) | 2 | – |
| Infosyst | 12 (10× storage `.gitignore` M + .env.bak + zip) | 1 | `public/downloads/*.zip` |
| Studieplanner | 5 (.env.bak* + downloads/ota/favicon) | 3 | `downloads/`, `ota/`, favicon |
| SafeHavun | 0 | 0 | – (cleanste) |
| JudoToernooi | n/a — geen `.git` op `/var/www/judotoernooi/laravel` | ? | ? |
| JudoScoreBoard | n/a — geen `.git` op `/var/www/judoscoreboard` | ? | ? |

**Patronen:**

1. **`.env.bak-20260423-1656xx`** zit op 5 van 6 servers met `.git` → één batch-actie tijdens SSL-rotatie 23-04. Eigenaar: `root` (handmatige sudo, niet deploy-script).
2. **Twee naam-conventies door elkaar:** `.env.bak-YYYYMMDD-HHMMSS` (compact) en `.env.bak.YYYY-MM-DD` (datum). Wijst op losse ad-hoc sessies zonder centrale conventie.
3. **Laravel storage `.gitignore` drift** (Infosyst): `bootstrap/cache/.gitignore`, `storage/app/.gitignore` etc. — bekend Laravel-issue waar `composer install`/`npm install` deze bestanden kan regenereren.
4. **Build/upload-output ongetracked**: `public/downloads/`, `public/ota/`, `public/fonts/`, `public/favicon.png` — niet in `.gitignore`.
5. **2 PWA-projecten zonder `.git`:** JudoToernooi en JudoScoreBoard. PWA toont ze tóch groen — `getProjectStatus()` faalt **stil** in plaats van een "geen git"-status terug te geven.

## Beslissing

Vier lagen + één bug-fix.

### Laag 1 — Preventie (cross-project `.gitignore`)

Voeg toe aan `.gitignore` van alle 8 projecten:

```gitignore
# Env backups (handmatig of via deploy-script)
.env.bak
.env.bak.*
.env.bak-*

# Build/upload-output (per project bepalen of dit klopt)
/public/downloads/
/public/ota/
/public/fonts/
```

**Niet alle paden gelden voor alle projecten** — `public/fonts/` is HP-specifiek, `public/ota/` is Studieplanner-specifiek. Per project verifiëren voordat ik regels toevoeg.

Update ook **scaffold-template** (`templates/server-configs/` of `project:scaffold`-output) zodat nieuwe projecten dit standaard hebben.

### Laag 2 — Cleanup nu

Voor elke server-checkout:
1. Verifieer dat `.env` zelf intact is en functioneel.
2. Archiveer `.env.bak*` ouder dan 30 dagen naar `/var/backups/havun-env/{project}/` (root-only, 600).
3. Bestanden < 30 dagen blijven staan (recente, mogelijk nodig).
4. Op productie `git pull` zodra Laag 1 gepushed is.

**Geen `rm`** — alleen `mv` naar archief. Reden: dit zijn credential-files. Verlies = drama. Archief = veilig.

### Laag 3 — Detectie (uitbreiding K&V)

Nieuwe artisan-command: `php artisan qv:scan-residu` (of als sub-check binnen bestaande `qv:scan`):

- Scant elk project op de server (via SSH of agent op de Hetzner-kant).
- Detecteert: `.env.bak*` ouder dan 30d, `*.old`, `tmp*-cwd`, build-output buiten `.gitignore`, storage-`.gitignore` drift.
- Output naar HavunCore dashboard + log.
- Geïntegreerd met scheduler (wekelijks, naast bestaande K&V-cron).

### Laag 4 — KB-policy + naamconventie

Nieuwe doc: `docs/kb/reference/repo-hygiene-policy.md`:

- **Naamconventie backup-files:** `.env.bak.YYYY-MM-DD-HHMMSS` (één formaat, sortable, leesbaar).
- **TTL:** 30 dagen lokaal in checkout → archief, 90 dagen in archief → verwijderen.
- **Eigenaar:** elk script/handmatige sessie die een backup maakt, gebruikt deze conventie + locatie.
- **Cross-project:** dit is de standaard voor alle Havun-projecten.

### Bonus-fix — PWA toont JudoToernooi/JudoScoreBoard ten onrechte groen

`projectStatusService.js:62-96` faalt stil als `git`-commands errors geven. Bug:

- `Promise.allSettled` verwerkt fouten, maar als alle drie de git-commands falen (geen `.git`), blijft `info.dirty=false` en `info.dirtyFiles=0` → status 'green' met label 'OK'.
- Gewenst: detecteer "geen git-repo" expliciet → status 'red' met label "geen git-repo op pad X".

Aparte fix in dezelfde commit-serie of als losse PR.

## Implementatie-volgorde

1. **Bonus-fix (PWA detectie geen-git)** — kleinste wijziging, zichtbaar effect.
2. **Laag 1 (`.gitignore` rollout)** — per-project commit + push, dan op server `git pull`.
3. **Laag 2 (cleanup nu)** — archiveer-script eenmalig draaien, verifieer alle servers schoon.
4. **Laag 4 (KB-policy)** — doc opstellen voordat Laag 3 erop kan refereren.
5. **Laag 3 (`qv:scan-residu`)** — implementatie + scheduler-integratie.

## Niet-doen (out of scope)

- Geen automatische `rm` op backups — alleen `mv` naar archief.
- Geen rewrite van git-history om eerder gecommitte `.env.bak`-files te verwijderen (geen aanwijzingen dat dit gebeurd is — `git status --porcelain` toont alleen untracked).
- Geen wijzigingen aan deploy-scripts in deze ADR — backups-tijdens-deploy is een andere discussie (Laag 4 policy zou daar wel het naam-conventie-handvat geven).

## Risico's

| Risico | Impact | Mitigatie |
|--------|--------|-----------|
| `.env.bak*` archiveren maar `.env` zelf was corrupt → terug-rollen onmogelijk | Hoog | Pre-check: `.env` geldig + app draait, vóór archief-stap. |
| `.gitignore` regel verbergt legitieme uncommitted file | Laag | Patronen zijn specifiek (`.env.bak*` is per definitie backup). |
| `qv:scan-residu` schrijft per ongeluk in productie | Hoog | Read-only scan, output naar dashboard, géén auto-cleanup zonder expliciete admin-action. |

## Open vragen

- Wil je dat `qv:scan-residu` een **auto-cleanup-knop** in de PWA krijgt (Laag 3 + extra UI), of blijft cleanup altijd handmatig via SSH/admin?
- TTL 30/90 dagen — akkoord of andere getallen?
- Locatie archief: `/var/backups/havun-env/` of in HavunCore Vault?
