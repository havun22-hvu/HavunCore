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

> **Portfolio-ronde 16-07:** alle 15 handovers geverifieerd + rechtgezet (zie *Recent afgerond*).
> **Les:** Vusista en JudoToernooi hadden tijdens die ronde een **actieve sessie** (bestanden
> seconden oud). Check de working tree (`git status` + `ls -la`) vóór je in een ander project
> opruimt — twee schrijvers op één bestand en de verkeerde wint.

## Open — wacht op Henk

| Wat | Details |
|-----|---------|
| **Blijvend-ingelogd-plan** | Geschreven, wacht op "ga maar" — `docs/kb/plans/blijvend-ingelogd-plan.md` |
| **Vite-build loopt achter op 5 checkouts — 3× production** | Gemeten 16-07: HP-prod (build 27-04 vs view-commit 17-05), HP-staging, Infosyst-prod, Studieplanner-prod, Vusista-staging. **Signaal, geen diagnose** — verifieer per project met de asset-hash-check in `runbooks/vite-build-bij-deploy.md`. Elk hoort in een eigen sessie; HP bouwt in GH Actions, dus daar kan de mtime misleiden |
| **Hardcoded Hetzner-wachtwoord op de server** | `/usr/local/bin/havun-backup.sh` bevat het Storage Box-wachtwoord in plain text (`HETZNER_PASS=`). Server-config + credential → jouw beslissing. Hoort in de Vault. Zie [[feedback-no-hardcoded-test-secrets]] |
| **Prod-deploys staan klaar (4 checkouts achter)** | Herdenkingsportaal (12 commits, waarvan 3 code — **passkey-login is af maar niet live**), HavunClub (15 code-commits: compliance/SEPA-batch), JudoToernooi (6), HavunCore zelf (17: het KB-werk). Deploy = altijd jouw klik. *HavunAdmin is 16-07 gedeployd (staging+prod) — van de lijst af* |
| **Security: dependencies** | HavunAdmin **19 composer-advisories, 2 high** (geverifieerd 16-07: `laravel/framework` CRLF email-rule, `symfony/mime` CRLF). JudoScoreBoard: **6 GitHub-advisories, 1 critical + 2 high**. Beide = `composer update`/`npm` → overleg vereist |
| **VPDUpdate: 54 commits achter + 5 dirty** | Bewust niet gedeployd. `users.json` (getrackt, mét live secrets) hangt eraan vast. Zie de handover daar |
| **HavunClub `public/aeterna-latest.apk`** | 26 MB, ander project, sinds 4 mei. De `.gitignore` staat alleen op `staging` — prod draait `main` en is daardoor nog dirty. **Niet verwijderd** — Laravel serveert `public/`, dus de link kan bij Aeterna-testers liggen |
| **GitGuardian #33883984** | Op *Resolved* zetten |
| **Aeterna** | Production keystore + update-adres. Het week2-plan blijkt **dood** (vraagt go voor crates die al bestaan) — archiveren of weg. Plus: `feat/v1.1-tor-socks5-3b` (PR #16 closed, niet merged) |
| **Studieplanner** | `chore/expo-sdk-55-upgrade`: code is af (230/230 groen) maar **nooit device-getest**; 3 maanden oud, dus versies achterhaald. Mergen of verwerpen |
| **Studieplanner-api** | `rescue/prod-stashes-2026-07-15`: wil je user settings (pomodoro/alarm) + observability? Ja → afmaken, nee → branch weg |
| **LastMatch** | Avast HTTPS-scanning uit = enige blocker voor de APK-build |
| **Vusista** | Testen in de app (personen-kolom, twijfelvoorstellen, bewerkingsset) + **installer op een schone PC** = laatste MVP-punt. Plus: de installer wordt +119 MB (80 MB OpenCV-DLL — minimale build zou ~35 MB kunnen), en de licentieketen van het SFace-model is niet te verifiëren |
| **JudoScoreBoard** | Google-review AAB 116 (ingediend 9 juni) — alleen Play Console weet de status |

## Open — Veen-ledenadministratie (nieuw project, gestart 17/18-07)

Overname Cees Veen's EOL-app (Laravel 5.5 · PHP 7.0 · Ubuntu 16.04) als **eigen
project** — HavunClub vervalt maar blijft staan (functies mogelijk herbruikbaar).
Functioneel identiek aan HavunClub; tegengas gegeven, Henk kiest eigen project.

- **Backup binnen** (`D:\GitHub\VeenLedenadministratie\_backup\`): 2 code-tarballs +
  DB-dump + `INTAKE-NOTES.md` — door de HavunClub-sessie veiliggesteld. App-`.env`
  zit in de tarball, dus DB/APP_KEY/SES-mail/Pusher-secrets zijn al mee. Mollie-key
  ontbreekt in `.env` (zit in `.env1` of DB — uitzoeken).
- **`vault:setup-veen`** command gebouwd + getest + gepusht (HavunCore master).
  **Lokaal draaien lukt niet:** `database/database.sqlite` bestaat niet lokaal → de
  Vault-DB is een prod-ding. Vault vullen dus later op production, geautomatiseerd
  (plan: `vault:import-env` in fase 1) — géén handmatig overtypen.
- **Fase 1 (repo-scaffold) wacht op "ga maar"** — volledig plan in
  `D:\GitHub\VeenLedenadministratie\.claude\blueprint.md`. Repo-opzet lokaal is aan de
  HavunCore-sessie toegewezen (blueprint-taakverdeling). **Geparkeerd 18-07:** Henk wil
  eerst groen licht van Cees + een offerte maken voordat Fase 1 draait.
- **Open business (Cees):** hosting (TransIP-VPS vs onze server) + moderniseren
  (L5.5→huidig) vs. EOL-app doorgebruiken. Advies staat in de blueprint: onze server
  als doel, maar niet de EOL-stack meeverhuizen.
- Nog apart op te halen bij Cees/HavunClub-sessie: TransIP-login, app-admin plaintext.

## Open — te doen

- **JudoScoreBoard `context.md` op `master` is nog 1039 regels** (4 sessieblokken). De opgeschoonde
  versie (523) staat op `chore/expo-sdk-56-upgrade`, omdat die SDK 56-kennis bevat over code die
  alleen daar leeft. Lost zichzelf op zodra die branch merget; tot dan blijft master's versie oud.
- **Web-push voor `critical` health-alerts — gekozen én gebouwd, alleen nooit getest.** Stond hier
  ooit als open keuze push/mail/Telegram; onjuist. Push is gekozen (2 jul) en de hele keten staat er
  (geverifieerd 16-07): Laravel `PushController` + `WebPushService` + VAPID in de Vault + de hook in
  `HealthAlertCommand`; webapp-kant `sw-push.js` + `usePushNotifications.js` + de knop in
  `Header.jsx`, met `.env.production` naar de Laravel-backend. **Wat rest is één browser-test**
  (permissie, subscription, echte push zien binnenkomen). Zie `plans/health-alerts-webpush-blueprint.md`.
  > **Leesval:** de code valt terug op `localhost:8009` = de Node-backend, waar push een lege stub
  > is — wie alleen die default leest denkt dat de knop dood is.

  Los daarvan nog wél open: `laravel-worker` + `toernooi-heartbeat` worden niet bewaakt —
  `runbooks/uptime-monitoring.md` §Bekende gaten.
- **havuncore-webapp** — update-banner activeert de wachtende SW niet zichtbaar (pas na
  app-herstart); verdenk ontbrekende `clientsClaim`/`controllerchange`. Verder: `DEPLOY.md`'s Quick
  Deploy mist excludes, en Vitest is geblokkeerd door wat hier "een npm-registry SSL-issue" heette —
  **dat is het niet**: 16-07 gemeten dat curl op Henks machine faalt met schannel
  `CRYPT_E_NO_REVOCATION_CHECK` (unpkg, raw.githubusercontent), terwijl github.com wél 200 geeft en
  de server dezelfde URL's prima haalt. Lokale HTTPS-interceptie (Avast), niet de registry — zelfde
  oorzaak als LastMatch's APK-blocker. Workaround: via de server ophalen + hash verifiëren.

## Recent afgerond (context die nog nut heeft)

- **De auth-norm werd als status gelezen (16-07)** — `reference/authentication-methods.md` staat in
  de tegenwoordige tijd en had een "Per Project"-tabel die las als een beschrijving. HavunAdmin nam
  z'n rij over als feit ("magic-link primair (v5.1)") terwijl daar **geen magic link is gebouwd**;
  Henk zocht een feature die nooit bestond. Tabel nu gelabeld als norm, met de geverifieerde
  afwijking erbij en de rest expliciet "niet geverifieerd". Regel staat in
  `standards/md-doc-grootte.md`. De drie HavunAdmin-gaten die eruit volgden staan in **hún**
  handover, niet hier.

- **KB-chunking (15-07)** — `docs/kb/plans/kb-chunking-plan.md`. De staart van lange docs was
  onvindbaar (22-59% van de KB). Nu een aparte tabel `doc_chunks` met float32-vectoren; 3178 docs
  → 13.091 chunks. Zoeken: **0,1s met `--project`**, 1,2s ongefilterd, DB 272 → 118 MB. De preview
  toont nu de gevonden passage + koppad i.p.v. de YAML-frontmatter. Drie lessen die breder gelden
  (uitgewerkt in het plan): eerst de **consumers** inventariseren dán het schema kiezen; **meten,
  niet redeneren** (OFFSET-paginatie kostte 27s vs 8s, Eloquent-hydratie 9 van de 14s); en **één
  weg de index in** — er waren 3 producenten van een `doc_embeddings`-rij, elk met een eigen kopie.

- **Grote schoonmaak + deploys (15-07)** — `docs/kb/plans/grote-schoonmaak-2026-07-15.md`.
  29 stashes → 0, nginx-warnings → 0, alles gedeployd behalve VPDUpdate. Kern om te onthouden:
  tussen de "rommel" zat 874 MB live APK's en vier bestanden die **nergens anders bestonden** →
  `git clean -fd` was een outage geweest. Preventie: `standards/server-hygiene.md`.
- **KB draaide maandenlang op keyword-matching** (`2c43318`) — alle embeddings waren woordmaps.
  Oorzaak + fix: `docs/kb/reference/doc-intelligence-embedding-fallback-bug.md`. Nu 2764 echte
  768-dim vectoren. Ook: JudoScoreBoard/Aeterna/LastMatch ontbraken in de index (190 docs
  onvindbaar) → toegevoegd aan `DocIndexer`.
- **Nieuwe bindende regels** (in alle 22 projecten): `standards/docs-first.md` (geen code zonder MD),
  `standards/md-doc-grootte.md` (doc-grootte + één levende handover), `standards/server-hygiene.md`.
  Aanleiding: JT's handover was 842 regels en sprak zichzelf tegen; `/end` schreef zelf voor om
  sessieblokken te stapelen.

## Vaste context voor dit project

- **Rol:** centrale kennisbank + orchestrator voor alle Havun-projecten. Scope-regel: alleen
  HavunCore aanwerken; ander project = eigen sessie (uitzondering: Henk geeft expliciet toestemming).
- KB zoeken: `php artisan docs:search "<onderwerp>"` — vereist Ollama op :11434.
- **Eerste prod-deploy per app = Henk klikt bewust** (Actions → Deploy to Production).
  Runbook: `docs/kb/runbooks/deploy-keys-github-actions.md`. Nooit auto-migrate op prod.
- havuncore-webapp deployt bewust anders: lokaal build → rsync + pm2. Zie `havuncore-webapp/DEPLOY.md`.
- Server-quirk: `composer install` als root maakt `storage/**` root-owned → 500s die zichzelf niet
  kunnen loggen. Fix: `chown -R www-data:www-data storage bootstrap/cache`.
