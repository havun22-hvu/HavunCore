# Project: Bert van der Heide - Uitvaartondernemer Website

**Aangemaakt:** 2025-12-02
**Status:** Server setup COMPLEET - klaar voor development
**Type:** Laravel + Filament CMS website
**Staging URL:** https://bertvanderheide.havun.nl
**SSL:** Geldig tot 2026-03-01

---

## Project Overzicht

**Klant:** Bert van der Heide - Uitvaartondernemer
**Huidige site:** bertvanderheide.nl (WordPress - wordt vervangen)
**Doel:** Nieuwe SEO-geoptimaliseerde website met simpel CMS

---

## Technische Stack

| Component | Keuze |
|-----------|-------|
| Framework | Laravel 11 |
| Admin Panel | Filament 3 |
| Database | MySQL |
| PHP versie | 8.2+ |
| Frontend | Blade + Tailwind CSS |

---

## Gewenste Functionaliteit

### CMS Features (Filament Admin)
- [ ] Pagina's beheren (Home, Over ons, Contact, etc.)
- [ ] Diensten beheren (uitvaart types, mogelijkheden)
- [ ] Team leden beheren (foto, naam, functie)
- [ ] Blog / Nieuws artikelen
- [ ] Contactformulier inzendingen

### SEO Optimalisatie
- [ ] Schema.org FuneralHome markup
- [ ] Meta tags per pagina (title, description)
- [ ] Automatische sitemap.xml
- [ ] Open Graph tags voor social media
- [ ] Canonical URLs

### Overige
- [ ] Contactformulier met email notificaties
- [ ] Responsive design (mobile-first)
- [ ] SSL certificaat
- [ ] Cookie consent (AVG)

---

## Server Setup (Besloten)

### Staging Omgeving
```
URL:      https://bertvanderheide.havun.nl
Server:   188.245.159.115
Path:     /var/www/bertvanderheide/staging
Database: bertvanderheide_staging
PHP:      8.2+
SSL:      Let's Encrypt
```

### Productie (na DNS overname)
```
URL:      https://www.bertvanderheide.nl
Path:     /var/www/bertvanderheide/production
Database: bertvanderheide_production
SSL:      Let's Encrypt
```

### Git Repo op Server
```
Bare repo: /var/www/bertvanderheide/repo.git
Remote:    git remote add staging root@188.245.159.115:/var/www/bertvanderheide/repo.git
Push:      git push staging main
```

---

## Initialisatie Commands

```bash
# Project aanmaken
composer create-project laravel/laravel bertvanderheide
cd bertvanderheide

# Filament installeren
composer require filament/filament:"^3.2"
php artisan filament:install --panels

# Admin user aanmaken
php artisan make:filament-user
```

---

## Relatie met HavunCore

### Mogelijke integraties
- **Backup System** - Kan toegevoegd worden aan centrale backups
- **Vault** - API keys en credentials centraal beheren
- **Task Queue** - Remote development via pollers

### Standalone vs Integrated
Dit project kan:
1. **Standalone** draaien (geen HavunCore dependency)
2. **Integrated** met `composer require havun/core` voor shared services

**Aanbeveling:** Start standalone, integreer later indien nodig.

---

## Content Referenties

### Huidige WordPress Site
- URL: https://bertvanderheide.nl
- Status: Publiek toegankelijk
- Admin toegang: Nog niet beschikbaar

### Te migreren content
- Homepage teksten
- Diensten beschrijvingen
- Team informatie
- Bestaande afbeeldingen

---

## Vragen voor Opdrachtgever

1. Welke pagina's moeten er komen?
2. Zijn er bestaande foto's in hoge resolutie?
3. Moet blog/nieuws sectie erin?
4. Speciale wensen qua kleurenschema?
5. Formulier: welke velden nodig?

---

## Server Keuze

**Gekozen: Hetzner VPS (bestaand)**
```
Server: 188.245.159.115
User: root
SSH: Key authentication
```

Voordelen:
- Centrale beheer samen met andere Havun projecten
- Backup systeem direct beschikbaar
- SSL via Let's Encrypt
- PHP 8.2+ en Composer/npm al aanwezig

---

## Volgende Stappen

1. [ ] Wacht op akkoord offerte
2. [ ] Bepaal server/hosting keuze
3. [ ] Staging omgeving opzetten
4. [ ] Laravel + Filament initialiseren
5. [ ] Basis CMS structuur bouwen
6. [ ] Content migreren van WordPress
7. [ ] SEO optimalisatie
8. [ ] Testing & QA
9. [ ] Go-live naar productie

---

## Communicatie

**Vanuit BertvanderHeide Claude:**
- Vragen over server setup
- Hulp bij Filament configuratie
- SEO best practices

**Vanuit HavunCore Claude:**
- Server toegang regelen
- Backup integratie (optioneel)
- Vault credentials (optioneel)

---

*Laatst bijgewerkt: 2025-12-02*
