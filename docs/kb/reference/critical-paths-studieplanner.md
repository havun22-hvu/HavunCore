---
title: Studieplanner-api — kritieke paden (audit-bewijs)
type: reference
scope: studieplanner
status: BINDING
last_reviewed: 2026-04-21
follows: "test-quality-policy.md"
---

# Kritieke paden — Studieplanner-api

> Deze paden moeten **100 %** gedekt zijn met zinvolle tests én
> mutation-score ≥ 80. Audit-bewijs voor Studieplanner-api (Laravel
> backend voor de Expo-mobiele app).

Studieplanner verwerkt **minderjarige-data** (schoolroosters, cijfers,
mentor-koppelingen). Een lek raakt een gezin. Magister / SOMtoday API-
tokens zijn persoonlijke auth — lekken daarvan = kindergegevens bij
derden.

Repo-pad: `D:/GitHub/Studieplanner-api`. Test-referenties zijn relatief
aan die root.

## Pad 1 — Authenticatie (user-login + magic-link)

**Waarom kritiek:** enige weg in. Ouders/leerlingen zien gevoelige data.

**Componenten:**

- `app/Http/Controllers/Auth/*`
- `app/Models/MagicLinkToken.php`
- `app/Http/Middleware/AdminMiddleware.php`

**Branches / edge-cases:**

- [ ] Login: correct → session; wrong → 401 + rate-limit.
- [ ] Magic-link: single-use, expired tokens weigeren, no-replay.
- [ ] Admin-routes: non-admin → 403.

**Tests:**

- `tests/Feature/AuthApiTest.php`
- `tests/Feature/AdminTest.php`
- `tests/Unit/MagicLinkTokenTest.php`
- `tests/Unit/StudentInviteTest.php`

**Mutation-score target:** 90 %.

## Pad 2 — Magister + SOMtoday integraties

**Waarom kritiek:** externe school-API's met persoonlijke OAuth-tokens.
Token-lek = kinderprofiel bij derden. Foutieve sync = verkeerd rooster.

**Componenten:**

- `app/Services/Magister*`
- `app/Services/SOMtoday*`
- Token-opslag (encrypted-at-rest)

**Branches / edge-cases:**

- [ ] Valide token → data binnen.
- [ ] Expired token → refresh-flow of user-prompt.
- [ ] Revoked token → clean error, geen crash.
- [ ] API-rate-limit van upstream → exponential backoff.

**Tests:**

- `tests/Feature/MagisterApiTest.php`
- `tests/Feature/MagisterWithTokenTest.php`
- `tests/Feature/SOMtodayApiTest.php`
- `tests/Feature/SOMtodayWithTokenTest.php`

**Mutation-score target:** 90 %.

## Pad 3 — Student-data API (roosters + cijfers)

**Waarom kritiek:** dit is de payload die de mobiele app krijgt. Fout =
leerling ziet verkeerd rooster / verkeerde cijfers = gemiste toets of
paniek.

**Componenten:**

- `app/Http/Controllers/StudentData*`
- `app/Http/Controllers/Session*`
- `app/Http/Controllers/PublicApi*`

**Branches / edge-cases:**

- [ ] Student kan alleen eigen data ophalen (multi-tenant-scheiding).
- [ ] Mentor ziet eigen leerlingen (MentorApi), niet die van anderen.
- [ ] Public-API: geen authentication vereist alleen voor echt
  publieke data (geen student-data lekt).
- [ ] Session-expiry werkt.

**Tests:**

- `tests/Feature/StudentDataApiTest.php`
- `tests/Feature/SessionApiTest.php`
- `tests/Feature/MentorApiTest.php`
- `tests/Feature/PublicApiTest.php`

**Mutation-score target:** 85 %.

## Pad 4 — Premium + push notifications

**Waarom kritiek:** premium-paywall afdwinging + push-token opslag
(notifications gaan naar juiste device). Premium-bypass = verlies
abonnement-inkomsten.

**Componenten:**

- `app/Http/Controllers/PremiumController.php` (of vergelijkbaar)
- Push-subscription opslag

**Branches / edge-cases:**

- [ ] Non-premium → 402 / feature-lock.
- [ ] Premium → full access.
- [ ] Expiry-datum gerespecteerd.
- [ ] Push-token uniek per device, revoked bij logout.

**Tests:**

- `tests/Feature/PremiumApiTest.php`
- `tests/Feature/PushSubscriptionTest.php`
- `tests/Unit/NotificationTest.php`

**Mutation-score target:** 85 %.

## Pad 5 — Security headers + session cookies

**Componenten:**

- `app/Http/Middleware/SecurityHeaders.php`
- `config/session.php`

**Branches / edge-cases:**

- [ ] CSP, HSTS, X-Frame-Options, X-Content-Type, Referrer-Policy,
  Permissions-Policy op elke response.
- [ ] CSP-nonce per request.
- [ ] APP_ENV=production → géén localhost in CSP (na 20-04 sessie ook
  op prod gefixt).

**Tests:**

- `tests/Feature/Middleware/SecurityHeadersTest.php` (7 tests / 12
  assertions — X-Content-Type, X-Frame=DENY, X-XSS, Referrer-Policy,
  Permissions-Policy, CSP default-deny, nonce-per-request-uniekheid)

**Mutation-score target:** 85 %.

## Pad 6 — HavunCore integratie

**Waarom kritiek:** SP-api stuurt errors naar HavunCore's
observability + gebruikt HavunCore's AI Proxy. Fout in die contracten
= silent drop van errors of dubbele AI-kosten.

**Componenten:**

- `app/Services/HavunCoreService.php`
- Error-report hook in `bootstrap/app.php` reportable-sectie.

**Tests:**

- `tests/Unit/HavunCoreServiceTest.php`

**Mutation-score target:** 85 %.

## Audit-checklist

1. Klopt het aantal paden? (6).
2. Tests actueel? → `critical-paths:verify --project=studieplanner`.

## Proces

- **Bij elke PR** die een kritiek pad raakt: update "branches" en "tests".
- **Maandelijks**: mutation-run + update `last_reviewed`.
