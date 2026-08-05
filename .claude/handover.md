---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-08-03
---

# HavunCore — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels. Regel: `docs/kb/standards/md-doc-grootte.md`.

**Branch:** master · **Status:** stabiel, 1415 tests groen, KB-audit 0 high. **Server:** disk 68%,
prod draait overal, 0 dirty checkouts, 0 stashes. **Alles staat live** (05-08, geen migraties).

**Afgerond 01/02-08:** drie secrets geroteerd (GitHub-PAT + MySQL van `havunadmin` en `havuncore`,
alle drie via de app geverifieerd, backups in `/root/backups/`), en twee backupgaten gedicht
(Herdenkingsportaal dumpte 4,5 maand de verkeerde database; HavunAdmins facturen zaten nergens in).
Beide restores getest. Volledig: `runbooks/secrets-veilig-ontvangen.md`,
`plans/registry-drift-check-plan.md`, `reference/databases-op-de-server.md`.

## De V&K-scan meet op de server weer (03/04-08)

Vier lagen van dezelfde fout, alle vier gemeten op de echte runs en alle vier gefixt. Het patroon
en de regels die eruit volgen: `patterns/bewaking-die-niets-meet.md`.

| Was | Nu |
|---|---|
| `backup-coverage` + `serverHealth` gingen via SSH naar `root@` — vanaf die server zelf | Backupscript schrijft een manifest; `runRemote()` draait lokaal als de host deze machine is. `errors=1` → `errors=0`, geverifieerd met een kunstmatige drempel (`/ — 68% full`) |
| `composer`/`npm`/`cargo` gebruikten `D:/GitHub/…` op Linux → 40 errors = nul projecten gemeten | Pad dat hier bestaat wint (let op: `server_path` **én** `remote_path`). Composer 2.10.2 erbij, hash geverifieerd — 2.2.6 kende `audit` niet |
| `qv:log`/`docs:handover` lazen één run per dag; de 8 wekelijkse checks hadden **nooit** iets gerapporteerd | `MergedRunAssembler` voegt 8 dagen samen, nieuwste run per check, mét de tijd per check |
| `actions:watch` had drie kwalen tegelijk: geen `gh`, Windows-pad, en de regex herkende de per-repo SSH-aliassen niet | Rechtstreeks naar de GitHub-API met `github_pat_ro`. Nul repo's of een onbereikbare repo = luide fout **plus** health-alert (cron-stdout gaat naar `/dev/null`) |

**Opbrengst: 2 critical + 22 high die niemand ooit gezien had**, plus studieplanner-api rood sinds
20 uur. Details in de tabel hieronder.

**Correctie op de diagnose van 02-08:** de scheduler draait **als root** (alle `schedule:run` staan
in roots crontab; de qv-scanbestanden zijn `root:root`), niet als `www-data`. De SSH-fout kwam dus
doordat *de server geen sleutel naar zichzelf heeft*. Bijvangst: die root-cron maakt `storage/**`
root-owned, waardoor `cache:clear` als `www-data` faalt — na elke deploy `chown` nodig.

**Jouw beslissing:** `herdenkingsportaal_production` bestaat nog en is de valstrik zelf. Dump in
`/root/backups/hp-dode-db-2026-08-01`; droppen is een prod-database.

## Open — wacht op Henk

