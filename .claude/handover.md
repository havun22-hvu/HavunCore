---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-07-19
---

# HavunCore — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels. Regel: `docs/kb/standards/md-doc-grootte.md`.

**Branch:** master · **Status:** stabiel. KB zoekt gechunkt (`--project` ~0,1s). **Server:** disk 67%
(12 GB vrij na opschoning 18-07), prod draait overal.

## Open — wacht op Henk

| Wat | Details |
|-----|---------|
| **Blijvend-ingelogd-plan** | Geschreven, wacht op "ga maar" — `docs/kb/plans/blijvend-ingelogd-plan.md` |
| **Prod-deploys staan klaar (3 checkouts achter)** | Herdenkingsportaal (3 code-commits — **passkey-login af maar niet live**), JudoToernooi (6), HavunCore zelf (KB-werk). Deploy = altijd jouw klik |
| **Stripe-sleutel geroteerd (JudoToernooi) 19-07** | Oude `sk_live_…4l13` staat **nergens actief meer** (JudoToernooi-prod = nieuwe sleutel, geverifieerd; HavunAdmin + `laravel-old` dode sleutels leeggemaakt). **Laat de oude in Stripe verlopen.** Optioneel: webhook-secret roteren + oude Stripe-regel in `credentials.md` opschonen. AWS SES-key = Cees' account, niet de onze |
| **Vite-build achter op 4 checkouts — 2× prod** | HP-prod/staging, Studieplanner-prod, Vusista-staging. Signaal, geen diagnose — verifieer per project (`runbooks/vite-build-bij-deploy.md`), elk in eigen sessie |
| **Hardcoded Hetzner-wachtwoord op server** | `/usr/local/bin/havun-backup.sh` (`HETZNER_PASS=` plain text). Hoort in de Vault. Zie [[feedback-no-hardcoded-test-secrets]] |
| **Security: dependencies** | HavunAdmin 19 composer-advisories (2 high); JudoScoreBoard 6 GitHub-advisories (1 critical + 2 high). `composer update`/`npm` → overleg |
| **VPDUpdate: 54 commits achter + 5 dirty** | Bewust niet gedeployd; `users.json` (getrackt, live secrets) hangt eraan. Zie handover daar |
| **GitGuardian #33883984** | Op *Resolved* zetten |
| **Aeterna** | Prod keystore + update-adres. Week2-plan dood (crates bestaan al) — archiveren. `feat/v1.1-tor-socks5-3b` (PR #16 closed, niet merged) |
| **Studieplanner** | `chore/expo-sdk-55-upgrade`: 230/230 groen maar nooit device-getest, 3 mnd oud — mergen of verwerpen |
| **Studieplanner-api** | `rescue/prod-stashes-2026-07-15`: user settings + observability afmaken of branch weg |
| **LastMatch** | Avast HTTPS-scanning uit = enige APK-build-blocker |
| **Vusista** | App testen + installer op schone PC = laatste MVP-punt. Installer +119 MB (80 MB OpenCV); SFace-licentieketen onverifieerbaar |
| **JudoScoreBoard** | Google-review AAB 116 (9 juni ingediend) — status alleen in Play Console |

## Open — Veen-ledenadministratie (orchestrator-deel afgerond)

Overname Cees' EOL-app als eigen project, route B (verse **Laravel 12**).
- **Fase 1+2 klaar (18-07):** GitHub-repo (private) + server live — production `veen.havun.nl`
  + staging `staging.veen.havun.nl` (HTTPS, auto-deploy E2E bewezen) + HavunCore-registratie.
- **Fase 3 (de herbouw: feature-inventaris + SEPA-datamigratie 15k payments) = een Veen-sessie,
  na Cees' groen licht — NIET vanuit HavunCore.** Eisen (o.a. SEPA-machtiging: geen internetvinkje,
  eMandate/Twikey of PSP) staan in `VeenLedenadministratie/.claude/modernisering-scope.md`.
- Credentials (admin-login + TransIP-CP) + `.env`-secrets staan in de centrale kluis.

## Open — te doen

- **Web-push voor `critical` health-alerts — gebouwd, nooit getest.** Hele keten staat (Laravel
  `PushController`/`WebPushService` + VAPID; webapp `sw-push.js` + knop). Rest = één browser-test.
  `plans/health-alerts-webpush-blueprint.md`. Leesval: valt terug op `localhost:8009` (lege stub).
  Los daarvan: `laravel-worker` + `toernooi-heartbeat` onbewaakt (`runbooks/uptime-monitoring.md`).
- **havuncore-webapp** — update-banner activeert wachtende SW niet zichtbaar (verdenk `clientsClaim`/
  `controllerchange`). Vitest geblokkeerd door Avast HTTPS-interceptie (niet de registry) — via server
  ophalen + hash. Zie [[env-ssl-interception]].
- **JudoScoreBoard `context.md` op master nog 1039 regels** — opgeschoonde 523-versie op
  `chore/expo-sdk-56-upgrade`; lost zichzelf op bij merge.

## Recent afgerond (context die nog nut heeft)

- **credentials.md lekte in de KB-index (19-07)** — `docs:index` indexeerde de kluis (secrets in
  `doc_embeddings`). Gepurged + `isSensitiveFile`-guard in `DocIndexer` (credentials.md/.env nooit
  meer indexeren). Methode om secrets veilig te ontvangen zonder chat-lek: `runbooks/secrets-veilig-ontvangen.md`.
- **Server-opschoning 18-07** — HavunClub + Umami + Infosyst van de server (disk 73%→67%, Umami's
  pm2 gaf de RAM-winst). Backups in `/root/backups/*-2026-07-18/`. Code/repos blijven waar nodig.
- **start2-command (19-07)** — werkwijze-primer voor de VS Code-extensie, uitgerold naar 16 projecten.
- **De auth-norm werd als status gelezen (16-07)** — `reference/authentication-methods.md`: "Per
  Project"-tabel las als beschrijving. Nu gelabeld als norm. Regel in `standards/md-doc-grootte.md`.
- **KB-chunking (15-07)** — `plans/kb-chunking-plan.md`. Aparte `doc_chunks`-tabel, zoeken 0,1s met
  `--project`. Lessen: eerst consumers dán schema; meten niet redeneren; één weg de index in.

## Vaste context voor dit project

- **Rol:** centrale kennisbank + orchestrator. Scope-regel: **alleen HavunCore aanwerken; ander
  project = eigen sessie** (uitzondering: Henk geeft expliciet toestemming). Zie [[feedback-scope-waarschuwen]].
- KB zoeken: `php artisan docs:search "<onderwerp>"` — vereist Ollama op :11434.
- **Eerste prod-deploy per app = Henk klikt bewust** (Actions → Deploy to Production). Nooit auto-migrate op prod.
- havuncore-webapp deployt anders: lokaal build → rsync + pm2 (`havuncore-webapp/DEPLOY.md`).
- Server-quirk: `composer install` als root maakt `storage/**` root-owned → 500s. Fix: `chown -R www-data:www-data storage bootstrap/cache`.
