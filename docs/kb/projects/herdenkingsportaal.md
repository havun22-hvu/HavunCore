# Project: Herdenkingsportaal

**URL:** https://herdenkingsportaal.nl
**Type:** Laravel 11 memorial portal met blockchain (Arweave)
**Versie:** 3.0.74

## Wat is het?

Online platform waar gebruikers digitale herdenkingspagina's (memorials) aanmaken voor overleden dierbaren. Memorials kunnen op de blockchain (Arweave) worden opgeslagen voor permanente bewaring.

## Omgevingen

| Omgeving | URL | Pad | Database |
|----------|-----|-----|----------|
| Local | localhost:8002 | `D:\GitHub\Herdenkingsportaal` | SQLite |
| Production | herdenkingsportaal.nl | `/var/www/herdenkingsportaal/production` | MySQL |

## Gebruikers & Rollen

| Rol | Beschrijving |
|-----|--------------|
| **Admin** | Havun, beheert templates, symbolen, betalingen, blockchain |
| **Premium** | Betaalde gebruiker, volledige memorial functies |
| **Registered** | Gratis account, 7 dagen trial |
| **Guest** | Niet ingelogd, 10 min sessie |

**Access levels:** guest (10 min) → registered (7 dagen gratis) → premium
**Pricing:** €19.99 (1e memorial), €14.99 (2e), €9.99 (3e+)

## Auth

- **Guard:** `web` (session-based, default Laravel)
- **Provider:** `eloquent-webauthn` (met password fallback)
- **Features:** WebAuthn/Passkey, OAuth (Google/Apple), 2FA, PIN login
- **Admin middleware:** `['auth', 'admin', 'enforce.2fa', 'production.redirect']`

## Core Features

- **Memorial Editor** - Monument templates, foto's, verhalen, condoleances
- **Blockchain** - Arweave permanente opslag (Node.js bridge)
- **Betalingen** - Mollie (iDEAL, creditcard), Tikkie, XRP, EPC QR
- **AI Chatbot** - Claude-powered hulp voor gebruikers
- **Export** - PDF export van memorials
- **Reviews** - Gebruikersbeoordelingen met moderatie

## Memorial Lifecycle

| Status type | Waarden |
|------------|---------|
| lifecycle_status | draft, active, published, expired |
| payment_status | unpaid, paid |
| publication_status | draft, published |
| privacy_level | public, link_only, private |

## Email (Resend)

- **Provider:** Resend (gratis 3000/maand)
- **Migratie:** SendGrid → Resend (jan 2026, SendGrid trial verlopen)
- **From:** noreply@herdenkingsportaal.nl

## Betalingen

| Provider | Methoden |
|----------|---------|
| Mollie | iDEAL, creditcard, banktransfer, PayPal |
| Tikkie | Handmatig/API |
| XRP | Crypto |
| EPC QR | Bankoverschrijving met QR code |

**Packages:** premium_upgrade (€4.99), memorial_website (€24.95), memorial_monument (€9.95-19.95)

## Blockchain (Arweave)

- **Network toggle:** testnet/mainnet (admin panel)
- **Node.js bridge:** voor transacties (niet PHP native)
- **Wallet:** mainnet ~5.79 AR saldo
- **Task Queue:** Poller via HavunCore (`claude-task-poller@herdenkingsportaal.service`)

## AutoFix (Automatische Error Fixing)

Claude AI analyseert production errors automatisch en past fixes direct toe (max 2 pogingen).

- **Config:** `config/autofix.php`
- **Service:** `app/Services/AutoFixService.php` (tenant: `herdenkingsportaal`)
- **Model:** `app/Models/AutofixProposal.php`
- **Email:** havun22@gmail.com (success + failure notificaties)
- **Admin:** `/admin/autofix` - overzicht met stats, proposals, user/memorial context
- **Context:** Slaat user naam/email en memorial naam op bij elke proposal

Zie [AutoFix Reference](../reference/autofix.md) voor volledige documentatie.

## Architectuur

| Onderdeel | Aantal |
|-----------|--------|
| Models | 28 |
| Controllers | 21 |
| Services | 37 |
| Migrations | 90 |

**Stack:** Laravel 11, Tailwind CSS, Alpine.js, Vite, WebAuthn, Arweave (Node.js bridge)

**Patterns:**
- Service layer (PaymentServiceFactory, ConfigurationService)
- WebAuthn biometric auth met password fallback
- Multi-provider payments (factory pattern)
- Arweave Node.js bridge voor blockchain

## Server

```bash
# Deploy
cd /var/www/herdenkingsportaal/production
git pull origin main
php artisan migrate --force
php artisan optimize:clear
```

## Documentatie in Project

| Doc | Locatie |
|-----|---------|
| Project context | `.claude/context.md` |
| Features | `.claude/features.md` |

---

*Laatste update: 19 februari 2026*
