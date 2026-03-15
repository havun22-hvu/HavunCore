# Project: JudoToernooi

**URL:** https://judotournament.org
**Type:** Laravel 11 SaaS multi-tenant toernooi management
**Eigenaar:** Havun (henkvu@gmail.com = sitebeheerder)
**GitHub:** https://github.com/havun22-hvu/judotoernooi
**Status:** Production (live)

## Bedrijfsmodel (SaaS)

Havun verhuurt de JudoToernooi software aan judoscholen (organisatoren).

| Rol | Beschrijving |
|-----|--------------|
| **Sitebeheerder** | Havun admin, ziet alle organisatoren en toernooien |
| **Organisator** | Klant (judoschool), beheert eigen toernooien |
| **Coach** | Beheerder van een club, toegang via Coach Portal |

### Toernooi Lifecycle

```
Nieuw -> Voorbereiding -> Wedstrijddag -> Afgesloten
          ^                               |
          +-- Templates hergebruiken -----+
```

## Omgevingen

| Omgeving | URL | Pad | Database |
|----------|-----|-----|----------|
| Local | localhost:8007 | `D:\GitHub\JudoToernooi\laravel` | SQLite |
| Staging | (geen domein) | `/var/www/staging.judotoernooi/laravel` | MySQL |
| Production | judotournament.org | `/var/www/judotoernooi/laravel` | MySQL |

## Tech Stack

| Component | Versie |
|-----------|--------|
| Laravel | 11.x (^11.0) |
| PHP | ^8.2 |
| Alpine.js | 3.14.3 |
| Tailwind CSS | 3.4.14 |
| Vite | 5.4 |
| Reverb | ^1.7 (WebSockets) |
| Sanctum | ^4.0 (API auth) |
| WebAuthn | laragear/webauthn (passkeys) |
| Mollie SDK | mollie-api-php 2.0 |
| Stripe SDK | stripe-php 19.4 |
| Excel | maatwebsite/excel (CSV/Excel import) |
| DOMPDF | barryvdh/laravel-dompdf (PDF generatie) |
| QR Code | simplesoftwareio/simple-qrcode 4.2 |

## Freemium Model

| Tier | Eigen Import | Handmatig | Totaal Max | Clubs Max | Presets Max | Demo CSV |
|------|-------------|-----------|------------|-----------|-------------|----------|
| **Free** | Max 20 | Max 20 | Max 50 | Max 2 | Max 1 | Ja (30/40/50) |
| **Betaald** | Onbeperkt | Onbeperkt | Onbeperkt | Onbeperkt | Onbeperkt | N.v.t. |

**Staffels (betaald):**

| Staffel | Prijs | Max judoka's |
|---------|-------|-------------|
| Klein | ~20 | 100 |
| Medium | ~30 | 150 |
| Groot | ~40 | 200+ |

- **Wimpel abonnement:** Jaarabonnement ~50 voor doorlopende puntencompetitie
- Demo CSV's: `storage/app/demo/demo-30/40/50.csv`
- `is_demo` veld op judokas tabel
- `is_test = true` op organisator bypasses betalingen
- DB velden: `plan_type` (free|paid|wimpel_abo), `paid_tier`, `paid_max_judokas`, `paid_at`
- Docs: `laravel/docs/2-FEATURES/FREEMIUM.md`

## Core Features

- **Toernooi Management** - Aanmaken, configureren, templates hergebruiken
- **Deelnemers Import** - CSV/Excel met auto-classificatie + "Uit database" stam-import
- **Poule Indeling** - Automatisch algoritme met clubspreiding
- **Mat Interface** - Wedstrijden en uitslagen (real-time via Reverb, geen polling)
- **Eliminatie** - Double elimination met B-bracket, aparte B-mat
- **Coach Portal** - Coaches beheren hun judoka's (3 modi: volledig/mutaties/bekijken)
- **Real-time Chat** - Multi-kanaal via Reverb WebSockets, toast notificaties
- **Danpunten (JBN)** - JBN lidnummers, CSV export, toggle per toernooi
- **Wimpel Toernooi** - Doorlopende puntencompetitie per organisator, milestones
- **Offline Server Pakket** - Go launcher + portable PHP, download via noodplan pagina
- **Unified Login** - PIN, QR code, passkey authenticatie
- **Weging Interface** - Aanwezigheid + gewicht registratie, suspicious weight warnings
- **Judoka Database** - Stambestand per organisator, hergebruik tussen toernooien
- **Juridische Pagina's** - Voorwaarden, privacy, cookies, disclaimer

