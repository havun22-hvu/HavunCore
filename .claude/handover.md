---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-06-07
---

# HavunCore — Handover

> Vul dit aan aan het einde van elke sessie. Houd het kort — wat een volgende Claude-sessie direct nodig heeft.

## Huidige status

**Branch:** master (schoon, alles gepusht)
**Laatste werk:** In-app health-meldingen (Fase 1) — mail vervangen door notificaties in de HavunCore-webapp.

## Wat is er gedaan (6-7 juni)

### Incident: reverb 2,5 dag down (opgelost)
- MySQL-restart 4 jun 06:21 liet reverb prod+staging in **FATAL** (supervisor herstelt daar niet uit). `supervisorctl restart reverb reverb-staging` → opgelost. Nieuw scenario §6 in `reverb-troubleshoot.md`.

### 3 monitoring-gaten gevonden + gedicht
1. Reverb werd niet bewaakt → `check_reverb()` in health-check.
2. JudoToernooi werd op dode URL `judotoernooi.havun.nl` (geen vhost) bewaakt → gecorrigeerd naar `judotournament.org`.
3. Alert-mail faalde stil (SendGrid `Maximum credits exceeded`).

### Fase 1: in-app health-meldingen (mail → webapp)
- **HavunCore (master):** migratie `health_alerts`, model `HealthAlert`, command `health:alert`, `HealthAlertController` (`GET /api/health-alerts`, `POST /{id}/dismiss`), config `services.webapp_notify_url`. 6 tests, suite 1243 groen.
- **Server-script** `scripts/havun-health-check.sh`: mail eruit, roept nu `php artisan health:alert` (stateless, DB dedupet). `havun-health-alert.php` = DEPRECATED.
- **webapp-repo (havuncore-webapp, main):** intern localhost-endpoint `/api/internal/notify` → `io.emit('health-alert')`; frontend `useHealthAlerts`-hook + `NotificationBell` (badge + paneel, gegroepeerd op scope/project) in de Header.
- **Keuzes Henk:** in-app paneel (geen PWA-push), gefaseerd (Fase 2 = per-app later), UptimeRobot als externe vangnet, GEEN eigen mail.

### DEPLOY-STATUS (7 jun, nacht)
**LIVE + geverifieerd op prod (188.245.159.115):**
- Laravel: `git merge origin/master` (prod had auto-commit-divergentie, conflictloos), `migrate --force` → `health_alerts` tabel aangemaakt, caches geleegd.
- Script `/usr/local/bin/havun-health-check.sh` vervangen door de artisan-versie (backup: `.bak-pre-artisan`). Draait schoon (healthy = 0 alerts). Mail-pad weg.
- nginx: `health-alerts` toegevoegd aan de Laravel-allowlist in `sites-enabled/havuncore.havun.nl` (backup `/root/havuncore.havun.nl.bak-2026-06-07`), `nginx -t` ok, reloaded. `GET /api/health-alerts` → `{"success":true,"open_count":0,"data":[]}`.
- E2E getest: command down→DB→API→resolve→cleanup. ✓

