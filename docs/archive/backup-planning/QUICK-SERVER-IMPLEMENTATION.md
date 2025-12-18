# ⚡ Quick Server Implementation Checklist

**Tijd:** ~30 minuten
**Voor:** HavunAdmin & Herdenkingsportaal

---

## 📋 Pre-Implementation Checklist

Heb je klaar:
- [ ] Server SSH toegang
- [ ] Encryption password: `QUfTHO0hjdagrLgW10zIWLGjJelGBtrvG915IzFqIDE=`
- [ ] Storage Box credentials: `u510616` / `G63^C@GB&PD2#jCl#1uj`

---

## 🚀 Implementation Steps

### 1️⃣ HavunAdmin (15 min)

```bash
# SSH to server
ssh your-server

# Navigate to HavunAdmin
cd /var/www/havunadmin

# Install SFTP driver
composer require league/flysystem-sftp-v3 "^3.0"

# Edit filesystems config
nano config/filesystems.php
```

**Add this disk to the 'disks' array:**
```php
'hetzner-storage-box' => [
    'driver' => 'sftp',
    'host' => env('HETZNER_STORAGE_HOST'),
    'port' => 23,
    'username' => env('HETZNER_STORAGE_USERNAME'),
    'password' => env('HETZNER_STORAGE_PASSWORD'),
    'root' => '', // Empty = Storage Box /home directory
    'timeout' => 60,
    'directoryPerm' => 0755,
    'visibility' => 'private',
    'throw' => false,
],

'backups-local' => [
    'driver' => 'local',
    'root' => storage_path('backups'),
    'visibility' => 'private',
],
```

**Edit .env:**
```bash
nano .env
```

**Add these lines:**
```env
# Hetzner Storage Box
HETZNER_STORAGE_HOST=u510616.your-storagebox.de
HETZNER_STORAGE_USERNAME=u510616
HETZNER_STORAGE_PASSWORD=G63^C@GB&PD2#jCl#1uj

# Backup Encryption
BACKUP_ENCRYPTION_ENABLED=true
BACKUP_ENCRYPTION_PASSWORD=QUfTHO0hjdagrLgW10zIWLGjJelGBtrvG915IzFqIDE=

# Project Paths
HAVUNADMIN_PATH=/var/www/havunadmin/production
HAVUNADMIN_DATABASE=havunadmin_production

# Notifications
BACKUP_NOTIFICATION_EMAIL=havun22@gmail.com
```

**Test connection:**
```bash
php artisan tinker

# In tinker:
use Illuminate\Support\Facades\Storage;
Storage::disk('hetzner-storage-box')->put('test.txt', 'Hello!');
Storage::disk('hetzner-storage-box')->get('test.txt');
Storage::disk('hetzner-storage-box')->delete('test.txt');
exit
```

**Create directories:**
```bash
php artisan tinker

# Test SFTP verbinding:
$disk = Storage::disk('hetzner-storage-box');
$disk->put('test.txt', 'Test from ' . now());
echo $disk->exists('test.txt') ? "✅ Upload successful!\n" : "❌ Failed\n";
$disk->delete('test.txt');
exit

# Directories worden automatisch aangemaakt door BackupOrchestrator
# bij eerste backup run: /home/{project}/archive/{year}/{month}/
```

**First backup:**
```bash
php artisan havun:backup:run --project=havunadmin
```

---

### 2️⃣ Herdenkingsportaal (15 min)

**Repeat same steps as HavunAdmin, but:**

```bash
cd /var/www/herdenkingsportaal
composer require league/flysystem-sftp-v3 "^3.0"
```

**In filesystems.php, use:**
```php
'root' => '', // Empty = Storage Box /home directory
```

**Note:** BackupOrchestrator will automatically upload to `/home/herdenkingsportaal/archive/{year}/{month}/`

**In .env, use:**
```env
HERDENKINGSPORTAAL_PATH=/var/www/herdenkingsportaal/production
HERDENKINGSPORTAAL_DATABASE=herdenkingsportaal_production
```

**Create directories:**
```bash
php artisan tinker
$disk = Storage::disk('hetzner-storage-box');
$disk->makeDirectory('/havun-backups/herdenkingsportaal');
$disk->makeDirectory('/havun-backups/herdenkingsportaal/hot');
$disk->makeDirectory('/havun-backups/herdenkingsportaal/archive');
$disk->makeDirectory('/havun-backups/herdenkingsportaal/archive/2025');
exit
```

**First backup:**
```bash
php artisan havun:backup:run --project=herdenkingsportaal
```

---

### 3️⃣ Cron Jobs (5 min)

```bash
crontab -e
```

**Add:**
```cron
# HavunAdmin Backup (Daily 03:00)
0 3 * * * cd /var/www/havunadmin && php artisan havun:backup:run >> /var/log/havun-backup.log 2>&1

# Herdenkingsportaal Backup (Daily 04:00)
0 4 * * * cd /var/www/herdenkingsportaal && php artisan havun:backup:run >> /var/log/havun-backup.log 2>&1

# Health Check (Hourly)
0 * * * * cd /var/www/havunadmin && php artisan havun:backup:health --quiet >> /var/log/havun-health.log 2>&1
```

---

## ✅ Verification

After implementation:

```bash
# Check backups were created
ls -lh /var/www/havunadmin/storage/backups/havunadmin/hot/
ls -lh /var/www/herdenkingsportaal/storage/backups/herdenkingsportaal/hot/

# Check offsite upload
ssh -p 23 u510616@u510616.your-storagebox.de
ls -lh havun-backups/havunadmin/hot/
ls -lh havun-backups/herdenkingsportaal/hot/
exit

# Check cron jobs
crontab -l
```

---

## 🎉 Done!

Your backup system is now:
- ✅ Running daily backups
- ✅ Uploading to Hetzner Storage Box
- ✅ Encrypted with AES-256
- ✅ Monitored hourly
- ✅ 7-year retention configured

**Next:**
- Monitor logs for first week
- Test restore after 1 week
- Quarterly restore test (every 3 months)

---

**Need help?** Check `SERVER-SETUP-BACKUP.md` for detailed troubleshooting.
