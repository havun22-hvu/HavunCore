---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-07-31
---

# HavunCore — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels. Regel: `docs/kb/standards/md-doc-grootte.md`.

**Branch:** master · **Status:** stabiel. KB zoekt gechunkt (`--project` ~0,1s). **Server:** disk 67%
(12 GB vrij na opschoning 18-07), prod draait overal.

## 🔴 Vier rode builds die niemand wist (31-07) — per project een eigen sessie

De nieuwe `actions:watch` vond ze meteen: **HavunAdmin 3 maanden rood** · HavunClub 3 maanden
(geparkeerd) · VeenLedenadministratie 1 dag · Studieplanner-api sinds 30-07 (**níét** door de
CLAUDE.md-uitrol — de run ervóór faalde al). Uitzoeken hoort in de projectsessie zelf.

## Stackkeuze-lessen (30/31-07) — beide plannen af, drie punten open

Volledig in `plans/stackkeuze-fundament-plan.md` (10/10) en `plans/vk-per-stack-plan.md`.
**Wat er nu geldt:** `project:scaffold` eist `--type` + een ingevulde `docs/intake.md` die hetzelfde
type concludeert, web-infra alleen bij `server-webapp` · omwegen tellen in `docs/omwegen.md`
(16 projecten) · elk architectuurbesluit noemt **aanname + omkeerpunt** · `qv:scan` detecteert de
stack en **meldt wat het niet kan meten** in plaats van nul · `actions:watch` maakt rode builds
zichtbaar. Post-mortem die dit uitlokte: `patterns/fundament-versus-omweg.md`.

**Nog open:**
- **Vusista 1 blijft staan tot Vusista2 áf is** — map, repo én KB-index. `qv:scan` staat daar op
  `enabled => false`; **niets meer repareren**, de openstaande bevindingen blijven bewust staan.
- **Jij:** DNS `vusista.havun.nl` bij mijn.host + deploy-key `server-read` uit de Vusista-repo.
- Vier CLAUDE.md's boven de 120-regelnorm (Vusista 138, Studieplanner-api 135, JudoScoreBoard 130,
  havuncore-webapp 125) — zaten er al aan vóór de uitrol.
- **De twee registries lopen structureel uit de pas:** 3× in twee dagen stond een project wel in
  `havun-projects.php` en niet in `quality-safety.php` (`vusista`, `vusista2`, Veen). Een check die
  dat verschil meldt is ~een half uur werk — nog niet gebouwd.

## Open — wacht op Henk

