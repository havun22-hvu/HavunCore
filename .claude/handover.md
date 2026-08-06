---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-08-06
---

# HavunCore — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels. Regel: `docs/kb/standards/md-doc-grootte.md`.

**Branch:** master · **Status:** stabiel, 1457 tests groen, KB-audit 0 critical / 0 high (verse run
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
| **AutoFix → Claude CLI: klaar om te proberen** | Alle vijf de stappen af (`plans/autofix-naar-claude-cli-plan.md`). **Jij nog:** token uit de Vault in `HAVUNCORE_TASKS_TOKEN` zetten en `./scripts/local-task-poller.sh --self-test` draaien, daarna `--once`. Startinstructie + grenzen: `runbooks/agent-grenzen.md`. Nog niet end-to-end beproefd met een echte taak — dat is de eerste echte test |
| **Blijvend-ingelogd-plan** | Geschreven, wacht op "ga maar" — `plans/blijvend-ingelogd-plan.md` |
| **Vusista 1** | DNS `vusista.havun.nl` bij mijn.host + deploy-key `server-read` uit de (gearchiveerde) repo. De lege map `D:\GitHub\Vusista` verdwijnt zodra je dat VS Code-venster sluit |
| **GitGuardian #33883984** | Op *Resolved* zetten |
| **Server OS-update + herstart** | De server meldt bij inloggen `*** System restart required ***` (draait op een vervangen kernel) en biedt Ubuntu **24.04.4 LTS** aan. De herstart is klein werk, de distributie-upgrade niet — die staat als kwartaalcheck voor oktober 2026. Runbook: `runbooks/server-os-updates.md` |

**Andere projecten:** wat daar openstaat — dependency-advisories, rode builds, per-project
punten — staat in [`reference/openstaand-per-project.md`](../docs/kb/reference/openstaand-per-project.md).
Oppakken doe je in een sessie van dát project, niet hier.

## Twee stille storingen, beide 05/06-08 gevonden en gedicht

**De taakwachtrij stond open op internet.** `/api/claude/tasks` had geen auth, op een groep die de
eigen doc "remote code execution" noemt. Nu Bearer-token (gehasht in config, waarde in de Vault) +
rate limiting op élke route. Van buitenaf geverifieerd. **De eerste deploy deed niets** — de
routecache stamde van 2 augustus; `route:clear` staat nu in `runbooks/deploy.md`.

**De AI-proxy lag 19 uur plat.** `claude-3-haiku-20240307` was in april uitgefaseerd; 46 calls, alle
404, AutoFix stil. Prod draait nu op `claude-opus-5`, met `max_tokens` op 16000 omdat Opus 5 standaard
denkt en dat plafond denken én antwoord dekt. Een 404 vuurt voortaan een `critical` health-alert.
Regel: `patterns/model-id-verloopt.md`.

Beide waren zichtbaar in de logs en bereikten niemand. Dat is hetzelfde patroon als de V&K-scan:
`patterns/bewaking-die-niets-meet.md`.

## Open — te doen (mijn kant)

**Niets.** Alles wat zonder jouw beslissing kon, is af en staat live.

## Wacht op één handeling van jou

- **Web-push voor `critical` health-alerts — gebouwd, nooit getest.** Rest is één browser-test.
  `plans/health-alerts-webpush-blueprint.md`. Leesval: valt terug op `localhost:8009` (lege stub).
  **Sinds 05-08 urgenter:** de AI-proxy vuurt nu zelf een `critical` alert bij een verdwenen model —
  precies het soort melding dat je wilt zien zonder in te loggen.

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
