---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-07-19
---

# HavunCore — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels. Regel: `docs/kb/standards/md-doc-grootte.md`.

**Branch:** master · **Status:** stabiel. KB zoekt gechunkt (`--project` ~0,1s). **Server:** disk 67%
(12 GB vrij na opschoning 18-07), prod draait overal.

## Vusista-lessen: de stackkeuze is nu een besluit (30-07) — 8 van 10 af

Vusista 1 liep vast op een keuze die niemand maakte: het werd een Laravel-project omdat
`project:scaffold` **elke andere stack hard weigerde**, terwijl het een lokale desktop-app is
(76.797 bestanden, één gebruiker). Daarna zes omwegen om het eigen fundament heen; de zesde
maakte de app stil onbruikbaar. Post-mortem: `patterns/fundament-versus-omweg.md`.
Plan + status per punt: `plans/stackkeuze-fundament-plan.md`.

**Af:** `standards/stack-keuze.md` · `patterns/omwegen-tellen.md` · besluit-sjabloon met **aanname
+ omkeerpunt** in `docs-first.md` · `project:scaffold` op verplichte `--type`, geblokkeerd zonder
ingevulde `docs/intake.md` die hetzelfde type concludeert, web-infra alleen bij `server-webapp`
(27 tests) · `/mpc` fase 0 + `/arch` · uitrol naar HavunCore + **14 actieve CLAUDE.md's**
(Vusista's norm staat op `staging`, want `main` loopt 335 commits achter).

**Registratie-gaten gedicht.** `vusista` stond niet in `quality-safety.php` — vier maanden nooit
gescand; eerste scan: **critical 1 · high 2 · medium 4**. En `vusista2` stond in géén van beide
configs terwijl het al werkende Rust-proeven had — dezelfde fout, één dag later. Beide nu
geregistreerd; Vusista2 heeft ook een repo (`havun22-hvu/Vusista2`, privé, 6 commits) want die
bestond alleen op één schijf. Vusista 1 had er al één. Beide zijn Vusista-werk → eigen sessie.

**Nog open:** (9) rode Actions moeten iemand bereiken — Vusista's staging faalde 13 dagen ongemerkt:
monitoring-gat, geen scaffold-gat · (10) **jouw go:** `/var/www/vusista/{production,staging}` opruimen
(demo serveert een 500). Losse gaten: **Veen heeft geen CLAUDE.md**; vier CLAUDE.md's boven de
120-regelnorm (Vusista 138, Studieplanner-api 135, JudoScoreBoard 130, havuncore-webapp 125) — zaten
er al aan vóór de uitrol.

## V&K kiest zijn checks op de stack (31-07) — af

`qv:scan` detecteert de ecosystemen uit de manifesten (detectie, géén `stack`-veld — dat zou een
tweede waarheid worden), draait `cargo audit` op elke `Cargo.lock` onder de root, en **meldt een
ecosysteem dat het niet kan auditen als `high`-bevinding** in plaats van als nul. Een
**overgeslagen check meldt nu zijn reden**; totalen dragen `overgeslagen: N`. Elke scan toont hoe
een project gebouwd is (`havuncore: js, php`). Plus `reference/testgereedschap-per-stack.md` —
de policy veronderstelde stilzwijgend PHP. Plan: `plans/vk-per-stack-plan.md`. 1345 tests groen.

**Vusista2 ging van 0 naar 34 findings.** Die eerste nul was mijn eigen bug: `cargo audit` zet
`unmaintained`/`unsound` in een apart `warnings`-veld naast `vulnerabilities`, en ik las alleen
dat laatste. Handmatig nameten bracht het aan het licht. **Go, Python en .NET blijven ongemeten —
maar nu zichtbaar.**

## Open — wacht op Henk

| Wat | Details |
|-----|---------|
| **Gelekt login-wachtwoord (`…ZxO#`) — rotatie loopt (19-07)** | Google meldde leak; waarde was over 10 havun.nl-sites hergebruikt. **Gedaan:** wachtwoord-login van `henkvu@` dood op HavunCore/SafeHavun/Studieplanner (random hash, magic-link blijft; rij-backups `/root/backups/pwreset-2026-07-19`). **Jij nog doen:** (1) `scripts/rotate-leaked-login.sh` draaien in Git Bash → nieuw uniek wachtwoord voor HavunAdmin (prod+staging) + JudoToernooi `.env`; (2) `infosyst`+`staging.havunclub` uit Google verwijderen (apps zijn 18-07 van server af — niks te roteren). **Apart (eigen sessie):** VPD/vpdupdate (`users.json`, Node/WebAuthn); HavunAdmin magic-link bouwen zodat wachtwoord ook dáár weg kan; password-kolommen nullable + wachtwoord-UI eruit op de 3 magic-link-apps |
| **Blijvend-ingelogd-plan** | Geschreven, wacht op "ga maar" — `docs/kb/plans/blijvend-ingelogd-plan.md` |
| **Prod-deploys staan klaar (3 checkouts achter)** | Herdenkingsportaal (3 code-commits — **passkey-login af maar niet live**), JudoToernooi (6), HavunCore zelf (KB-werk). Deploy = altijd jouw klik |
| **Stripe-sleutel geroteerd (JudoToernooi) 19-07** | Oude `sk_live_…4l13` staat **nergens actief meer** (JudoToernooi-prod = nieuwe sleutel, geverifieerd; HavunAdmin + `laravel-old` dode sleutels leeggemaakt). **Laat de oude in Stripe verlopen.** Optioneel: webhook-secret roteren + oude Stripe-regel in `credentials.md` opschonen. AWS SES-key = Cees' account, niet de onze |
| **Vite-build achter op 4 checkouts — 2× prod** | HP-prod/staging, Studieplanner-prod, Vusista-staging. Signaal, geen diagnose — verifieer per project (`runbooks/vite-build-bij-deploy.md`), elk in eigen sessie |
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
| **Vusista** | App testen + installer op schone PC = laatste MVP-punt. Installer +119 MB (80 MB OpenCV); SFace-licentieketen onverifieerbaar |
| **JudoScoreBoard** | Google-review AAB 116 (9 juni ingediend) — status alleen in Play Console |

## Open — Veen-ledenadministratie (orchestrator-deel afgerond)

Overname Cees' EOL-app als eigen project, route B (verse **Laravel 12**).
- **Fase 1+2 klaar (18-07):** GitHub-repo (private) + server live — production `veen.havun.nl`
  + staging `staging.veen.havun.nl` (HTTPS, auto-deploy E2E bewezen) + HavunCore-registratie.
- **Fase 3 (de herbouw: feature-inventaris + SEPA-datamigratie 15k payments) = een Veen-sessie,
  na Cees' groen licht — NIET vanuit HavunCore.** Eisen (o.a. SEPA-machtiging: geen internetvinkje,
  eMandate/Twikey of PSP) staan in `VeenLedenadministratie/.claude/modernisering-scope.md`.