## Betalingen (Mollie + Stripe)

Dual provider, dual mode systeem:

| Provider | Dekking | Toeslag (platform) |
|----------|---------|-------------------|
| **Mollie** (standaard) | Europa (iDEAL) | +0,50 |
| **Stripe** | Wereldwijd (creditcard) | +0,50 |

| Modus | Geld naar | Toeslag |
|-------|-----------|---------|
| **Connect** | Organisator's eigen account | Geen |
| **Platform** | JudoToernooi's account | +0,50 |

**Key files:**
- `app/Contracts/PaymentProviderInterface.php` - Interface
- `app/DTOs/PaymentResult.php` - Genormaliseerd resultaat
- `app/Services/PaymentProviderFactory.php` - Factory
- `app/Services/Payments/MolliePaymentProvider.php` - Mollie wrapper
- `app/Services/Payments/StripePaymentProvider.php` - Stripe implementatie
- `app/Services/MollieService.php` - Bestaande Mollie service
- **Stripe Connect:** Account Links onboarding (NIET legacy OAuth)
- **Docs:** `laravel/docs/2-FEATURES/BETALINGEN.md`

## Coach Portal

Coaches kunnen via het portal hun judoka's beheren. Configureerbaar per toernooi:

| Modus | Nieuwe judoka's | Wijzigen | Sync | Verwijderen |
|-------|-----------------|----------|------|-------------|
| `volledig` | Ja | Ja | Ja | Ja |
| `mutaties` | Nee | Ja | Ja | Nee |
| `bekijken` | Nee | Nee | Nee | Nee |

- **Deadline:** Na `inschrijving_deadline` is ALLES geblokkeerd
- **Sync:** Judoka moet volledig zijn (naam, geboortejaar, geslacht, band, gewicht) EN passen in categorie
- **Betaling:** `betaling_actief = true` -> sync via Mollie webhook na succesvolle betaling
- **Free tier:** Max 20 judoka's per coach

## Interfaces & PWA Rollen

| Rol | Interface | Auth | Navigatie |
|-----|-----------|------|-----------|
| Superadmin | Layout.app | Wachtwoord/PIN | Volledig |
| Organisator | Layout.app | Email+wachtwoord | Volledig + financieel |
| Beheerder | Layout.app | Email+wachtwoord | Volledig (geen financieel) |
| Hoofdjury | Layout.app | Email+wachtwoord | Volledig (geen financieel) |
| Weging | Standalone PWA | URL+PIN+device | Geen |
| Mat | Standalone PWA | URL+PIN+device | Geen |
| Spreker | Standalone PWA | URL+PIN+device | Geen |
| Dojo | Standalone PWA | URL+PIN+device | Geen |

## Wimpel Toernooi (Puntencompetitie)

- Doorlopend puntensysteem per organisator
- Judoka herkenning op naam + geboortejaar
- Automatische puntentelling per poule
- Milestones (10, 20, 30, 40, 50 punten)
- Spreker integratie voor uitreikingen
- Export + backup

## Eliminatie (Double Elimination)

- Wiskundige SAMEN/DUBBEL berekening
- B-groep herkansing
- Min. 8 judoka's per eliminatie poule
- Vaste gewichtsklassen verplicht
- Slot nummering + bypass regels
- IJF B-1/4 finale nog NIET geimplementeerd (alleen B-1/2 + Brons)
- Docs: `laravel/docs/2-FEATURES/ELIMINATIE/`

