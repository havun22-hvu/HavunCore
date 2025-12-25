# Runbook: SafeHavun Deployment

> **BELANGRIJK:** Altijd deployen via GitHub (git push → git pull).
> **NOOIT** rsync, scp of directe file transfers gebruiken!

## Pre-requisites

- SSH toegang tot server (zie `context.md`)
- GitHub repo toegang

---

## 1. Server Setup (eenmalig)

```bash
# SSH naar server (credentials in context.md)
ssh root@SERVER_IP

# Maak folders
mkdir -p /var/www/safehavun/production

# Database aanmaken (wachtwoord genereren en opslaan in context.md)
mysql -u root -p
CREATE DATABASE safehavun CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'safehavun'@'localhost' IDENTIFIED BY 'GENEREER_VEILIG_WACHTWOORD';
GRANT ALL PRIVILEGES ON safehavun.* TO 'safehavun'@'localhost';
FLUSH PRIVILEGES;
```

## 2. Clone & Install

```bash
cd /var/www/safehavun/production
git clone GITHUB_REPO_URL .
composer install --no-dev --optimize-autoloader
cp .env.example .env
# Configureer .env met credentials uit context.md
php artisan key:generate
php artisan migrate
```

## 3. Permissions

```bash
chown -R www-data:www-data /var/www/safehavun/production
chmod -R 755 /var/www/safehavun/production
chmod -R 775 storage bootstrap/cache
```

## 4. Nginx & SSL

- Kopieer nginx config van ander project en pas aan
- `certbot --nginx -d DOMEIN`

---

## Updates Deployen

**Workflow:** Lokaal → GitHub → Server

```bash
# 1. LOKAAL
git add . && git commit -m "beschrijving" && git push

# 2. SERVER
cd /var/www/safehavun/production
git pull origin master
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:clear && php artisan cache:clear
```

---

## Referenties

- **Credentials:** Zie `.claude/context.md` (lokaal, NIET in git)
- **Server info:** Zie `.claude/context.md`
