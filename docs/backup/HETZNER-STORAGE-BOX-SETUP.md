# 📦 Hetzner Storage Box Setup Guide

**Voor:** HavunCore Multi-Project Backup System
**Versie:** 1.0.0
**Tijd:** ~30 minuten

---

## 🎯 Wat is een Hetzner Storage Box?

Hetzner Storage Box is een **betaalbare offsite backup storage oplossing** met:
- ✅ EU datacenters (GDPR compliant)
- ✅ SFTP/FTP/rsync toegang
- ✅ Betrouwbaar (Hetzner = grote EU hosting provider)
- ✅ Goedkoop (€3,81/100GB of €19,04/5TB per maand)
- ✅ Perfect voor compliance (7 jaar retention)

---

## 💰 Pricing & Capaciteit

### Kies de juiste Storage Box

| Model | Capaciteit | Prijs/maand | Aanbeveling |
|-------|------------|-------------|-------------|
| **BX10** | 100 GB | €3,81 | Start (1-2 projecten) |
| **BX20** | 1 TB | €9,52 | Medium (3-5 projecten) |
| **BX30** | 5 TB | €19,04 | ⭐ **Aanbevolen** (alle Havun projecten + ruimte) |
| **BX40** | 10 TB | €31,36 | Overkill (tenzij veel klanten) |
| **BX60** | 20 TB | €54,11 | Voor hosting bedrijven |

**Voor Havun (4 projecten + toekomst):**
- **Aanbeveling: BX30 (5 TB) - €19,04/maand**
- 7 jaar totaal: ~520 GB (zie cost breakdown)
- 5 TB = 10x capacity = ruimte voor groei! 🚀

---

## 📝 Stap 1: Bestellen Storage Box

### 1.1 Ga naar Hetzner Robot

```
https://robot.hetzner.com/storage
```

### 1.2 Login/Registreer

- Als je al een Hetzner account hebt (voor servers), login
- Anders: Registreer nieuw account

### 1.3 Bestel Storage Box

1. Click: **"Order new Storage Box"**
2. Kies: **BX30 (5 TB)** - €19,04/maand
3. Selecteer datacenter: **Falkenstein (Deutschland)** (dichtstbij Nederland)
4. Order quantity: **1**
5. Controle period: **Monthly** (maandelijks opzegbaar)
6. Click: **Order now**

### 1.4 Betaling Setup

- Kies betaalmethode: **SEPA Direct Debit** (automatische incasso) of **Credit Card**
- Vul betaalgegevens in
- Bevestig order

**⏱ Activatie tijd:** ~5-15 minuten (meestal instant!)

---

## 🔑 Stap 2: Storage Box Configureren

### 2.1 Krijg Inloggegevens

Na activatie ontvang je een email met:

```
Storage Box Activated!

Your Storage Box Details:
- Storage Box ID: uXXXXXX
- Server: uXXXXXX.your-storagebox.de
- Username: uXXXXXX
- Password: [generated password]
- Protocol: SFTP, FTP, rsync, WebDAV
```

**⚠️ BELANGRIJK: Bewaar deze credentials veilig!**

---

### 2.2 Test Connectie (SFTP)

**Windows (via WinSCP of FileZilla):**

```bash
# Installeer WinSCP: https://winscp.net/

Host: uXXXXXX.your-storagebox.de
Port: 23
Protocol: SFTP
Username: uXXXXXX
Password: [your password]
```

**Linux/Mac (via command line):**

```bash
sftp -P 23 uXXXXXX@uXXXXXX.your-storagebox.de

# Password: [enter password]

# Succeeded? Test commands:
sftp> pwd
/home

sftp> ls
.

sftp> mkdir havun-backups
sftp> ls
havun-backups

sftp> exit
```

**✅ Als dit werkt, is je Storage Box klaar!**

---

### 2.3 Maak Directory Structuur

Via SFTP:

```bash
sftp -P 23 uXXXXXX@uXXXXXX.your-storagebox.de

# Maak root backup directory
sftp> mkdir havun-backups
sftp> cd havun-backups

# Maak project directories
sftp> mkdir havunadmin
sftp> mkdir herdenkingsportaal
sftp> mkdir havuncore
sftp> mkdir havun-mcp

# Maak subdirectories voor havunadmin
sftp> cd havunadmin
sftp> mkdir hot
sftp> mkdir archive
sftp> cd archive
sftp> mkdir 2025
sftp> cd ..
sftp> cd ..

# Herhaal voor andere projecten...

sftp> exit
```

**Of gebruik dit script:**

