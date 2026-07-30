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

## Open — Vusista-lessen: `scaffold` legt een stack op (30-07)

Vusista 1 liep vast op een keuze die niemand bewust maakte: het werd een Laravel-project omdat
`project:scaffold` dat oplevert, terwijl het een lokale desktop-app is. Daarna zijn er **zes
omwegen om het eigen fundament heen** gebouwd — PHP zonder Laravel voor thumbnails, een
Node-proces voor grote bestanden, compressie om een bufferlimiet heen, een vangnet voor de eigen
JS-bundel, een C++-sidecar omdat PHP geen FFI heeft. De zesde veroorzaakte een bug waarbij de app
er normaal uitzag en volledig dood was. Vusista wordt herbouwd in Rust (`D:\GitHub\Vusista2`).

**Onderbouwing:** `docs/kb/reference/vusista-1-retrospectief.md`.

| # | Taak | Waarom |
|---|------|--------|
| 1 | **`project:scaffold` krijgt een app-type** (`--type=web\|desktop\|api\|mobile`), en vraagt ernaar als het ontbreekt. Bij `desktop`: geen webserver, geen staging, geen deploy-pipeline, geen deploy-workflows | De impliciete stackkeuze is de kern van het probleem |
| 2 | **Neem `D:\GitHub\Vusista2` als sjabloon voor type `desktop`** (CLAUDE.md, docs-structuur, `.claude/handover.md`) | Een gewerkt voorbeeld, geen bedacht formaat |
| 3 | **Vijf intakevragen verplicht vóór het scaffolden**, antwoorden vastleggen in het project | Waar draait het, hoeveel gebruikers tegelijk, waar staat de data, wat is de zwaarste operatie, waar merkt de gebruiker vertraging. Voor Vusista sloten die antwoorden een webstack uit — ze zijn nooit gesteld |
| 4 | **Zet `vusista` in `config/quality-safety.php`** | Staat er niet in; er heeft dus **nooit** een geplande V&K-scan op gedraaid — geen `composer audit`, geen secrets-scan. Gold vier maanden |
| 5 | **Ruim `/var/www/vusista/{production,staging}` op** — overleg met Henk vóór verwijderen | Hoort er niet te zijn voor een desktop-app. Staging-deploy faalde sinds 17-07 (`Not possible to fast-forward`; 30-07 rechtgezet), demo serveert een 500, en PHP 8.2 op de server haalt `^8.3` niet |

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

- **Stackkeuze wordt een besluit, geen erfenis — plan klaar, wacht op "ga maar" (30-07).**
  Vusista's post-mortem: een lokale fotomanager (76.797 bestanden, één gebruiker) draait op
  Laravel + `php -S` in een Electron-schil, omdat élk Havun-project zo begint. Zes omwegen om
  het eigen fundament heen; de zesde maakte de app stil onbruikbaar. Geverifieerd en scherper
  dan gemeld: `project:scaffold` **weigert** elke stack behalve laravel (`--stack`-guard,
  `ProjectScaffoldCommand.php:47`), en er bestaat in de hele KB geen beslisregel voor
  stackkeuze. Vijf maatregelen (intake vóór stack · scaffold op `--type` · omwegen-register ·
  besluit + omkeerpunt · "Havun-standaard" vervalt als argument) in
  `plans/stackkeuze-fundament-plan.md`; post-mortem in `patterns/fundament-versus-omweg.md`.
  **Punt 8 staat los:** Vusista's staging gaf 13 dagen rode Actions zonder dat iemand het
  merkte — monitoring-gat, niet scaffold. De herbouw zelf (`Vusista2/PLAN.md`, Rust + Tauri)
  is een Vusista2-sessie.
- **Web-push voor `critical` health-alerts — gebouwd, nooit getest.** Hele keten staat (Laravel
  `PushController`/`WebPushService` + VAPID; webapp `sw-push.js` + knop). Rest = één browser-test.
  `plans/health-alerts-webpush-blueprint.md`. Leesval: valt terug op `localhost:8009` (lege stub).
  Los daarvan: `laravel-worker` + `toernooi-heartbeat` onbewaakt (`runbooks/uptime-monitoring.md`).
