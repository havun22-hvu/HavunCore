---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-08-01
---

# HavunCore — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels. Regel: `docs/kb/standards/md-doc-grootte.md`.

**Branch:** master · **Status:** stabiel, 1389 tests groen, KB-audit 0 high. **Server:** disk 68%,
prod draait overal, 0 dirty checkouts.

**Klaar om te deployen (jouw klik):** HavunCore zelf 10 commits / 11 codebestanden — de twee
V&K-checks, de docs-audit-fixes en de Vusista-opruiming van 01-08. Geen migraties. En HavunAdmin
6 commits / 4 codebestanden uit een andere sessie (oefenomgeving-markering, sticky kolomkoppen).

## 🔴 Twee dingen die morgen als eerste moeten

| Wat | Waarom nu |
|---|---|
| **`/root/roteer-havunadmin-db.sh` draaien** | Het MySQL-wachtwoord van `havunadmin` staat in een sessie-transcript (mijn fout: een grep op `^CENTRAL` over de staging-`.env` pakte ook `CENTRAL_DB_PASSWORD`). Geverifieerd: die waarde geeft **ALL PRIVILEGES op `havunadmin_production`**. Het script genereert 48 tekens op de server, test de verbinding vóór het een `.env` aanraakt, werkt prod+staging bij en doet een rooktest. Back-up vooraf naar `/root/backups/havunadmin-env-<datum>` |
| **GitHub-PAT verloopt ~08-08** | `havuncore-webapp-mobile-monitoring`, werkt nu nog. Maak een fine-grained token met toegang tot **judoscoreboard (privé)** + Studieplanner, permissions Metadata/Contents/Pull requests **Read**, en draai dan `/root/vervang-github-pat.sh` (leest hem verborgen in). Procedure: `reference/repo-hygiene-policy.md` |

## Backups: twee gaten gevonden en gedicht (01-08)

Volledig: `plans/registry-drift-check-plan.md` + `reference/databases-op-de-server.md`.

- **Herdenkingsportaal had 4,5 maand geen bruikbare databasebackup.** Van 15-03 t/m 27-07 dumpte
  het script `herdenkingsportaal_production` (dood restant, 47 rijen) terwijl de app op
  `herdenkingsportaal_prod` draait (50.520 rijen). Elke nacht een vers bestand van 5,1 KB, upload
  geslaagd — alles wat naar de *backup* keek zag een gezonde backup. Sinds 28-07 goed, en de
  bestandenbackup (172 MB) was de hele tijd wél in orde.
- **HavunAdmins facturen zaten in géén backup.** Het script archiveerde `storage/invoices`, een pad
  dat nooit bestond, en sloeg dat stil over. Nu `storage/app` (3,8 MB: facturen, bunq-exports,
  verantwoording-2024). 7 jaar bewaarplicht.

**Nu bewaakt door twee checks** (`qv:scan --only=registries` 03:02 en `--only=backup-coverage`
05:30, beide 0 high/0 medium). De tweede vraagt het aan de app: elke `DB_DATABASE` uit de `.env`'s
moet als `<naam>.sql.gz` in de verwachting staan. **Beide restores getest** — HP 52/52 tabellen,
HavunAdmin 39/39 met alle veertien boekhoudtabellen gelijk. Frequentie klopt: 31/31 dagen in juli,
box op 2% van 1 TB.

**Jouw beslissing:** `herdenkingsportaal_production` bestaat nog en is de valstrik zelf. Dump in
`/root/backups/hp-dode-db-2026-08-01`; droppen is een prod-database.

## Open — wacht op Henk

| Wat | Details |
|-----|---------|
| **Gelekt login-wachtwoord (`…ZxO#`) — rotatie loopt (19-07)** | Google meldde leak; waarde was over 10 havun.nl-sites hergebruikt. **Gedaan:** wachtwoord-login van `henkvu@` dood op HavunCore/SafeHavun/Studieplanner (rij-backups `/root/backups/pwreset-2026-07-19`). **Jij nog doen:** (1) `scripts/rotate-leaked-login.sh` draaien in Git Bash → nieuw wachtwoord voor HavunAdmin (prod+staging) + JudoToernooi `.env`; (2) `infosyst`+`staging.havunclub` uit Google verwijderen. **Apart (eigen sessie):** VPD/vpdupdate; HavunAdmin magic-link bouwen; password-kolommen nullable op de 3 magic-link-apps |
| **Vier rode builds (31-07)** | HavunAdmin 3 maanden rood · HavunClub 3 maanden (geparkeerd) · VeenLedenadministratie 1 dag · Studieplanner-api sinds 30-07. Gevonden door `actions:watch`. Uitzoeken hoort in de projectsessie zelf |
| **Security: dependencies** | HavunCore zelf is schoon (01-08: 6 guzzle-advisories dicht, live). Open: HavunAdmin 19 composer-advisories (2 high); JudoScoreBoard 6 GitHub-advisories (1 critical + 2 high) — eigen sessie, `composer update`/`npm` → overleg |
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

