# Runbook: SafeHavun Deployment

> **BELANGRIJK:** Altijd deployen via GitHub (git push → git pull).
> **NOOIT** rsync, scp of directe file transfers gebruiken!

## Pre-requisites

- SSH toegang tot server (188.245.159.115)
- GitHub repo: https://github.com/havun22-hvu/SafeHavun

---

## 1. Server Folder Aanmaken

```bash
ssh root@188.245.159.115

# Maak productie folder
mkdir -p /var/www/safehavun/production
cd /var/www/safehavun/production
```

## 2. Database Aanmaken

```bash
mysql -u root -p

CREATE DATABASE safehavun CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'safehavun'@'localhost' IDENTIFIED BY 'GENEREER_WACHTWOORD';
GRANT ALL PRIVILEGES ON safehavun.* TO 'safehavun'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> **Bewaar het wachtwoord!** Voeg toe aan `.env` en update `context.md` lokaal.

## 3. Git Clone & Composer

```bash
cd /var/www/safehavun/production
git clone https://github.com/havun22-hvu/SafeHavun.git .
composer install --no-dev --optimize-autoloader
```

## 4. Environment Configureren

```bash
cp .env.example .env
nano .env
```

Pas aan:
```env
APP_NAME=SafeHavun
APP_ENV=production
APP_DEBUG=false
APP_URL=https://safehavun.havun.nl

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=safehavun
DB_USERNAME=safehavun
DB_PASSWORD=JOUW_WACHTWOORD

# Mail: kopieer MAIL_* settings van Herdenkingsportaal .env op server
```

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed --class=AssetSeeder
```

## 5. Permissions

```bash
chown -R www-data:www-data /var/www/safehavun/production
chmod -R 755 /var/www/safehavun/production
chmod -R 775 storage bootstrap/cache
```

## 6. Nginx Configuratie

```bash
nano /etc/nginx/sites-available/safehavun.havun.nl
```

```nginx
server {
    listen 80;
    server_name safehavun.havun.nl;
    root /var/www/safehavun/production/public;

    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/safehavun.havun.nl /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

## 7. SSL Certificaat

```bash
certbot --nginx -d safehavun.havun.nl
```

## 8. DNS Record

Voeg toe in Hetzner DNS of je DNS provider:
```
safehavun.havun.nl  A  188.245.159.115
```

---

## Updates Deployen

**Workflow:** Lokaal → GitHub → Server (nooit direct!)

```bash
# 1. LOKAAL: commit en push
git add .
git commit -m "beschrijving"
git push origin master

# 2. SERVER: pull van GitHub
ssh root@188.245.159.115
cd /var/www/safehavun/production
git pull origin master
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

---

## Scheduler (Cron) - Later

Voor automatische data fetching, voeg toe aan crontab:
```bash
crontab -e

# SafeHavun scheduler
* * * * * cd /var/www/safehavun/production && php artisan schedule:run >> /dev/null 2>&1
```

---

## Troubleshooting

| Probleem | Oplossing |
|----------|-----------|
| 500 error | Check `storage/logs/laravel.log` |
| Permission denied | `chown -R www-data:www-data storage` |
| Database error | Check `.env` credentials |
