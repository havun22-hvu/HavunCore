---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-07-15
---

# HavunCore — Handover

> Nieuwste sessie bovenaan, 15-30 regels per sessie. Sessies ouder dan ~3 maanden weg — git
> bewaart de historie. Regel: `docs/kb/standards/md-doc-grootte.md`.

**Branch:** master

## Open — wacht op Henk

| Wat | Waar |
|-----|------|
| **Blijvend-ingelogd-plan** — geschreven, wacht op "ga maar" | `docs/kb/plans/blijvend-ingelogd-plan.md` |
| **PWA-login prod-go per app** — HavunClub (main-merge+deploy), JT (deploy-knop), HP, webapp (build+rsync+pm2) | eerst testen op staging |
| **VPDUpdate passkey-fix** `f6f5e1a` — klaar, niet gedeployed | `deploy vpd` = git pull op server |
| GitGuardian incident #33883984 op *Resolved* zetten | GitGuardian UI |
| WIP terughalen in webapp-repo | `git stash pop`, stash@{0} |
| **KB-chunking** — lange docs worden alleen op hun begin geëmbed, staart onvindbaar | raakt schema, aparte taak |

## Open — eigen project-sessie (hier alleen genoteerd)

- **HavunClub — Cees ziet 0 judoka's i.p.v. 229.** Geen data-bug. Cees is `is_platform_eigenaar=true`
  → `ClubScope.php:27-33` haalt zijn club uit `session('club_id')` (club-switcher), niet uit
  `$user->club`. Directe fix: switcher op "Judoschool Cees Veen". Structureel = business-keuze Henk.
  **Bijvangst-lek:** `Aanwezigheid/BandExamen/Dashboard` doen `Judoka::where('status','actief')`
  zónder club_id-filter → tenant-lek.
- **HavunAdmin** — hardcoded staging Bearer-token in `docs/05-api-integration/API-SYNC-HERDENKINGSPORTAAL.md`
  (r257+423). Opruimen + purgen. Zie [[feedback-no-hardcoded-test-secrets]].
- **VPDUpdate** — `users.json` is getrackt met bcrypt-hashes van Henks account. Hoort niet in git.
- **havuncore-webapp** — update-banner activeert de wachtende SW niet zichtbaar (pas na app-herstart);
  verdenk ontbrekende `clientsClaim`/`controllerchange`. Plus: push-frontend (SW + subscribe-knop bij
  de 🔔) — backend staat al live sinds 2 juli.
- **Reverb/monitoring** — geen actief kanaal voor `critical` alerts (leeft alleen in het passieve
  webapp-paneel); `laravel-worker` + `toernooi-heartbeat` worden niet bewaakt. Zie
  `runbooks/uptime-monitoring.md` §Bekende gaten.
- **Docs te lang** (nieuwe regel, zie hieronder): handovers JudoToernooi 835, HavunClub 559,
  JudoScoreBoard 351, Herdenkingsportaal 243, IDSee 227 regels. CLAUDE.md van Havun (128) en
  Studieplanner-api (147) net over de 120.
- **Server** (Henks go, raakt config): `havuncore.havun.nl.bak.2026-07-02` hangt nog in
  `sites-enabled` → "conflicting server name"-warnings. HavunVet = obsoleet, vhost mag weg.
  Composer server-side updaten (PHP 8.4 `${var}`-deprecation — cosmetisch).

## Sessie 15 juli — scoreboard-security + KB draaide op keyword-search

**1. JudoToernooi ↔ JudoScoreBoard security-review** (Henk wil externe testers toelaten).
Review: `docs/kb/reference/scoreboard-api-security-review-2026-07-15.md`. App-kant kwam schoon uit
(geen hardcoded secrets, SecureStore, cleartext uit, historie schoon) — alleen docs gecorrigeerd.
Vier lekken in JT, alle gefixt (`f3445e46` + reset-fix, main):
- `/result` scoopte niet op het toernooi van het token → elk token kon uitslagen zetten op élk toernooi.
- `/event` broadcastte het hele `DeviceToegang`-record (incl. `api_token`) op een **publiek** kanaal —
  empirisch bewezen vóór de fix. Oorzaak: `$request->merge()` zet het model in de input-bag.
- **"Reset" nulde `api_token` niet** → een gereset device schreef gewoon door. Reset trekt nu écht in
  (token weg, device los, **nieuwe code**); `resetAll` idem. Correctie op Henks aanname dat dit al werkte.
