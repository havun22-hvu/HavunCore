---
title: SafeHavun — kritieke paden (audit-bewijs)
type: reference
scope: safehavun
status: BINDING
last_reviewed: 2026-04-21
follows: "test-quality-policy.md"
---

# Kritieke paden — SafeHavun

> Deze paden moeten **100 %** gedekt zijn met zinvolle tests én
> mutation-score ≥ 80. Audit-bewijs voor SafeHavun.
> Bij elke PR die één van deze paden raakt: update dit document.

SafeHavun is de portefeuille-tracker (crypto + goud + fear/greed index +
exchange-integraties). Een bug hier raakt iemands zicht op eigen geld —
verkeerde prijs, verkeerde holdings, of zelfs het lekken van
exchange-credentials zijn de failure-modes die we moeten voorkomen.

Repo-pad: `D:/GitHub/SafeHavun` (via `havuncore:config/quality-safety.php`).
Test-referenties zijn **relatief aan die root**.

## Pad 1 — Authenticatie (Login + PIN + QR + Register)

**Waarom kritiek:** portfolio-data is financieel gevoelig + we bewaren
exchange-credentials. Elke auth-bypass = lezen van die credentials.

**Componenten:**

- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Auth/RegisterController.php`
- `app/Http/Controllers/Auth/PinAuthController.php`
- `app/Http/Controllers/Auth/QrAuthController.php`
- `app/Models/AuthDevice.php`
- `app/Models/QrLoginToken.php`

**Branches / edge-cases:**

- [ ] Login met geldig wachtwoord → success + session.
- [ ] Wrong password → 401 + rate-limit.
- [ ] Register: duplicate email → 422.
- [ ] PIN: brute-force weigeren (rate-limit).
- [ ] QR-token: expired token → 410; re-use na succesvol login → 403.

**Tests:**

- `tests/Feature/Auth/LoginControllerTest.php`
- `tests/Feature/Auth/RegisterControllerTest.php`
- `tests/Feature/Auth/PinAuthControllerTest.php`
- `tests/Feature/Auth/QrAuthControllerTest.php`
- `tests/Unit/Models/AuthDeviceTest.php`
- `tests/Unit/Models/QrLoginTokenTest.php`

**Mutation-score target:** 90 %.

## Pad 2 — Exchange-integraties + credential-opslag

**Waarom kritiek:** API-keys van Kraken/Binance/Coinbase etc. worden
opgeslagen. Lekken = rechtstreeks geld-risico. Transaction-import moet
idempotent zijn anders tellen aankopen dubbel.

**Componenten:**

- `app/Models/ExchangeCredential.php`
- `app/Models/ExchangeTransaction.php`
- `app/Services/Exchanges/*`

**Branches / edge-cases:**

- [ ] Credential-opslag: encrypted-at-rest.
- [ ] Credential-decrypt: alleen voor authenticated owner.
- [ ] Transaction-import: hash-dedupe — dubbele import = 1 record.
- [ ] API-failure: retry met backoff, geen runaway.

**Tests:**

- `tests/Unit/Models/ExchangeCredentialTest.php`
- `tests/Unit/Models/ExchangeTransactionTest.php`

**Mutation-score target:** 90 %.

## Pad 3 — Portfolio + asset-pricing

**Waarom kritiek:** verkeerde prijs = verkeerd portfolio-totaal =
paniek-actie door eigenaar.

**Componenten:**

- `app/Http/Controllers/PortfolioController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Models/Asset.php` + `Price.php`
- `app/Console/Commands/FetchCryptoPrices.php`
- `app/Console/Commands/FetchGoldPrice.php`
- `app/Console/Commands/GenerateMarketSignals.php`
- `app/Console/Commands/FetchFearGreedIndex.php`
- `app/Console/Commands/SeedDefaultAssets.php`

**Branches / edge-cases:**

- [ ] Portfolio rendert met correcte totalen (som × prijs).
- [ ] Prijs-fetch: external API down → cache-fallback, geen 500.
- [ ] Seed-assets: idempotent, dubbele run = 1 set.
- [ ] Market signals: drempelwaarden getest (buy/hold/sell).
- [ ] Fear & greed: uit 0-100 correct gemapt naar label.

**Tests:**

- `tests/Feature/PortfolioControllerTest.php`
- `tests/Feature/DashboardControllerTest.php`
- `tests/Feature/Commands/FetchCryptoPricesTest.php`
- `tests/Feature/Commands/FetchGoldPriceTest.php`
- `tests/Feature/Commands/FetchFearGreedIndexTest.php`
- `tests/Feature/Commands/GenerateMarketSignalsTest.php`
- `tests/Feature/Commands/SeedDefaultAssetsTest.php`
- `tests/Unit/Models/AssetTest.php`
- `tests/Unit/Models/PriceTest.php`
- `tests/Unit/Models/FearGreedIndexTest.php`
- `tests/Unit/Models/MarketSignalTest.php`
- `tests/Unit/Models/WhaleAlertTest.php`

**Mutation-score target:** 85 %.

## Pad 4 — API + PWA offline-modus

**Waarom kritiek:** mobiele/PWA gebruikers willen hun portfolio zien
ook zonder netwerk. Een cache-corruptie = lege portfolio = paniek.

**Componenten:**

- `app/Http/Controllers/ApiController.php`
- `app/Http/Controllers/PwaController.php`

**Branches / edge-cases:**

- [ ] API returnt JSON met Bearer-auth.
- [ ] PWA-manifest + service-worker serves.
- [ ] Offline-fallback: cached portfolio zichtbaar zonder netwerk.

**Tests:**

- `tests/Feature/ApiControllerTest.php`
- `tests/Feature/PwaControllerTest.php`
- `tests/Feature/RouteTest.php`

**Mutation-score target:** 85 %.

## Pad 5 — Security headers + session cookies

**Componenten:**

- `app/Http/Middleware/SecurityHeaders.php`
- `config/session.php`

**Tests:**

- `tests/Feature/Middleware/SecurityHeadersTest.php` (7 tests / 13
  assertions — X-Content-Type, X-Frame=DENY, X-XSS, Referrer-Policy,
  Permissions-Policy, CSP default-deny + frame-ancestors='none',
  nonce-per-request-uniekheid)

**Mutation-score target:** 85 %.

## Audit-checklist

1. Klopt het aantal paden? (5).
2. Tests actueel? → `critical-paths:verify --project=safehavun`.

## Proces

- **Bij elke PR** die een kritiek pad raakt: update "branches" en "tests".
- **Maandelijks**: mutation-run + update `last_reviewed`.
