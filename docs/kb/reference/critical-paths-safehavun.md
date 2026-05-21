---
title: SafeHavun — kritieke paden (audit-bewijs)
type: reference
scope: safehavun
status: BINDING
last_reviewed: 2026-05-22
follows: "test-quality-policy.md"
---

# Kritieke paden — SafeHavun

> Deze paden moeten **100 %** gedekt zijn met zinvolle tests én
> mutation-score ≥ 80. Audit-bewijs voor SafeHavun.
> Bij elke PR die één van deze paden raakt: update dit document.

SafeHavun is een Smart Money Crypto Tracker (whale tracking, sentiment,
macro, scoring engine). Een bug hier raakt het zicht op marktbewegingen —
verkeerde prijs, verkeerde scores of gemiste whale alerts zijn de
failure-modes die we moeten voorkomen.

Repo-pad: `D:/GitHub/SafeHavun`.
Test-referenties zijn **relatief aan die root**.

## Pad 1 — Authenticatie (Login + PIN + QR + WebAuthn)

**Waarom kritiek:** de app toont financiële marktdata. Auth-bypass
is de toegangspoort. PIN + QR + WebAuthn zijn alternatieve login-methodes
die elk eigen brute-force surfaces hebben.

**Componenten:**

- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Auth/RegisterController.php`
- `app/Http/Controllers/Auth/PinAuthController.php`
- `app/Http/Controllers/Auth/QrAuthController.php`
- `app/Http/Controllers/WebAuthn/WebAuthnLoginController.php`
- `app/Http/Controllers/WebAuthn/WebAuthnRegisterController.php`
- `app/Models/AuthDevice.php`
- `app/Models/QrLoginToken.php`

**Branches / edge-cases:**

- [x] Login met geldig wachtwoord → success + session.
- [x] Wrong password → 401 + rate-limit.
- [x] Register: uitgeschakeld (redirect naar login).
- [x] PIN: brute-force weigeren (rate-limit throttle:auth).
- [x] QR-token: expired token → 410; re-use na succesvol login → 403.

**Tests:**

- `tests/Feature/Auth/LoginControllerTest.php`
- `tests/Feature/Auth/RegisterControllerTest.php`
- `tests/Feature/Auth/PinAuthControllerTest.php`
- `tests/Feature/Auth/QrAuthControllerTest.php`
- `tests/Unit/Models/AuthDeviceTest.php`
- `tests/Unit/Models/QrLoginTokenTest.php`

**Mutation-score target:** 90 %.

## Pad 2 — Prijzen + Data-fetching

**Waarom kritiek:** verkeerde prijs = verkeerd marktbeeld = paniek-actie.
Externe API-failures mogen de app niet neer leggen.

**Componenten:**

- `app/Http/Controllers/ApiController.php` (prices, priceHistory, sparklines)
- `app/Models/Asset.php`
- `app/Models/Price.php`
- `app/Console/Commands/FetchCryptoPrices.php`
- `app/Console/Commands/SeedDefaultAssets.php`
- `app/Services/CoinGeckoService.php`
- `app/Services/GoldPriceService.php`

**Branches / edge-cases:**

- [x] Prijzen returnen correcte JSON voor alle actieve assets (BTC/ETH/ADA/XRP/SOL).
- [x] Prijshistoriek: max 90 dagen, chronologisch gesorteerd.
- [x] Sparklines: 7-daagse data per coin.
- [x] Prijs-fetch: externe API down → graceful failure, geen 500.
- [x] Seed-assets: idempotent, dubbele run = 1 set.

**Tests:**

- `tests/Feature/ApiControllerTest.php`
- `tests/Feature/Commands/FetchCryptoPricesTest.php`
- `tests/Feature/Commands/FetchGoldPriceTest.php`
- `tests/Feature/Commands/SeedDefaultAssetsTest.php`
- `tests/Feature/SparklineTest.php`
- `tests/Unit/Models/AssetTest.php`
- `tests/Unit/Models/PriceTest.php`
- `tests/Unit/Services/CoinGeckoServiceTest.php`
- `tests/Unit/Services/GoldPriceServiceTest.php`

**Mutation-score target:** 85 %.

## Pad 3 — Signals + Sentiment + Macro

**Waarom kritiek:** market signals en macro-indicatoren vormen de kern
van de tracking-functionaliteit. Foute drempelwaarden of verkeerde
Fear & Greed mapping = verkeerd advies.

**Componenten:**

- `app/Http/Controllers/ApiController.php` (signals, marketOverview, fearGreedHistory)
- `app/Console/Commands/GenerateMarketSignals.php`
- `app/Console/Commands/FetchFearGreedIndex.php`
- `app/Console/Commands/FetchMacroIndicators.php`
- `app/Models/MarketSignal.php`
- `app/Models/FearGreedIndex.php`
- `app/Models/MacroIndicator.php`
- `app/Services/MarketSignalService.php`
- `app/Services/FearGreedService.php`
- `app/Services/FredService.php`
- `app/Services/HorizonSentimentService.php`

**Branches / edge-cases:**

- [x] Market signals: drempelwaarden getest (buy/hold/sell).
- [x] Fear & greed: 0-100 correct gemapt naar label (extreme fear/greed etc.).
- [x] FRED: FED rate + CPI correct opgeslagen als MacroIndicator.
- [x] Horizon sentiment: correct samengesteld uit signals.

**Tests:**

- `tests/Feature/Commands/GenerateMarketSignalsTest.php`
- `tests/Feature/Commands/FetchFearGreedIndexTest.php`
- `tests/Feature/Commands/FetchMacroIndicatorsTest.php`
- `tests/Unit/Models/MarketSignalTest.php`
- `tests/Unit/Models/FearGreedIndexTest.php`
- `tests/Unit/Services/MarketSignalServiceTest.php`
- `tests/Unit/Services/FearGreedServiceTest.php`
- `tests/Unit/Services/FredServiceTest.php`
- `tests/Unit/Services/HorizonSentimentServiceTest.php`

**Mutation-score target:** 85 %.

## Pad 4 — Whale Tracking

**Waarom kritiek:** whale alerts zijn het onderscheidend kenmerk van
SafeHavun. Missende transacties of verkeerde inflow/outflow-classificatie
ondermijnt de tracking-waarde.

**Componenten:**

- `app/Http/Controllers/ApiController.php` (whaleAlerts, whaleAggregations)
- `app/Console/Commands/FetchWhaleAlerts.php`
- `app/Console/Commands/AggregateWhaleAlerts.php`
- `app/Models/WhaleAlert.php`
- `app/Models/WhaleAggregation.php`
- `app/Services/WhaleTrackingService.php`

**Branches / edge-cases:**

- [x] Whale alerts: inflow/outflow correct geclassificeerd.
- [x] Etherscan: internal txs + normal txs verwerkt.
- [x] Aggregatie: bucket-berekening correct (sentiment_score, net_flow_usd).
- [x] Deduplicatie: zelfde tx hash niet dubbel ingevoegd.

**Tests:**

- `tests/Feature/Commands/FetchWhaleAlertsTest.php`
- `tests/Feature/Commands/AggregateWhaleAlertsTest.php`
- `tests/Unit/Models/WhaleAlertTest.php`
- `tests/Unit/Services/WhaleTrackingServiceTest.php`

**Mutation-score target:** 85 %.

## Pad 5 — HolderScore Engine

**Waarom kritiek:** de scoring engine is de kern van de voorspellings-
functionaliteit. Verkeerde scores = verkeerde signalen aan de gebruiker.

**Componenten:**

- `app/Http/Controllers/HolderScoreController.php`
- `app/Http/Controllers/ScoreVerificationController.php`
- `app/Console/Commands/GenerateHolderScores.php`
- `app/Console/Commands/AdjustHolderScoreWeights.php`
- `app/Console/Commands/EvaluateScoreVerifications.php`
- `app/Models/HolderScore.php`
- `app/Models/ScoreComponent.php`
- `app/Models/ScoreVerification.php`
- `app/Services/HolderScoreService.php`

**Branches / edge-cases:**

- [x] Score berekening: gewogen som van deelscores klopt.
- [x] Score verificatie: predicted vs actual correct vergeleken.
- [x] Weight adjustment: gewichten bijgesteld op basis van verificatie-resultaat.
- [x] Historiek: `/api/holder-scores/{coin}/history` correct gesorteerd.

**Tests:**

- `tests/Feature/Api/HolderScoreApiTest.php`
- `tests/Feature/Commands/GenerateHolderScoresTest.php`
- `tests/Feature/Commands/AdjustHolderScoreWeightsTest.php`
- `tests/Feature/Commands/EvaluateScoreVerificationsTest.php`
- `tests/Feature/ScoreVerificationTest.php`
- `tests/Unit/Services/HolderScoreServiceTest.php`

**Mutation-score target:** 85 %.

## Pad 6 — PWA + Push Notifications + Security

**Waarom kritiek:** PWA is de primaire user interface. Push notifications
zijn de alerting-laag. Security headers beschermen de sessie.

**Componenten:**

- `app/Http/Controllers/PwaController.php`
- `app/Http/Controllers/PushController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Middleware/SecurityHeaders.php`
- `app/Models/PushSubscription.php`
- `app/Services/PushNotificationService.php`
- `config/session.php`

**Branches / edge-cases:**

- [x] PWA-manifest + service-worker served correct.
- [x] Push subscribe/unsubscribe idempotent.
- [x] Dashboard: authenticated-only, redirect anders naar /pwa.
- [x] Security headers: X-Content-Type, X-Frame=DENY, X-XSS, Referrer-Policy,
      Permissions-Policy, CSP default-deny + frame-ancestors='none',
      nonce-per-request-uniciteit.
- [x] Push90: push notification na 90 dagen inactief.

**Tests:**

- `tests/Feature/PwaControllerTest.php`
- `tests/Feature/PushSubscriptionTest.php`
- `tests/Feature/Push90Test.php`
- `tests/Feature/DashboardControllerTest.php`
- `tests/Feature/RouteTest.php`
- `tests/Feature/Middleware/SecurityHeadersTest.php`

**Mutation-score target:** 85 %.

## Audit-checklist

1. Klopt het aantal paden? (6).
2. Tests actueel? → `php artisan test --no-coverage` (384 tests, 800 assertions).
3. Alle actieve assets: BTC, ETH, ADA, XRP, SOL.

## Proces

- **Bij elke PR** die een kritiek pad raakt: update "branches" en "tests".
- **Maandelijks**: mutation-run + update `last_reviewed`.
