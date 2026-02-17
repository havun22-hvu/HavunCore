# Runbook: Deploy

> Hoe deploy ik naar production?

## HavunCore

**Let op:** HavunCore wordt ALLEEN lokaal bewerkt!

```bash
# 1. Lokaal testen
cd D:\GitHub\HavunCore
php artisan test

# 2. Commit en push
git add .
git commit -m "Description"
git push

# 3. Op server pullen
ssh root@SERVER_IP (zie context.md)
cd /var/www/development/HavunCore
git pull origin master
php artisan config:clear
php artisan cache:clear
```

## HavunAdmin

```bash
# 1. Lokaal testen
cd D:\GitHub\HavunAdmin
php artisan test

# 2. Push naar GitHub
git add .
git commit -m "Description"
git push

# 3. Deploy naar staging
ssh root@SERVER_IP (zie context.md)
cd /var/www/havunadmin/staging
git pull origin master
php artisan migrate
php artisan config:clear

# 4. Test staging
# Open https://staging.havunadmin.havun.nl

# 5. Deploy naar production
cd /var/www/havunadmin/production
git pull origin master
php artisan config:clear
```

## Herdenkingsportaal

```bash
# Zelfde als HavunAdmin maar met:
cd /var/www/herdenkingsportaal/staging
# en
cd /var/www/herdenkingsportaal/production
```

## JudoToernooi

```bash
# 1. Lokaal testen
cd D:\GitHub\JudoToernooi\laravel
php artisan test

# 2. Push naar GitHub
git add .
git commit -m "Description"
git push

# 3. Deploy naar production
ssh root@SERVER_IP (zie context.md)
cd /var/www/judotoernooi/laravel
git pull origin main
composer install --no-dev
npm run build
php artisan migrate --force
php artisan config:clear && php artisan cache:clear
```

> **Let op:** JudoToernooi gebruikt `main` branch (niet `master`)
> **Server pad:** `/var/www/judotoernooi/laravel`
> **Staging:** `/var/www/staging.judotoernooi/laravel`

## HavunCore Webapp

```bash
# 1. Build lokaal
cd D:\GitHub\havuncore-webapp\frontend
npm run build

# 2. Upload frontend
scp -r dist/* root@SERVER_IP (zie context.md):/var/www/havuncore.havun.nl/public/

# 3. Backend (indien gewijzigd)
scp backend/src/*.js root@SERVER_IP (zie context.md):/var/www/havuncore.havun.nl/backend/src/
ssh root@SERVER_IP (zie context.md) "pm2 restart havuncore-backend"
```

## Studieplanner

### Frontend (React)
```bash
# 1. Lokaal builden en pushen
cd D:\GitHub\Studieplanner
npm run build
git add .
git commit -m "Description"
git push

# 2. Server deploy
ssh root@SERVER_IP (zie context.md)
cd /var/www/studieplanner/production
git pull origin master
npm ci && npm run build
```

### Backend (Laravel API)
```bash
# 1. Lokaal pushen
cd D:\GitHub\Studieplanner-api
git add .
git commit -m "Description"
git push

# 2. Server deploy
ssh root@SERVER_IP (zie context.md)
cd /var/www/studieplanner-api
git pull origin master
composer install --no-dev
php artisan migrate
php artisan config:clear
php artisan cache:clear
```

### Eerste keer server setup (backend)
```bash
ssh root@SERVER_IP (zie context.md)

# 1. Database aanmaken
mysql -u root -p
CREATE DATABASE studieplanner;
GRANT ALL ON studieplanner.* TO 'havun'@'localhost';
FLUSH PRIVILEGES;
exit;

# 2. Git clone
mkdir -p /var/www/studieplanner-api
cd /var/www/studieplanner-api
git clone git@github.com:USERNAME/Studieplanner-api.git .

# 3. Laravel setup
composer install --no-dev
cp .env.example .env
nano .env  # Configureer DB, MAIL, APP_URL
php artisan key:generate
php artisan migrate

# 4. Permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## HavunClub

```bash
# 1. Lokaal testen
cd D:\GitHub\HavunClub
php artisan test

# 2. Push naar GitHub
git add .
git commit -m "Description"
git push

# 3. Deploy naar staging
ssh root@188.245.159.115
cd /var/www/havunclub/staging
git pull origin main
php artisan migrate
php artisan config:clear && php artisan cache:clear

# 4. Test staging
# Open https://staging.havunclub.havun.nl

# 5. Deploy naar production
cd /var/www/havunclub/production
git pull origin main
php artisan migrate
php artisan config:clear && php artisan cache:clear
```

> **Let op:** HavunClub gebruikt `main` branch (niet `master`)
> **Git remote:** `github-havunclub:havun22-hvu/HavunClub.git`

---

## Checklist na deploy

- [ ] Config cache gecleared
- [ ] Applicatie laadt zonder errors
- [ ] Kritieke features getest
- [ ] Logs gecontroleerd