| Wat | Details |
|-----|---------|
| **Gelekt login-wachtwoord (`…ZxO#`) — rotatie loopt (19-07)** | Google meldde leak; waarde was over 10 havun.nl-sites hergebruikt. **Gedaan:** wachtwoord-login van `henkvu@` dood op HavunCore/SafeHavun/Studieplanner (random hash, magic-link blijft; rij-backups `/root/backups/pwreset-2026-07-19`). **Jij nog doen:** (1) `scripts/rotate-leaked-login.sh` draaien in Git Bash → nieuw uniek wachtwoord voor HavunAdmin (prod+staging) + JudoToernooi `.env`; (2) `infosyst`+`staging.havunclub` uit Google verwijderen (apps zijn 18-07 van server af — niks te roteren). **Apart (eigen sessie):** VPD/vpdupdate (`users.json`, Node/WebAuthn); HavunAdmin magic-link bouwen zodat wachtwoord ook dáár weg kan; password-kolommen nullable + wachtwoord-UI eruit op de 3 magic-link-apps |
| **Blijvend-ingelogd-plan** | Geschreven, wacht op "ga maar" — `docs/kb/plans/blijvend-ingelogd-plan.md` |
| **Prod-deploys staan klaar (3 checkouts achter)** | Herdenkingsportaal (3 code-commits — **passkey-login af maar niet live**), JudoToernooi (6), HavunCore zelf (KB-werk). Deploy = altijd jouw klik |
| **Stripe-sleutel geroteerd (JudoToernooi) 19-07** | Oude `sk_live_…4l13` staat **nergens actief meer** (JudoToernooi-prod = nieuwe sleutel, geverifieerd; HavunAdmin + `laravel-old` dode sleutels leeggemaakt). **Laat de oude in Stripe verlopen.** Optioneel: webhook-secret roteren + oude Stripe-regel in `credentials.md` opschonen. AWS SES-key = Cees' account, niet de onze |
| **Vite-build achter op 3 checkouts — 2× prod** | HP-prod/staging, Studieplanner-prod (Vusista-staging vervallen: server opgeruimd 31-07). Signaal, geen diagnose — verifieer per project (`runbooks/vite-build-bij-deploy.md`), elk in eigen sessie |
| **Hardcoded Hetzner-wachtwoord op server** | `/usr/local/bin/havun-backup.sh` (`HETZNER_PASS=` plain text). Hoort in de Vault. Zie [[feedback-no-hardcoded-test-secrets]] |
| **Server OS-update: volgende kwartaalcheck oktober 2026** | Gedaan 19-07: kernel 5.15.0-186, alle packages bij. `ondrej/nginx`-PPA verwijderd (IPv6 403). Runbook: `runbooks/server-os-updates.md` |
| **Security: dependencies** | HavunAdmin 19 composer-advisories (2 high); JudoScoreBoard 6 GitHub-advisories (1 critical + 2 high). `composer update`/`npm` → overleg |
| **VPDUpdate: gedeployd 25-07, `users.json` blijft een risico** | 59 commits ingelopen, `users.json` is nu **untracked** en staat alleen nog op de server (+ backup in `/root/backups/vpdupdate-predeploy-2026-07-25`). **Let op:** de pull verwijderde het bestand eerst — een staged `git rm --cached`-deletion opheffen maakt het weer tracked. Hersteld uit backup, app draait (200). Regel toegevoegd aan `standards/server-hygiene.md`. **Nog open:** de secrets zitten nog in de git-historie — purgen is een eigen sessie (vgl. HavunClub, waar Henk bewust niet purgede) |
| **GitGuardian #33883984** | Op *Resolved* zetten |
| **Aeterna** | Prod keystore + update-adres. Week2-plan dood (crates bestaan al) — archiveren. `feat/v1.1-tor-socks5-3b` (PR #16 closed, niet merged) |
| **Studieplanner** | `chore/expo-sdk-55-upgrade`: 230/230 groen maar nooit device-getest, 3 mnd oud — mergen of verwerpen |
| **Studieplanner-api: coverage is deels padding (gemeten 24-07)** | **91,9% / 322 tests** (niet de 94,1% die in `CLAUDE.md` stond). Scheef verdeeld: `PremiumController` 67,7% (XRP-betalingen), `AuthController` 80,7%, `UserDevice` 0% — terwijl 11 modellen 100% zijn. `Push90Test` (36 tests) bestaat volgens zijn eigen docblock om het cijfer te liften; `ModelRelationsTest` (377 regels) test `belongsTo`. **Ernstigst:** `MagisterApiTest`/`SOMtodayApiTest` leggen met `assertStatus(500)` vast dat een onbereikbare externe API een 500 van ónze API geeft — hoort 502/503. Fix = eigen sessie, volgorde in `Studieplanner-api/docs/testschuld.md` |
| **Studieplanner-api** | `rescue/prod-stashes-2026-07-15`: user settings + observability afmaken of branch weg |
| **LastMatch** | Avast HTTPS-scanning uit = enige APK-build-blocker |
| **JudoScoreBoard** | Google-review AAB 116 (9 juni ingediend) — status alleen in Play Console |

## Veen-ledenadministratie — GEPARKEERD (Henk, 31-07)

**Voorlopig niets mee doen.** De herbouw lag al stil (Cees vond de offerte te duur, besluit 003);
nu ligt het hele project stil, ook de kleine betaalde klussen. Hier stond nog "fase 3 wacht op
Cees' groen licht" — dat klopte al sinds ~29-07 niet meer.

**Onze serveromgeving opgeruimd (31-07).** `veen.havun.nl` + staging, cert, beide checkouts en
**beide databases** (staging had 26 tabellen / ~18.941 rijen — die dataset zit niet in git). Backup
root-only vanwege de `.env`'s: `/root/backups/veen-cleanup-2026-07-31` (72 MB + beide dumps +
nginx-config), integriteit geverifieerd vóór verwijderen. Alle andere sites 200.
**⛔ De oude app van Cees op `37.34.60.216` (TransIP) is NIET van ons en is niet aangeraakt** —
daar staat de live administratie. **Lokale checkout blijft staan**: Cees kan nog vragen hebben.

