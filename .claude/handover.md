---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-07-15
---

# HavunCore — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels. Regel:
> `docs/kb/standards/md-doc-grootte.md`.

**Branch:** master · **Status:** stabiel. KB doet weer semantisch zoeken (2764 echte vectoren).
**Server 15-07 opgeschoond en bijgewerkt:** 29 stashes → 0, nginx-warnings → 0, alle checkouts
schoon en up-to-date op twee bewuste uitzonderingen na (zie Open). Prod draait overal.

## Open — wacht op Henk

| Wat | Details |
|-----|---------|
| **Blijvend-ingelogd-plan** | Geschreven, wacht op "ga maar" — `docs/kb/plans/blijvend-ingelogd-plan.md` |
| **VPDUpdate: 49 commits achter + 3 dirty** | Bewust niet gedeployd 15-07. Werktree is 46 commits **ouder** met alleen CRLF-drift, en `users.json` (getrackt, mét live secrets) hangt eraan vast. Eerst uitzoeken, dan pas deployen. Bevat o.a. de passkey-fix `f6f5e1a` |
| **HavunClub `public/aeterna-latest.apk`** | 26 MB, ander project, sinds 4 mei. Nu gitignored (op `staging`) zodat de checkout schoon is; **niet verwijderd** — Laravel serveert `public/`, dus de link kan bij Aeterna-testers liggen. Weg = jouw keuze |
| **GitGuardian #33883984** | Op *Resolved* zetten |
| **WIP in webapp-repo** | `git stash pop`, stash@{0} |

## Open — te doen

- **KB-chunking.** Lange docs worden alleen op hun **begin** geëmbed (~2000-8000 tekens, Ollama's
  contextlimiet) — de staart is onvindbaar via `docs:search`. Gold altijd al, is nu zichtbaar.
  Oplossing = chunking (meerdere rijen per bestand), raakt het schema.
  Zie `docs/kb/reference/doc-intelligence-embedding-fallback-bug.md`.
- **JudoScoreBoard `context.md` op `master` is nog 1039 regels** (4 sessieblokken). De opgeschoonde
  versie (523) staat op `chore/expo-sdk-56-upgrade`, omdat die SDK 56-kennis bevat over code die
  alleen daar leeft. Lost zichzelf op zodra die branch merget; tot dan blijft master's versie oud.
- **Sessieblokken nog in LastMatch + VPDUpdate handover** — die twee projecten draaien niet op de
  server en zijn bij de opschoning van 15-07 overgeslagen. Klein werk.
- **Actief kanaal voor `critical` health-alerts.** Alerts leven nu alleen in het passieve
  webapp-paneel; een 10-daagse Reverb-outage bereikte Henk daardoor niet. Keuze push/mail/Telegram
  is aan Henk (raakt mogelijk `.env`). `laravel-worker` + `toernooi-heartbeat` worden niet bewaakt.
  `runbooks/uptime-monitoring.md` §Bekende gaten.
- **havuncore-webapp** — push-frontend (SW + subscribe-knop bij de 🔔); backend staat live sinds
  2 juli. Plus: update-banner activeert de wachtende SW niet zichtbaar (pas na app-herstart);
  verdenk ontbrekende `clientsClaim`/`controllerchange`.

## Open — eigen project-sessie (hier alleen genoteerd)

- **HavunAdmin** — hardcoded staging Bearer-token in
  `docs/05-api-integration/API-SYNC-HERDENKINGSPORTAAL.md` (r257+423). Opruimen + purgen.
  Zie [[feedback-no-hardcoded-test-secrets]].
- **VPDUpdate — `users.json` getrackt mét live bcrypt-hashes én TOTP-secrets** (15-07 bevestigd).
  Staat dus in de GitHub-historie. Untracken raakt de deploy (verse clone heeft dan geen bestand)
  → eigen taak, niet een opruiming.
