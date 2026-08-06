---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-08-03
---

# HavunCore — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels. Regel: `docs/kb/standards/md-doc-grootte.md`.

**Branch:** master · **Status:** stabiel, 1449 tests groen, KB-audit 0 critical / 0 high (verse run
06-08; het rapportbestand was van 02-08 en dus achterhaald). **Server:** disk 68%, prod draait
overal, 0 dirty checkouts, 0 stashes. **Alles staat live** (06-08, geen migraties).

**Afgerond 01/02-08:** drie secrets geroteerd en twee backupgaten gedicht, restores getest.
Volledig: `runbooks/secrets-veilig-ontvangen.md`, `reference/databases-op-de-server.md`.

## De V&K-scan meet op de server weer (03/04-08)

Vier lagen van dezelfde fout, alle vier gemeten op de echte runs en gefixt: de scan ging via SSH
naar zichzelf, gebruikte Windows-paden op Linux, las één run per dag (de 8 wekelijkse checks hadden
**nooit** iets gerapporteerd), en `actions:watch` zag geen enkele repo. **Opbrengst: 2 critical +
22 high die niemand ooit gezien had.** Patroon en regels: `patterns/bewaking-die-niets-meet.md`.

De scheduler draait **als root**, niet als `www-data` — daardoor worden `storage/**` root-owned en
faalt `cache:clear`; na elke deploy `chown`. (Correctie op de diagnose van 02-08.)

**Jouw beslissing:** `herdenkingsportaal_production` bestaat nog en is de valstrik zelf. Dump in
`/root/backups/hp-dode-db-2026-08-01`; droppen is een prod-database.

## Open — wacht op Henk

| Wat | Details |
|-----|---------|
| **Gelekt login-wachtwoord (`…ZxO#`) — rotatie loopt (19-07)** | Google meldde leak; waarde was over 10 havun.nl-sites hergebruikt. **Gedaan:** wachtwoord-login van `henkvu@` dood op HavunCore/SafeHavun/Studieplanner (rij-backups `/root/backups/pwreset-2026-07-19`). **Jij nog doen:** (1) `scripts/rotate-leaked-login.sh` draaien in Git Bash → nieuw wachtwoord voor HavunAdmin (prod+staging) + JudoToernooi `.env`; (2) `infosyst`+`staging.havunclub` uit Google verwijderen. **Apart (eigen sessie):** VPD/vpdupdate; HavunAdmin magic-link bouwen; password-kolommen nullable op de 3 magic-link-apps |
| **GitHub-PAT ziet 4 van de 8 repo's niet** | `github_pat_ro` (Vault) geeft 404 op HavunAdmin, Herdenkingsportaal, VPDUpdate en havuncore-webapp — hij is gemaakt voor de mobiele monitoring. **Jij:** in GitHub de fine-grained PAT uitbreiden naar alle `havun22-hvu`-repo's (alleen `metadata:read` + `actions:read` nodig), daarna `php artisan vault:setup-mobile-monitoring --from-env`. Tot die tijd meldt `actions:watch` het elke ronde |
| **Vier rode builds** | HavunAdmin 3 maanden · HavunClub 3 maanden (geparkeerd) · VeenLedenadministratie · Studieplanner-api (04-08 nog steeds rood). Uitzoeken hoort in de projectsessie zelf |
| **Security: dependencies — 2 critical + 22 high, nooit eerder gerapporteerd** | Zichtbaar door de scanfixes van 03-08. **npm:** Studieplanner-mobile 2 critical (`shell-quote`, `tar`) + 6 high · havun.nl 3 high (next, postcss, sharp) · VPDUpdate 1 high (`xlsx`, geen fix — vervangen door exceljs). **composer:** Studieplanner-api 6 high + 24 medium · JudoToernooi 3 high + 10 medium · SafeHavun 3 high + 17 medium (laravel/framework, symfony, web-token/jwt). HavunAdmin, Herdenkingsportaal en HavunCore zijn schoon. **Elk in de eigen projectsessie** — `composer update`/`npm audit fix` op productie-apps → overleg. Los daarvan: JudoScoreBoard 6 GitHub-advisories (1 critical + 2 high) |
| **AutoFix → Claude CLI: klaar om te proberen** | Alle vijf de stappen af (`plans/autofix-naar-claude-cli-plan.md`). **Jij nog:** token uit de Vault in `HAVUNCORE_TASKS_TOKEN` zetten en `./scripts/local-task-poller.sh --self-test` draaien, daarna `--once`. Startinstructie + grenzen: `runbooks/agent-grenzen.md`. Nog niet end-to-end beproefd met een echte taak — dat is de eerste echte test |
| **Blijvend-ingelogd-plan** | Geschreven, wacht op "ga maar" — `plans/blijvend-ingelogd-plan.md` |
| **🔴 Hetzner-backupwachtwoord roteren — vanavond** | Staat niet meer wereldleesbaar (06-08: naar `/etc/havun-backup.env` 600, script 700, `www-data` buitengesloten en geverifieerd). **Maar de waarde is in een transcript beland** — ik schreef hem ongequoteerd weg en het `#` erin liet de shell een deel echoën. **Doen:** nieuw wachtwoord in de Hetzner Robot, dan op de server `bash /root/roteer-hetzner-wachtwoord.sh` — die vraagt erom met verborgen invoer, test de verbinding vóór hij iets vervangt, en draait terug als de test faalt. `reference/security-findings.md` |
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

