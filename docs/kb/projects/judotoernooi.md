# Project: JudoToernooi

**URL:** https://judotournament.org
**Type:** Laravel 11 SaaS multi-tenant toernooi management
**Eigenaar:** Havun (henkvu@gmail.com = sitebeheerder)

## Bedrijfsmodel (SaaS)

Havun verhuurt de JudoToernooi software aan judoscholen (organisatoren).

| Rol | Beschrijving |
|-----|--------------|
| **Sitebeheerder** | Havun admin, ziet alle organisatoren en toernooien |
| **Organisator** | Klant (judoschool), beheert eigen toernooien |
| **Coach** | Beheerder van een club, toegang via Coach Portal |

## Omgevingen

| Omgeving | URL | Pad | Database |
|----------|-----|-----|----------|
| Local | localhost:8007 | `D:\GitHub\JudoToernooi\laravel` | SQLite |
| Staging | - | `/var/www/staging.judotoernooi/laravel` | MySQL |
| Production | judotournament.org | `/var/www/judotoernooi/laravel` | MySQL |

## Core Features

- **Toernooi Management** - Aanmaken, configureren, templates hergebruiken
- **Deelnemers Import** - CSV/Excel met auto-classificatie
- **Poule Indeling** - Automatisch algoritme
- **Mat Interface** - Wedstrijden en uitslagen
- **Eliminatie** - Double elimination
- **Coach Portal** - Coaches beheren hun judoka's
- **Real-time Sync** - Reverb WebSockets voor chat en score updates

## Coach Portal

Coaches kunnen via het portal hun judoka's beheren. Configureerbaar per toernooi:

| Modus | Nieuwe judoka's | Wijzigen | Sync | Verwijderen |
|-------|-----------------|----------|------|-------------|
| `volledig` | Ja | Ja | Ja | Ja |
| `mutaties` | Nee | Ja | Ja | Nee |
| `bekijken` | Nee | Nee | Nee | Nee |

**Deadline:** Na `inschrijving_deadline` is ALLES geblokkeerd.

## Mollie Betalingen

Hybride systeem met twee modes:

| Modus | Geld naar | Toeslag |
|-------|-----------|---------|
| **Connect** | Organisator's eigen Mollie account | Geen |
| **Platform** | JudoToernooi's Mollie (split payment) | +€0,50 |

**Key files:**
- `app/Services/MollieService.php` - Hybride service
- `app/Models/Toernooi.php` - Helper methods (`usesConnect()`, `mollieApiKey()`)

## Data Model

Per organisator blijft bewaard tussen toernooien:
- **Clubs** - Deelnemende judoscholen (fuzzy name matching)
- **Templates** - Toernooi configuraties
- **Presets** - Gewichtsklassen presets
- **Toernooien** - Historisch overzicht

## Belangrijke Regels

| Onderwerp | Regel |
|-----------|-------|
| **Band** | Alleen kleur opslaan (wit, geel, oranje) - GEEN kyu |
| **Gewichtsklasse** | NIET invullen bij variabele gewichten |
| **Bug fixes** | Max 2 pogingen, daarna verslag aan gebruiker |

## Email (Brevo)

Email voor AutoFix failure notifications en toekomstige transactionele emails.

- **Provider:** Brevo (voorheen SendGrid, proefperiode verlopen)
- **SMTP:** `smtp-relay.brevo.com:587`
- **From:** noreply@judotournament.org
- **Credentials:** Brevo SMTP key in `.env` op server

Zie [External Services](../reference/external-services.md) voor Brevo dashboard toegang.

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

## Architectuur

| Onderdeel | Aantal | Opmerking |
|-----------|--------|-----------|
| Models | 30 | Incl. Wimpel systeem, sync queue |
| Controllers | 34 | Fat controllers zijn tech debt |
| Services | 22 | BlokVerdeling helpers zijn voorbeeldig |
| Migrations | 127 | Multi-tenant, offline sync |
| Middleware | 7 | Role, device, freemium, security, locale, offline |
| Enums | 4 | Band, Geslacht, Leeftijdsklasse, AanwezigheidsStatus |

**Stack:** Laravel 11, Alpine.js 3.14, Tailwind CSS 3.4, Vite 5.4, Reverb WebSockets

**Patterns:**
- Service layer voor business logic
- Model traits (HasMolliePayments, HasPortaalModus, HasCategorieBepaling)
- Result Object pattern, Circuit Breaker
- Custom exception hiërarchie (JudoToernooiException)
- BlokVerdeling subfolder met SOLID helpers (voorbeeldig)

## AutoFix (Automatische Error Fixing)

Claude AI analyseert production errors automatisch en past fixes direct toe (max 2 pogingen). Pas als beide mislukken krijgt de admin een email.

- **Config:** `config/autofix.php`
- **Service:** `app/Services/AutoFixService.php`
- **Model:** `app/Models/AutofixProposal.php` (tabel: `autofix_proposals`)
- **Email:** havun22@gmail.com (alleen bij falen)
- **API:** HavunCore AI Proxy (tenant: `judotoernooi`)
- **Review:** `/autofix/{token}` web UI

Zie [AutoFix Reference](../reference/autofix.md) voor volledige documentatie.

## Code Review (14 feb 2026)

**Scores:** Models 8.5/10 | Controllers B+ | Services B+ | Security B+

**Sterke punten:**
- BlokVerdeling helpers: SOLID, pure functions, goed testbaar
- Exception hiërarchie: user/technical message scheiding
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
- Local sync API (`api.php`) heeft geen auth → data leak risico
- Coach PIN login geen rate limiting → brute force risico
- `/health/detailed` onbeschermd → info disclosure
- Hardcoded admin password defaults in `config/toernooi.php`
- CSP met `unsafe-inline` en `unsafe-eval`

## Documentatie in Project

| Doc | Locatie |
|-----|---------|
| Project details | `.claude/context.md` |
| Features | `.claude/features.md` |
| Mollie | `.claude/mollie.md` |
| Deploy | `.claude/deploy.md` |
| Code standaarden | `laravel/docs/3-DEVELOPMENT/CODE-STANDAARDEN.md` |

---

*Laatste update: 18 februari 2026*
