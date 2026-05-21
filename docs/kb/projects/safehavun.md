---
title: Project: SafeHavun
type: reference
scope: safehavun
last_check: 2026-05-22
---

# Project: SafeHavun

**URL:** https://safehavun.havun.nl (live)
**Type:** Laravel 12 API + Vanilla JS PWA
**Repo:** https://github.com/havun22-hvu/SafeHavun
**Server:** /var/www/safehavun/production op 188.245.159.115
**Lokaal:** `D:\GitHub\SafeHavun` — `php artisan serve --port=8004`

## Doel

Smart Money Crypto Tracker — volg de whales, niet de massa:
- Whale alerts (grote transacties, on-chain)
- Sentiment indicators (Fear & Greed)
- Macro indicatoren (FED rate, CPI)
- HolderScore scoring engine
- Marktrichting voorspellingen per tijdshorizon

## Stack

| Component | Technologie |
|-----------|-------------|
| Backend | Laravel 12 + PHP 8.2 + MySQL |
| Frontend | Vanilla JS PWA (GEEN React, GEEN Alpine) |
| Styling | TailwindCSS 4 |
| Grafieken | Chart.js |
| Auth | Laravel Sanctum + QR + PIN + WebAuthn/Passkey |

## PWA Tabs

Voorspelling · Whales · Sentiment · Technisch · Macro

Na login: altijd redirect naar `/pwa` (niet dashboard).

## Actieve Assets

BTC, ETH, ADA, XRP, SOL (5 coins)

## Data Bronnen

| Bron | Data | Status |
|------|------|--------|
| CoinGecko | Prijzen, sparklines | Actief |
| Alternative.me | Fear & Greed Index | Actief |
| Etherscan | ETH whale tracking (internals + normals) | Actief |
| FRED (FredService) | FED rate, CPI | Actief |
| Eigen scoring engine | HolderScoreService | Actief |

## Database Schema (werkelijke tabellen)

```
assets              - Ondersteunde coins (BTC, ETH, ADA, XRP, SOL)
prices              - Prijsgeschiedenis per asset (niet price_history)
whale_alerts        - Grote transacties (on-chain)
whale_aggregations  - Geaggregeerde whale flows per bucket
holder_scores       - Berekende scores per coin/horizon
score_components    - Deelscores per holder score
score_verifications - Verificaties van scoring
market_signals      - Fear & greed, news signals, fed/cpi
push_subscriptions  - Web push subscriptions
fear_greed_index    - Fear & Greed historiek
macro_indicators    - FED rate, CPI en andere macro data
crypto_news         - Nieuws + Claude AI-analyse
auth_devices        - PIN/biometrie devices per user
qr_login_tokens     - QR-login tokens
```

## API Endpoints

Alle endpoints zijn public (geen auth vereist) tenzij anders aangegeven:

```
GET  /api/prices                        - alle koersen (actieve assets)
GET  /api/prices/sparklines             - 7-daagse sparkline data
GET  /api/prices/{asset}/history?days=  - prijsgeschiedenis (max 90 dagen)
GET  /api/signals                       - market signals (24u)
GET  /api/market-overview               - marktoverzicht + fear/greed
GET  /api/fear-greed/history?days=30    - Fear & Greed historiek
GET  /api/whale-alerts                  - whale transacties (inflow/outflow)
GET  /api/whale-aggregations            - geaggregeerde whale flows
GET  /api/sentiment/horizons            - horizon sentiments
GET  /api/holder-scores                 - holder scores overzicht
GET  /api/holder-scores/{coin}/history  - score historiek per coin
GET  /api/score-verifications           - score verificaties
GET  /api/push/public-key               - VAPID public key
POST /api/push/subscribe                - push subscription aanmaken
DEL  /api/push/unsubscribe              - push subscription verwijderen
GET  /api/user  [auth]                  - ingelogde user info
```

## Console Commands

```
crypto:fetch-prices           - CoinGecko prijzen ophalen
crypto:fetch-fear-greed       - Alternative.me Fear & Greed
crypto:fetch-whales           - Whale transacties ophalen
crypto:generate-signals       - Market signals genereren
crypto:fetch-macro-indicators - FED/CPI via FRED
crypto:fetch-news             - Crypto nieuws + Claude analyse
crypto:generate-holder-scores - HolderScore berekenen
crypto:aggregate-whale-alerts - Whale flows aggregeren
crypto:adjust-score-weights   - Score weights bijstellen
crypto:evaluate-score-verifications - Verificaties evalueren
db:seed-default-assets        - Standaard assets seeden
```

## Repositories & Paths

| Onderdeel | Pad |
|-----------|-----|
| Lokaal | `D:\GitHub\SafeHavun` |
| Server | `/var/www/safehavun/production` |

## Tests

```bash
php artisan test --no-coverage
```

384 tests, 800 assertions — alles groen (2026-05-22)

## Roadmap Status

Alle basis functionaliteit is live en operationeel:
- Backend + database: klaar
- CoinGecko + Fear & Greed + FRED + Etherscan: klaar
- Vanilla JS PWA (5 tabs): klaar
- Push notifications: klaar
- Scoring engine (HolderScore): klaar
- Auth (login + PIN + QR + WebAuthn): klaar

---

## Contact

Eigenaar: Henk van Unen
