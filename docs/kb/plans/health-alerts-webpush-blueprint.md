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

## Nog te doen op de server (prod = Henks go)

1. Deploy HavunCore-backend (git pull + `composer install --no-dev` op `/var/www/havuncore/production`).
2. `php artisan migrate` (nieuwe `push_subscriptions`-tabel — **prod-migratie = overleg**).
3. `php artisan vapid:setup` (genereert + zet VAPID-keys in de Vault, eenmalig).
4. **nginx-allowlist** `/api/push/*` toevoegen (zoals `/api/health-alerts` — anders 403).

## Frontend (havuncore-webapp) — eigen sessie

1. Service worker: `push` → `showNotification()`, `notificationclick` → focus webapp.
2. Bij de 🔔 bel: `Notification.requestPermission()` → `pushManager.subscribe({applicationServerKey})`
   met de opgehaalde VAPID public key → `POST /api/push/subscribe`.
3. Deploy via de bestaande build-en-upload (`DEPLOY.md`) + `sw.js` cache-bust.

## Waarom push en niet mail

Henks keuze 2 juli: PWA-push = geen externe partij, geen kosten, betrouwbaarder dan mail
(SendGrid dood, spam-risico). Zie `runbooks/uptime-monitoring.md` §Bekende gaten.
