---
title: Repo Hygiene Policy — backup-files, residu, deploy-output
type: reference
scope: cross-project
last_check: 2026-05-09
---

# Repo Hygiene Policy

> Cross-project regels voor `.env`-backups, deploy-residu en build-output op productie-checkouts.
> **Single source of truth** — referentie vanuit `.gitignore`-rollouts, deploy-scripts en `qv:scan-residu`.
> Achtergrond + diagnose: [`decisions/repo-hygiene-2026-05-09.md`](../decisions/repo-hygiene-2026-05-09.md).

## .env backup-files

### Naamconventie (verplicht)

```
.env.bak.YYYY-MM-DD-HHMMSS
```

Voorbeeld: `.env.bak.2026-05-09-143015`

**Geen alternatieve formaten** — geen `.env.bak-YYYYMMDD-HHMMSS` (geen scheidingsteken), geen `.env.bak.YYYY-MM-DD` (geen tijd). Eén canoniek formaat zorgt voor sortable, leesbare en eenduidig parseerbare filenamen voor `qv:scan-residu`.

### Wanneer een backup maken

- Voor **elke handmatige `.env`-wijziging** op productie (SSL-rotatie, secret-roll, app-config update).
- Voor **elke deploy-script run** die `.env` aanraakt (template-render, secret-injection).
- **Niet** voor reguliere code-deploys die `.env` ongemoeid laten.

### Locatie

Backups worden in dezelfde directory als `.env` zelf gemaakt — `/var/www/{project}/production/.env.bak.…`. Niet in `/tmp/`, niet in een aparte sub-directory.

### TTL — lifecycle

| Fase | Locatie | Duur | Actie aan einde |
|------|---------|------|-----------------|
| Actief | naast `.env` (in checkout) | 14 dagen | `mv` naar archief |
| Gearchiveerd | `/var/backups/havun-env/{project}/` | 90 dagen | `rm` |

**Pre-check vóór archief-stap:** `.env` bestaat én size >100 bytes. Anders stoppen — de backup is mogelijk je enige werkende copy.

**Geen `rm` zonder eerst archief.** Backups bevatten credentials; verlies = drama.

### Archief-eigenschappen

| Eigenschap | Waarde |
|------------|--------|
| Pad | `/var/backups/havun-env/{project}/` |
| Eigenaar | `root:root` |
| Dir-permissies | `700` |
| File-permissies | `600` |

### .gitignore — verplichte regels

Elk Havun-project `.gitignore` bevat:

```gitignore
# .env backups (cross-project hygiene policy — repo-hygiene-policy.md)
*env.bak*
```

Trailing wildcard is essentieel — matcht zowel `.env.bak` als `.env.bak.2026-05-09-143015`. Zonder de trailing `*` blijven timestamped backups zichtbaar als untracked in `git status`.

## Andere deploy-residu (informatief)

Deze patronen verschijnen ook regelmatig in productie-checkouts. **Per-project beslissen** of ze in `.gitignore` horen — geen blanket cross-project rollout zonder context.

| Pattern | Voorkomen | Aanbeveling |
|---------|-----------|-------------|
| `tmpclaude-*-cwd` | Claude-Code temp-mappen | `tmpclaude-*` in `.gitignore` (per-project) |
| `*.old` | Handmatige edit-backups | Cross-project `.gitignore` (cosmetisch, geen creds) |
| `public/downloads/*.zip` | Build-uploads of klantdownloads | Project-specifiek — kan legitiem getrackt zijn |
| `public/ota/`, `public/fonts/` | OTA-bundles, fontfiles | Project-specifiek (Studieplanner OTA wel ignore) |
| Laravel `storage/.../.gitignore` drift | `composer install` regenereert | Bekend Laravel-issue, geen action nodig |

## Detectie en handhaving

**`php artisan qv:scan --only=residu`** is een sub-check van het bestaande `qv:scan` Quality & Safety systeem. Draait via SSH vanaf de scan-runner naar de productie-server en rapporteert:

- `.env.bak*` ouder dan 14d in productie-checkout (`informational` finding, candidate voor archief)
- Files in `/var/backups/havun-env/{project}/` ouder dan 90d (`informational` finding, candidate voor `rm`)
- Backups die niet aan canonical naamconventie voldoen (`low` finding, drift)

Output gaat door dezelfde flow als andere `qv:scan` findings: persisted in `qv-scans/` storage, JSON-renderbaar, integreerbaar met dashboard / scheduler.

Voorbeeld:

```bash
php artisan qv:scan --only=residu --json
php artisan qv:scan --only=residu --project=havunadmin
```

**Géén auto-cleanup** — alleen detectie + voorstel; uitvoering blijft handmatig of via expliciete admin-action. Verwijderen van een productie-`.env`-backup zonder menselijke check is per definitie te risicovol (zie risk-tabel in ADR).

## Eigendom

Iedere actor die een `.env`-backup maakt — of dat nu Claude in een sessie is, een deploy-script of een handmatige `cp`-commando — gebruikt deze conventie. Inconsistente naam-formaten verraden ad-hoc werk en zijn een drift-signaal.

## Mobile-monitoring Vault PAT

De PWA mobile-project monitoring (zie `decisions/mobile-project-monitoring-2026-05-09.md`) gebruikt een GitHub PAT die in HavunCore Vault ligt — niet op disk. Dezelfde lifecycle-discipline als `.env.bak*` geldt voor deze secret.

### Rotation cadens

- **PAT (in Vault als `github_pat_ro`)**: 90 dagen geldig — fine-grained, read-only, beperkt tot de mobile-repos.
- **Vault project token (`hvn_…` in PWA `.env.production` als `VAULT_PROJECT_TOKEN`)**: roteren bij verdenking compromise of bij personeel-wisselingen.

### Rotation-procedure

```bash
# 1. Genereer nieuwe PAT op github.com (jouw account, fine-grained, expiratie + 90d)
# 2. Update secret + (optioneel) project token via setup-command
ssh hetzner
GITHUB_PAT_RO='github_pat_NEW' php artisan vault:setup-mobile-monitoring --from-env
# Met --rotate-token forceer je ook een nieuw Vault Bearer-token (bij compromise)

# 3. Restart PWA zodat de nieuwe PAT in-memory geladen wordt
sudo -u www-data pm2 restart havuncore-backend
```

De `vault:setup-mobile-monitoring` command is idempotent — re-running met een nieuwe PAT update het bestaande secret zonder zijdelings effect.

## Wijzigingshistorie

| Datum | Wijziging |
|-------|-----------|
| 2026-05-09 | Initieel — uit `decisions/repo-hygiene-2026-05-09.md` Laag 4 |
| 2026-05-09 | Mobile-monitoring Vault PAT-sectie toegevoegd |