## Chat (Reverb WebSockets)

- Real-time multi-kanaal: `chat.{toernooi_id}.{rol}.{device_id}`
- Toggle "free chat" door hoofdjury
- Toast notificaties + badge counts
- Per-device routing
- Config: `config/reverb.php` (host 0.0.0.0:8080, app ID: judotoernooi)

## Data Model

Per organisator blijft bewaard tussen toernooien:
- **Clubs** - Deelnemende judoscholen (fuzzy name matching)
- **Templates** - Toernooi configuraties
- **Presets** - Gewichtsklassen presets
- **StamJudoka** - Judoka stambestand
- **Toernooien** - Historisch overzicht
- **Vrijwilligers** - Saved volunteers

## Belangrijke Regels

| Onderwerp | Regel |
|-----------|-------|
| **Band** | Alleen kleur opslaan (wit, geel, oranje, groen, blauw, bruin, zwart) - GEEN kyu |
| **Gewichtsklasse** | NIET invullen bij variabele gewichten |
| **Bug fixes** | Max 2 pogingen, daarna verslag aan gebruiker |
| **Test data** | NOOIT data "goed zetten" om bugs te maskeren - fix de CODE |
| **Sessie start** | EERST server AutoFix wijzigingen committen+pushen, dan lokaal pullen |

## Email (Brevo)

- **Provider:** Brevo (voorheen SendGrid, proefperiode verlopen)
- **SMTP:** `smtp-relay.brevo.com:587`
- **From:** noreply@judotournament.org
- **Credentials:** Brevo SMTP key in `.env` op server

Zie [External Services](../reference/external-services.md) voor Brevo dashboard toegang.

## SEO & Analytics

- **Google Analytics:** GA4 property `G-42KGYDWS5J` (production only, via `<x-seo />` component)
- **Google Search Console:** DNS TXT verificatie, sitemap ingediend
- **Bing Webmaster Tools:** Geimporteerd vanuit Google Search Console, sitemap ingediend
- **Structured Data:** Organization, WebSite, SoftwareApplication, FAQPage, SportsEvent, BreadcrumbList
- **Nginx:** Gzip compression, static cache headers (30d assets, 1h XML/JSON)
- **Dynamic sitemap:** via `SitemapController`

Zie `laravel/docs/4-SEO/SEO.md` voor volledige SEO documentatie.

## Juridische Pagina's

| Pagina | Route | View |
|--------|-------|------|
| Voorwaarden | `/algemene-voorwaarden` | `legal/terms.blade.php` |
| Privacy | `/privacyverklaring` | `legal/privacy.blade.php` |
| Cookies | `/cookiebeleid` | `legal/cookies.blade.php` |
| Disclaimer | `/disclaimer` | `legal/disclaimer.blade.php` |

**Disclaimer kritiek:** Havun NIET aansprakelijk voor internet/server outages. Organisatoren moeten noodplan + lokale server gebruiken.

## Architectuur

| Onderdeel | Aantal | Opmerking |
|-----------|--------|-----------|
| Models | 37 | Incl. Wimpel, sync queue, coach, devices |
| Controllers | 39 | Fat controllers zijn tech debt |
| Services | 25 | BlokVerdeling helpers zijn voorbeeldig |
| Migrations | 151 | Multi-tenant, offline sync, payments |
| Middleware | 8 | Role, device, freemium, security, locale, offline, local sync |
| Enums | 4 | Band, Geslacht, Leeftijdsklasse, AanwezigheidsStatus |
| Tests | 17 | PHPUnit 11 |

**Patterns:**
- Service layer voor business logic
- Model traits (HasMolliePayments, HasPortaalModus, HasCategorieBepaling)
- Result Object pattern, Circuit Breaker
- Custom exception hierarchie (JudoToernooiException)
- BlokVerdeling subfolder met SOLID helpers (voorbeeldig)
- PaymentProviderInterface + Factory pattern voor Mollie/Stripe

