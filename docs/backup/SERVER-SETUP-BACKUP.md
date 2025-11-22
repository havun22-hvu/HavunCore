# 🚀 Server Setup Guide - Hetzner Backup System

**Doel:** Backup systeem configureren op de productie server

**Tijd:** ~45 minuten

---

## ✅ Checklist Overzicht

- [ ] 1. SFTP library installeren op server
- [ ] 2. Filesystem configuratie toevoegen
- [ ] 3. .env variabelen instellen
- [ ] 4. Storage Box connectie testen
- [ ] 5. Directory structuur aanmaken
- [ ] 6. Eerste backup draaien
- [ ] 7. Cron jobs opzetten

---

## 📦 Stap 1: SFTP Library Installeren (Op Server)

SSH naar je server en navigeer naar HavunCore:

```bash
ssh [your-server]
cd /var/www/havuncore  # Of waar HavunCore staat
```

Installeer de SFTP driver:

```bash
composer require league/flysystem-sftp-v3 "^3.0"
```

**Verificatie:**
```bash
composer show league/flysystem-sftp-v3
# Moet tonen: league/flysystem-sftp-v3 3.30.0 (of nieuwer)
```

---

## ⚙️ Stap 2: Filesystem Configuratie

### 2.1 In HavunAdmin

Voeg toe aan `config/filesystems.php`:

```php
<?php

return [
    'disks' => [
        // ... existing disks ...

        'hetzner-storage-box' => [
            'driver' => 'sftp',
            'host' => env('HETZNER_STORAGE_HOST'),
            'port' => 23, // Hetzner uses port 23 for SFTP
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
    ],
];
```

### 2.2 In Herdenkingsportaal

Zelfde configuratie, maar met andere root:

```php
'hetzner-storage-box' => [
    // ... same config ...
    'root' => '', // Empty = Storage Box /home directory
],

// Note: BackupOrchestrator uploads to /home/{project}/archive/{year}/{month}/
```

---

## 🔐 Stap 3: Environment Variabelen

### 3.1 In HavunAdmin `.env`

```env
# Hetzner Storage Box
HETZNER_STORAGE_HOST=u510616.your-storagebox.de
HETZNER_STORAGE_USERNAME=u510616
HETZNER_STORAGE_PASSWORD=G63^C@GB&PD2#jCl#1uj

# Backup Encryption (BELANGRIJK!)
BACKUP_ENCRYPTION_ENABLED=true
BACKUP_ENCRYPTION_PASSWORD=  # Generate with: openssl rand -base64 32

# Backup Paths
HAVUNADMIN_PATH=/var/www/havunadmin/production
HAVUNADMIN_DATABASE=havunadmin_production

# Notifications
BACKUP_NOTIFICATION_EMAIL=havun22@gmail.com
```

### 3.2 Genereer Encryption Password

⚠️ **ZEER BELANGRIJK:**

```bash
openssl rand -base64 32
```

Output example: `Kx9mP3vR2wQ7nF5jL8tY1cZ6hB4dA0sE`

**Voeg toe aan `.env`:**
```env
BACKUP_ENCRYPTION_PASSWORD=Kx9mP3vR2wQ7nF5jL8tY1cZ6hB4dA0sE
```

**⚠️ BEWAAR DIT WACHTWOORD VEILIG!**
- Zonder dit wachtwoord kun je backups NIET restoren!
- Zet het in je password manager
- Print het eventueel uit en bewaar het veilig

### 3.3 In Herdenkingsportaal `.env`

```env
# Zelfde Hetzner credentials
HETZNER_STORAGE_HOST=u510616.your-storagebox.de
HETZNER_STORAGE_USERNAME=u510616
HETZNER_STORAGE_PASSWORD=G63^C@GB&PD2#jCl#1uj

# Zelfde encryption password (!)
BACKUP_ENCRYPTION_PASSWORD=Kx9mP3vR2wQ7nF5jL8tY1cZ6hB4dA0sE

# Project specific paths
HERDENKINGSPORTAAL_PATH=/var/www/herdenkingsportaal/production
HERDENKINGSPORTAAL_DATABASE=herdenkingsportaal_production

BACKUP_NOTIFICATION_EMAIL=havun22@gmail.com
```

---

## 🧪 Stap 4: Test Storage Box Connectie

### 4.1 Via PHP Tinker

In HavunAdmin:

```bash
cd /var/www/havunadmin
php artisan tinker
```

Test de connectie:

```php
use Illuminate\Support\Facades\Storage;

// Test write
Storage::disk('hetzner-storage-box')->put('test.txt', 'Hello from HavunAdmin! ' . now());

// Test read
$content = Storage::disk('hetzner-storage-box')->get('test.txt');
echo $content;

// Test list
$files = Storage::disk('hetzner-storage-box')->files('/');
print_r($files);

// Cleanup test file
Storage::disk('hetzner-storage-box')->delete('test.txt');

exit
```

**Verwachte output:**
```
Hello from HavunAdmin! 2025-11-22 20:30:00
Array
(
    [0] => test.txt
)
```

✅ **Als dit werkt, is je Storage Box correct geconfigureerd!**

---

## 📁 Stap 5: Directory Structuur Aanmaken

### 5.1 Via PHP Script

Maak tijdelijk bestand `create-structure.php`:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Storage;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$disk = Storage::disk('hetzner-storage-box');

// Create directory structure
$directories = [
    '/havun-backups',
    '/havun-backups/havunadmin',
    '/havun-backups/havunadmin/hot',
    '/havun-backups/havunadmin/archive',
    '/havun-backups/havunadmin/archive/2025',
    '/havun-backups/herdenkingsportaal',
    '/havun-backups/herdenkingsportaal/hot',
    '/havun-backups/herdenkingsportaal/archive',
    '/havun-backups/herdenkingsportaal/archive/2025',
    '/havun-backups/havuncore',
    '/havun-backups/havuncore/hot',
    '/havun-backups/havuncore/archive',
];