- Credentials (admin-login + TransIP-CP) + `.env`-secrets staan in de centrale kluis.

## Open — te doen

- **Web-push voor `critical` health-alerts — gebouwd, nooit getest.** Hele keten staat (Laravel
  `PushController`/`WebPushService` + VAPID; webapp `sw-push.js` + knop). Rest = één browser-test.
  `plans/health-alerts-webpush-blueprint.md`. Leesval: valt terug op `localhost:8009` (lege stub).
  Los daarvan: `laravel-worker` + `toernooi-heartbeat` onbewaakt (`runbooks/uptime-monitoring.md`).
- **havuncore-webapp update-banner — niet reproduceerbaar (24-07).** E2E tegen de productie-build
  (`npm run test:e2e:pwa`): banner verschijnt, klik activeert + herlaadt, beide workbox-vensters.
  Geen code gewijzigd. Wéér last? Check of `getRegistration()` een `waiting` heeft.
  `plans/webapp-sw-update-fix.md`. Los daarvan: Vitest geblokkeerd door Avast HTTPS-interceptie
  (niet de registry) — via server ophalen + hash. Zie [[env-ssl-interception]].
- **JudoScoreBoard `context.md` op master nog 1039 regels** — opgeschoonde 523-versie op
  `chore/expo-sdk-56-upgrade`; lost zichzelf op bij merge.

