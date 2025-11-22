# 🚀 Setup Backup in HavunAdmin/Herdenkingsportaal

**Quick setup guide om backup functionaliteit te activeren**
**Tijd:** ~15 minuten per project

---

## Stap 1: Update HavunCore Package

Zorg dat je de laatste versie van HavunCore hebt met backup functionaliteit:

```bash
cd D:/GitHub/HavunAdmin # Of HerdenkingsPortaal

# Update HavunCore
composer update havun/core
```

---

## Stap 2: Publish Migrations

```bash
# Publish backup migrations
php artisan vendor:publish --tag=havun-migrations --force

# Run migrations
php artisan migrate
```

Dit maakt de volgende tabellen aan:
- `havun_backup_logs`
- `havun_restore_logs`
- `havun_backup_test_logs`

---

## Stap 3: Configureer Filesystems

**config/filesystems.php** - Voeg deze disks toe:

```php
'disks' => [
    // ... existing disks

    'backups-local' => [
        'driver' => 'local',
        'root' => storage_path('backups'),
        'visibility' => 'private',
    ],

    'hetzner-storage-box' => [
        'driver' => 'sftp',
        'host' => env('HETZNER_STORAGE_HOST'),
        'port' => 23, // Hetzner uses port 23
        'username' => env('HETZNER_STORAGE_USERNAME'),
        'password' => env('HETZNER_STORAGE_PASSWORD'),
        'root' => '/havun-backups',
        'timeout' => 60,
        'directoryPerm' => 0755,
        'visibility' => 'private',

        // Optional: SSH key authentication
        // 'privateKey' => env('HETZNER_STORAGE_PRIVATE_KEY'),
        // 'passphrase' => env('HETZNER_STORAGE_PASSPHRASE'),
    ],
],
```

---

## Stap 4: Environment Variables

**.env** - Voeg deze variabelen toe:

```env
# === BACKUP CONFIGURATION ===

# Backup Storage
BACKUP_LOCAL_PATH="${APP_NAME}/storage/backups"

# Hetzner Storage Box (see HETZNER-STORAGE-BOX-SETUP.md)
HETZNER_STORAGE_HOST=uXXXXXX.your-storagebox.de
HETZNER_STORAGE_USERNAME=uXXXXXX
HETZNER_STORAGE_PASSWORD=your-storage-box-password

# Backup Encryption (⚠️ BEWAAR DIT VEILIG!)
BACKUP_ENCRYPTION_ENABLED=true
BACKUP_ENCRYPTION_PASSWORD=generate-random-32-char-password-here

# Project Paths (for backup)
HAVUNADMIN_PATH=/var/www/havunadmin/production
HAVUNADMIN_DATABASE=havunadmin_production
BACKUP_HAVUNADMIN_ENABLED=true

# Notifications
BACKUP_NOTIFICATION_EMAIL=havun22@gmail.com
```

**⚠️ BELANGRIJK:**
- Bewaar `BACKUP_ENCRYPTION_PASSWORD` veilig (password manager!)
- Zonder dit wachtwoord zijn backups ONLEESBAAR

---

## Stap 5: Create Directories

```bash
# Create backup directories
mkdir -p storage/backups
mkdir -p storage/backups/temp

# Set permissions
chmod -R 775 storage/backups
```

---

## Stap 6: Test Backup

```bash
# Test backup (dry run)
php artisan havun:backup:run --project=havunadmin --dry-run

# Real backup
php artisan havun:backup:run --project=havunadmin

# Output:
╔════════════════════════════════════════╗
║   HavunCore Backup Orchestrator       ║
╚════════════════════════════════════════╝

📦 Starting backup for: havunadmin

──────────────────────────────────────
Project: havunadmin
Status:   ✅ Success
Name:     2025-11-21-15-30-00-havunadmin
Size:     52.5 MB
Duration: 23.5s
Local:    ✅
Offsite:  ✅
Checksum: a1b2c3d4e5f6...

✅ All backups completed successfully!
```

---

## Stap 7: Verify Backup

```bash
# Check backup health
php artisan havun:backup:health

# List recent backups
php artisan havun:backup:list

# Check files
ls -lh storage/backups/havunadmin/hot/
```

---

## Stap 8: Setup Cron Jobs (Productie)

**crontab -e:**

```bash
# HavunAdmin backup - Daily 03:00
0 3 * * * cd /var/www/havunadmin/production && php artisan havun:backup:run --project=havunadmin >> /var/log/backup-havunadmin.log 2>&1

# Herdenkingsportaal backup - Daily 04:00
0 4 * * * cd /var/www/herdenkingsportaal/production && php artisan havun:backup:run --project=herdenkingsportaal >> /var/log/backup-herdenkingsportaal.log 2>&1

# Health check - Hourly
0 * * * * cd /var/www/havunadmin/production && php artisan havun:backup:health >> /var/log/backup-health.log 2>&1
```

---

## ✅ Production Checklist

- [ ] **HavunCore updated** met backup functionaliteit
- [ ] **Migrations uitgevoerd** (3 nieuwe tabellen)
- [ ] **Filesystem disks configured** (local + Hetzner)
- [ ] **.env variables toegevoegd** (credentials, paths, encryption)
- [ ] **Directories aangemaakt** (storage/backups)
- [ ] **Test backup succesvol** (dry-run + real)
- [ ] **Backup verified** (checksum, files exist)
- [ ] **Hetzner Storage Box accessible** (SFTP test)
- [ ] **Cron jobs geconfigureerd** (dagelijks backups)
- [ ] **Encryption password veilig opgeslagen**

---

## 🔧 Troubleshooting

### Error: "Database dump failed"

**Check:**
```bash
# Test MySQL connection
php artisan tinker
>>> DB::connection()->getPdo();

# Check .env database credentials
cat .env | grep DB_

# Test mysqldump manually
mysqldump -u root -p havunadmin_production > test_dump.sql
```

---

### Error: "Offsite upload failed"

**Check:**
```bash
# Test SFTP connection
php artisan tinker
>>> Storage::disk('hetzner-storage-box')->files('test');

# Or via CLI:
sftp -P 23 uXXXXXX@uXXXXXX.your-storagebox.de
```

---

### Error: "ZipArchive extension not available"

**Install:**
```bash
sudo apt-get install php8.2-zip
sudo systemctl restart php8.2-fpm
```

---

### Backups te groot (>100 MB)

**Optimize:**
- Exclude logs: Add `/storage/logs` to `.gitignore` style exclusions
- Compress older archives: Use `gzip -9` for max compression
- Cleanup old files before backup

---

## 📚 Volgende Stappen

1. **Read:** `HETZNER-STORAGE-BOX-SETUP.md` (Hetzner setup)
2. **Setup:** Herdenkingsportaal (herhaal deze stappen)
3. **Test:** Quarterly restore test
4. **Monitor:** Check daily backup logs

---

## 📞 Support

**Issues?**
- Check logs: `tail -f storage/logs/laravel.log`
- Backup logs: `php artisan havun:backup:list`
- Email: havun22@gmail.com

---

**Backup Setup Complete!** ✅ Je data is nu veilig! 🔒