foreach ($directories as $dir) {
    try {
        $disk->makeDirectory($dir);
        echo "✅ Created: $dir\n";
    } catch (\Exception $e) {
        echo "⚠️  Already exists or error: $dir\n";
    }
}

echo "\n✅ Directory structure created!\n";
```

Run het:

```bash
php create-structure.php
rm create-structure.php  # cleanup
```

---

## 🎯 Stap 6: Eerste Backup Draaien

### 6.1 Test Backup Command

In HavunAdmin:

```bash
cd /var/www/havunadmin
php artisan havun:backup:run --project=havunadmin --verbose
```

**Verwachte output:**
```
╔════════════════════════════════════════╗
║   HavunCore Backup Orchestrator       ║
╚════════════════════════════════════════╝

📦 Starting backup for: havunadmin

[1/6] Collecting database...
✅ Database dump: 5.2 MB

[2/6] Collecting files...
✅ Files collected: 12 files (2.1 MB)

[3/6] Creating ZIP archive...
✅ Archive created: 7.3 MB

[4/6] Generating SHA256 checksum...
✅ Checksum: a1b2c3d4e5f6...

[5/6] Uploading to local storage...
✅ Local: storage/backups/havunadmin/hot/2025-11-22-20-30-00-havunadmin.zip

[6/6] Uploading to offsite storage...
✅ Offsite: Hetzner Storage Box

✅ BACKUP COMPLETE!
Duration: 23.5s
Size: 7.3 MB
```

### 6.2 Verify Backup

```bash
# Check local
ls -lh storage/backups/havunadmin/hot/

# Check offsite via Tinker
php artisan tinker
>>> Storage::disk('hetzner-storage-box')->files('/havunadmin/hot');
```

---

## ⏰ Stap 7: Cron Jobs Opzetten

### 7.1 Open Crontab

```bash
crontab -e
```

### 7.2 Voeg Backup Jobs Toe

```cron
# HavunAdmin Backup (Daily 03:00)
0 3 * * * cd /var/www/havunadmin && php artisan havun:backup:run >> /var/log/havun-backup.log 2>&1

# Herdenkingsportaal Backup (Daily 04:00)
0 4 * * * cd /var/www/herdenkingsportaal && php artisan havun:backup:run >> /var/log/havun-backup.log 2>&1

# HavunCore Backup (Weekly Sunday 05:00)
0 5 * * 0 cd /var/www/havuncore && php artisan havun:backup:run --project=havuncore >> /var/log/havun-backup.log 2>&1

# Health Check (Hourly)
0 * * * * cd /var/www/havunadmin && php artisan havun:backup:health --quiet >> /var/log/havun-health.log 2>&1
```

### 7.3 Test Cron

Wacht tot de volgende cron run, of trigger handmatig:

```bash
# Manual test
cd /var/www/havunadmin && php artisan havun:backup:run

# Check logs
tail -f /var/log/havun-backup.log
```

---

## ✅ Verificatie Checklist

**Na setup, controleer:**

- [ ] SFTP library geïnstalleerd (`composer show league/flysystem-sftp-v3`)
- [ ] Filesystem config toegevoegd in beide projecten
- [ ] `.env` variabelen correct ingesteld
- [ ] Encryption password gegenereerd en opgeslagen
- [ ] Storage Box connectie test succesvol
- [ ] Directory structuur aangemaakt
- [ ] Eerste backup succesvol (check lokaal + offsite)
- [ ] Cron jobs toegevoegd
- [ ] Log files aangemaakt en beschrijfbaar

---

## 🔧 Troubleshooting

### Probleem: "Connection refused"

```bash
# Check firewall
telnet u510616.your-storagebox.de 23

# Check credentials in .env
cat .env | grep HETZNER
```

### Probleem: "Permission denied" op Storage Box

```bash
# Login via SFTP and check permissions
sftp -P 23 u510616@u510616.your-storagebox.de
sftp> ls -la havun-backups/
# Directories should be drwxr-xr-x (755)
```

### Probleem: "Encryption password not set"

```bash
# Check .env
cat .env | grep BACKUP_ENCRYPTION

# Generate new password
openssl rand -base64 32
```

### Probleem: Backup hangt

```bash
# Check disk space
df -h

# Check if another backup is running
ps aux | grep artisan

# Check timeout settings in config/filesystems.php (increase to 120)
```

---

## 📊 Monitoring

### Daily Check

```bash
# Check laatste backup
php artisan havun:backup:list --project=havunadmin | head -5

# Check health
php artisan havun:backup:health
```

### Weekly Check

```bash
# Check alle backups
php artisan havun:backup:list

# Check offsite storage
sftp -P 23 u510616@u510616.your-storagebox.de
sftp> ls -lh havun-backups/*/archive/2025/11/
```

---

## 🎉 Klaar!

Je backup systeem is nu volledig operationeel!

**Wat er nu gebeurt:**
- ✅ Dagelijks om 03:00: HavunAdmin backup
- ✅ Dagelijks om 04:00: Herdenkingsportaal backup
- ✅ Wekelijks zondag 05:00: HavunCore backup
- ✅ Elk uur: Health check
- ✅ 30 dagen hot backups (lokaal)
- ✅ 7 jaar archive backups (Hetzner)

**Volgende stappen:**
1. Monitor de eerste week dagelijks
2. Test een restore na 1 week
3. Quarterly restore test (elk kwartaal)
4. Review logs maandelijks

---

**Last updated:** 2025-11-22
**HavunCore Version:** v0.6.0
