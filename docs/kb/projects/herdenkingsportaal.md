---
title: Project: Herdenkingsportaal
type: reference
scope: havuncore
last_check: 2026-04-22
---

# Project: Herdenkingsportaal

**URL:** https://herdenkingsportaal.nl
**Type:** Laravel 12 memorial portal met blockchain (Arweave)
**Versie:** 3.3.1

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
| **Betaald** | Gebruiker met basis/standaard/compleet pakket |
| **Registered** | Gratis account, 7 dagen trial |
| **Guest** | Niet ingelogd, 10 min sessie |

**Access levels:** guest (10 min) → registered (7 dagen gratis) → betaald pakket

## Auth (HP-specifiek — afwijking van Havun-portfolio)

- **Guard:** `web` (session-based, default Laravel)
- **Provider:** `eloquent-webauthn` (met wachtwoord-fallback)
- **Login-methoden op /login (3.3.x):**
  - Wachtwoord + email (primair, voor de HP-doelgroep — oudere users)
  - Magic-link knop "Stuur me een login-link" (alternatief, type=`'login'`)
  - Biometrie-knop "Inloggen met vingerafdruk" (alleen smartphone `min(width,height)<550`, expliciet klikken)
- **NIET op /login:** QR-modal, silent biometric op page-load, login-methode-voorkeur, provider-display
- **Passkey-management op /profile:** delegated click listener op `[data-action="delete-passkey"]` (CSP-veilig, sinds 3.3.1 — 30-04-2026)
- **2FA TOTP:** alleen voor admins (henkvu@gmail.com); UI verborgen voor reguliere users
- **Admin middleware:** `['auth', 'admin', 'enforce.2fa', 'production.redirect']`
- **Backend blijft staan:** QR-routes (`/auth/qr/*`), passkey-management (`/profile`), magic-link routes — niet bereikbaar vanaf /login UI maar wel functioneel
- **Bewuste afwijking:** Havun-standaard (`reference/authentication-methods.md`) zegt "wachtwoord NIET MEER GEBRUIKEN" voor alle projecten. HP houdt wachtwoord-primair vanwege doelgroep (memoriale herdenking voor familie/oudere bezoekers waar magic-link drempel te hoog is). Heroverwegen bij grote redesign.
- **Verwijderd 27-04-2026:** Google OAuth (incident "deleted_client" Google Cloud)
- **Verwijderd 29-04-2026 (3.3.0):** QR-modal op /login, silent biometric op page-load, csrfFetch-helper, login-methode-voorkeur, provider-display per passkey
- **Drift:** PIN login routes bestaan nog maar zijn niet meer aangesloten op de UI; DB-kolom `users.preferred_login_method` ongebruikt sinds 3.3.0 (cleanup gepland)

## Core Features

- **Memorial Editor** - Monument templates, foto's, verhalen, condoleances
- **Blockchain** - Arweave permanente opslag (Node.js bridge)
- **Betalingen** - Mollie (iDEAL, creditcard), Tikkie, Stripe (gepland)
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

## Email (Brevo SMTP)

- **Provider:** Brevo (gratis 300/dag)
- **Migratie:** SendGrid → Resend (jan 2026) → Brevo (maart 2026)
- **From:** noreply@herdenkingsportaal.nl
- **Domein:** herdenkingsportaal.nl (geverifieerd, DKIM+SPF+DMARC)

## Betalingen

| Provider | Methoden |
|----------|---------|
| Mollie | iDEAL, creditcard, banktransfer, PayPal |
| Tikkie | Handmatig/API |
| Stripe | *Gepland* |

**Pakketten:** basis (€9,95), standaard (€24,95), compleet (€49,95)

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
- **Blade detection:** `extractBladeFile()` voor ViewException messages
- **Let op:** `excluded_message_patterns` nog NIET geïmplementeerd (wel in JudoToernooi)

Zie [AutoFix Reference](../reference/autofix.md) voor volledige documentatie.

## Architectuur

| Onderdeel | Aantal |
|-----------|--------|
| Models | 28 |
| Controllers | 21 |
| Services | 37 |
| Migrations | 90 |

**Stack:** Laravel 12, Tailwind CSS, Alpine.js (`@alpinejs/csp` build), Vite, WebAuthn (Laragear), Arweave (Node.js bridge)

**Patterns:**
- Service layer (PaymentServiceFactory, ConfigurationService)
- WebAuthn biometric auth met password fallback
- Multi-provider payments (factory pattern)
- €0 test user payment bypass (Mollie overgeslagen, direct betaald)
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

*Laatste update: 30 april 2026 (login-vereenvoudiging 3.3.0, Laravel 12, Brevo email)*
