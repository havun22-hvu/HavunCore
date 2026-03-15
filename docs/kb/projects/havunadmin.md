# Project: HavunAdmin

**URL:** https://havunadmin.havun.nl
**Type:** Laravel 12 multi-tenant SaaS boekhouding & facturatie
**Versie:** v0.8.0 (10 jan 2026)
**Status:** Live in production | 97% belastingcompliant
**Platform:** Desktop-only (min 1024px)

## Wat is het?

SaaS boekhoudapplicatie voor Havun projecten. Database-per-tenant met centrale user management. Sidebar altijd zichtbaar (geen hamburger menu).

## Omgevingen

| Omgeving | URL | Pad | Database |
|----------|-----|-----|----------|
| Local | localhost:8001 | `D:\GitHub\HavunAdmin` | MySQL (havun_admin) |
| Staging | staging.havunadmin.havun.nl | `/var/www/havunadmin/staging` | MySQL (havunadmin_staging) |
| Production | havunadmin.havun.nl | `/var/www/havunadmin/production` | MySQL (havunadmin_production) |

**Deploy regel:** Altijd staging eerst, nooit alleen production. Staging en production na een release op dezelfde commit.

## Tech Stack

| Component | Versie |
|-----------|--------|
| Laravel | 12.x (12.38.1) |
| PHP | ^8.2 |
| Livewire | (actief) |
| Alpine.js | 3.4.2 + @alpinejs/collapse 3.15.8 |
| Tailwind CSS | 3.1 + @tailwindcss/forms 0.5.2 |
| Vite | 7.0.7 |
| Chart.js | 4.5.1 |
| WebAuthn | laragear/webauthn 4.0 (passkeys) |
| Mollie SDK | mollie-api-php 2.0 |
| Bunq SDK | bunq/sdk_php 1.14 |
| Stripe SDK | stripe-php 19.4 |
| DOMPDF | barryvdh/laravel-dompdf 3.1 |
| PDF Parser | smalot/pdfparser |
| HavunCore | havun/core @dev (local dependency) |

## Multi-Tenant Architectuur

```
havunadmin.havun.nl (Centrale applicatie)
         |
    +----+----+----+
    v         v         v
havunadmin_  havunadmin_  havunadmin_
tenant_havun tenant_klantx tenant_klanty
```

- **Central DB:** `havunadmin_central` (tenants, central_users, tenant_users, subscription_events)
- **Tenant DBs:** `havunadmin_tenant_{slug}` (per-company isolatie)
- **Production:** Single-tenant fallback (`havunadmin_production`) via TenantMiddleware — geen `havunadmin_central` op prod
- **Tenant switching:** Session-based
- **Code:** Multi-tenant ready, full migration nog niet uitgevoerd

**Pricing Plans:**

| Plan | Prijs | Users | Limiet |
|------|-------|-------|--------|
| Trial | Gratis (14 dagen) | 1 | Max 10 facturen |
| Basic | 9,95/maand | 1 | - |
| Pro | 19,95/maand | 3 | + API |
| Enterprise | 49,95/maand | Onbeperkt | - |

## Core Features

- **Boekhouding** - Verkoop facturen (in/uit), projecten, categorieen, grootboekrekeningen (70+)
- **Inkoop** - Inkoop facturen van leveranciers, PDF parsing met Claude AI
- **Journaalposten** - Handmatige boekingen
- **Dashboard** - 6 Chart.js grafieken (omzet, kosten, winst, YoY vergelijking)
- **Compliance (97%)** - Audit trail, PDF checksums (SHA-256), snapshots (7 jaar), BTW exports
- **Banking** - Bunq import + auto-categorisatie, Mollie sync, Herdenkingsportaal sync
- **PDF Parsing** - Claude AI (sonnet-4-20250514) extraheert leverancier, bedrag, BTW uit facturen
- **Local Folder Sync** - File System Access API (Chrome/Edge only)
- **Urenregistratie** - Tijdsregistratie per project voor startersaftrek
- **Offertes** - Quote management
- **Vaste Activa** - Bedrijfsmiddelen + afschrijvingen
- **Reconciliation** - Duplicate detection matching
- **SmartSearch** - Complexe zoekfilters (MOET via Vite gebundeld)
- **Factuur Templates** - Automatische facturatie (eenmalig, maandelijks, per kwartaal, jaarlijks)
- **Rapportages** - Belastingdienst CSV exports (kwartaal, jaar, BTW)
- **AI Chat** - Claude Sonnet chat sessies
- **Bankrekeningen** - Zakelijk, prive, creditcard beheer

