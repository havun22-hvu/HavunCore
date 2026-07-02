---
title: Blueprint — Web push voor kritieke health-alerts
type: plan
scope: havuncore
last_check: 2026-07-02
status: wacht-op-go
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

## Backend (HavunCore) — na go

1. `composer require minishlink/web-push`.
2. VAPID-keypair genereren (`VAPID::createVapidKeys()`), private in Vault, public naar config.
3. `config/services.php` → `vapid` block (subject/public/private) — kopie SafeHavun.
4. Migratie `push_subscriptions` (id, user_id?, endpoint uniek, p256dh, auth, created_at) + model.
5. `POST /api/push/subscribe` + `POST /api/push/unsubscribe` (+ nginx-allowlist zoals `/api/health-alerts`).
6. `GET /api/push/vapid-public-key` zodat de frontend de key kan ophalen.
7. `WebPushService::send(title, body, data)` — port van SafeHavuns `PushNotificationService`
   (active subs ophalen, `WebPush` met VAPID, 410/404 → sub opruimen).
8. **Hook:** in het `health:alert`-command, bij `status=down && severity=critical`, na het
   opslaan van de alert → `WebPushService::send(...)`. Rate-limit per key (hergebruik alert-dedup).
9. Tests: subscribe-endpoint, send bouwt correcte payload, 410 ruimt sub op, critical-hook vuurt.

## Frontend (havuncore-webapp) — eigen sessie

1. Service worker: `push` → `showNotification()`, `notificationclick` → focus webapp.
2. Bij de 🔔 bel: `Notification.requestPermission()` → `pushManager.subscribe({applicationServerKey})`
   met de opgehaalde VAPID public key → `POST /api/push/subscribe`.
3. Deploy via de bestaande build-en-upload (`DEPLOY.md`) + `sw.js` cache-bust.

## Waarom push en niet mail

Henks keuze 2 juli: PWA-push = geen externe partij, geen kosten, betrouwbaarder dan mail
(SendGrid dood, spam-risico). Zie `runbooks/uptime-monitoring.md` §Bekende gaten.
