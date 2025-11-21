# ⚡ Backup System Quick Start

**Voor:** Snel overzicht en snelle implementatie
**Tijd:** 1 dag (basisversie) - 4 dagen (compleet)

---

## 🎯 Wat hebben we nodig?

### Hardware/Accounts
- ✅ **Hetzner Storage Box** (€19/maand voor 5TB)
- ✅ **Server toegang** tot alle projecten
- ✅ **MySQL credentials** voor databases

### Software
- ✅ **Laravel 12** (HavunCore)
- ✅ **PHP 8.2+**
- ✅ **Composer**
- ✅ **Flysystem SFTP driver**

---

## 🚀 Snelle Implementatie (5 Stappen)

### Stap 1: Hetzner Storage Box (30 min)

```bash
# 1. Bestel: https://robot.hetzner.com/storage
# 2. Kies: BX30 (5TB) - €19,04/maand
# 3. Test connectie:
sftp -P 23 uXXXXXX@uXXXXXX.your-storagebox.de

# 4. Maak directories:
mkdir havun-backups
mkdir havun-backups/havunadmin
mkdir havun-backups/herdenkingsportaal
# etc...
```

**📖 Details:** Zie `HETZNER-STORAGE-BOX-SETUP.md`

---

### Stap 2: Laravel Dependencies (10 min)

```bash
cd D:/GitHub/HavunCore

# Install SFTP driver
composer require league/flysystem-sftp-v3

# Run migrations
php artisan migrate

# Files aanmaken:
# - src/Services/BackupOrchestrator.php
# - src/Strategies/*.php
# - src/Commands/Backup*.php
# - config/havun.php (backup sectie)
```

**📖 Details:** Zie `BACKUP-IMPLEMENTATION-GUIDE.md`

---

### Stap 3: Configuratie (20 min)

**config/filesystems.php**
```php
'hetzner-storage-box' => [
    'driver' => 'sftp',
    'host' => env('HETZNER_STORAGE_HOST'),
    'port' => 23,
    'username' => env('HETZNER_STORAGE_USERNAME'),
    'password' => env('HETZNER_STORAGE_PASSWORD'),
    'root' => '/havun-backups',
    'timeout' => 60,
],
```

**.env**
```env
# Hetzner Storage Box
HETZNER_STORAGE_HOST=uXXXXXX.your-storagebox.de
HETZNER_STORAGE_USERNAME=uXXXXXX
HETZNER_STORAGE_PASSWORD=your-password

# Backup Encryption
BACKUP_ENCRYPTION_ENABLED=true
BACKUP_ENCRYPTION_PASSWORD=generate-random-32-char-password

# Project Paths
HAVUNADMIN_PATH=/var/www/havunadmin/production
HAVUNADMIN_DATABASE=havunadmin_production
HERDENKINGSPORTAAL_PATH=/var/www/herdenkingsportaal/production
HERDENKINGSPORTAAL_DATABASE=herdenkingsportaal_production
```

**config/havun.php**
```php
'backup' => [
    'projects' => [
        'havunadmin' => [
            'enabled' => true,
            'type' => 'laravel-app',
            'priority' => 'critical',
            // ... (see full config in guide)
        ],
        // ... andere projecten
    ],
],
```

---

### Stap 4: Test Eerste Backup (15 min)

```bash
# Handmatige backup test
php artisan havun:backup:run --project=havunadmin

# Output:
Starting backup for project: havunadmin...
✅ Database dump: 5.2 MB
✅ Files archived: 47.3 MB
✅ Compressed: 52.5 MB
✅ SHA256: a1b2c3d4...
✅ Uploaded to local storage
✅ Uploaded to Hetzner Storage Box
✅ Backup completed in 23.5s

Backup successful: 2025-11-21-03-00-00-havunadmin.zip (52.5 MB)
```

**Verify:**
```bash
# Check local storage
ls -lh /backups/havunadmin/hot/

# Check offsite storage
sftp -P 23 uXXXXXX@uXXXXXX.your-storagebox.de
sftp> ls havun-backups/havunadmin/archive/2025/11/
```

---

### Stap 5: Automatisering (10 min)

**Crontab:**
```bash
crontab -e

# Add:
0 3 * * * cd /var/www/havuncore && php artisan havun:backup:run --project=havunadmin
0 4 * * * cd /var/www/havuncore && php artisan havun:backup:run --project=herdenkingsportaal
0 5 * * 0 cd /var/www/havuncore && php artisan havun:backup:run --project=havuncore
0 * * * * cd /var/www/havuncore && php artisan havun:backup:health
```

**✅ KLAAR! Backups draaien nu automatisch elke nacht.**

---

## 📊 Monitoring Dashboard

### Quick Health Check

```bash
php artisan havun:backup:health

# Output:
✅ havunadmin (Critical)
   Last backup: 18 hours ago
   Size: 52.5 MB
   Checksum: ✅ Verified
   Offsite: ✅ Accessible

✅ herdenkingsportaal (Critical)
   Last backup: 19 hours ago
   Size: 128.3 MB
   Checksum: ✅ Verified
   Offsite: ✅ Accessible

❌ havun-mcp (Medium)
   Last backup: 9 days ago
   ⚠️ WARNING: Expected weekly backup
```

---

### Daily Email Report

**Automatisch verzonden elke ochtend:**

