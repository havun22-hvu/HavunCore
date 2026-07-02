---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-06-26
---

# HavunCore — Handover

> Vul dit aan aan het einde van elke sessie. Houd het kort — wat een volgende Claude-sessie direct nodig heeft.

## Huidige status

**Branch:** master
**Laatste werk (1 juli):** CI-deploytoegang uitgerold over de web-apps. Aanleiding: HavunClub-deploy wachtte op de GitHub-secret `SSH_PRIVATE_KEY`. Henk koos "volledig: workflow + key per web-app" en **handmatige knop (workflow_dispatch), geen auto-op-push** (prod = bewuste keuze).

- **Herbruikbaar gemaakt:** `scripts/setup-deploy-key.sh <Repo>` — genereert (idempotent) een **dedicated** ed25519 deploy-key per repo op de server (`/root/.ssh/github_deploy_<slug>`), zet de public in root's `authorized_keys` en pipet de private key **zonder te printen** naar de GitHub-secret `SSH_PRIVATE_KEY`. Plus centraal `/root/deploy-havun.sh <dir> <branch> [subdir] [build] [migrate]` op de server (git pull --ff-only + composer --no-dev + npm build + artisan optimize; **migrate alleen bij expliciete input**, want auto-migrate op prod mag niet).
- **Klaar + geverifieerd deploybaar (server-pull getest):** HavunClub (staging, had workflow), HavunAdmin (had alles), Herdenkingsportaal (key gezet, workflow bestond al), **infosyst / SafeHavun / Judotoernooi** (key+secret+nieuwe handmatige prod-workflow `deploy-production.yml`; Judotoernooi via `laravel/`-subdir). Alle 7 secrets ✓.
- **VPDUpdate — nu OPGELOST (2 juli):** de server-remote stond op `git@github.com:` (default key, geen toegang tot private repo). Read-deploykey `deploy_vpdupdate.pub` als deploy key op de GitHub-repo gezet + remote omgezet naar host-alias `github-vpdupdate` → pull werkt. Non-Laravel: deploy = git pull + `npm run build` (check bij eerste klik of dat script bestaat).
- **Studieplanner-api — deploybaar gemaakt (2 juli):** draait op `/var/www/studieplanner/production` (repo `Studieplanner-api`, master, Laravel). Key+secret+handmatige workflow gezet, pull geverifieerd.
- **havuncore-webapp — GEEN gat, heeft eigen werkende deploy** (correctie 2 juli, na lezen `havuncore-webapp/DEPLOY.md`): deployt NIET via server-side git pull maar via **lokaal build → upload**: frontend `npm run build` + `rsync --delete dist/` → `public/` + `sw.js` cache-bust; backend `scp backend/src/*` + `sudo -u www-data pm2 restart havuncore-backend`. Past dus niet in het `deploy-havun.sh`-patroon en hoeft dat ook niet. Alleen naar CI tillen als Henk dat expliciet wil (dan build-en-upload-workflow, geen git-pull-recept).
- **GEPARKEERD (Henk, 2 juli) — niet aanwerken:** **HavunVet = obsoleet** (vhost + leeg pad mag ooit opgeruimd, server-config → Henks go). **IDSee + Agorano = "nog lang niet aan de beurt"** — server-setup pas als ze aan de beurt zijn.
- **Enige openstaande deploy-taak:** havuncore-webapp Node/pm2-deployvariant (zie punt hierboven) — vereist pm2-procesnaam.
- **Buiten scope:** native apps (judoscoreboard, LastMatch, Studieplanner, Aeterna) + geparkeerd Munus = geen server-deploy.
- **Eerste deploy per app = Henk klikt bewust** (Actions → Deploy to Production → Run workflow). Ik heb geen prod-deploy getriggerd. Runbook: `docs/kb/runbooks/deploy-keys-github-actions.md`.

**Ook 2 juli — Reverb-outage JudoToernooi hersteld:** Henk vroeg "waarom draait reverb niet". Bleek: MySQL-blip 23 jun ~06:07 UTC → supervisor-processen `reverb`, `reverb-staging`, `laravel-worker`, `laravel-worker-staging` én `toernooi-heartbeat` naar **FATAL** ("exited too quickly"; boot-cachecheck faalde op `Connection refused`) → **~10 dagen down**, DB was allang gezond. Alle 5 hersteld met `supervisorctl start` (handmatige `reverb:start` bewees dat de config klopt); reverb draait stabiel op 8080/8081. **Dit is de 3e keer (april, 4-6 jun, 23 jun) — zelfde failure mode.** Twee gaten blijven open (beide raken config/bewaking → Henks go): (1) supervisor `startretries`↑ of DB-tolerante reverb-boot, (2) **de monitoring ving deze outage NIET af** (regressie t.o.v. 4-6 jun). Details: `docs/kb/runbooks/reverb-troubleshoot.md` §6.