## Auth Systeem

4 methoden, Unified Login:

| Methode | Implementatie |
|---------|--------------|
| Email/Wachtwoord | Laravel Breeze based |
| PIN Numpad | Custom implementation |
| WebAuthn/Passkey | laragear/webauthn 4.0 (Windows Hello, Touch ID, YubiKey) |
| QR Code | Lokaal (geen HavunCore dependency) |

**Routes:** `/webauthn/*`, `/qr/*`

**Rollen:**
- Super Admin (Havun only)
- Owner (tenant creator)
- Admin (full access)
- User (limited access)
- Accountant (read-only, toekomstig)

## Externe Integraties

### Mollie
- Betalingen + subscriptions
- Webhook: `/webhooks/mollie/subscription`
- Status: LIVE (production key actief)

### Bunq
- Bankrekening transacties sync
- Auto-categorisatie op transaction description
- Sandbox + Live support
- Originele +/- tekens behouden (GEEN `abs()`)

### Stripe
- Basis setup (niet volledig geimplementeerd)

### Claude AI (Anthropic)
- **PDF Parsing:** `ClaudePdfParsingService` - text extractie via smalot/pdfparser, dan Claude API
- **AI Chat:** `AiChatService` - model: `claude-sonnet-4-20250514`
- **Direct API:** `ANTHROPIC_API_KEY` env var

### Gmail
- OAuth2 factuur import (DEPRECATED/niet meer actief)

### Externe APIs
- **Postcode lookup:** Straat/plaats validatie
- **KvK lookup:** Bedrijfsgegevens via externe API

## Compliance (97%)

| Categorie | Score | Details |
|-----------|-------|---------|
| Bewaarplicht | 95% | HavunCore daily backups, 7-jaar retentie |
| Authenticiteit | 100% | Email metadata, customer/supplier snapshots |
| Integriteit | 100% | SHA256 checksums, audit trail |
| Leesbaarheid | 100% | CSV exports, PDF archief |
| Controleerbaarheid | 95% | Audit logs, transaction matching |
| Toegankelijkheid | 40% | Accountant role (lage prioriteit) |

**Implementatie:**
- `audit_logs` tabel (WHO/WHAT/WHEN immutable logging, geen updated_at)
- `AuditObserver` geregistreerd voor 6 modellen
- PDF integrity checks (`file_hash`, `verifyFileIntegrity()`)
- Automatic snapshots bij invoice creation
- `invoices:calculate-checksums` command

## URL Structuur

| URL | Functie |
|-----|---------|
| `/dashboard` | Main dashboard (6 grafieken) |
| `/verkoop` | Verkoop facturen CRUD |
| `/inkoop` | Inkoop facturen (PDF parsing) |
| `/inkoop/sync` | Map synchronisatie |
| `/klanten` | Customer management |
| `/leveranciers` | Supplier management |
| `/projecten` | Project management |
| `/urenregistratie` | Time entry CRUD |
| `/vaste-activa` | Asset management |
| `/journaalposten` | Handmatige boekingen |
| `/offertes` | Quote management |
| `/rapportages` | Tax export forms (CSV) |
| `/reconciliatie` | Duplicate detection |
| `/settings` | App settings + bankrekeningen |
| `/register` | Tenant registratie + Mollie checkout |

Oude URLs (`/invoices`, `/local-invoices`) redirect automatisch.

## Belangrijke Regels

