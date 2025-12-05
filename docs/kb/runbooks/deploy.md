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
ssh root@188.245.159.115
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
ssh root@188.245.159.115
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

## HavunCore Webapp

```bash
# 1. Build lokaal
cd D:\GitHub\havuncore-webapp\frontend
npm run build

# 2. Upload frontend
scp -r dist/* root@188.245.159.115:/var/www/havuncore.havun.nl/public/

# 3. Backend (indien gewijzigd)
scp backend/src/*.js root@188.245.159.115:/var/www/havuncore.havun.nl/backend/src/
ssh root@188.245.159.115 "pm2 restart havuncore-backend"
```

## Checklist na deploy

- [ ] Config cache gecleared
- [ ] Applicatie laadt zonder errors
- [ ] Kritieke features getest
- [ ] Logs gecontroleerd
