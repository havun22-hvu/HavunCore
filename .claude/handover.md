---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-08-03
---

# HavunCore — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels. Regel: `docs/kb/standards/md-doc-grootte.md`.

**Branch:** master · **Status:** stabiel, 1403 tests groen, KB-audit 0 high. **Server:** disk 68%,
prod draait overal, 0 dirty checkouts. **Alles van 03-08 staat live** (geen migraties). HavunAdmin
heeft 16 commits / 7 codebestanden klaar uit een andere sessie.

**Afgerond 01/02-08:** drie secrets geroteerd (GitHub-PAT + MySQL van `havunadmin` en `havuncore`,
alle drie via de app geverifieerd, backups in `/root/backups/`), en twee backupgaten gedicht
(Herdenkingsportaal dumpte 4,5 maand de verkeerde database; HavunAdmins facturen zaten nergens in).
Beide restores getest. Volledig: `runbooks/secrets-veilig-ontvangen.md`,
`plans/registry-drift-check-plan.md`, `reference/databases-op-de-server.md`.

## ⛔ De V&K-scan meet op de server bijna niets (03-08) — twee van drie gefixt

Gevonden bij het afmaken van de backupfix. Drie losse oorzaken, alle drie gemeten op de runs van
vannacht op prod:

1. **De rapportage las één run per dag.** Elke `--only=X` schrijft een eigen bestand; `qv:log`
   (03:27) en `docs:handover` (04:00) pakten het nieuwste. De observatory-run van 04:37 vond een
   **high** (safehavun grade C) die nergens stond, en de acht wekelijkse checks (04:07–05:47)
   hadden **nooit** iets gerapporteerd. Beide rapporten zeiden `high 0`.
   → **Gefixt:** `MergedRunAssembler` voegt 8 dagen samen, nieuwste run per check wint, en het
   rapport zegt nu per check wanneer die draaide. `plans/qv-rapportage-venster-plan.md`.
2. **`composer`/`npm`/`cargo` scanden op de server niets** — 40 errors `Project path not found:
   D:/GitHub/…`; de scanner gebruikte Henks Windows-pad. Dít is waarom de 34 advisories op
   Herdenkingsportaal 13 commits bleven liggen.
   → **Gefixt** (03-08): de scan pakt het pad dat op déze machine bestaat. Valkuil:
   `havun-projects.php` noemt het `server_path`, de scanlijst `remote_path` — alleen de eerste
   kennen loste niets op. Ook geïnstalleerd (jouw go): **composer 2.10.2** naast het Ubuntu-pakket
   2.2.6, dat `audit` niet kende (kwam pas in 2.4); hash geverifieerd tegen `installer.sig`.
   **Eindstand op de server: `errors: 0`, 6 terecht overgeslagen, en 12 high + 51 medium die
   niemand ooit gezien had.** `cargo` en `gh` ontbreken er nog — die checks melden dat nu eerlijk.
3. **`serverHealth`** gaat via SSH naar `root@` en valt op de server om — zelfde oorzaak als de
   backupcheck. → **Open.**

**Correctie op de diagnose van 02-08:** de scheduler draait **als root** (alle `schedule:run` staan
in roots crontab; de qv-scan-bestanden zijn `root:root`), niet als `www-data`. De oorzaak van de
SSH-fout is dus dat *de server geen sleutel naar zichzelf heeft*. Bijvangst: die root-cron maakt
`storage/**` root-owned, waardoor `cache:clear` als `www-data` faalt — 03-08 rechtgezet, maar het
komt terug zolang de cron als root draait.

## De backupcheck meet weer iets (03-08) — klaar, wacht op deploy

Het backupscript schrijft als root een manifest (`/var/lib/havun/backup-manifest.json`, 644, geen
wachtwoorden) — dat script staat er al en draait. De check leest dat en gebruikt SSH alleen nog
buiten de server. **Niets gemeten = critical** (en dan alléén die finding), **manifest ouder dan
26 uur = high**, en de handover toont `errors N`. Alleen de HavunCore-deploy staat nog open.
Volledig: `plans/registry-drift-check-plan.md`.

Apart punt, zelfde patroon: **`actions:watch` heeft op de server nooit gewerkt** — `gh` staat er
niet op, dus de crons van 07:00/19:00 controleren niets. Het log zegt dat eerlijk.

**Jouw beslissing:** `herdenkingsportaal_production` bestaat nog en is de valstrik zelf. Dump in
`/root/backups/hp-dode-db-2026-08-01`; droppen is een prod-database.

## Open — wacht op Henk

| Wat | Details |
|-----|---------|
| **Gelekt login-wachtwoord (`…ZxO#`) — rotatie loopt (19-07)** | Google meldde leak; waarde was over 10 havun.nl-sites hergebruikt. **Gedaan:** wachtwoord-login van `henkvu@` dood op HavunCore/SafeHavun/Studieplanner (rij-backups `/root/backups/pwreset-2026-07-19`). **Jij nog doen:** (1) `scripts/rotate-leaked-login.sh` draaien in Git Bash → nieuw wachtwoord voor HavunAdmin (prod+staging) + JudoToernooi `.env`; (2) `infosyst`+`staging.havunclub` uit Google verwijderen. **Apart (eigen sessie):** VPD/vpdupdate; HavunAdmin magic-link bouwen; password-kolommen nullable op de 3 magic-link-apps |
| **Vier rode builds (31-07)** | HavunAdmin 3 maanden rood · HavunClub 3 maanden (geparkeerd) · VeenLedenadministratie 1 dag · Studieplanner-api sinds 30-07. Gevonden door `actions:watch` **lokaal** — op de server is `gh` niet geïnstalleerd, dus die cron (07:00/19:00) heeft nooit iets gecontroleerd. Uitzoeken hoort in de projectsessie zelf |
| **Security: dependencies — 2 critical + 22 high, nooit eerder gerapporteerd** | Zichtbaar door de scanfixes van 03-08. **npm:** Studieplanner-mobile 2 critical (`shell-quote`, `tar`) + 6 high · havun.nl 3 high (next, postcss, sharp) · VPDUpdate 1 high (`xlsx`, geen fix — vervangen door exceljs). **composer:** Studieplanner-api 6 high + 24 medium · JudoToernooi 3 high + 10 medium · SafeHavun 3 high + 17 medium (laravel/framework, symfony, web-token/jwt). HavunAdmin, Herdenkingsportaal en HavunCore zijn schoon. **Elk in de eigen projectsessie** — `composer update`/`npm audit fix` op productie-apps → overleg. Los daarvan: JudoScoreBoard 6 GitHub-advisories (1 critical + 2 high) |
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

## Open — te doen

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
