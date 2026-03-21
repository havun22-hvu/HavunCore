@echo off
cd /d D:\GitHub\HavunCore
php artisan docs:index all --force 2>&1 >> storage\logs\kb-update.log
php artisan docs:detect 2>&1 >> storage\logs\kb-update.log
echo [%date% %time%] KB update completed >> storage\logs\kb-update.log