| Onderwerp | Regel |
|-----------|-------|
| **Timezone** | Date-only fields MOETEN `immutable_date:Y-m-d` gebruiken (niet `date`) |
| **Bunq sync** | Originele +/- tekens behouden (geen `abs()`) |
| **Project filter** | Facturen: "Algemeen" verborgen. Kosten: "Diversen" verborgen |
| **Primary color** | Blue (#2563EB) |
| **SmartSearch** | MOET via Vite gebundeld worden (niet standalone) |
| **Deploy** | Altijd staging eerst, nooit alleen production |
| **Data** | ALTIJD backup voor data wijzigingen |
| **Grootboek 4750** | = Bankkosten (niet 4300, gebruiker kiest 4750) |
| **Crypto** | Is zakelijk (Arweave voor herdenkingsportaal blockchain) |
| **Production DB** | `havunadmin_production` (niet `havunadmin_tenant_havun`) |
| **Inkoop pagina** | Alleen inkomende facturen. Verkoop hoort op /verkoop |
| **BTW** | KOR actief, onder 20k omzet |

## Architectuur

| Onderdeel | Aantal | Opmerking |
|-----------|--------|-----------|
| Models | 32 | Incl. central (Tenant, CentralUser, TenantUser) |
| Controllers | 45 | Auth, WebAuthn, admin, API, sync |
| Services | 19 | AI, PDF parsing, banking, tax exports |
| Migrations | 76 | Multi-tenant, compliance, banking |
| Middleware | 5 | TenantMiddleware, SuperAdmin, Admin, Edit, Export |

**Custom Artisan Commands:**
- `havun:backup:run` / `havun:backup:health` - HavunCore backup
- `invoices:calculate-checksums` - SHA256 hashing
- `sync:mollie` / `sync:bunq` - Payment/transaction sync
- `central:migrate --seed` - Central database setup
- `tenant:create "Company"` / `tenant:list` - Tenant management

## PWA

| Omgeving | Naam | Kleur |
|----------|------|-------|
| Staging | "HA Staging" | Oranje (#f59e0b) |
| Production | "Havun Admin" | Paars (#4F46E5) |

## Server

```bash
# 1. Staging (altijd eerst)
ssh root@188.245.159.115 "cd /var/www/havunadmin/staging && git pull origin main && php artisan migrate --force && php artisan cache:clear"

# 2. Production (na goedkeuring / na staging)
ssh root@188.245.159.115 "cd /var/www/havunadmin/production && git stash && git pull origin main && php artisan migrate --force && php artisan cache:clear"
```

## Task Queue

Dit project kan taken ontvangen van HavunCore:
- Poller: `claude-task-poller@havunadmin.service`

## Versie Historie

| Versie | Datum | Highlights |
|--------|-------|-----------|
| v0.8.0 | 10 jan 2026 | Reconciliation, bank accounts |
| v0.7.0 | 27 nov 2025 | Theme toggle, auto-logout, quotes, production deploy |
| v0.6.0 | 22 nov 2025 | Compliance 81% -> 97%, audit trail, PDF checksums |
| v0.5.0 | 14 nov 2025 | Ledger accounts, journal entries |
| v0.4.0 | 9 nov 2025 | Security hardening, GitHub auto-deployment |
| v0.3.0 | 28 okt 2025 | Staging deployment |
| v0.2.0 | 27 okt 2025 | API integrations (Gmail, Mollie, Bunq) |
| v0.1.0 | 27 okt 2025 | MVP release |

## Bedrijfsgegevens (Havun)

- **KvK:** 98516000
- **BTW-ID:** NL002995910B70
- **Tax-nr:** 195200305B01
- **IBAN:** NL75BUNQ2167592531
- **BTW-Status:** Klein Ondernemersregeling (KOR), actief, onder 20k
- **Belasting 2025:** Omzet 10,10 / Kosten 1.550,41 / Verlies -1.540,31

## Openstaande Items

- [ ] Bestaande Anthropic-facturen handmatig naar USD corrigeren
- [ ] Grootboek toevoegen aan uitgaande facturen (vereist migratie)
- [ ] Crypto transacties boeken op 4900
- [ ] Accountant role (read-only access)
- [ ] Offsite backup (Hetzner Storage Box)
- [ ] XAF export (XML Audit File)

## Documentatie in Project

| Doc | Locatie |
|-----|---------|
| Project regels | `CLAUDE.md` |
| Project context | `.claude/context.md` |
| README | `README.md` (uitgebreid) |
| Project status | `docs/01-getting-started/PROJECT-STATUS.md` |
| Business info | `docs/01-getting-started/BUSINESS-INFO.md` |
| Database design | `docs/02-architecture/DATABASE-DESIGN.md` |
| Compliance | `docs/03-compliance/BELASTINGDIENST-COMPLIANCE.md` |
| Deployment | `docs/04-deployment/DEPLOYMENT-PROTOCOL.md` |
| API setup | `docs/05-api-integration/API-SETUP-GUIDE.md` |
| Bunq categorisatie | `docs/05-api-integration/BUNQ-CATEGORIZATION.md` |
| Local folder sync | `docs/05-api-integration/LOCAL-FOLDER-SYNC.md` |
| Multi-tenant | `docs/08-multi-tenant/README.md` |
| Changelog | `docs/09-project-info/CHANGELOG.md` |

---

*Laatste update: 14 maart 2026*
