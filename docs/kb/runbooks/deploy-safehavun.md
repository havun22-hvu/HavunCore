---
title: Runbook: SafeHavun Deployment
type: runbook
scope: safehavun
last_check: 2026-05-22
---

# Runbook: SafeHavun Deployment

> **BELANGRIJK:** Altijd deployen via GitHub (git push → git pull).
> **NOOIT** rsync, scp of directe file transfers gebruiken!

## Server info

- **IP:** 188.245.159.115
- **Pad:** `/var/www/safehavun/production`
- **URL:** https://safehavun.havun.nl

## Pre-requisites

- SSH toegang tot server (credentials in `.claude/context.md`)
- GitHub repo toegang

---

## Updates Deployen (standaard workflow)

**Workflow:** Lokaal → GitHub → Server

```bash
# 1. LOKAAL — tests draaien voor deploy
php artisan test --no-coverage

# 2. LOKAAL — commit + push
git add <bestanden> && git commit -m "beschrijving" && git push

# 3. SERVER
ssh root@188.245.159.115
cd /var/www/safehavun/production
git pull
php artisan config:cache && php artisan view:cache && php artisan cache:clear
```

> Composer install is alleen nodig bij nieuwe/gewijzigde dependencies.
> Migrations alleen bij schema-wijzigingen (altijd `--force` op production).

---

## 1. Server Setup (eenmalig, al gedaan)

```bash
ssh root@188.245.159.115

mkdir -p /var/www/safehavun/production

mysql -u root -p
CREATE DATABASE safehavun CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'safehavun'@'localhost' IDENTIFIED BY 'GENEREER_VEILIG_WACHTWOORD';
GRANT ALL PRIVILEGES ON safehavun.* TO 'safehavun'@'localhost';
FLUSH PRIVILEGES;
```

## 2. Clone & Install (eenmalig, al gedaan)

```bash
cd /var/www/safehavun/production
git clone https://github.com/havun22-hvu/SafeHavun .
composer install --no-dev --optimize-autoloader
cp .env.example .env
# Configureer .env met credentials uit .claude/context.md
php artisan key:generate
php artisan migrate
php artisan db:seed-default-assets
```

## 3. Permissions (eenmalig)

```bash
chown -R www-data:www-data /var/www/safehavun/production
chmod -R 755 /var/www/safehavun/production
chmod -R 775 storage bootstrap/cache
```

## 4. Cron (actief op server)

De volgende commands draaien via Laravel scheduler:

```
crypto:fetch-prices           - elk uur
crypto:fetch-fear-greed       - elk uur
crypto:fetch-whales           - elk kwartier
crypto:generate-signals       - elk uur
crypto:fetch-macro-indicators - dagelijks
crypto:fetch-news             - dagelijks
crypto:generate-holder-scores - elk uur
crypto:aggregate-whale-alerts - elk kwartier
```

Cron entry op server:
```bash
* * * * * www-data php /var/www/safehavun/production/artisan schedule:run >> /dev/null 2>&1
```

---

## Referenties

- **Credentials:** Zie `.claude/context.md` (lokaal, NIET in git)
- **Server info:** Zie `.claude/context.md`
- **Tests:** `php artisan test --no-coverage` (384 tests, 800 assertions)
