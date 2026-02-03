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

## Server

```bash
# Deploy
cd /var/www/judotoernooi/laravel
git pull
composer install --no-dev
npm run build
php artisan migrate --force
php artisan config:clear && php artisan cache:clear
```

## Documentatie in Project

| Doc | Locatie |
|-----|---------|
| Project details | `.claude/context.md` |
| Features | `.claude/features.md` |
| Mollie | `.claude/mollie.md` |
| Deploy | `.claude/deploy.md` |
| Code standaarden | `laravel/docs/3-DEVELOPMENT/CODE-STANDAARDEN.md` |

---

*Laatste update: 3 februari 2026*
