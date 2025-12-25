# Project: SafeHavun

**URL:** https://safehavun.havun.nl (gepland)
**Type:** Laravel 12 API + React Dashboard + PWA
**Repo:** https://github.com/havun22-hvu/SafeHavun

## Doel

Crypto Smart Money Tracker - On-chain analyse om "smart money" (whales) te volgen:
- Whale alerts (grote transacties)
- Exchange in/outflow
- Stablecoin ratio's
- Sentiment indicators
- Marktrichting voorspellingen

## Componenten

| Component | Technologie | Doel |
|-----------|-------------|------|
| Backend | Laravel 12 | API + data fetching |
| Dashboard | React + Vite | Grafieken, statistieken |
| PWA | React (simpel) | Marktrichting, alerts |

## Assets

Top 10 crypto + stablecoins + goud:
- BTC, ETH, ADA, XRP, SOL, DOGE, DOT, AVAX, LINK, MATIC
- USDT, USDC (stablecoins)
- XAU (goud)

## Data Bronnen

| Bron | Data | Gratis? |
|------|------|---------|
| CoinGecko | Prijzen, market cap | ✅ Ja |
| Whale Alert | Grote transacties | ✅ Gratis tier |
| Alternative.me | Fear & Greed Index | ✅ Ja |
| Glassnode | On-chain metrics | ⚠️ Beperkt gratis |

## Database Schema

```
assets          - Ondersteunde coins (BTC, ETH, etc.)
price_history   - Prijsgeschiedenis per asset
whale_alerts    - Grote transacties
exchange_flows  - Exchange in/outflow metrics
stablecoin_metrics - USDT/USDC supply ratio's
sentiment_data  - Fear/Greed, social volume
predictions     - AI-gegenereerde voorspellingen
```

## Repositories

| Component | Lokaal | Server |
|-----------|--------|--------|
| Backend + Frontend | `D:\GitHub\SafeHavun` | `/var/www/safehavun/production` |

## HavunCore Integratie

| Service | Gebruik |
|---------|---------|
| Vault | API keys opslag (WhaleAlert, etc.) |
| Backup | Daily backup |
| Auth | Eigen (Laravel Sanctum) |

## Roadmap

### Fase 1: Backend Basis
- [x] Laravel 12 project setup
- [x] Database migraties
- [ ] CoinGecko service
- [ ] Data fetch scheduler

### Fase 2: API Integraties
- [ ] Whale Alert API
- [ ] Fear & Greed Index
- [ ] Exchange flow data

### Fase 3: Frontend
- [ ] React dashboard setup
- [ ] Prijsgrafieken
- [ ] Whale alerts overzicht

### Fase 4: PWA
- [ ] Simpele mobiele view
- [ ] Push notifications
- [ ] Marktrichting indicator

---

## Contact

Eigenaar: Henk van Unen