- Geen rate limit → `throttle:scoreboard` 120/min **per token** (niet per IP: één NAT-IP per zaal).
Plus `config/cors.php` (wildcard weg) en een 500 bij ontbrekende optionele `updated_at`.
**Bewust geaccepteerd door Henk:** publieke Reverb-kanalen (data = wat in de zaal op het scherm staat;
lekt na fix geen token meer) en JSB die scores schrijft (jury corrigeert achteraf in de webapp).

**2. 🔴 KB-kernbug: alle 2758 embeddings waren woordmaps, geen vectoren** (`2c43318`). De KB deed
maandenlang keyword-matching terwijl `CLAUDE.md` semantisch zoeken belooft. Drie oorzaken:
`generateLocalEmbedding()` geeft altijd een gevulde array → het label zei altijd `nomic-embed-text`
(`tfidf-fallback` was onbereikbare code, dus geen enkel signaal); `indexFile()` skipt op
`content_hash` → degraded rijen herstelden nooit; en Ollama serveert nomic met ~2048 tokens context
(niet 8192) en weigert langere input met een 500, terwijl de code op 8000 **tekens** afkapte.
Fix: labeling gesplitst, herstel-trigger die de fallback op **vorm** herkent (self-healing), en
adaptieve truncatie 8000→4000→2000. Herindexering: **2764/2764 echte 768-dim vectoren**.
Details: `docs/kb/reference/doc-intelligence-embedding-fallback-bug.md`.

**3. Nieuwe bindende regel: MD-doc grootte** (`docs/kb/standards/md-doc-grootte.md`), verwerkt in de
CLAUDE.md van **alle 22 projecten**. Aanleiding: Henk — "anders kan Claude het ook niet allemaal
lezen, laatste tekst wordt zinloos". Extra grond: de KB indexeert alleen het begin van een bestand.

## Sessie 14 juli — PWA-logins portfolio-breed + webapp-deploy

- **KB-standaard `patterns/havun-mobile-login.md` (BINDEND):** device-detectie via `pointer:coarse`,
  QR nooit op smartphone, bio-detect met 2s-timeout, volgorde bio→magic link→wachtwoord.
- **HavunClub** (staging live): WEBAUTHN_ID/ORIGINS/NAME op prod+staging gezet — dát was de bio-bug;
  **oude passkeys zijn dood, opnieuw registreren**. 353 groen.
- **havuncore-webapp LIVE**: bio-login-fix op prod, `webauthn/available` geeft nu echt antwoord.
  Henk bevestigt: biometrie werkt op zijn telefoon.
- **JudoToernooi** (`140045ab`, staging) + **Herdenkingsportaal** (`e961edf`, niet gedeployed).
- **Vusista** opgezet (fotoalbum-webapp, eigen repo, :8008, staging+prod op server, CI/deploy werkend).
  Spec staat als `.claude/blueprint.md` → Vusista-sessie start met `/mpc` + "ga maar".
- **JudoToernooi AutoFix-alert** bleek achterhaald: prod was consistent; restschuld was één open
  `system_alert` id=3 (26 juni) die nooit gesloten was. Gesloten na DB-backup. KB: `reference/autofix.md`.

## Sessie 4 juli — reis-sync + branch-cleanup

Alle 21 repos gesynct naar GitHub; 5 werkprojecten op a=0 b=0 dirty=0 (JudoToernooi=main,
HavunClub=main+staging, HavunAdmin=main, HavunCore=master, Agorano=master).
**Deploy-infra (1-2 juli):** `scripts/setup-deploy-key.sh <Repo>` + `/root/deploy-havun.sh` op de
server; handmatige prod-knop per app (workflow_dispatch), **nooit auto-migrate op prod**.
Runbook: `docs/kb/runbooks/deploy-keys-github-actions.md`. **Eerste prod-deploy per app = Henk klikt.**
havuncore-webapp deployt bewust anders (lokaal build → rsync + pm2), zie `havuncore-webapp/DEPLOY.md`.

> Oudere sessies (8 juni — 26 juni: integratiecontract 3 SaaS-apps, forms-coverage, test-quality §11,
> Doc Intelligence-kalibratie, Playwright-uitrol, Agorano-opzet) staan in de git-historie van dit
> bestand. Contracten/beleid die daaruit voortkwamen leven in `docs/kb/` en zijn daar leidend.