```
Subject: [HavunCore] Daily Backup Report - 2025-11-21

✅ ALL BACKUPS SUCCESSFUL

1. HavunAdmin: 52.5 MB (✅ Success)
2. Herdenkingsportaal: 128.3 MB (✅ Success)

Storage: 12.5 GB local / 245.8 GB offsite
```

---

## 🔄 Veelgebruikte Commands

```bash
# === BACKUP ===
# Backup alle projecten
php artisan havun:backup:run

# Backup specifiek project
php artisan havun:backup:run --project=havunadmin

# Dry run (test zonder upload)
php artisan havun:backup:run --dry-run

# === MONITORING ===
# Health check
php artisan havun:backup:health

# List backups
php artisan havun:backup:list --project=havunadmin

# Verify checksums
php artisan havun:backup:verify

# === RESTORE ===
# Restore latest backup
php artisan havun:backup:restore --project=havunadmin --latest

# Restore specific backup
php artisan havun:backup:restore --project=havunadmin --date=2025-11-21

# Test restore (to test environment)
php artisan havun:backup:restore --project=havunadmin --latest --test

# === CLEANUP ===
# Cleanup old hot backups
php artisan havun:backup:cleanup --all

# Dry run cleanup (see what would be deleted)
php artisan havun:backup:cleanup --all --dry-run

# === TESTING ===
# Quarterly test restore
php artisan havun:backup:test --project=havunadmin

# Test all projects
php artisan havun:backup:test --all
```

---

## 🚨 Troubleshooting

### Backup Failed

```bash
# 1. Check logs
tail -f storage/logs/laravel.log

# 2. Check database connection
php artisan tinker
>>> DB::connection()->getPdo();

# 3. Check Storage Box connection
php artisan tinker
>>> Storage::disk('hetzner-storage-box')->files('test');

# 4. Manual backup with verbose output
php artisan havun:backup:run --project=havunadmin --verbose
```

---

### No Email Notifications

```bash
# Check .env mail settings
cat .env | grep MAIL

# Test email
php artisan tinker
>>> Mail::raw('Test email', function($m) { $m->to('havun22@gmail.com')->subject('Test'); });
```

---

### Offsite Upload Fails

```bash
# Check SFTP credentials
sftp -P 23 uXXXXXX@uXXXXXX.your-storagebox.de

# Check firewall (if configured)
# Check disk space on Storage Box

# Test Laravel connection
php artisan tinker
>>> Storage::disk('hetzner-storage-box')->put('test.txt', 'hello');
```

---

## 📚 Complete Documentatie

| Document | Beschrijving |
|----------|--------------|
| `COMPLIANCE-BACKUP-ARCHITECTURE.md` | 🏗️ Complete architectuur en compliance eisen |
| `MULTI-PROJECT-BACKUP-SYSTEM.md` | 🏢 Multi-project setup en configuratie |
| `BACKUP-IMPLEMENTATION-GUIDE.md` | 📖 Stap-voor-stap implementatie (4 dagen) |
| `HETZNER-STORAGE-BOX-SETUP.md` | 📦 Storage Box setup (30 min) |
| **`BACKUP-QUICK-START.md`** | ⚡ **Dit document** - Quick overview |

---

## ✅ Production Ready Checklist

### Minimaal Vereist (Fase 1)

- [ ] Hetzner Storage Box account
- [ ] SFTP driver geïnstalleerd
- [ ] BackupOrchestrator service
- [ ] Database migrations
- [ ] Eerste succesvolle backup
- [ ] Cron jobs geconfigureerd

### Aanbevolen (Fase 2)

- [ ] Email notificaties
- [ ] Health check monitoring
- [ ] Test restore succesvol
- [ ] Encryption enabled
- [ ] SSH key authentication
- [ ] Weekly reports

### Nice-to-Have (Fase 3)

- [ ] Slack integratie
- [ ] Web dashboard
- [ ] Automated quarterly tests
- [ ] Multi-user access
- [ ] Audit trail dashboard

---

## 💰 Kosten Samenvatting

| Item | Kosten |
|------|--------|
| **Hetzner Storage Box BX30 (5TB)** | €19,04/maand |
| **Totaal per jaar** | €228,48 |
| **Totaal 7 jaar (compliance)** | €1.599,36 |
| **Per project per jaar** | ~€57 |

**ROI:** Onbetaalbaar bij data loss! 💰💾

---

## 🎯 Quick Win: Start Vandaag!

**Minimale setup in 1 dag:**

1. **09:00 - 09:30:** Bestel Hetzner Storage Box
2. **09:30 - 10:00:** Test SFTP connectie
3. **10:00 - 12:00:** Implementeer BackupOrchestrator (basis)
4. **12:00 - 13:00:** Lunch 🍕
5. **13:00 - 15:00:** Implementeer strategies en commands
6. **15:00 - 16:00:** Configuratie en testing
7. **16:00 - 16:30:** Eerste backup van HavunAdmin
8. **16:30 - 17:00:** Setup cron jobs en monitoring

**Einde dag: Compliance-proof backup systeem draait!** ✅

---

## 📞 Support

**Vragen? Issues?**
- 📧 Email: havun22@gmail.com
- 📂 Docs: `D:\GitHub\HavunCore\*.md`
- 🐛 GitHub Issues: (indien git repo)

---

**Happy Backing Up!** 💾🚀

**Je data is nu veilig voor de komende 7 jaar (en verder)!**

---

⚡ **Quick Start Complete** - Klaar om te implementeren!