## Recent afgerond (context die nog nut heeft)

- **`/start` en `/end`: deploy-achterstand is niet meer te missen (25-07)** —
  `php artisan havun:deploy-status` scheidt code van docs, licht security-commits eruit als alarm,
  meldt migraties apart. Staat **ook in `/start`** (stap 1d) — die draait altijd, `/end` niet.
- **Negen coverage-audits (24/25-07)** — norm gewijzigd: géén drempel meer, wél zo hoog mogelijk
  *zinvolle* dekking (`decisions/coverage-drempel-vervalt-2026-07-24.md`). Per project een
  `docs/testschuld.md`. Rode draad: gedekt is wat makkelijk was, niet wat kapot mag gaan.

- **AI-synthese-risico's afgedekt (24-07)** — nieuwe bindende standaard
  `standards/ai-synthese-risicos.md` + `patterns/test-rood-gezien.md`: **een bugfix-test die je
  niet rood hebt gezien tegen de oude code, bewijst niets.** Uitgerold naar 14 CLAUDE.md's en 4
  `start.md`'s. Aanleiding: de update-banner-meting, waar een groene test bijna een
  niet-bestaande bug als opgelost had gerapporteerd. Plan: `plans/ai-synthese-afdekking-plan.md`.
- **13 CLAUDE.md's droegen twee achterhaalde normen (24-07)** — "KB indexeert alleen het begin van
  een bestand" (onwaar sinds de chunking) en "handover 15-30 regels". Gecorrigeerd.
  **De 6 geparkeerde projecten dragen de foute normen nog** — bewust niet aangeraakt.
- **Opruiming server + GitHub (24-07)** — dode vhost, 10 ongebruikte certs (25→15), 7 oude
  configs. Backups in `/root/backups/cleanup-2026-07-24/`. **Munus volledig weg**, **HavunVet
  gearchiveerd**. Geparkeerd, géén uitrol meer: HavunClub, Demo, Havunity, Infosyst, IDSee, Agorano.
- **credentials.md lekte in de KB-index (19-07)** — `isSensitiveFile`-guard in `DocIndexer`;
  credentials.md/.env worden nooit meer geïndexeerd. `runbooks/secrets-veilig-ontvangen.md`.

## Vaste context voor dit project

- **Rol:** centrale kennisbank + orchestrator. Scope-regel: **alleen HavunCore aanwerken; ander
  project = eigen sessie** (uitzondering: Henk geeft expliciet toestemming). Zie [[feedback-scope-waarschuwen]].
- KB zoeken: `php artisan docs:search "<onderwerp>"` — vereist Ollama op :11434.
- **Eerste prod-deploy per app = Henk klikt bewust** (Actions → Deploy to Production). Nooit auto-migrate op prod.
- **Prod kán wél pushen** (as root) — de oude notitie "prod kan niet pushen" klopt niet meer;
  geverifieerd 25-07 met een echte push naar een `rescue/`-branch. **Maar `www-data` kan het niet:**
  `git` weigert daar met *dubious ownership* (`safe.directory`). Dat is waarom
  `AutoCommitRegeneratedCommand` zijn eigen veiligheidsklep niet kon gebruiken en
  havuncore/production 10 lokale commits opbouwde tegenover 66 in origin. Opgelost door de
  prod-staat naar `rescue/havuncore-prod-autocommits-2026-07-25` te pushen en hard te resetten.
  **Nog te doen:** `safe.directory` goedzetten voor `www-data` (raakt server-config → overleg).
- havuncore-webapp deployt anders: lokaal build → rsync + pm2 (`havuncore-webapp/DEPLOY.md`).
- Server-quirk: `composer install` als root maakt `storage/**` root-owned → 500s. Fix: `chown -R www-data:www-data storage bootstrap/cache`.
