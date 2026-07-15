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
| **Server-opruiming** (raakt config) | `havuncore.havun.nl.bak.2026-07-02` hangt nog in `sites-enabled` → "conflicting server name"-warnings. HavunVet = obsoleet, vhost mag weg |

## Open — te doen

- **KB-chunking.** Lange docs worden alleen op hun **begin** geëmbed (~2000-8000 tekens, Ollama's
  contextlimiet) — de staart is onvindbaar via `docs:search`. Gold altijd al, is nu zichtbaar.
  Oplossing = chunking (meerdere rijen per bestand), raakt het schema.
  Zie `docs/kb/reference/doc-intelligence-embedding-fallback-bug.md`.
- **CLAUDE.md van Havun (128) en Studieplanner-api (147)** staan net over de 120 regels.
  Klein, maar nog niet gedaan.
- **JudoScoreBoard `context.md` op `master` is nog 1039 regels.** De opgeschoonde versie (523) staat
  op `chore/expo-sdk-56-upgrade`, omdat die SDK 56-kennis bevat over code die alleen daar leeft.
  Lost zichzelf op zodra die branch merget; tot dan is master's versie de oude.
- **Actief kanaal voor `critical` health-alerts.** Alerts leven nu alleen in het passieve
  webapp-paneel; een 10-daagse Reverb-outage bereikte Henk daardoor niet. Keuze push/mail/Telegram
  is aan Henk (raakt mogelijk `.env`). `laravel-worker` + `toernooi-heartbeat` worden niet bewaakt.
  `runbooks/uptime-monitoring.md` §Bekende gaten.
- **havuncore-webapp** — push-frontend (SW + subscribe-knop bij de 🔔); backend staat live sinds
  2 juli. Plus: update-banner activeert de wachtende SW niet zichtbaar (pas na app-herstart);
  verdenk ontbrekende `clientsClaim`/`controllerchange`.
- **Projectlijst is gedupliceerd** — `havun-projects.php` én de hardcoded lijst in `DocIndexer`.
  Ooit consolideren; nu vergeet je er één (JudoScoreBoard/Aeterna/LastMatch ontbraken tot 15-07).

## Open — eigen project-sessie (hier alleen genoteerd)

- **HavunAdmin** — hardcoded staging Bearer-token in
  `docs/05-api-integration/API-SYNC-HERDENKINGSPORTAAL.md` (r257+423). Opruimen + purgen.
  Zie [[feedback-no-hardcoded-test-secrets]].
- **VPDUpdate — `users.json` getrackt mét live bcrypt-hashes én TOTP-secrets** (15-07 bevestigd).
  Staat dus in de GitHub-historie. Untracken raakt de deploy (verse clone heeft dan geen bestand)
  → eigen taak, niet een opruiming.
- **3 rescue-branches beoordelen, daarna opruimen** (van de schoonmaak 15-07; alles wat er in staat
  bestond nergens anders): `Studieplanner-api rescue/prod-stashes-2026-07-15` (UserSettings +
  Observability + syncSettings, WIP met een kapotte regel), `VPDUpdate rescue/prod-untracked-2026-07-15`
  (PIN-login-variant, waarschijnlijk dood hout), `Herdenkingsportaal rescue/prod-stash-2026-07-15`
  (route naar `settings/biometric.blade.php` — **die view staat in git maar is onbereikbaar**;
  mogelijk achterhaald door het login-werk van 14-07).
- **HavunClub `public/aeterna-latest.apk`** (26 MB, ander project). Laravel serveert `public/`, dus
  `havunclub.havun.nl/aeterna-latest.apk` kan als link gedeeld zijn. Niet verwijderd — Henks keuze.

> **Les 15-07:** het item "HavunClub — Cees ziet 0 judoka's / ClubScope leest `session('club_id')`
> / tenant-lek in Aanwezigheid+BandExamen+Dashboard" stond hier nog, maar was **al opgelost**
> (multi-tenant hardening `dae025c`, staat op prod: `ClubScope` houdt een platform-eigenaar altijd
> op de eigen club, en die drie controllers filteren expliciet op `club_id`). Ik had het bij het
> opschonen overgenomen zónder te verifiëren — precies de fout die deze regel moet voorkomen.
> **Cross-project items hier zijn kopieën; verifieer ze in het bronproject vóór je erop afgaat.**

## Recent afgerond (context die nog nut heeft)