- **Web-push voor `critical` health-alerts — gebouwd, nooit getest.** Hele keten staat. Rest = één
  browser-test. `plans/health-alerts-webpush-blueprint.md`. Leesval: valt terug op `localhost:8009`
  (lege stub). Los daarvan: `laravel-worker` + `toernooi-heartbeat` onbewaakt.
- **havuncore-webapp update-banner — niet reproduceerbaar (24-07).** Wéér last? Check
  `getRegistration()` op een `waiting`. `plans/webapp-sw-update-fix.md`. Vitest daar geblokkeerd
  door Avast HTTPS-interceptie, niet de registry. Zie [[env-ssl-interception]].
- **Drie CLAUDE.md's boven de 120-regelnorm** — Studieplanner-api 135, JudoScoreBoard 136,
  havuncore-webapp 125.
- **JudoScoreBoard `context.md` op master nog 1039 regels** — opgeschoonde versie staat op
  `chore/expo-sdk-56-upgrade`; lost zichzelf op bij merge.
- **`origin/rescue/havuncore-prod-autocommits-2026-07-25`** staat er nog met 10 commits die nergens
  anders bestaan — allemaal `chore(auto): refresh handover, qv-scan-latest` van 15 t/m 25 juli.
  Inhoudelijk achterhaald door nieuwere snapshots, maar niet gemergd: beoordelen en dan weg, niet
  blind droppen.

## Veen-ledenadministratie — GEPARKEERD (Henk, 31-07)

**Voorlopig niets mee doen**, ook de kleine betaalde klussen niet. Onze serveromgeving is 31-07
opgeruimd (backup `/root/backups/veen-cleanup-2026-07-31`); de lokale checkout blijft en wordt
gescand. **⛔ De oude app van Cees op `37.34.60.216` (TransIP) is niet van ons en is niet
aangeraakt** — daar draait de live administratie. Volledig, inclusief de openstaande high
(`session.php` zonder secure-cookie-default, bewust niet gefixt):
`projects/veen-ledenadministratie.md`.

## Vaste context voor dit project

- **Rol:** centrale kennisbank + orchestrator. Scope-regel: **alleen HavunCore aanwerken; ander
  project = eigen sessie** (uitzondering: Henk geeft expliciet toestemming). Zie
  [[feedback-scope-waarschuwen]].
- **Geparkeerd, géén uitrol meer:** HavunClub, Demo, Havunity, Infosyst, IDSee, Agorano, Veen
  (31-07), HavunVet (01-08). Munus is weg; HavunVet en Vusista 1 zijn gearchiveerd. Hun databases
  blijven staan met reden: `reference/databases-op-de-server.md`.
- **De 6 Onschendbare Regels** staan canoniek in `runbooks/claude-werkwijze.md` §0 — verwijs
  ernaar, kopieer ze niet. Ze stonden in zeven docs los, waarvan vier er nog vijf noemden.
- KB zoeken: `php artisan docs:search "<onderwerp>"` — vereist Ollama op :11434.
- **Eerste prod-deploy per app = Henk klikt bewust.** Nooit auto-migrate op prod.
- **Prod kán pushen als root, `www-data` niet** — die krijgt *dubious ownership*
  (`safe.directory`), waardoor `AutoCommitRegeneratedCommand` zijn veiligheidsklep niet kon
  gebruiken. **Nog te doen:** `safe.directory` goedzetten voor `www-data` (server-config → overleg).
- havuncore-webapp deployt anders: lokaal build → rsync + pm2 (`havuncore-webapp/DEPLOY.md`).
- Server-quirks: `composer install` als root maakt `storage/**` en `vendor/` root-owned → 500s
  (`chown -R www-data:www-data`). Een Vite-build is pas gedeployd als de **asset-hash** op de site
  verandert — zie `runbooks/vite-build-bij-deploy.md`, inclusief de check of er überhaupt iets te
  bouwen valt.