```bash
#!/bin/bash
# create-storage-structure.sh

STORAGE_USER="uXXXXXX"
STORAGE_HOST="uXXXXXX.your-storagebox.de"

projects=("havunadmin" "herdenkingsportaal" "havuncore" "havun-mcp")

sftp -P 23 $STORAGE_USER@$STORAGE_HOST << EOF
mkdir havun-backups
cd havun-backups

$(for project in "${projects[@]}"; do
  echo "mkdir $project"
  echo "mkdir $project/hot"
  echo "mkdir $project/archive"
  echo "mkdir $project/archive/2025"
done)

exit
EOF

echo "✅ Storage Box structure created!"
```

---

## 🔐 Stap 3: Laravel Filesystem Configuratie

### 3.1 Installeer SFTP Driver

```bash
cd D:/GitHub/HavunCore

composer require league/flysystem-sftp-v3
```

### 3.2 Update config/filesystems.php

```php
// config/filesystems.php

'disks' => [
    // ... existing disks

    'hetzner-storage-box' => [
        'driver' => 'sftp',
        'host' => env('HETZNER_STORAGE_HOST'),
        'port' => 23, // Hetzner uses port 23 for SFTP
        'username' => env('HETZNER_STORAGE_USERNAME'),
        'password' => env('HETZNER_STORAGE_PASSWORD'),
        'root' => '/havun-backups',
        'timeout' => 60, // Longer timeout for large files
        'directoryPerm' => 0755,
        'visibility' => 'private',

        // Optional: gebruik key-based auth in plaats van password
        // 'privateKey' => env('HETZNER_STORAGE_PRIVATE_KEY'),
        // 'passphrase' => env('HETZNER_STORAGE_PASSPHRASE'),
    ],
],
```

### 3.3 Update .env

```env
# Hetzner Storage Box
HETZNER_STORAGE_HOST=uXXXXXX.your-storagebox.de
HETZNER_STORAGE_USERNAME=uXXXXXX
HETZNER_STORAGE_PASSWORD=your-storage-box-password
```

**⚠️ Security: .env should NEVER be in git!**

---

## ✅ Stap 4: Test Upload/Download

### 4.1 Test via Tinker

```bash
php artisan tinker
```

```php
use Illuminate\Support\Facades\Storage;

// Test connectie
$disk = Storage::disk('hetzner-storage-box');

// Test write
$testContent = "Hello from HavunCore Backup System! " . now();
$disk->put('test/test-file.txt', $testContent);

// Test read
$content = $disk->get('test/test-file.txt');
echo $content; // Should show: Hello from HavunCore Backup System! ...

// Test list
$files = $disk->files('test');
print_r($files); // Should show: ['test/test-file.txt']

// Test delete
$disk->delete('test/test-file.txt');

// Verify deleted
$exists = $disk->exists('test/test-file.txt');
echo $exists ? "Still exists" : "Deleted successfully";

exit
```

**✅ Als alles werkt: Je Storage Box is ready to use!**

---

## 🔐 Stap 5: Security Best Practices

### 5.1 SSH Key Authentication (Recommended)

**Waarom?** Veiliger dan password, geen password in .env

**Setup:**

```bash
# 1. Genereer SSH key pair op je server
ssh-keygen -t ed25519 -f ~/.ssh/hetzner_storage_box -C "havun-backups"

# 2. Bekijk public key
cat ~/.ssh/hetzner_storage_box.pub

# 3. Upload public key naar Storage Box via Hetzner Robot:
# https://robot.hetzner.com/storage
# → Click op je Storage Box
# → Tab: "SSH Keys"
# → Add SSH key
# → Paste public key

# 4. Test SSH key login
sftp -P 23 -i ~/.ssh/hetzner_storage_box uXXXXXX@uXXXXXX.your-storagebox.de
```

**Update Laravel config:**

```php
'hetzner-storage-box' => [
    'driver' => 'sftp',
    'host' => env('HETZNER_STORAGE_HOST'),
    'port' => 23,
    'username' => env('HETZNER_STORAGE_USERNAME'),
    // 'password' => env('HETZNER_STORAGE_PASSWORD'), // Remove!
    'privateKey' => env('HETZNER_STORAGE_PRIVATE_KEY'), // Path to key
    'root' => '/havun-backups',
    'timeout' => 60,
],
```

```env
HETZNER_STORAGE_PRIVATE_KEY=/home/youruser/.ssh/hetzner_storage_box
```

---

### 5.2 Firewall (Optioneel)

Restrict Storage Box access to specific IPs:

**In Hetzner Robot:**
1. Go to Storage Box settings
2. Tab: "Firewall"
3. Add allowed IP: `188.245.159.115` (HavunAdmin server)
4. Add allowed IP: `your-herdenkingsportaal-server-ip`
5. Enable firewall

**⚠️ Let op:** Als je IP verandert, moet je firewall updaten!

---

### 5.3 Subaccounts (Voor Team Access)

**Use case:** Geef accountant/team member read-only access

**Setup:**
1. Hetzner Robot → Storage Box → "Sub-accounts"
2. Create sub-account (bijv. `accountant`)
3. Set permissions: **Read-only**
4. Share credentials met team member

---

## 📊 Stap 6: Monitoring & Alerts

### 6.1 Storage Usage Monitor

**Script:**

```bash
#!/bin/bash
# check-storage-usage.sh

STORAGE_USER="uXXXXXX"
STORAGE_HOST="uXXXXXX.your-storagebox.de"

# Get storage usage via SFTP
USAGE=$(sftp -P 23 $STORAGE_USER@$STORAGE_HOST << EOF | grep -oP '\d+%'
df -h
exit
EOF
)

echo "Storage Box Usage: $USAGE"

# Alert if >80%
PERCENT=${USAGE%\%}
if [ $PERCENT -gt 80 ]; then
    echo "⚠️ WARNING: Storage Box >80% full!"
    # Send email alert
    mail -s "Hetzner Storage Box Alert" havun22@gmail.com <<< "Storage Box is $USAGE full!"
fi
```

**Cron (weekly check):**

```bash
0 8 * * 0 /path/to/check-storage-usage.sh
```

---

### 6.2 Hetzner Robot Notifications

Enable email alerts:

1. Hetzner Robot → Settings → Notifications
2. Enable: "Storage Box usage >90%"
3. Email: havun22@gmail.com

---

## 🔧 Troubleshooting

### Probleem: "Connection refused" of "Timeout"

**Oplossing:**
```bash
# Check firewall (if enabled)
# Check port 23 is open on your server

# Test via command line:
telnet uXXXXXX.your-storagebox.de 23

# Should connect (Ctrl+C to exit)
```

---

### Probleem: "Permission denied" tijdens upload

**Oplossing:**
```bash
# Check directory permissions op Storage Box
sftp -P 23 uXXXXXX@uXXXXXX.your-storagebox.de

sftp> ls -la
# Directories should be drwxr-xr-x (755)

# If not, fix permissions:
sftp> chmod 755 havun-backups
```

---

### Probleem: "Disk quota exceeded"

**Oplossing:**
- Check storage usage in Hetzner Robot
- Cleanup old backups: `php artisan havun:backup:cleanup`
- Upgrade to larger Storage Box (BX40, BX60)

---

### Probleem: Upload zeer langzaam

**Oorzaken:**
1. Large backup files (>1 GB)
2. Slow internet connection
3. Server location (far from datacenter)

**Oplossingen:**
- Increase SFTP timeout in config (60s → 120s)
- Use compression (gzip -9)
- Consider CDN/edge location closer to Hetzner DC
- Check server bandwidth: `speedtest-cli`

---

## 📋 Checklist: Is je Storage Box production-ready?

- [ ] **Storage Box besteld en geactiveerd**
- [ ] **SFTP connectie getest (via command line/WinSCP)**
- [ ] **Directory structuur aangemaakt** (`/havun-backups/...`)
- [ ] **Laravel SFTP driver geïnstalleerd** (`composer require league/flysystem-sftp-v3`)
- [ ] **config/filesystems.php geconfigureerd** (hetzner-storage-box disk)
- [ ] **.env credentials toegevoegd** (HETZNER_STORAGE_*)
- [ ] **Upload/download getest via tinker**
- [ ] **SSH key authentication setup** (recommended)
- [ ] **Firewall configured** (optional maar aanbevolen)
- [ ] **Monitoring setup** (usage alerts)
- [ ] **Backup credentials veilig opgeslagen** (password manager)

---

## 📞 Support

**Hetzner Support:**
- Docs: https://docs.hetzner.com/robot/storage-box/
- Support: https://accounts.hetzner.com/support
- FAQ: https://docs.hetzner.com/robot/storage-box/faq/

**Havun Support:**
- Email: havun22@gmail.com
- Docs: D:\GitHub\HavunCore\*.md

---

**Je Storage Box is nu klaar voor productie backups!** 🚀

**Next Step:** Ga naar `BACKUP-IMPLEMENTATION-GUIDE.md` voor complete implementatie

---

**Hetzner Storage Box Setup Complete** ✅