| Wat | Details |
|-----|---------|
| **Gelekt login-wachtwoord (`…ZxO#`) — rotatie loopt (19-07)** | Google meldde leak; waarde was over 10 havun.nl-sites hergebruikt. **Gedaan:** wachtwoord-login van `henkvu@` dood op HavunCore/SafeHavun/Studieplanner (rij-backups `/root/backups/pwreset-2026-07-19`). **Jij nog doen:** (1) `scripts/rotate-leaked-login.sh` draaien in Git Bash → nieuw wachtwoord voor HavunAdmin (prod+staging) + JudoToernooi `.env`; (2) `infosyst`+`staging.havunclub` uit Google verwijderen. **Apart (eigen sessie):** VPD/vpdupdate; HavunAdmin magic-link bouwen; password-kolommen nullable op de 3 magic-link-apps |
| **GitHub-PAT ziet 4 van de 8 repo's niet** | `github_pat_ro` (Vault) geeft 404 op HavunAdmin, Herdenkingsportaal, VPDUpdate en havuncore-webapp — hij is gemaakt voor de mobiele monitoring. **Jij:** in GitHub de fine-grained PAT uitbreiden naar alle `havun22-hvu`-repo's (alleen `metadata:read` + `actions:read` nodig), daarna `php artisan vault:setup-mobile-monitoring --from-env`. Tot die tijd meldt `actions:watch` het elke ronde |
| **Vier rode builds** | HavunAdmin 3 maanden · HavunClub 3 maanden (geparkeerd) · VeenLedenadministratie · Studieplanner-api (04-08 nog steeds rood). Uitzoeken hoort in de projectsessie zelf |
| **Security: dependencies — 2 critical + 22 high, nooit eerder gerapporteerd** | Zichtbaar door de scanfixes van 03-08. **npm:** Studieplanner-mobile 2 critical (`shell-quote`, `tar`) + 6 high · havun.nl 3 high (next, postcss, sharp) · VPDUpdate 1 high (`xlsx`, geen fix — vervangen door exceljs). **composer:** Studieplanner-api 6 high + 24 medium · JudoToernooi 3 high + 10 medium · SafeHavun 3 high + 17 medium (laravel/framework, symfony, web-token/jwt). HavunAdmin, Herdenkingsportaal en HavunCore zijn schoon. **Elk in de eigen projectsessie** — `composer update`/`npm audit fix` op productie-apps → overleg. Los daarvan: JudoScoreBoard 6 GitHub-advisories (1 critical + 2 high) |
| **AutoFix draait nu op Haiku 4.5** | Jouw keuze 05-08. Haiku is de goedkoopste klasse maar zwak op code-analyse, en AutoFix doet precies dat: productie-errors lezen en een fix voorstellen. Levert het slechtere voorstellen op → `CLAUDE_MODEL=claude-opus-5` in prod-`.env`. Alternatief zonder de chat duurder te maken: model splitsen per gebruik (extra config-key + kleine wijziging in `AIProxyService`) |
| **Blijvend-ingelogd-plan** | Geschreven, wacht op "ga maar" — `plans/blijvend-ingelogd-plan.md` |
| **Hardcoded Hetzner-wachtwoord op server** | `/usr/local/bin/havun-backup.sh` (`HETZNER_PASS=` plain text). Hoort in de Vault |
| **Stripe-sleutel geroteerd (JudoToernooi) 19-07** | Oude `sk_live_…4l13` staat nergens actief meer. **Laat 'm in Stripe verlopen.** Optioneel: webhook-secret roteren + `credentials.md` opschonen |
| **VPDUpdate: `users.json` blijft een risico** | Staat alleen op de server (+ backup), **loopt sinds 01-08 mee in de nachtelijke backup**. Nog open: de secrets zitten nog in de git-historie — purgen is een eigen sessie |
| **Vusista 1** | DNS `vusista.havun.nl` bij mijn.host + deploy-key `server-read` uit de (gearchiveerde) repo. De lege map `D:\GitHub\Vusista` verdwijnt zodra je dat VS Code-venster sluit |
| **GitGuardian #33883984** | Op *Resolved* zetten |
| **Server OS-update** | Volgende kwartaalcheck oktober 2026. Runbook: `runbooks/server-os-updates.md` |
| **Aeterna** | Prod keystore + update-adres. Week2-plan dood — archiveren. `feat/v1.1-tor-socks5-3b` (PR #16 closed, niet merged) |
| **Studieplanner** | `chore/expo-sdk-55-upgrade`: 230/230 groen maar nooit device-getest, 3 mnd oud — mergen of verwerpen |
| **Studieplanner-api: coverage is deels padding (24-07)** | 91,9% / 322 tests. `PremiumController` 67,7%, `UserDevice` 0%. **Ernstigst:** `MagisterApiTest`/`SOMtodayApiTest` leggen met `assertStatus(500)` vast dat een onbereikbare externe API een 500 van ónze API geeft — hoort 502/503. Eigen sessie, volgorde in `Studieplanner-api/docs/testschuld.md`. Los daarvan: `rescue/prod-stashes-2026-07-15` afmaken of weg |
| **LastMatch** | Avast HTTPS-scanning uit = enige APK-build-blocker |
| **JudoScoreBoard** | Google-review AAB 116 (9 juni ingediend) — status alleen in Play Console |

## De AI-proxy lag 19 uur plat en niemand kreeg een melding (05-08)

`claude-3-haiku-20240307` is per **19-04-2026** door Anthropic uitgefaseerd. Vannacht 00:30 begon
elke call 404 te geven — 46 calls, 100% mislukt, AutoFix stil voor Herdenkingsportaal en
JudoToernooi. Gevonden bij het lezen van de productielogs na een deploy, niet door een alert.

**Opgelost:** `.env` op prod → `CLAUDE_MODEL=claude-haiku-4-5` (backup in `/root/backups/`),
config-default mee, echte API-call geverifieerd. De tweede default in `AIProxyService` is weg —
één plek om te wijzigen. Jouw keuze was Haiku 4.5; mijn kanttekening staat hieronder.

**Nog open:** een 404 van de AI-API bereikt niemand. De health-alerts bestaan al
(`health:alert`) — er hangt alleen niets aan deze fout. Regel: `patterns/model-id-verloopt.md`.

## Open — te doen

- **Vier andere duurmetingen staan nog op `microtime(true)`** — `RequestMetricsMiddleware`,
  `Chaos\ChaosExperiment` (2×), `CriticalPaths\TestRunner`. Migreren naar `App\Support\Timing\
  Stopwatch` in een eigen commit; geen haast, geen van hen is flaky. `plans/tijdmeting-
  injecteerbaar-plan.md`.
- **Web-push voor `critical` health-alerts — gebouwd, nooit getest.** Rest = één browser-test.
  `plans/health-alerts-webpush-blueprint.md`. Leesval: valt terug op `localhost:8009` (lege stub).
  Los daarvan: `laravel-worker` + `toernooi-heartbeat` onbewaakt.
- **havuncore-webapp update-banner — niet reproduceerbaar (24-07).** Wéér last? Check
  `getRegistration()` op een `waiting`. `plans/webapp-sw-update-fix.md`. Vitest daar geblokkeerd
  door Avast, niet de registry — [[env-ssl-interception]].
- **Drie CLAUDE.md's boven de 120-regelnorm** — Studieplanner-api 135, JudoScoreBoard 136,
  havuncore-webapp 125.
- **JudoScoreBoard `context.md` op master nog 1039 regels** — opgeschoonde versie staat op
  `chore/expo-sdk-56-upgrade`; lost zichzelf op bij merge.
- **`origin/rescue/havuncore-prod-autocommits-2026-07-25`** — 10 commits die nergens anders bestaan
  (`chore(auto)`-snapshots van 15–25 juli), inhoudelijk achterhaald. Beoordelen en dan weg.

**Veen-ledenadministratie — GEPARKEERD (31-07).** Niets mee doen, ook de kleine betaalde klussen
niet. Onze serveromgeving is opgeruimd; de lokale checkout blijft en wordt gescand. **⛔ De oude app
van Cees op `37.34.60.216` (TransIP) is niet van ons en is niet aangeraakt.** Volledig, inclusief de
openstaande high: `projects/veen-ledenadministratie.md`.

## Vaste context voor dit project

- **Rol:** centrale kennisbank + orchestrator. **Alleen HavunCore aanwerken; ander project = eigen
  sessie** (tenzij Henk expliciet toestemt). Zie [[feedback-scope-waarschuwen]].
- **Geparkeerd, géén uitrol meer:** HavunClub, Demo, Havunity, Infosyst, IDSee, Agorano, Veen
  (31-07), HavunVet (01-08). Munus is weg; HavunVet en Vusista 1 zijn gearchiveerd. Hun databases
  blijven staan met reden: `reference/databases-op-de-server.md`.
- **De 6 Onschendbare Regels** staan canoniek in `runbooks/claude-werkwijze.md` §0 — verwijs
  ernaar, kopieer ze niet.
- KB zoeken: `php artisan docs:search "<onderwerp>"` — vereist Ollama op :11434.
- **Eerste prod-deploy per app = Henk klikt bewust.** Nooit auto-migrate op prod.
- **De scheduler draait als root** (roots crontab, elke minuut per app) — daardoor worden
  `storage/**` en de qv-scanbestanden root-owned en faalt `cache:clear` als `www-data`. Na elke
  deploy: `chown -R www-data:www-data storage bootstrap/cache`. Idem `safe.directory` voor
  `www-data` (nog te doen, server-config → overleg).
- havuncore-webapp deployt anders: lokaal build → rsync + pm2 (`havuncore-webapp/DEPLOY.md`).
- Een Vite-build is pas gedeployd als de **asset-hash** op de site verandert —
  `runbooks/vite-build-bij-deploy.md`, inclusief de check of er iets te bouwen valt.
