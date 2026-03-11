# Project: HavunAdmin

**URL:** https://havunadmin.havun.nl
**Type:** Laravel 11 multi-tenant SaaS boekhouding & facturatie
**Versie:** v0.8.0
**Status:** Live in production | 97% belastingcompliant

## Wat is het?

SaaS boekhoudapplicatie voor Havun projecten. Database-per-tenant met centrale user management. Desktop-only (min 1024px).

## Omgevingen

| Omgeving | URL | Pad | Database |
|----------|-----|-----|----------|
| Local | localhost:8001 | `D:\GitHub\HavunAdmin` | MySQL |
| Staging | staging.havunadmin.havun.nl | `/var/www/havunadmin/staging` | MySQL |
| Production | havunadmin.havun.nl | `/var/www/havunadmin/production` | MySQL |

## Architectuur

- **Central DB:** `havunadmin_central` (tenants, users, subscriptions)
- **Tenant DBs:** `havunadmin_tenant_{slug}` (per-company isolatie)
- **Production:** Single-tenant fallback (`havunadmin_production`) via TenantMiddleware
- **Auth:** Unified login (PIN, biometric/WebAuthn, QR code, password fallback)

## Core Features

- **Boekhouding** - Facturen (in/uit), projecten, categorieën, grootboekrekeningen (70+)
- **Journaalposten** - Handmatige boekingen
- **Dashboard** - 6 charts (omzet, kosten, winst, YoY vergelijking)
- **Compliance (97%)** - Audit trail, PDF checksums (SHA-256), snapshots (7 jaar), BTW exports
- **Banking** - Bunq import + auto-categorisatie, Mollie sync, Herdenkingsportaal sync
- **PDF Parsing** - Claude AI extraheert leverancier, bedrag, BTW uit facturen
- **Local Folder Sync** - File System Access API (Chrome/Edge only)
- **Urenregistratie** - Tijdsregistratie per project voor startersaftrek

## Tech Stack

**Framework:** Laravel 11, Blade + Tailwind + Alpine.js, Chart.js 4.x
**Database:** MySQL 8.0
**Server:** Nginx + PHP 8.2-FPM
**Payments:** Mollie
**Banking:** Bunq API

## Belangrijke Regels

| Onderwerp | Regel |
|-----------|-------|
| **Timezone** | Date-only fields MOETEN `immutable_date:Y-m-d` gebruiken (niet `date`) |
| **Bunq sync** | Originele +/- tekens behouden (geen `abs()`) |
| **Project filter** | Facturen: "Algemeen" verborgen. Kosten: "Diversen" verborgen |
| **Primary color** | Blue (#2563EB) |
| **SmartSearch** | MOET via Vite gebundeld worden (niet standalone) |

## Server

```bash
# Deploy
cd /var/www/havunadmin/production
git pull origin main
composer install --no-dev
php artisan migrate --force
php artisan optimize:clear
```

## Documentatie in Project

| Doc | Locatie |
|-----|---------|
| Project regels | `CLAUDE.md` |
| Project context | `.claude/context.md` |
| README | `README.md` (uitgebreid) |

---

*Laatste update: 11 maart 2026*
