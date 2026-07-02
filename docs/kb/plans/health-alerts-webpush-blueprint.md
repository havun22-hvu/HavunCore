---
title: Blueprint — Web push voor kritieke health-alerts
type: plan
scope: havuncore
last_check: 2026-07-02
status: backend-gebouwd-wacht-op-prod-deploy+frontend
---

# Blueprint — Web push voor kritieke health-alerts

> **Doel:** een `critical` health-alert (bv. reverb/workers FATAL) duwt een PWA-push naar
> Henk, ook als de webapp dicht is. Lost het gat op waardoor de reverb-outage 23 jun–2 jul
> 10 dagen onopgemerkt bleef (alert stond correct in het paneel, maar zonder actief kanaal).
> **Hergebruikt het bestaande SafeHavun-patroon** (`minishlink/web-push ^10`, VAPID via
> `config/services.php`, `push_subscriptions`-tabel, `PushNotificationService::send()`).

## Twee repos

- **HavunCore backend** — subscriptions opslaan + push versturen bij `health:alert down+critical`.
- **havuncore-webapp frontend** (apart project, Node/PWA) — permissie vragen, subscriben,
  service-worker `push`/`notificationclick`. **Eigen sessie** (buiten HavunCore-scope).

## ⛔ Vereist Henks go (verboden zonder overleg)

1. **Composer-dependency** `minishlink/web-push:^10.0` in HavunCore.
2. **VAPID-sleutelpaar** opslaan. Voorstel: **HavunCore Vault** (centrale scoped secret-opslag)
   i.p.v. los in `.env` — netter dan SafeHavuns `.env`-aanpak. `config/services.php` leest de
   keys (uit Vault-injectie of env). Subject `mailto:alerts@havun.nl`.

## Backend (HavunCore) — ✅ GEBOUWD (commit 2acd428, 2 juli)

- `minishlink/web-push:^10` toegevoegd.
- `push_subscriptions` migratie + `PushSubscription` model (dedup op endpoint-sha256).
- `WebPushService::send()` — leest VAPID **at-runtime uit de Vault** (niet config, want
  `config:cache` kan geen DB lezen); 404/410 → sub opruimen. `config/services.php` → `vapid.subject`.
- `PushController`: `GET /api/push/vapid-public-key`, `POST /api/push/subscribe`,
  `POST /api/push/unsubscribe` (throttled) + `SubscribePushRequest`.
- `vapid:setup`-command genereert het keypair en zet het in de Vault (idempotent, `--rotate`).
- **Hook** in `HealthAlertCommand`: push alleen bij een **verse** `down`+`critical` (niet elke 5 min);
  best-effort, breekt de health-check nooit.
- 10 tests; volledige suite **1272 groen**.

## Server-deploy — ✅ LIVE (2 juli)

Uitgevoerd op `/var/www/havuncore/production`: reset naar origin/master, `composer install --no-dev`,
`migrate` (push_subscriptions), `vapid:setup` (keys in Vault), nginx-allowlist `push` toegevoegd aan
de Laravel-regex-location + reload. Geverifieerd: `GET /api/push/vapid-public-key` → 200,
`POST /api/push/subscribe` → 201, lege body → 422.

> ⚠️ **Deploy-valkuil (kostte hier ~een uur):** `composer install` als **root** draait
> `artisan package:discover` als root en maakt `storage/**` + `bootstrap/cache` **root-owned**
> (625 bestanden). Daarna faalt elke request die de file-cache of het log schrijft met een
> **500 die zichzelf niet kan loggen** (permission denied op het logbestand → originele fout
> onzichtbaar). **Altijd** na een root-`composer install`:
> `chown -R www-data:www-data storage bootstrap/cache`. Beter: composer als www-data draaien.

> ⚠️ **Prod-checkout divergeert:** een server-cron (`auto:commit-regenerated`) committ dagelijks
> `handover`/`qv-scan-latest` op de prod-checkout maar **pusht nooit** → `git pull --ff-only` faalt.
> Opgelost met backup-branch `backup-prod-autocommits-2026-07-02` + `git reset --hard origin/master`.
> Structureel: die cron zou niet op een deploy-checkout moeten committen (of moeten pushen).

## Nog te doen — frontend (havuncore-webapp, eigen sessie)

## Frontend (havuncore-webapp) — eigen sessie

1. Service worker: `push` → `showNotification()`, `notificationclick` → focus webapp.
2. Bij de 🔔 bel: `Notification.requestPermission()` → `pushManager.subscribe({applicationServerKey})`
   met de opgehaalde VAPID public key → `POST /api/push/subscribe`.
3. Deploy via de bestaande build-en-upload (`DEPLOY.md`) + `sw.js` cache-bust.

## Waarom push en niet mail

Henks keuze 2 juli: PWA-push = geen externe partij, geen kosten, betrouwbaarder dan mail
(SendGrid dood, spam-risico). Zie `runbooks/uptime-monitoring.md` §Bekende gaten.