- **`Studieplanner-api` branch `rescue/prod-stashes-2026-07-15` — jouw keuze.** Bevat werk dat
  nergens anders bestond: een `UserSettings`-model (pomodoro, alarm-instellingen), een
  `ObservabilityServiceProvider` (slow-query-logging naar HavunCore), `config/observability.php` en
  de `user_settings`-migratie. Geverifieerd: geen van die vier zit in master. Het is onaffe WIP (de
  provider heeft een kapotte string-interpolatie), dus dit is een **productvraag**: wil je die
  feature? Ja → afmaken. Nee → branch weg. De twee andere rescue-branches zijn afgehandeld.
- **HavunClub `public/aeterna-latest.apk`** (26 MB, ander project). Laravel serveert `public/`, dus
  `havunclub.havun.nl/aeterna-latest.apk` kan als link gedeeld zijn. Niet verwijderd — Henks keuze.

> **Les 15-07:** het item "HavunClub — Cees ziet 0 judoka's / ClubScope leest `session('club_id')`
> / tenant-lek in Aanwezigheid+BandExamen+Dashboard" stond hier nog, maar was **al opgelost**
> (multi-tenant hardening `dae025c`, staat op prod: `ClubScope` houdt een platform-eigenaar altijd
> op de eigen club, en die drie controllers filteren expliciet op `club_id`). Ik had het bij het
> opschonen overgenomen zónder te verifiëren — precies de fout die deze regel moet voorkomen.
> **Cross-project items hier zijn kopieën; verifieer ze in het bronproject vóór je erop afgaat.**

## Recent afgerond (context die nog nut heeft)

- **Grote schoonmaak + deploys (15-07)** — `docs/kb/plans/grote-schoonmaak-2026-07-15.md`.
  29 stashes → 0, nginx-warnings → 0, alles gedeployd behalve VPDUpdate. Kern om te onthouden:
  tussen de "rommel" zat 874 MB live APK's en vier bestanden die **nergens anders bestonden** →
  `git clean -fd` was een outage geweest. Preventie: `standards/server-hygiene.md`.
- **KB draaide maandenlang op keyword-matching** (`2c43318`) — alle embeddings waren woordmaps.
  Oorzaak + fix: `docs/kb/reference/doc-intelligence-embedding-fallback-bug.md`. Nu 2764 echte
  768-dim vectoren. Ook: JudoScoreBoard/Aeterna/LastMatch ontbraken in de index (190 docs
  onvindbaar) → toegevoegd aan `DocIndexer`.
- **Scoreboard-API security** (JT, live) — `docs/kb/reference/scoreboard-api-security-review-2026-07-15.md`.
  **Les breed toepasbaar:** `$request->merge()` om een geauthenticeerd model door te geven is een
  anti-patroon (belandt in `$request->all()`, lekt in elke broadcast) → `$request->attributes`.
- **Nieuwe bindende regels** (in alle 22 projecten): `standards/docs-first.md` (geen code zonder MD),
  `standards/md-doc-grootte.md` (doc-grootte + één levende handover), `standards/server-hygiene.md`.
  Aanleiding: JT's handover was 842 regels en sprak zichzelf tegen; `/end` schreef zelf voor om
  sessieblokken te stapelen.
- **Vusista opgezet** (14-07): fotoalbum-webapp, eigen repo, :8008, staging+prod live, CI werkt.
  Spec staat als `.claude/blueprint.md` → Vusista-sessie start met `/mpc` + "ga maar".

## Vaste context voor dit project

- **Rol:** centrale kennisbank + orchestrator voor alle Havun-projecten. Scope-regel: alleen
  HavunCore aanwerken; ander project = eigen sessie (uitzondering: Henk geeft expliciet toestemming).
- KB zoeken: `php artisan docs:search "<onderwerp>"` — vereist Ollama op :11434.
- **Eerste prod-deploy per app = Henk klikt bewust** (Actions → Deploy to Production).
  Runbook: `docs/kb/runbooks/deploy-keys-github-actions.md`. Nooit auto-migrate op prod.
- havuncore-webapp deployt bewust anders: lokaal build → rsync + pm2. Zie `havuncore-webapp/DEPLOY.md`.
- Server-quirk: `composer install` als root maakt `storage/**` root-owned → 500s die zichzelf niet
  kunnen loggen. Fix: `chown -R www-data:www-data storage bootstrap/cache`.