**Parkeren ≠ monitoring uit — en het stónd niet eens aan.** Veen zat in `havun-projects.php` maar
**niet** in `quality-safety.php`: nooit een `composer audit` of secrets-scan. Dat is de **derde**
keer in twee dagen (na `vusista` en `vusista2`) dat een project in de ene registry staat en niet in
de andere. Nu toegevoegd; scannen blijft aan op de lokale checkout, want een geparkeerd project
waar niemand kijkt is juist waar een advisory blijft zitten. **Verschil met Vusista 1**
(`enabled => false`): dat wordt weggegooid, dit kan zo weer opgepakt worden.

**Eerste scan: 1 high** — `config/session.php` secure-cookie-default niet `true`. **Niet gefixt:
het project is geparkeerd.** Jouw call of dit een uitzondering waard is.

## Open — te doen

- **Web-push voor `critical` health-alerts — gebouwd, nooit getest.** Hele keten staat (Laravel
  `PushController`/`WebPushService` + VAPID; webapp `sw-push.js` + knop). Rest = één browser-test.
  `plans/health-alerts-webpush-blueprint.md`. Leesval: valt terug op `localhost:8009` (lege stub).
  Los daarvan: `laravel-worker` + `toernooi-heartbeat` onbewaakt (`runbooks/uptime-monitoring.md`).
- **havuncore-webapp update-banner — niet reproduceerbaar (24-07).** E2E tegen de productie-build:
  banner werkt in beide workbox-vensters. Wéér last? Check `getRegistration()` op een `waiting`.
  `plans/webapp-sw-update-fix.md`. Los daarvan: Vitest geblokkeerd door Avast HTTPS-interceptie
  (niet de registry). Zie [[env-ssl-interception]].
- **JudoScoreBoard `context.md` op master nog 1039 regels** — opgeschoonde 523-versie op
  `chore/expo-sdk-56-upgrade`; lost zichzelf op bij merge.

## Vaste context voor dit project

- **Geparkeerd, géén uitrol meer:** HavunClub, Demo, Havunity, Infosyst, IDSee, Agorano,
  **Veen** (31-07). Die zes dragen nog twee achterhaalde normen in hun CLAUDE.md — bewust
  niet aangeraakt. Munus is weg, HavunVet gearchiveerd.

- **Rol:** centrale kennisbank + orchestrator. Scope-regel: **alleen HavunCore aanwerken; ander
  project = eigen sessie** (uitzondering: Henk geeft expliciet toestemming). Zie [[feedback-scope-waarschuwen]].
- KB zoeken: `php artisan docs:search "<onderwerp>"` — vereist Ollama op :11434.
- **Eerste prod-deploy per app = Henk klikt bewust** (Actions → Deploy to Production). Nooit auto-migrate op prod.
- **Prod kán pushen als root, `www-data` niet** — die krijgt *dubious ownership* (`safe.directory`),
  waardoor `AutoCommitRegeneratedCommand` zijn veiligheidsklep niet kon gebruiken en prod 10 lokale
  commits opbouwde. **Nog te doen:** `safe.directory` goedzetten voor `www-data` (server-config → overleg).
- havuncore-webapp deployt anders: lokaal build → rsync + pm2 (`havuncore-webapp/DEPLOY.md`).
- Server-quirk: `composer install` als root maakt `storage/**` root-owned → 500s. Fix: `chown -R www-data:www-data storage bootstrap/cache`.
