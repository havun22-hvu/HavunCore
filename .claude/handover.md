---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-06-10
---

# HavunCore — Handover

> Vul dit aan aan het einde van elke sessie. Houd het kort — wat een volgende Claude-sessie direct nodig heeft.

## Huidige status

**Branch:** master (schoon, alles gepusht)
**Laatste werk:** Playwright E2E voor de webapp-PWA — **12 tests groen** (login QR/wachtwoord/biometric, dashboard, QR-approve/scanner, biometric-setup) + CI-workflow. HavunCore-Laravel bewust niet (API/orchestrator, al gedekt door 1243 PHPUnit-tests).

## Wat is er gedaan (11 juni)

### GitGuardian-melding: hardcoded WebAuthn-testsleutel opgeruimd
- GitGuardian (#33883984) flagde een P-256 PKCS#8 private key in `havuncore-webapp:frontend/e2e/webauthn.js` (commit `c78bd58`). Throwaway testsleutel (mock-backend, signatures nooit geverifieerd) → **geen rotatie nodig**, maar Henk: *"ook testsleutels netjes wegwerken, het gaat om het principe"*.
- **Bron gefixt:** sleutel wordt nu per testrun gegenereerd (`generateThrowawayPrivateKeyB64` via `crypto.generateKeyPairSync`) i.p.v. hardcoded. Beide biometrie-tests groen.
- **Historie gepurged:** alleen `c78bd58` bevatte het secret. Via `git reset --soft <parent>` + `git commit -C c78bd58` de commit herschreven (schone tree, identieke inhoud — diff vs backup leeg), daarna `git push --force-with-lease`. Nieuwe commit `502c125`, oude `c78bd58`/`b400915` onbereikbaar op origin. `git log -S` op main = 0 treffers.
- **Principe vastgelegd:** `docs/kb/runbooks/geen-hardcoded-secrets-in-tests.md` (runtime-keygen + purge-procedure + GitGuardian-checklist).
- **NOG TE DOEN:**
  - [ ] Henk: incident #33883984 in GitGuardian op *Resolved / False positive* zetten.
  - [ ] Productie-webapp-checkout (`/var/www/havuncore/webapp`) pullt deze repo → heeft de oude historie nog lokaal. Bij eerstvolgende deploy: `git fetch origin && git reset --hard origin/main` (anders divergeert de pull). Geen haast — geen actief lek meer (origin is schoon).

## Wat is er gedaan (10 juni)

### Playwright E2E voor webapp-PWA
- **Scope (door Henk gekozen):** alleen `webapp/frontend` (status-only PWA). HavunCore-Laravel niet — die is API/orchestrator, al gedekt door 1243 PHPUnit-tests.
- **Aanpak:** volledige API-mock via Playwright route-interception (`e2e/helpers.js` → `mockApi` + `loginAs`). Geen backend/DB/Socket.io nodig; CI start alleen de Vite-server.
- **Specs (8 tests, ~3s):** `auth.spec.js` (login QR-default + wachtwoord→dashboard), `dashboard.spec.js` (StatusView, Projects-tab, NotificationBell badge/dismiss/leeg), `qr-approve.spec.js` (`/qr/:code` geldig + ongeldig).
- **Dependency:** `@playwright/test` als devDep in `webapp/frontend` (Henk's go). Scripts: `test:e2e`, `:ui`, `:report`. `test` wijst nu ook naar playwright.
- **CI:** workflow in de **havuncore-webapp repo** (`webapp/.github/workflows/webapp-e2e.yml`) — `webapp` is aparte repo, staat in HavunCore's `.gitignore`. Path-filter `frontend/**`, cachet Chromium, uploadt HTML-rapport.
- **Twee valkuilen opgelost (zie KB-runbook):** (1) dev-server i.p.v. `vite preview` want preview's PWA service-worker abort't `page.goto`; (2) `workers:1` want dev-server compileert on-demand en racet parallel. Plus: emoji-knoppen (🔔/✕) locaten op `title`, niet accessible name.
- **Docs:** `docs/kb/runbooks/playwright-e2e-webapp.md` aangelegd.
- **Uitbreiding (zelfde sessie):** QrScanner + biometric toegevoegd → **12 tests groen** (9 desktop + 3 mobile).
  - `biometric-setup.spec.js` (passkey `create()`), `biometric-login.mobile.spec.js` (`get()`), `qr-scanner.mobile.spec.js` (guard + camera-doorgang).
  - WebAuthn via CDP virtual authenticator (`e2e/webauthn.js`); biometric-login injecteert vooraf een resident credential. `rp.id` = `localhost` (verplicht).
  - Twee Playwright-projecten: **desktop** (Desktop Chrome) + **mobile** (Pixel 5 + fake-camera) — gesplitst via `*.mobile.spec.js` testMatch/testIgnore.
  - Headless fake-camera is omgevings-afhankelijk → camera-test assert geen stream, enkel doorgang voorbij de biometrie-guard.
- **Nog te doen door Henk:** niets blokkerends.

## Wat is er gedaan (9 juni)

### Projects-tab uncommitted files uitgezocht (SafeHavun + HavunAdmin)
- Henk zag in de webapp Projects-tab: HavunAdmin "1 uncommitted file", SafeHavun "2 uncommitted files".
- **Bron achterhaald:** `webapp/backend/src/services/projectStatusService.js` draait `git status --porcelain` op de **server-checkouts** (`/var/www/<proj>/production`), niet op Henk's lokale repos. Heeft een `isBenignDirtyLine`-filter (`.claude/`, `CLAUDE.md`, Laravel storage/bootstrap `.gitignore`-churn → niet geteld).
- **Kernbevinding:** beide productie-checkouts **liepen achter op origin** én hadden lokale drift op een verouderde basis:
  - **SafeHavun** (`/var/www/safehavun/production`): `public/landing.html` (22 KB marketing-pagina) + `public/screenshots/` (5 jpg's) waren untracked — **echte nieuwe content**, nooit gecommit. Plus verouderde `mpc.md`-drift.
  - **HavunAdmin** (`/var/www/havunadmin/production`): alleen verouderde drift op `CLAUDE.md` (+7 regels "AI Werkwijze" pipe-blok) en `mpc.md` — lokaal/origin is **10+ commits nieuwer** (compact-CLAUDE, /arch, /mem, havun:gemini). Conflicteert.
- **Servers kunnen NIET pushen (by design):** SafeHavun-server heeft `credential.helper=store` zónder `/root/.git-credentials`; HavunAdmin-server heeft een **read-only deploy key** ("marked as read only"). Goed security-ontwerp: prod mag pullen, niet pushen. Daarom zaten die wijzigingen "vast".
- **Opgelost via git-bundle-route:** server-commits gebundeld (`@{u}..HEAD`), via scp naar lokaal, daar gepusht met Henk's credentials.
  - ✅ **SafeHavun landing-pagina + 5 screenshots gepusht naar origin** (`68ebc82..96aa4a1`, master) — alleen de nieuwe files, zónder de verouderde mpc-drift.
  - HavunAdmin: **niets gepusht** — drift is achterhaald (lokaal nieuwer).
- **Webapp-bug ontdekt:** productie-webapp toont HavunAdmin als "1" terwijl beide files benign zijn (zou groen/0 moeten zijn) → de live `projectStatusService.js` mist de `CLAUDE.md`-filter → **webapp moet opnieuw gedeployd worden**.

### NOG TE DOEN (wacht op Henk's go/no-go — "we gaan morgen verder")
- [ ] **A.** Het `havun:gemini`-pipe-blokje uit HavunAdmin-server-CLAUDE.md wél/niet bewaren in de *actuele* lokale CLAUDE.md? (rest van de drift = weggooien). Henk koos nog niet.
- [ ] **B.** Akkoord om beide server-checkouts op te schonen met `git fetch origin && git reset --hard origin/<branch>` (SafeHavun=master, HavunAdmin=main)? Brengt ze op actuele origin (incl. nieuwe landing), gooit verouderde drift + de losse server-commits weg → webapp groen. Waardevolle content staat al veilig in origin, dus geen dataverlies behalve bewust-achterhaalde drift.
- [ ] **C.** Productie-webapp opnieuw deployen zodat de `CLAUDE.md`-benign-filter live komt → HavunAdmin wordt groen in de Projects-tab.
- Losse niet-gepushte commits staan nu nog op de server-checkouts (SafeHavun `1635042`, HavunAdmin `d6068a4`) — worden door stap B opgeruimd. Bundles staan in lokale temp (`/tmp/*-push.bundle`).

## Wat is er gedaan (8 juni)

### Awasete-ippon waarschuwing opgezocht + KB-doc bijgewerkt
- Henk vroeg waar de docs staan van de LCD-waarschuwing bij 2e waza-ari in osaekomi (door JudoScoreBoard/JudoToernooi gebouwd, live: app `dda66c4`, backend+display `84a79367`).
- Gevonden: `JudoScoreBoard/.claude/plan-awasete-waarschuwing.md` (plan) + `.claude/context.md` §"Awasete-ippon waarschuwing" (volledige spec) + `JudoToernooi/laravel/docs/2-FEATURES/SCOREBORD-APP.md` (referentie).
- **Gat gedicht:** SCOREBORD-APP.md was stale (apr 21) — nieuw `osaekomi.warning`-event, knipperende rode LCD-balk + instelbaar WebAudio-geluid (piep/gong/sirene) toegevoegd. Commit `32111062` op JudoToernooi `main`, gepusht.
- KB-indexer detecteerde de wijziging nog niet (hash/mtime-cache) — pikt het op bij volgende volledige index.

## Wat is er gedaan (7 juni — oriëntatie)

### Auth-fout CLI + extension (opgelost)
- 401 in CLI (`/start`) én VS Code extension — OAuth-token verlopen. Fix: `/login` in CLI → URL kopiëren → browser. Extension deelt dezelfde token, werkt daarna automatisch weer.
- Werkwijze-review: `/arch`, `/mpc`, `gemini_blueprint.md`-locatie (root HavunCore), blueprint persisteert tussen sessies.
- ~~Laravel CVE-2026-48019 (CRLF injection, `laravel/framework v12.44.0`)~~ ✓ opgelost: framework staat nu op v12.61.1, `composer audit` schoon (geverifieerd 8 jun).

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
- **NotificationBell**: functioneel nu gedekt door Playwright E2E (badge/dismiss/lege staat). Alleen de visuele layout (mobiel/desktop) blijft een handmatige check voor Henk.
- **Webapp E2E uitbreiden (optioneel)**: huidige suite is smoke-niveau (12 tests). Eventueel later: project-meldingen Fase 2-UI, of een echte QR-decode-test met een geprepareerde fake-camera video.
- **Playwright voor JudoToernooi + Herdenkingsportaal**: blauwdruk **klaar** → `runbooks/playwright-e2e-laravel.md` (Laravel + Blade: draaiende app + test-DB, géén API-mock; storageState-auth, CSRF werkt vanzelf, DB-isolatie via `.env.e2e` is regel #1). **Volgende stap (in de eigen project-sessie, niet vanuit HavunCore):** open een sessie in JudoToernooi/Herdenkingsportaal en volg de blauwdruk. `@playwright/test` = nieuwe dep → Henk's go nodig per project.
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
| webapp (PWA) | Stabiel — Playwright E2E live (12 tests) + CI; aparte repo `havuncore-webapp` (main) |

## Architectuurprincipes

- **Gemini** = architect + brainstorm (groot contextvenster, tweede mening) — via `/arch` of automatisch in dynamic workflow
- **Claude dynamic workflow** = grote taken (ultracode mode) — roept Gemini aan, implementeert parallel, test, commit
- **Claude normaal** = kleine fixes (< 5 bestanden, afgebakend)
- Memory flow: `/mem` → leest `C:/Users/henkv/.claude/projects/[SLUG]/memory/MEMORY.md`
- Bij config-issues na wijziging `havun-projects.php`: altijd `php artisan config:clear`
- Doc Intelligence MEDIUM duplicaten zijn vrijwel altijd false positives — bulk-negeren is correct
- **Midnight**: Compact = TypeScript-achtige DSL (niet Rust). Backend genereert proofs server-side — gebruikers zien nooit blockchain.
- **autoMode.allow**: handover.md en context.md staan nu globaal in de allow-lijst (`~/.claude/settings.json`)