- **Grote schoonmaak server (15-07)** — `docs/kb/plans/grote-schoonmaak-2026-07-15.md`.
  **29 stashes → 0**, nginx-warnings → 0, verweesde `judotoernooi/.git` weg (oude historie met losse
  root gebundeld: 103 MB, `verify` = complete). Alles eerst geback-upt in `/var/backups/`.
  **Wat er tussen de "rommel" bleek te zitten:** 874 MB live APK's, 34 MB OTA-bundles, de gebouwde
  PWA, en vier bestanden die **nergens anders bestonden** — SafeHavuns landingstekst, Infosysts
  Python-zip, havun.nl's PM2-config, en Studieplanners favicon (waar de layout naar verwees terwijl
  git 'm niet had). `git clean -fd` was een outage geweest.
  **Preventie:** `standards/server-hygiene.md` + checks in `/start` en `/end` van 13 projecten.

- **KB draaide maandenlang op keyword-matching** (`2c43318`). Alle 2758 embeddings waren
  woordfrequentie-maps, geen vectoren. Drie oorzaken: `generateLocalEmbedding()` geeft altijd een
  gevulde array → het label zei altijd `nomic-embed-text` (`tfidf-fallback` was onbereikbare code);
  `indexFile()` skipt op `content_hash` → degraded rijen herstelden nooit; en Ollama serveert nomic
  met ~2048 tokens context (niet 8192) en weigert langere input met een 500, terwijl de code op 8000
  **tekens** afkapte. Fix: labeling gesplitst, herstel-trigger die de fallback op **vorm** herkent
  (self-healing), adaptieve truncatie 8000→4000→2000. Nu 2764/2764 echte 768-dim vectoren.
- **JudoScoreBoard/Aeterna/LastMatch ontbraken in de KB-index** — 190 docs onvindbaar terwijl
  `CLAUDE.md` voorschrijft elke taak met `docs:search` te beginnen. Toegevoegd aan `DocIndexer`.
- **Scoreboard-API security** (JT `f3445e46` + `34bd9549`) — zie
  `docs/kb/reference/scoreboard-api-security-review-2026-07-15.md`. Les breed toepasbaar:
  `$request->merge()` om een geauthenticeerd model door te geven is een **anti-patroon** (het landt
  in `$request->all()` en lekt in elke echo/broadcast) → gebruik `$request->attributes`.
- **Nieuwe bindende regels:** `standards/md-doc-grootte.md` (doc-grootte + één levende handover),
  verwerkt in CLAUDE.md + `/end` van alle 22 projecten. **Oorzaak was het `/end`-template zelf:**
  dat schreef voor om een blok `## Sessie [DATUM]` toe te voegen, aan `context.md` **of**
  `handover.md` — dus stapelden sessies zich op in beide, en niemand haalde ooit iets weg.
- **Achterstallig onderhoud weggewerkt (15-07), met verificatie per punt.** Elk project leverde
  bewijs op dat de docs logen:
  | Project | handover | context | Loog over |
  |---|---|---|---|
  | JudoToernooi | 842 → 75 | — | "Laravel 12 GEDEPLOYED" pal boven "NOG NIET gedeployed" (draait al weken op ^12.0) |
  | HavunClub | 559 → 63 | — | "prod loopt ver achter" terwijl prod `82035f3` juist ná die reeks ligt |
  | JudoScoreBoard | 351 → 107 | 1264 → 523 (branch) | handover liep 6 weken achter op context.md; grootste open punt (22-commit-branch) stond er niet in |
  | Herdenkingsportaal | 243 → 61 | 575 → 173 | "Laravel 11 + Livewire" (is ^12.0, geen Livewire); 3× "Laatste Sessie", 2 met dezelfde datum |
  | IDSee | 227 → 140 | — | bevatte een eigen disclaimer dat de lijst erboven achterhaald was |
  | Havun | 29 → 31 | 294 → 153 | handover verlaten sinds 22 maart; 7 sessieblokken in context.md |
- **Vusista opgezet** (14-07): fotoalbum-webapp, eigen repo, :8008, staging+prod op de server, CI +
  deploy werkend. Spec staat als `.claude/blueprint.md` → Vusista-sessie start met `/mpc` + "ga maar".

## Vaste context voor dit project

- **Rol:** centrale kennisbank + orchestrator voor alle Havun-projecten. Scope-regel: alleen
  HavunCore aanwerken; ander project = eigen sessie (uitzondering: Henk geeft expliciet toestemming).
- KB zoeken: `php artisan docs:search "<onderwerp>"` — vereist Ollama op :11434.
- **Eerste prod-deploy per app = Henk klikt bewust** (Actions → Deploy to Production).
  Runbook: `docs/kb/runbooks/deploy-keys-github-actions.md`. Nooit auto-migrate op prod.
- havuncore-webapp deployt bewust anders: lokaal build → rsync + pm2. Zie `havuncore-webapp/DEPLOY.md`.
- Server-quirk: `composer install` als root maakt `storage/**` root-owned → 500s die zichzelf niet
  kunnen loggen. Fix: `chown -R www-data:www-data storage bootstrap/cache`.
