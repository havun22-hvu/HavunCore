---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-07-16
---

# HavunCore — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels. Regel:
> `docs/kb/standards/md-doc-grootte.md`.

**Branch:** master · **Status:** stabiel. KB zoekt gechunkt: 3178 docs → 13.091 float32-vectoren,
0,1s met `--project`. **Server:** 0 stashes, nginx-warnings 0. Prod draait overal.

> **Portfolio-ronde 16-07:** alle handovers geverifieerd + rechtgezet (zie *Recent afgerond*).
> **Let op:** Vusista en JudoToernooi hadden op 16-07 om 00:50 een **actieve sessie** (bestanden
> seconden oud). Check `ls -la` op de working tree vóór je in een ander project opruimt.

## Open — wacht op Henk

| Wat | Details |
|-----|---------|
| **Blijvend-ingelogd-plan** | Geschreven, wacht op "ga maar" — `docs/kb/plans/blijvend-ingelogd-plan.md` |
| **Prod-deploys staan klaar (5 checkouts achter)** | Herdenkingsportaal (12 commits, waarvan 3 code — **passkey-login is af maar niet live**), HavunClub (15 code-commits: compliance/SEPA-batch), JudoToernooi (6), HavunAdmin (9, alleen docs), HavunCore zelf (17: het KB-werk). Deploy = altijd jouw klik |
| **Security: dependencies** | HavunAdmin **19 composer-advisories, 2 high** (geverifieerd 16-07: `laravel/framework` CRLF email-rule, `symfony/mime` CRLF). JudoScoreBoard: **6 GitHub-advisories, 1 critical + 2 high**. Beide = `composer update`/`npm` → overleg vereist |
| **VPDUpdate: 54 commits achter + 5 dirty** | Bewust niet gedeployd. `users.json` (getrackt, mét live secrets) hangt eraan vast. Zie de handover daar |
| **HavunClub `public/aeterna-latest.apk`** | 26 MB, ander project, sinds 4 mei. De `.gitignore` staat alleen op `staging` — prod draait `main` en is daardoor nog dirty. **Niet verwijderd** — Laravel serveert `public/`, dus de link kan bij Aeterna-testers liggen |
| **GitGuardian #33883984** | Op *Resolved* zetten |
| **Aeterna** | Production keystore + update-adres. Het week2-plan blijkt **dood** (vraagt go voor crates die al bestaan) — archiveren of weg. Plus: `feat/v1.1-tor-socks5-3b` (PR #16 closed, niet merged) |
| **Studieplanner** | `chore/expo-sdk-55-upgrade`: code is af (230/230 groen) maar **nooit device-getest**; 3 maanden oud, dus versies achterhaald. Mergen of verwerpen |
| **Studieplanner-api** | `rescue/prod-stashes-2026-07-15`: wil je user settings (pomodoro/alarm) + observability? Ja → afmaken, nee → branch weg |
| **LastMatch** | Avast HTTPS-scanning uit = enige blocker voor de APK-build |
| **Vusista** | Vier vragen over gezichten (G1-G4) blokkeren het plan |
| **JudoScoreBoard** | Google-review AAB 116 (ingediend 9 juni) — alleen Play Console weet de status |

## Open — te doen

- **JudoScoreBoard `context.md` op `master` is nog 1039 regels** (4 sessieblokken). De opgeschoonde
  versie (523) staat op `chore/expo-sdk-56-upgrade`, omdat die SDK 56-kennis bevat over code die
  alleen daar leeft. Lost zichzelf op zodra die branch merget; tot dan blijft master's versie oud.
- **Actief kanaal voor `critical` health-alerts — de keuze is al gemaakt én gebouwd.** Dit stond
  hier als open keuze push/mail/Telegram; onjuist. Push is gekozen (2 jul) en de hele keten staat
  er: Laravel `PushController` + `WebPushService` + VAPID in de Vault + de hook in
  `HealthAlertCommand`, en aan de webapp-kant `sw-push.js` + de subscribe-knop. **Wat rest is één
  browser-test** (permissie geven, subscription laten aanmaken, een echte push zien binnenkomen).
  Zie `plans/health-alerts-webpush-blueprint.md`.
  Los daarvan nog wél open: `laravel-worker` + `toernooi-heartbeat` worden niet bewaakt —
  `runbooks/uptime-monitoring.md` §Bekende gaten.
- **havuncore-webapp** — update-banner activeert de wachtende SW niet zichtbaar (pas na
  app-herstart); verdenk ontbrekende `clientsClaim`/`controllerchange`. Verder: Vitest geblokkeerd
  door een npm-registry SSL-issue, en `DEPLOY.md`'s Quick Deploy mist excludes.
  > **De push-frontend stond hier als "nog te doen" — die bestaat al** (geverifieerd 16-07):
  > `sw-push.js` + `usePushNotifications.js` (114 regels) + de knop in `Header.jsx`, en
  > `.env.production` wijst naar de Laravel-backend waar de `PushController` leeft. Wat rest is
  > een browser-test. Zie het blueprint. **Leesval:** de code valt terug op `localhost:8009` = de
  > Node-backend, waar push een lege stub is — wie alleen die default leest denkt dat de knop dood is.

## Let op: slash-commands bestaan twee keer

`~/.claude/commands/*.md` (globaal) wint van `HavunCore/.claude/commands/*.md` (project). Op 16-07
bleek de **globale `/end` nog `## Laatste Sessie: [DATUM]` voor te schrijven** terwijl de
projectversie sessieblokken al verbood — dát is waarom vijf projecten na de regel van 15-07 alsnog
sessieblokken hadden. Gefixt in de globale versie.

> **Twee dingen om te weten.** (1) Werk je een slash-command bij, controleer **beide** locaties —
> anders schrijft de globale het oude gedrag terug. (2) **`~/.claude` staat niet onder
> versiebeheer.** Die commands leven alleen op deze schijf; schijf weg = weg. Eigen keuze, maar
> weet het.

## Cross-project items staan vanaf nu in het bronproject

Deze handover hield kopieën bij van open punten uit andere projecten. Sinds de ronde van 16-07
staan **alle** projecthandovers in de levende vorm en kloppen ze met git — dus de kopieën zijn weg.
Het item hoort waar het thuishoort: HavunAdmin's token-purge in HavunAdmin, VPDUpdate's
`users.json` in VPDUpdate, de Studieplanner-rescue-branch in Studieplanner-api.

> **Waarom dit hard is.** Op 15-07 stond hier een tenant-lek in HavunClub dat **al was opgelost**
> (`dae025c`, op prod). Overgenomen bij het opschonen, nooit geverifieerd. Een kopie veroudert
> zonder dat iemand het merkt. Wil je een portfolio-overzicht: lees de handovers, of laat een
> agent-ronde ze verifiëren — schrijf ze niet hier over.

## Recent afgerond (context die nog nut heeft)

- **KB-chunking (15-07)** — `docs/kb/plans/kb-chunking-plan.md`. De staart van lange docs was
  onvindbaar (22-59% van de KB). Nu een aparte tabel `doc_chunks` met float32-vectoren; 3178 docs
  → 13.091 chunks. Zoeken: **0,1s met `--project`**, 1,2s ongefilterd, DB 272 → 118 MB. De preview
  toont nu de gevonden passage + koppad i.p.v. de YAML-frontmatter.
  **Drie lessen die breder gelden:**
  1. De handover schreef "meer rijen in `doc_embeddings`" voor — dat zou ~30 aannames hebben
     gebroken (`IssueDetector` parst `content` als heel MD-bestand, de API telt `COUNT(*)` als
     `total_files`). Eerst de consumers inventariseren, dán het schema kiezen.
  2. **Meten, niet redeneren.** `chunk()` pagineert met OFFSET (27s vs 8s t.o.v. `chunkById`);
     Eloquent-hydratie kostte 9 van de 14s. Beide onzichtbaar zonder meting.
  3. **Eén weg de index in.** Er waren 3 producenten van een `doc_embeddings`-rij, elk met een
     eigen kopie — daardoor droeg `StructureIndexer` de 15-07-mislabelbug maanden later nog.
     Nu `DocIndexer::storeDocument()`.

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