- **havuncore-webapp update-banner — niet reproduceerbaar (24-07).** Gemeten met een nieuwe
  E2E-suite tegen de productie-build (`npm run test:e2e:pwa`): banner verschijnt en de klik
  activeert + herlaadt, in beide workbox-vensters (<60s en >60s na registratie). Geen code
  gewijzigd. `sw.js` op prod heeft correcte no-cache-headers. Doet het zich wéér voor: check
  eerst of `getRegistration()` een `waiting` heeft. Meting: `plans/webapp-sw-update-fix.md`.
  Los daarvan: Vitest geblokkeerd door Avast HTTPS-interceptie (niet de registry) — via server
  ophalen + hash. Zie [[env-ssl-interception]].
- **JudoScoreBoard `context.md` op master nog 1039 regels** — opgeschoonde 523-versie op
  `chore/expo-sdk-56-upgrade`; lost zichzelf op bij merge.

## Recent afgerond (context die nog nut heeft)

- **`/start` en `/end`: deploy-achterstand is niet meer te missen (25-07)** — nieuw
  `php artisan havun:deploy-status`: scheidt code van docs, licht security-commits eruit als
  alarm, meldt migraties apart. Staat nu **ook in `/start`** (stap 1d) — dát is de plek die telt,
  want `/start` draait altijd en `/end` niet. Meting die dit uitwees: de check in `/end` wérkte,
  maar vond 13 achterlopende checkouts zonder dat iemand het las. Plan:
  `plans/start-end-verbetering.md`.
- **Negen coverage-audits (24/25-07)** — norm gewijzigd: géén drempel meer, wél zo hoog mogelijk
  *zinvolle* dekking (`decisions/coverage-drempel-vervalt-2026-07-24.md`). Per project een
  `docs/testschuld.md`. Rode draad: gedekt is wat makkelijk was, niet wat kapot mag gaan.

- **AI-synthese-risico's afgedekt (24-07)** — nieuwe bindende standaard
  `standards/ai-synthese-risicos.md` + `patterns/test-rood-gezien.md`: **een bugfix-test die je
  niet rood hebt gezien tegen de oude code, bewijst niets.** Uitgerold naar 14 CLAUDE.md's en 4
  `start.md`'s. Aanleiding: de update-banner-meting, waar een groene test bijna een
  niet-bestaande bug als opgelost had gerapporteerd. Plan: `plans/ai-synthese-afdekking-plan.md`.
- **13 CLAUDE.md's droegen twee achterhaalde normen (24-07)** — "de KB indexeert alleen het begin
  van een bestand" (onwaar sinds de chunking van 15-07) en "handover 15-30 regels per sessie"
  (norm is 120, en het sprak de regel eronder tegen). Gecorrigeerd. Ook opgeruimd: Havun 129→106
  regels en JudoScoreBoard hadden een instructie die *toestemming vragen* eiste terwijl de tabel
  ernaast zegt dat Claude technische keuzes zelf maakt. **De 6 geparkeerde projecten dragen de
  foute normen nog** — bewust niet aangeraakt.
- **Opruiming server + GitHub (24-07)** — dode vhost `demo.havun.nl` (wees naar een niet-bestaand
  pad), 10 ongebruikte certs (25→15), 7 oude configs, lege `/var/www/lastmatch`. Backups in
  `/root/backups/cleanup-2026-07-24/`. **Munus volledig weg** (map + lege repo + alle
  registraties); **HavunVet gearchiveerd**. Geparkeerd, géén uitrol meer: HavunClub, Demo,
  Havunity, Infosyst, IDSee, Agorano.

- **credentials.md lekte in de KB-index (19-07)** — `docs:index` indexeerde de kluis (secrets in
  `doc_embeddings`). Gepurged + `isSensitiveFile`-guard in `DocIndexer` (credentials.md/.env nooit
  meer indexeren). Methode om secrets veilig te ontvangen zonder chat-lek: `runbooks/secrets-veilig-ontvangen.md`.
- **Server-opschoning 18-07** — HavunClub + Umami + Infosyst van de server (disk 73%→67%, Umami's
  pm2 gaf de RAM-winst). Backups in `/root/backups/*-2026-07-18/`. Code/repos blijven waar nodig.
- **start2-command (19-07)** — werkwijze-primer voor de VS Code-extensie, uitgerold naar 16 projecten.
- **De auth-norm werd als status gelezen (16-07)** — `reference/authentication-methods.md`: "Per
  Project"-tabel las als beschrijving. Nu gelabeld als norm. Regel in `standards/md-doc-grootte.md`.
- **KB-chunking (15-07)** — `plans/kb-chunking-plan.md`. Aparte `doc_chunks`-tabel, zoeken 0,1s met
  `--project`. Lessen: eerst consumers dán schema; meten niet redeneren; één weg de index in.

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
