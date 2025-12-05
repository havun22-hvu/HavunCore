# Template: Nieuwe Laravel Site

> Stappen om een nieuwe Laravel site op te zetten op de Havun server

## 1. Server directories

```bash
ssh root@188.245.159.115

# Maak directories
mkdir -p /var/www/{project}/staging
mkdir -p /var/www/{project}/production
```

## 2. Clone repository

```bash
cd /var/www/{project}/staging
git clone https://github.com/havun22-hvu/{Project}.git .

cd /var/www/{project}/production
git clone https://github.com/havun22-hvu/{Project}.git .
```

## 3. Laravel setup

```bash
# Per environment (staging en production)
cp .env.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate
php artisan config:clear
php artisan cache:clear
```

## 4. Permissions

```bash
chown -R www-data:www-data /var/www/{project}
chmod -R 755 /var/www/{project}
chmod -R 775 /var/www/{project}/*/storage
chmod -R 775 /var/www/{project}/*/bootstrap/cache
```

## 5. Nginx configuratie

```bash
# Kopieer template
cp /etc/nginx/sites-available/havunadmin.havun.nl /etc/nginx/sites-available/{domain}

# Pas aan:
# - server_name
# - root path
# - access/error log names

# Enable site
ln -s /etc/nginx/sites-available/{domain} /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

## 6. SSL certificaat

```bash
certbot --nginx -d {domain} -d www.{domain}
```

## 7. Database

```bash
mysql -u root -p

CREATE DATABASE {database};
CREATE USER '{user}'@'localhost' IDENTIFIED BY '{password}';
GRANT ALL PRIVILEGES ON {database}.* TO '{user}'@'localhost';
FLUSH PRIVILEGES;
```

## 8. Cron jobs (indien nodig)

```bash
crontab -e

# Laravel scheduler
* * * * * cd /var/www/{project}/production && php artisan schedule:run >> /dev/null 2>&1
```

## 9. Project docs aanmaken

Maak in het project:
```
CLAUDE.md
.claude/context.md
.claude/rules.md
```

Zie PKM-SYSTEEM.md voor de structuur.