## De taakwachtrij stond open op internet — dicht sinds 06-08

`/api/claude/tasks` had geen enkele auth, op een groep die de eigen doc "remote code execution"
noemt. Bewezen met een `curl` van buiten. Nu: Bearer-token (gehasht in de config, token in de Vault
onder `havuncore_tasks_token`) + rate limiting op élke route, ook de leesroutes. Van buitenaf
geverifieerd: 401 zonder token, 200 met.

**De eerste deploy deed niets.** De routecache stamde van 2 augustus, dus de app draaide de oude
routes — `config:clear` raakt die niet. `route:clear` staat nu in `runbooks/deploy.md`. Alleen een
test van buitenaf ving dit; `route:list` leest de bronbestanden en zag er goed uit.

## De AI-proxy lag 19 uur plat en niemand kreeg een melding (05-08)

`claude-3-haiku-20240307` is per **19-04-2026** uitgefaseerd; vanaf 00:30 gaf elke call 404 — 46
calls, 100% mislukt, AutoFix stil. Gevonden in de productielogs, niet door een alert.

**Opgelost:** prod draait op `claude-opus-5` (backup van `.env` in `/root/backups/`), config-default
mee, echte API-call geverifieerd. De tweede default in `AIProxyService` is weg — één plek om te
wijzigen. **`max_tokens` moest mee omhoog naar 16000** (`AIProxyService::MAX_TOKENS`): Opus 5 denkt
standaard en het plafond dekt denken én antwoord samen, dus 1024 brak antwoorden af. Het is een
plafond, geen verbruik.

**Ook gedicht:** een 404 van de messages-endpoint vuurt nu een `critical` health-alert
(`ai-proxy-model`, noemt het model bij naam); een geslaagde call sluit 'm weer. Alleen 404 — een
429 of 500 is de API die het even niet trekt, een verdwenen model blijft stuk. Beide tests eerst
rood gezien. Regel: `patterns/model-id-verloopt.md`.

## Open — te doen

- **Web-push voor `critical` health-alerts — gebouwd, nooit getest.** Rest = één browser-test.
  `plans/health-alerts-webpush-blueprint.md`. Leesval: valt terug op `localhost:8009` (lege stub).
  **Sinds 05-08 urgenter:** de AI-proxy vuurt nu zelf een `critical` alert bij een verdwenen
  model — precies het soort melding die je wilt zien zonder in te loggen. (`laravel-worker` +
  `toernooi-heartbeat` wórden inmiddels wel bewaakt — 06-08 uitgebreid naar alle niet-draaiende
  statussen, en nul processen is nu een alarm in plaats van groen.)
- **havuncore-webapp update-banner — niet reproduceerbaar (24-07).** Wéér last? Check
  `getRegistration()` op een `waiting`. `plans/webapp-sw-update-fix.md`. Vitest daar geblokkeerd
  door Avast, niet de registry — [[env-ssl-interception]].
- **Drie CLAUDE.md's boven de 120-regelnorm** — Studieplanner-api 135, JudoScoreBoard 136,
  havuncore-webapp 125.
- **JudoScoreBoard `context.md` op master nog 1039 regels** — opgeschoonde versie staat op
  `chore/expo-sdk-56-upgrade`; lost zichzelf op bij merge.

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