## AutoFix (Automatische Error Fixing)

Claude AI analyseert production errors automatisch en past fixes direct toe (max 2 pogingen).

- **Config:** `config/autofix.php`
- **Service:** `app/Services/AutoFixService.php`
- **Model:** `app/Models/AutofixProposal.php` (tabel: `autofix_proposals`)
- **Email:** havun22@gmail.com (success + failure notificaties)
- **API:** HavunCore AI Proxy (tenant: `judotoernooi`)
- **Admin:** `/admin/autofix` - overzicht met stats, proposals, user/toernooi context
- **Review:** `/autofix/{token}` web UI
- **Context:** Slaat organisator naam/id en toernooi naam/id op bij elke proposal
- **Rate limit:** 60 min per uniek error
- **Excluded:** EADDRINUSE, ECONNREFUSED, disk full, validation, auth, 404

Zie [AutoFix Reference](../reference/autofix.md) voor volledige documentatie.

## Server

```bash
# Deploy
cd /var/www/judotoernooi/laravel
git pull origin main
composer install --no-dev
npm run build
php artisan migrate --force
php artisan config:clear && php artisan cache:clear
```

## Code Review (14 feb 2026)

**Scores:** Models 8.5/10 | Controllers B+ | Services B+ | Security B+

**Sterke punten:**
- BlokVerdeling helpers: SOLID, pure functions, goed testbaar
- Exception hierarchie: user/technical message scheiding
- Security headers: CSP, HSTS, permissions policy
- Multi-layer auth: organisator + role + device binding
- FreemiumService & ActivityLogger: clean en focused

**Bekende tech debt:**
- Fat controllers: BlokController (1315 LOC), PouleController (1192), MatController (1161)
- N+1 query risks in Toernooi, Poule, Club, Mat models
- Missing DB transactions bij judoka verplaatsingen (WedstrijddagController)
- Inconsistente naming (NL/EN mix in methods)
- Club pincodes plaintext, CoachKaart pincode 4 cijfers

**Security aandachtspunten:**
- Local sync API (`api.php`) heeft geen auth -> data leak risico
- Coach PIN login geen rate limiting -> brute force risico
- `/health/detailed` onbeschermd -> info disclosure
- Hardcoded admin password defaults in `config/toernooi.php`
- CSP met `unsafe-inline` en `unsafe-eval`

## Documentatie in Project

| Doc | Locatie |
|-----|---------|
| Project details | `.claude/context.md` |
| Features | `.claude/features.md` |
| Mollie | `.claude/mollie.md` |
| Deploy | `.claude/deploy.md` |
| Handover | `.claude/handover.md` |
| Code standaarden | `laravel/docs/3-DEVELOPMENT/CODE-STANDAARDEN.md` |
| Stability/errors | `laravel/docs/3-DEVELOPMENT/STABILITY.md` |
| Classificatie | `laravel/docs/2-FEATURES/CLASSIFICATIE.md` |
| Betalingen | `laravel/docs/2-FEATURES/BETALINGEN.md` |
| Freemium | `laravel/docs/2-FEATURES/FREEMIUM.md` |
| Eliminatie | `laravel/docs/2-FEATURES/ELIMINATIE/` |
| Wimpeltoernooi | `laravel/docs/2-FEATURES/WIMPELTOERNOOI.md` |
| Danpunten | `laravel/docs/2-FEATURES/DANPUNTEN.md` |
| Chat | `laravel/docs/2-FEATURES/CHAT.md` |
| Interfaces | `laravel/docs/2-FEATURES/INTERFACES.md` |
| Import | `laravel/docs/2-FEATURES/IMPORT.md` |
| Noodplan | `laravel/docs/2-FEATURES/NOODPLAN-HANDLEIDING.md` |
| Judoka Database | `laravel/docs/2-FEATURES/JUDOKA-DATABASE.md` |

---

*Laatste update: 14 maart 2026*
