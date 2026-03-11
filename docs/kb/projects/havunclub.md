# Project: HavunClub

**URL:** https://havunclub.havun.nl
**Type:** Laravel 11 multi-club SaaS ledenadministratie
**Status:** In development (opgezet feb 2026)

## Wat is het?

Multi-club SaaS platform voor ledenadministratie, primair gericht op judo/sportverenigingen. Clubs beheren leden, gezinnen, abonnementen, bandexamens en aanwezigheid.

## Omgevingen

| Omgeving | URL | Pad |
|----------|-----|-----|
| Local | localhost:8009 | `D:\GitHub\HavunClub` |
| Production | havunclub.havun.nl | `/var/www/havunclub/production` |

## Geplande Features

- **Ledenadministratie** - Leden, gezinnen, contactgegevens
- **Abonnementen** - Mollie recurring payments
- **Bandexamens** - Registratie en tracking
- **QR Check-in** - Aanwezigheidsregistratie
- **Multi-club** - Elke club eigen omgeving

## Tech Stack

**Framework:** Laravel 11
**Frontend:** Blade + Tailwind + Alpine.js
**Database:** MySQL
**Payments:** Mollie (recurring)

## Relatie met JudoToernooi

HavunClub is complementair aan JudoToernooi:
- JudoToernooi = toernooi management (events)
- HavunClub = club/ledenadministratie (dagelijks)

## Server

```bash
# Deploy
cd /var/www/havunclub/production
git pull origin main
composer install --no-dev
php artisan migrate --force
php artisan optimize:clear
```

---

*Laatste update: 11 maart 2026*