**Vorig werk (26 juni):** integratiecontract HavunClub ↔ JudoToernooi ↔ HavunAdmin vastgesteld + uitgeschreven als HavunCore-spec (`docs/kb/contracts/havunclub-koppelingen.md`). 3 beslispunten door Henk beslist. JT + HA moeten nog bouwen (eigen sessies); HavunClub = kleine aanpassing.

## Wat is er gedaan (26 juni — integratiecontract 3 SaaS-apps)

HavunClub leverde z'n kant (`HavunClub/docs/integratie-contract.md`, al live) + 3 open beslispunten. Vastgesteld + uitgeschreven naar `docs/kb/contracts/havunclub-koppelingen.md` (naast bestaande `memorial-reference.md`).

- **Architectuur:** HavunClub = hub; JT en HA praten onderling niet. HavunClub push't stamdata/inschrijving naar JT, HA trekt facturen/betalingen op uit HavunClub.
- **Beslissing 1 (tenant-id):** geen centrale HavunCore-id. Elke app master van eigen id; HavunClub bewaart crosswalk (`judotoernooi_tenant_id`/`havunadmin_tenant_id`). **Koppelsleutel = opgeslagen bevestigde mapping, NIET runtime e-mailvergelijking** (correctie op Henks eerste idee — e-mail/naam = enkel menselijke herkenning in `/koppelingen`-UI).
- **Beslissing 2 (HA-richting):** HA trekt op (pull). HavunClub master financiële data. `havunadmin_api_key`-pushveld vervalt.
- **Beslissing 3 (factuurvelden):** HavunClub levert platte factuur + **regels met BTW per regel** (HA's `invoice_items` ondersteunt het al). HA wijst grootboek/kostenplaats zelf toe (heeft `LedgerAccount` + `category_id` + import-filters).
- **Grounding:** JT heeft géén tenant maar wél Bearer-token-patroon (`scoreboard.token`) → key = tenant, `tenant`-param overbodig. JT's `SyncApiController` is app↔cloud, NIET de judoka-push → 3 endpoints zijn nieuw. HA heeft al multi-tenant + `InvoiceSyncController` → pull sluit naadloos aan.
- **Open punten** (in het contract-doc): JT base-URL + `resultaat`-waardenset; HA header- vs regel-BTW + `?sinds=`-akkoord; HavunClub `havunadmin_api_key` verwijderen.
- **Vervolg = eigen project-sessies:** JT bouwt 3 endpoints, HA bouwt pull-job. HavunCore-scope hier was alleen het contract vaststellen + voorbereiden.
- **GEÏMPLEMENTEERD (27 jun, Henk gaf expliciet go om JT+HA aan te passen):** beide kanten gebouwd, getest, gepusht als feature-branch `feat/havunclub-koppeling` — additief + config-gated, solo blijft werken.
  - **JudoToernooi** (`9102e6f`): `club_api_tokens`-tabel + `CheckClubToken` (token=Organisator=tenant), 3 API-endpoints (`POST /api/judokas` idempotente stam-upsert, `POST /api/inschrijvingen`, `GET resultaten`), `ClubInschrijvingService` (API-only, coach-portaal onaangeroerd), `club:token-create`, 7 tests groen. **Correctie t.o.v. blueprint:** JT's tenant = `Organisator` (geen apart club-model); StamJudoka heeft `naam`+`geboortejaar` (niet voornaam/achternaam/geboortedatum) → server-side mapping.
  - **HavunAdmin** (`adcfe32`): `Invoice::createFromHavunClub()` (idempotent op source+external_reference, regels→InvoiceItem), `HavunClubSyncService` (pull+dedup+matching+cursor), `sync:havunclub` + scheduler, 4 tests groen. **Actieve correctie:** HA's `InvoiceSyncController` bleek een *push-ontvanger* (Herdenkingsportaal), géén puller — pull is nieuw maar hergebruikt het `createFrom...`-patroon + `TransactionMatchingService`. Pull honoreert Henks beslissing + HavunClubs live provider-werk.
  - **Open (Henk/HavunClub):** PR's reviewen+mergen, deploy + JT-migraties, HavunClub bevestigt base-URL/geslacht-waarden/betaling-payload + verwijdert `havunadmin_api_key`. Contract bijgewerkt met antwoorden (resultaat=eindpositie, regel-BTW).
- **Blueprints geschreven** (27 jun) zodat de project-sessies direct kunnen: `docs/kb/plans/havunclub-koppeling-jt-blueprint.md` (JT: `club_api_tokens`-tabel + `CheckClubToken` gespiegeld op `scoreboard.token`, `ClubSyncController`, `InschrijvingService`-extractie uit `CoachPortalController`) en `havunclub-koppeling-ha-blueprint.md` (HA: `sync:havunclub`-command gespiegeld op `sync:bunq`, `HavunClubSyncService`, `updateOrCreate` op source+external_reference, scheduler `everyFifteenMinutes`). Beide bevatten Havun-kwaliteitseisen + testplan. Droppen in `<project>/.claude/blueprint.md` → `/mpc` + "ga maar".

**Vorig werk (24 juni):** forms-coverage heuristiek van `qv:scan` route-evenredig gemaakt (optie C — usage-based). Veilig uitgerold, 0 regressies. JudoToernooi blijft `high` (residu = JudoToernooi-scope, geen meetartefact).

## Wat is er gedaan (24 juni — forms-coverage usage-based)

Overdracht uit een JudoToernooi-sessie (`scratchpad/HAVUNCORE-forms-coverage-plan.md`): de `forms`-check ondertelde omdat een **gedeelde** FormRequest (op `store`+`update`) als 1 klasse telde. Henk koos **optie C** + direct implementeren.

- **Fix** (`QualitySafetyScanner::formsCoverage()`): naast de legacy occurrence-telling nu een **usage-telling** — FormRequest type-hint-injectiepunten (`function store(FooRequest $r)`) i.p.v. klassedefinities. Gedeelde FormRequest telt 1× per route. Regex leunt op de `*Request`-naamconventie (sluit base `Request $request` uit). Gating-mode via `config('quality-safety.forms_coverage_mode')` (default `usages`, `occurrences` = rollback-valve). **Dual-compute:** beide getallen altijd in de finding-payload.
- **Tests:** skeleton type-hint nu elke FormRequest (`*Request`-genaamd) → 6 bestaande forms-tests groen onder beide modi. 3 nieuwe: gedeelde-FormRequest (kern), mode-rollback, dual-compute-payload. Scanner-suite **73 groen**.
- **Verificatie op 7 lokale projecten** (zie `docs/kb/runbooks/forms-coverage-heuristic.md`): `use% ≥ occ%` overal, **0 regressies** → default `usages` veilig.
- **JudoToernooi: 56%→59%, blijft `high`.** Optie C is hier ontoereikend — het gat zit in input-loze write-routes (215 routes) + mogelijke service-laag-validatie, **niet** in de gedeelde-FormRequest-ondertelling. Geen HavunCore-fix: vereist write-route-audit in een **JudoToernooi-sessie** (de bijlage die het overdracht-doc openliet). Bewust niet groen geforceerd (§4.3: teller niet losser maken).

### Residu opgeruimd — JudoToernooi prod
`.env.bak.2026-05-02-225842` (52d) op `/var/www/judotoernooi/repo-prod/laravel/` was archief-kandidaat. Pre-check (live `.env` 2972b >100) → `mv` naar `/var/backups/havun-env/judotoernooi/` (600, root:root). Residu-scan daarna 0 findings. Purge (`rm`) pas ~eind juli (90d), handmatig.

## Vorig werk (22-23 juni)
test-quality-policy §11 (realtime/visual/device) + master-compliance-matrix. Doc Intelligence outdated-treadmill structureel gestopt.

## Wat is er gedaan (22-23 juni — strengere testeisen, policy-breed)

Henk wil dat **álle** projecten aan strenge test/kwaliteitseisen voldoen — niet alleen waar het toevallig al staat. Twee docs-deliverables (geen code, expliciet "geen code"):

### §11 toegevoegd aan `test-quality-policy.md` (commit `e4120c6`)
Vier eisen die mock-gebaseerde E2E (§10) niet dekt, generiek geformuleerd (judo als voorbeeld):
- **11.1 Realtime/cross-device** — echte broadcaster aan (geen `BROADCAST_CONNECTION=null`), ≥2 browser-contexts, assert B-update na A-actie, incl. reconnect. §10's mock-regel heeft hiervoor nu een uitzondering.
- **11.2 Full-suite mutation-sweep** — als audit bovenop kritieke-paden-mutation (§7).
- **11.3 Visual regression** (`toHaveScreenshot`) op pixel-fragiele schermen, desktop-only.
- **11.4 Echt-device sweep** — handmatige/BrowserStack-gate; gat hoort in project-handover.

### Master-compliance-matrix `test-quality-compliance.md` (commit `2eecde1`)
Nieuw, BINDING. Zet elk project af tegen alle dimensies (KP/MUT/E2E/RT/VIS/DEV) met ✅/🟡/❌/❓/n.v.t. + gaten-prioriteit + bewaking. Single source of truth voor "voldoet project X?". Policy + playwright-rollout-plan linken ernaar (rollout-plan = het §10-deel hiervan).
- **Grootste gaten:** (1) JudoToernooi realtime E2E (kern, nooit echt getest), (2) HavunAdmin E2E (Mollie+Stripe, niets), (3) JudoToernooi specs groen+CI, (4) Herdenkingsportaal specs, (5) visual JudoToernooi, (6) mutation-status overal vaststellen.
- **MUT-kolom staat overal op ❓:** actuele mutation-score is nergens centraal bijgehouden — eerste actie per project-sessie.
- **Scope:** dit overzicht bijhouden = HavunCore. Gaten dichten = eigen project-sessie.

## Wat is er gedaan (22 juni — outdated-treadmill gestopt)

Henk: "werk die 85 issues ook weg, schone lei staat het best." De 85 open issues waren allemaal `outdated` (puur age-staleness) in **externe** project-repos (havuncore-webapp 24, vpdupdate 18, idsee 9, havun 6, studieplanner(-api) 3, havunvet 2, agorano 1). Bulk-ignoren = symptoombestrijding: `detectOutdated()` dedupte alleen op **open** issues, dus elke `docs:detect` maakte ze opnieuw aan (de treadmill die ik 14 juni als observatie meldde).

**Structurele fix** (`IssueDetector::detectOutdated()`, commit `6c6dbaa`): de dedup-check telt nu óók `ignored`/`resolved` outdated-issues mee — een bewust afgehandelde staleness-reminder regenereert niet **zolang de doc niet is gewijzigd**. Wordt het bestand ná de afhandeling aangepast (`file_modified_at` > `resolved_at`), dan mag het opnieuw geëvalueerd worden. Alleen `outdated` — broken links/inconsistenties zijn echte content-fouten en blijven wél terugkomen.
- 2 unit-tests toegevoegd (regenereert-niet + reflag-na-wijziging). Suite **1259 groen**.
- KB bijgewerkt: `runbooks/doc-intelligence-setup.md` §Detectie-precisie.
- Geverifieerd: `docs:detect` over álle projecten → 0 nieuwe outdated, `docs:issues` → **0 open**. Komt niet meer terug bij volgende /start.

**Laatste werk (20 juni):** nieuw project **Agorano** opgezet (greenfield, eigen repo). Playwright E2E-werkwijze beleidsconform gemaakt + uitrolplan. guzzle/psr7 security-patch bij /start.

## Wat is er gedaan (20 juni — Agorano opzet + Playwright-uitrol)

### Nieuw project Agorano (politiek + crypto intelligence + B2B/B2C-netwerk)
Greenfield opgezet vanuit deze sessie (meta-setup). Eigen repo: **GitHub private `havun22-hvu/Agorano`**, lokaal `D:\GitHub\Agorano`.
- **Kernkeuzes (Henk):** beide gecombineerd (info + netwerk, gefaseerd), hybride KB, Havun-patroon. **Crypto-scope: puur info + gebruikerstoepassingen, GEEN markt/speculatie → buiten MiCA/AFM.**
- **Scaffold:** Laravel 12.62 backend + Vite React PWA (`vite-plugin-pwa`, `/api`-proxy). 14 Claude-commands + alle werkwijze-docs + architectuur/compliance-docs.
- **In HavunCore geregistreerd:** `havun-projects.php` (agorano → :8007, want 8006=Infosyst) + poort-register. **Let op:** dat is NIET genoeg voor Doc Intelligence — de DocIndexer heeft een **eigen hardcoded projectlijst** (`$localPaths`/`$serverPaths`). Agorano daar ook toegevoegd (localPaths). `docs:index agorano` werkt nu (31 docs, /kb getest). server-pad nog niet (niet gedeployd). **Structureel verbeterpunt:** projectlijst is gedupliceerd (havun-projects.php ↔ DocIndexer) — ooit consolideren.
- **Open (Henk):** server-deploy (domein + DNS + vhost + deploy-key — verboden-zonder-overleg, per stap), Fase 1-bronkeuze. Verder werk = **Agorano-sessie**.

### Playwright E2E — werkwijze-gat gedicht + uitrolplan
- **Gat:** beleid (`test-quality-policy.md` §10) schreef E2E voor, maar `/test` en `/start` dwongen het niet af. **Gedicht:** `/test` stap 3 (Playwright bij UI, meldt gat + blauwdruk) + `/start` kwaliteitsnorm — in HavunCore-template én Agorano.
- **Nieuw doc:** `docs/kb/reference/playwright-rollout-plan.md` — per-project status + volgorde. Slechts 2 projecten hadden het echt (webapp ✅, JudoToernooi specs-maar-CI-draait-niet). JudoScoreBoard/Studieplanner = React Native **native** (geen web-E2E), Aeterna = Tauri → buiten scope.
- **JudoToernooi-bevinding:** lokaal de 9 specs gedraaid (geïsoleerde e2e.sqlite) → **≥4 falen** (mat overview/interface + Windows pad-fouten; 2× CSP-violations). **CI bewust NIET gewired** op rode specs. Repo heeft al WIP (`testplan-playwright.md`, `diag.auth.spec.ts`, `flows.auth.spec.ts`) → afronden in **JudoToernooi-sessie**.

## Wat is er gedaan (20 juni — /start)

### guzzle + psr7 security-patch (3 medium, commit e5a6642)
`composer audit` bij /start meldde 3 medium advisories binnen de bestaande `^7.8`-constraint:
- CVE-2026-55568: silent HTTPS proxy downgrade to cleartext (guzzle)
- CVE-2026-55767: CRLF injection in HTTP start-line (guzzle)
- CVE-2026-55766: CRLF injection in start-line serialization (psr7)

`composer update guzzlehttp/guzzle guzzlehttp/psr7 --with-dependencies` → guzzle **7.12.1**, psr7 **2.12.1**. composer.json ongewijzigd (lockfile-only, constraint dekte de patch al). `composer audit` schoon. npm audit n.v.t. (geen package.json in repo-root).

### Doc Intelligence — 0 open
havuncore zelf 0 issues. 83 open issues in externe project-repos (havuncore-webapp 24, vpdupdate 18, idsee 9, havun 5, infosyst 5, havunvet 2, studieplanner(-api) 3, e.a.) waren allemaal "Verouderd" (age-staleness, geen HIGH/broken links/inconsistenties) → bulk-genegeerd `auto-start-2026-06-20`. Bekende treadmill, buiten HavunCore-scope.

## Wat is er gedaan (17 juni — sessie 2)

### phpseclib SSRF-patch (medium, GHSA-m557-wrgg-6rp4)
`composer audit` bij /start meldde phpseclib 3.0.15 kwetsbaar (X.509 AIA → SSRF, gemeld 16 jun). `composer update phpseclib/phpseclib` binnen de bestaande `^3.0`-constraint (transitief via league/flysystem-sftp-v3) → gepatcht, audit schoon. Commit `2837f92` (alleen composer.lock).

### Toon & feedback-gedragsregels uitgerold
Henk wil nuchter: geen complimenten/bevestigend meepraten, actief corrigeren (*"klopt, maar..."*), conclusie eerst zonder omslachtige inleiding. Vastgelegd in globale `~/.claude/CLAUDE.md` (§Toon & feedback), `HavunCore/.claude/commands/start.md` (commit `91397d0`) en memory `feedback-tone-no-flattery`. NB: globale CLAUDE.md + memory liggen buiten de git-repo (lokaal op deze machine, niet in versiebeheer).

## Wat is er gedaan (17 juni — sessie 1)

### lastmatch.havun.nl onbereikbaar — gediagnosticeerd (geen serverprobleem)
Henk meldde "niet beveiligd / kan niet geopend worden". Diagnose: DNS ✅ (→188.245.159.115), HTTP 301→HTTPS ✅, site draait ✅ (PWA HTML, `curl -k` = 200), echt **Let's Encrypt-cert geldig** (vandaag 07:08 uitgegeven, t/m 14 sep 2026, bevestigd in crt.sh CT-logs). Oorzaak = **lokaal**: Avast Web/Mail Shield onderschept HTTPS (cert-issuer "Avast Antivirus for SSL/TLS scanning") en de browser vertrouwt dat her-signeerde cert niet. Fix ligt bij Henk (incognito / Avast HTTPS-scanning uit). NB: `curl` op deze Windows-machine gaat door Avast → revocatiefout `CRYPT_E_NO_REVOCATION_CHECK`; omzeil met `--ssl-no-revoke`.

### Beslissing: The Last Matchstick wordt native Android (PWA verlaten)
Henk stopt met de PWA-route → native Android-app. Vastgelegd in memory `project-lastmatch`. **Opvolging (alleen met Henk's go, raakt serverconfig):** de uitgefaseerde PWA-deploy op prod opruimen — nginx-vhost `lastmatch.havun.nl` + Let's Encrypt-cert + gedeployde files. Draait nu nog live, doet geen kwaad. LastMatch zelf = apart project (eigen git, buiten HavunCore-scope).

## Wat is er gedaan (16 juni)

### The Last Matchstick — projectsetup + freemium/PWA-onderzoek (cross-project, in `D:\GitHub\LastMatch`)
MAUI-game uit Visual Studio geïmporteerd. Lite + full → samenvoegen tot één app met freemium-unlock.

## Wat is er gedaan (16 juni)

### The Last Matchstick — projectsetup + freemium/PWA-onderzoek (cross-project, in `D:\GitHub\LastMatch`)
MAUI-game uit Visual Studio geïmporteerd. Lite + full → samenvoegen tot één app met freemium-unlock.
- **Werkwijze-infra aangelegd in LastMatch:** `CLAUDE.md`, `.claude/{context,rules,handover,blueprint,platform-onderzoek}.md` + commands (`start`/`mpc`/`test`, MAUI-passend; geen KB/Laravel-commands). Staan nog **uncommitted** in LastMatch's eigen git — wordt in de LastMatch-sessie gecommit.
- **Freemium-blauwdruk** op basis van het Studieplanner-model (bunq.me/Havun + `lm-`-prefix → HavunAdmin bunq-sync → premium-API). Legaliteitsbasis = distributie buiten de Play Store.
- **Platform-onderzoek:** Google verplicht vanaf 2026 (NL ~2027) developer-verificatie voor álle Android-apps, óók sideload. Conclusie destijds: **PWA** (JS/TS + Vite) omzeilt dit en houdt het Havun-freemium intact. **→ Achterhaald 17 jun: Henk koos alsnog native Android (zie 17-juni-sectie boven).**

### ⚠️ Security-bevinding (actie in HavunAdmin-sessie)
- [ ] **HavunAdmin:** hardcoded **staging Bearer-token** in `docs/05-api-integration/API-SYNC-HERDENKINGSPORTAAL.md` (regel 257 + 423). Strijdig met "geen secrets in code/docs" — opruimen + uit historie purgen in een HavunAdmin-sessie. Zie [[feedback-no-hardcoded-test-secrets]].

## Wat is er gedaan (14 juni)

### Doc Intelligence — false-positives bij de bron weggenomen (0 open over alle projecten)
Bij `/start` stonden 143 open issues (53 duplicate + 75 outdated + ...). Henk: "pak alle issues op" → niet negeren maar structureel oplossen. 5 SaaS-brede fixes (werken voor élk project, voorkomen regeneratie). Suite **1257 groen** (+8 tests).

**`DocIndexer.php`:**
1. **Nested-project-uitsluiting**: een map die zélf een geconfigureerd project is wordt niet ook onder de parent geïndexeerd. `havuncore-webapp` (`/webapp`) zat ín `havuncore` → elke webapp-doc werd dubbel geteld (alle 20 havuncore-"outdated" + 3 duplicaten waren phantom `webapp/*`). 44 phantom-embeddings verwijderd.
2. **Build-/test-output uitgesloten**: `dist`, `build`, `playwright-report`, `test-results`, `coverage` toegevoegd aan `excludePaths`. JudoToernooi's 10 "duplicaten" bleken Playwright `error-context.md`-artefacten (terecht als duplicaat herkend door de nieuwe gate, maar horen niet geïndexeerd).

**`IssueDetector.php`:**
3. **Lexicale-overlap-gate op duplicaten**: náást cosine ≥ 0.90 nu óók verbatim-overlap vereist (Jaccard word-trigrams ≥ 0.30). Elimineert "zelfde-onderwerp-andere-inhoud" (twee `server.md`'s, ADR's, parallelle per-project refs) → havuncore 20→0, idsee 5→0, safehavun 3→0, havunadmin 4→0, infosyst 1→0.
4. **Broken-link `#anchor`-fragment strippen** vóór bestandsresolutie. JudoToernooi's enige broken link (`./README.md#uitslag-...`) was een false positive — README.md bestaat, alleen het fragment werd als bestandsnaam gelezen.
5. **Gedateerde snapshots frozen**: `*-YYYY-MM-DD.md` (bv. `mutation-baseline-2026-04-17.md`) niet meer als outdated.

**Eindresultaat:** alle duplicate- + broken-link-false-positives weg (0 over alle projecten, regenereren niet). Resterende 65 `outdated` = echte leeftijds-staleness van stabiele docs in **andere project-repos** (havuncore-webapp 19, vpdupdate 17, havunclub 11, idsee 8, infosyst 5, judotoernooi 3, havunadmin 2) — updaten vereist per-project-sessies (scope: alleen HavunCore). Bulk-genegeerd met reden `age-staleness-external-repos-2026-06-14`. KB: `doc-intelligence-setup.md` bijgewerkt.

> **Observatie voor Henk:** de `outdated`-categorie is een treadmill — flagt elke stabiele doc >90d, wordt elke `/start` weer genegeerd, regenereert. Overweeg: drempel omhoog, scopen tot living-docs, of de categorie als puur informatief markeren. Business-keuze, niet eigenhandig aangepast.

## Wat is er gedaan (13 juni)

### Webapp-deploy + server-checkouts opgeschoond (prod 188.245.159.115)
Henk's go op alle drie de openstaande 9-juni-punten (A/B/C) + webapp-deploy.
- **SafeHavun** (`/var/www/safehavun/production`): `reset --hard origin/master` → `1635042` (losse server-commit, landing al in origin) → **`96aa4a1`**. Checkout nu schoon.
- **HavunAdmin** (`/var/www/havunadmin/production`): `reset --hard origin/main` → losse `d6068a4` (gemini-workflow drift, **niet bewaard** per Henk's keuze) → **`51f073b`**. Bleek dat origin de havun:gemini-config al in nieuwere vorm bevat (`51f073b` = "add autoMode allow for havun:gemini"), dus de losse commit was terecht achterhaald. Checkout nu schoon.
- **Webapp-deploy** (`/var/www/havuncore/webapp`): source stond al op `502c125` (benign-filter), maar pm2 draaide 5d oude build. `npm run build` (frontend) → `rsync -a --delete --exclude=/downloads --exclude=/aeterna-snapshot.apk dist/ public/` (stale `index-utGneFkT.*` opgeruimd, apk+downloads behouden) → `pm2 restart havuncore-backend`.
- **Geverifieerd:** PWA 200, live asset nu `index-CCbwtUpZ.js` (verse hash), backend schoon herstart op :3001 (401 = auth-protected, leeft). Beide checkouts `git status --porcelain` leeg → Projects-tab toont HavunAdmin + SafeHavun groen (benign-filter live).

### guzzle/psr7 security-patch (commit 7f06279)
- `composer update guzzlehttp/psr7` → **2.11.0** (was <2.10.2). Lost CVE-2026-48998 (Host Confusion) + CVE-2026-49214 (CRLF Injection) op. `composer audit` schoon. Volledige suite 1249 groen.

### Doc Intelligence — detector-kalibratie + issue-opruiming (0 open, commit f9f70be)

### Doc Intelligence — detector-kalibratie + issue-opruiming (0 open)
Bij `/start` stonden 202+ open issues, waarvan 24 "HIGH" die in werkelijkheid puur leeftijds-staleness waren. 5 structurele fixes in `app/Services/DocIntelligence/IssueDetector.php` (+ tests, 78 unit-tests groen):
1. **Archive-uitsluiting** (`detectOutdated`): `archive/`, `archived/`, `legacy/`, `_history/`-paden worden nooit meer als verouderd geflagd (bevroren docs). `isFrozenDoc()` + `frozenDocPatterns`.
2. **Outdated-severity herijkt** (Henk's keuze): `>180d → MEDIUM` (was HIGH), `>90d → LOW`. HIGH blijft gereserveerd voor échte content-fouten (broken links, prijs-inconsistenties). `/start`'s HIGH-regel weer betekenisvol.
3. **Non-file-schemes** (`detectBrokenLinks`): `mailto:`/`tel:`/`sms:`/`ftp:`/`data:` worden overgeslagen (stond bv. `taylor@laravel.com` als broken link).
4. **Mermaid code-fences gestript** vóór link-extractie: `[label<br/>...]` in ```mermaid-blokken werd als broken markdown/wiki-link gelezen.
5. **Memory-wikilinks in `.claude/`-docs** (`[[slug]]` in handover/context) worden overgeslagen — dat zijn memory-store-refs, geen doc-links.
- **Resultaat na herdetect-all:** 0 HIGH, 0 broken links, 0 inconsistencies. Resterende 36 duplicaten (false-positive-principe) + 99 outdated (maintenance-signaal) bulk-genegeerd → **0 open over alle projecten**.
- Duplicaten + non-archive outdated regenereren bij volgende `docs:detect` (docs zelf ongewijzigd) — dat is de geaccepteerde steady-state, `/start` bulk-negeert die. De gefixte categorieën (archive/mailto/Mermaid/memory-wikilinks/false-HIGH) komen NIET meer terug.

### Nog open (vereist Henk)
- [ ] Henk: incident #33883984 in GitGuardian op *Resolved / False positive* zetten.
- [ ] Henk: WIP terughalen in webapp-repo (`git stash pop`, stash@{0} "henk-wip-claude-commands-2026-06-07").
- [x] ~~Webapp-deploy benign-filter~~ — gedaan 13 jun (zie boven).
- [x] ~~HavunAdmin/SafeHavun server-checkout opschonen (9-juni A/B/C)~~ — gedaan 13 jun.

## Wat is er gedaan (11 juni)

### GitGuardian-melding: hardcoded WebAuthn-testsleutel opgeruimd
- GitGuardian (#33883984) flagde een P-256 PKCS#8 private key in `havuncore-webapp:frontend/e2e/webauthn.js` (commit `c78bd58`). Throwaway testsleutel (mock-backend, signatures nooit geverifieerd) → **geen rotatie nodig**, maar Henk: *"ook testsleutels netjes wegwerken, het gaat om het principe"*.
- **Bron gefixt:** sleutel wordt nu per testrun gegenereerd (`generateThrowawayPrivateKeyB64` via `crypto.generateKeyPairSync`) i.p.v. hardcoded. Beide biometrie-tests groen.
- **Historie gepurged:** alleen `c78bd58` bevatte het secret. Via `git reset --soft <parent>` + `git commit -C c78bd58` de commit herschreven (schone tree, identieke inhoud — diff vs backup leeg), daarna `git push --force-with-lease`. Nieuwe commit `502c125`, oude `c78bd58`/`b400915` onbereikbaar op origin. `git log -S` op main = 0 treffers.
- **Principe vastgelegd:** `docs/kb/runbooks/geen-hardcoded-secrets-in-tests.md` (runtime-keygen + purge-procedure + GitGuardian-checklist).
- **Productie-checkout gereset (gedaan):** server stond op `1bd11b1` (achter, had de secret-commit nooit gepulld). `git fetch` + `git reset --hard origin/main` → nu op `502c125`. Lokale drift op `projectStatusService.js` was een redundante handmatige kopie van de benign-filter (origin had 'm al gecommit) → veilig gereset, backup op server `/tmp/projectStatusService.js.server-drift-2026-06-11.bak`. `?? public/` (build-artefacten) ongemoeid.
- **NOG TE DOEN:**
  - [ ] Henk: incident #33883984 in GitGuardian op *Resolved / False positive* zetten.
  - [ ] **Webapp-deploy om de filter live te zetten:** reset wijzigde alleen de source; pm2 draait nog `1bd11b1`-code. Voor HavunAdmin groen in Projects-tab: frontend build → `dist`→`public` → `pm2 restart havuncore-backend`. Geen haast — gebeurt anders bij eerstvolgende reguliere deploy.

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