**Webapp-UI nu OOK live (7 jun, na "het kan nu"):**
- `havuncore-webapp` `main` gereconcilieerd: jouw WIP veilig in `stash@{0}` ("henk-wip-claude-commands-2026-06-07"), mijn feature gerebased op origin (duplicaat-commit `dd43bbe` auto-dropped), `server.js`-conflict opgelost (origin's skip-lijst + `/internal/`), gepusht `27b2f5a..1bd11b1`.
- Server-webapp (`/var/www/havuncore/webapp`): `git pull` (ff), frontend gebuild, `dist`→`public` (extras apk/downloads behouden, oude assets opgeruimd), `pm2 restart havuncore-backend`.
- Geverifieerd live: PWA 200, `/api/internal/notify` 200 (real-time bridge), `/api/health-alerts` 200, volledige E2E (command→DB→ping→API→resolve) ✓.

**NOG TE DOEN door Henk:**
1. **Browser-check**: 🔔 bel-badge + paneel + layout (mobiel + desktop) — visueel.
2. **Je WIP terughalen**: `cd D:\GitHub\HavunCore\webapp && git stash pop` (stash@{0}). De `.claude`-command files (mpc/start/CLAUDE/wu) zijn op origin gewijzigd, dus pop kan conflicten geven — even nakijken.
> Let op: `sites-available/havuncore.havun.nl` is een losse file (geen symlink) en wijkt af van `sites-enabled` — pre-existing; alleen `sites-enabled` is geladen.

## Wat is er recent gedaan (31 mei)

### IDSee — Midnight Network kennisbank aangelegd
- `docs/midnight/OVERVIEW.md` — platform architectuur, SDK, roadmap status (Kolu actief!)
- `docs/midnight/ZK-PATTERNS.md` — commitment/nullifier/Merkle patronen + 3 IDSee circuits uitgewerkt
- `docs/midnight/COMPACT-LANGUAGE.md` — Compact DSL syntax, types, Midnight.js integratie (TypeScript-achtig, NIET Rust)
- `docs/midnight/INTEGRATION-PLAN.md` — fasering fase 0-4, nieuwe services, DB schema
- `docs/midnight/HOSKINSON-CONTEXT.md` — video samenvatting incl. Hawaiian roadmap (Kolu=actief, Mahalu=Q2, Ua=Q3 2026)
- `docs/contracts/VERIFICATION.md` — pseudo-code gecorrigeerd van Rust naar Compact
- Memory opgeslagen: `project_midnight_network.md` — Midnight voor IDSee én Aeterna

### Midnight gebruik: IDSee + Aeterna
- **IDSee**: anonieme ZK-verificatie fokkers/dierenartsen/chippers
- **Aeterna**: zelfde patroon (use case nog te concretiseren)
- Academy: https://academy.midnight.network (gratis, 3 certificaten — doorlopen vóór implementatie)

### Globale settings fix — autoMode MD-bestanden
- `~/.claude/settings.json`: `autoMode.allow` uitgebreid met patronen voor handover.md, context.md, HANDOVER.md, CLAUDE.md
- Reden: extension vroeg steeds om bevestiging bij MD-edits buiten `.claude/*.md`

## Openstaande punten

- **Health-meldingen Fase 2**: project-meldingen óók in de betreffende app tonen (via `GET /api/health-alerts?project=<naam>`) — per app, aparte sessies. Fase 1 (centraal in HavunCore-webapp) is live.
- **Mailprovider**: SendGrid zit op creditlimiet. Eigen mail is nu helemaal uit (in-app + UptimeRobot ipv). Henk parkeerde de keuze om SendGrid bij te laden of naar Resend te gaan — alleen relevant als er ooit weer mail nodig is. Zie [[project-health-alerts-broken]].
- **NotificationBell in browser testen**: door Henk visueel te checken (badge/paneel/layout) — Claude kan browser-UI niet testen.
- **JudoScoreBoard**: pre-publish review via dynamic workflow (eerste echte dynamic workflow sessie)
- **Aeterna**: Week 2-plan wacht op go/no-go van Henk + Midnight use case concretiseren
- **HavunAdmin**: Alpine CSP-migratie 21 views open
- **IDSee Midnight**: Fase 0 = Academy doorlopen vóór implementatie begint
- ~~Dutch error string in `HavunPackCommand::fetchApiSamples()`~~ ✓ opgelost 6 jun (nu Engels: `timeout or connection error`)
- ~~`sync-start-command.md` runbook heeft incomplete projectlijst~~ ✓ opgelost 6 jun (tabel gesynct met projects-index + Havun/Studieplanner-api/IDSee/JudoScoreBoard/VPDUpdate toegevoegd)

## Lopende projecten (per project)

| Project | Status |
|---------|--------|
| JudoScoreBoard | Play Console screenshots OK — pre-publish review via dynamic workflow |
| Aeterna | Feature-complete — Week 2-plan wacht op go/no-go + Midnight use case |
| SafeHavun | Stabiel v1.1.3 |
| Herdenkingsportaal | Stabiel |
| JudoToernooi | Stabiel |
| HavunAdmin | Stabiel — Alpine CSP-migratie 21 views open |
| IDSee | Midnight KB aangelegd — klaar voor Fase 0 (Academy) |
| Munus | **GEPARKEERD** |
| Studieplanner | In ontwikkeling — geen bekende open items |

## Architectuurprincipes

- **Gemini** = architect + brainstorm (groot contextvenster, tweede mening) — via `/arch` of automatisch in dynamic workflow
- **Claude dynamic workflow** = grote taken (ultracode mode) — roept Gemini aan, implementeert parallel, test, commit
- **Claude normaal** = kleine fixes (< 5 bestanden, afgebakend)
- Memory flow: `/mem` → leest `C:/Users/henkv/.claude/projects/[SLUG]/memory/MEMORY.md`
- Bij config-issues na wijziging `havun-projects.php`: altijd `php artisan config:clear`
- Doc Intelligence MEDIUM duplicaten zijn vrijwel altijd false positives — bulk-negeren is correct
- **Midnight**: Compact = TypeScript-achtige DSL (niet Rust). Backend genereert proofs server-side — gebruikers zien nooit blockchain.
- **autoMode.allow**: handover.md en context.md staan nu globaal in de allow-lijst (`~/.claude/settings.json`)
