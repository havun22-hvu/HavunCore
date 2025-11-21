# 🔒 Compliance-Proof Backup Architectuur

**Voor:** HavunAdmin (en andere Havun projecten)
**Versie:** 1.0.0
**Datum:** 21 november 2025

---

## 🎯 Compliance Eisen Samenvatting

### Belastingdienst (Nederland) - Wettelijke Vereisten

| Eis | Vereiste | Status |
|-----|----------|--------|
| **Bewaarplicht** | 7 jaar | 🔴 Kritiek |
| **Offsite Storage** | Niet op productie server | 🔴 Kritiek |
| **Integriteit** | SHA256 checksums | 🟡 Belangrijk |
| **Authenticiteit** | Audit trail | 🟡 Belangrijk |
| **Leesbaarheid** | Plain SQL dumps | ✅ Basis |
| **Toegankelijkheid** | Restore binnen 24h | 🟡 Belangrijk |
| **Encryptie** | At-rest encryption | 🟢 Aanbevolen |
| **Test Restore** | Quarterly tests | 🟡 Belangrijk |
| **Monitoring** | Backup success/failure alerts | 🟢 Aanbevolen |
| **Immutability** | Geen modificatie na creatie | 🟡 Belangrijk |

---

## 🏗️ Architectuur Overzicht

```
┌─────────────────────────────────────────────────────────────────────┐
│                         HAVUN BACKUP SYSTEEM                          │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────────┐      Daily 03:00 AM      ┌──────────────────────┐
│  HavunAdmin      │ ────────────────────────> │  Laravel Backup      │
│  Production      │                            │  (Spatie)            │
│                  │                            │                      │
│  • Database      │                            │  • MySQL Dump        │
│  • Invoices/PDFs │                            │  • File Archive      │
│  • Config        │                            │  • Compression       │
└──────────────────┘                            │  • SHA256 Checksums  │
                                                 │  • Optional Encrypt  │
                                                 └──────────┬───────────┘
                                                            │
                                    ┌───────────────────────┼───────────────────────┐
                                    │                       │                       │
                                    ▼                       ▼                       ▼
                          ┌──────────────────┐   ┌──────────────────┐   ┌──────────────────┐
                          │  Local Storage   │   │ Hetzner Storage  │   │  BackupLog       │
                          │  (Hot Backups)   │   │  Box (Offsite)   │   │  (Audit Trail)   │
                          │                  │   │                  │   │                  │
                          │  Last 30 days    │   │  7+ Years        │   │  • Timestamp     │
                          │  Quick restore   │   │  Compliance      │   │  • Size          │
                          │  /backups/hot/   │   │  /backups/archive│   │  • Checksum      │
                          └──────────────────┘   └──────────────────┘   │  • Status        │
                                                                          │  • Location      │
                                                                          └──────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                        RESTORE & MONITORING                              │
└─────────────────────────────────────────────────────────────────────────┘

     ┌────────────────┐         ┌────────────────┐         ┌────────────────┐
     │  Restore       │         │  Health Check  │         │  Notifications │
     │  Procedures    │         │  (Daily)       │         │                │
     │                │         │                │         │  • Email       │
     │  • Automated   │         │  • Age < 25h   │         │  • Slack       │
     │  • Manual      │         │  • Size OK     │         │  • Discord     │
     │  • Test (Q)    │         │  • Checksum OK │         │  • Log         │
     └────────────────┘         └────────────────┘         └────────────────┘
```

---

## 📦 Storage Strategie

### Tier 1: Hot Backups (Local Storage)

**Locatie:** `/backups/havunadmin/hot/`
**Doel:** Snelle disaster recovery
**Retention:** 30 dagen
**Medium:** Local SSD/NVMe

```
/backups/havunadmin/hot/
├── 2025-11-21-03-00-00.zip
├── 2025-11-21-03-00-00.zip.sha256
├── 2025-11-20-03-00-00.zip
├── 2025-11-20-03-00-00.zip.sha256
└── ... (last 30 days)
```

**Automatische cleanup:** Ja (na 30 dagen)

---

### Tier 2: Archive Backups (Hetzner Storage Box)

**Locatie:** Hetzner Storage Box `/havunadmin/archive/`
**Doel:** Compliance (7 jaar bewaarplicht)
**Retention:** 7+ jaar
**Medium:** Offsite storage

```
/havunadmin/archive/
├── 2025/
│   ├── 11/
│   │   ├── havunadmin-2025-11-21.zip
│   │   ├── havunadmin-2025-11-21.zip.sha256
│   │   └── ...
│   └── 12/
├── 2024/
│   ├── 01/ ... 12/
├── 2023/
└── ... (tot 2019 = 7 jaar terug)
```

**Automatische cleanup:** NOOIT (handmatig na 7+ jaar)

---

### Tier 3: Quarterly Test Backups

**Locatie:** `/backups/havunadmin/test-restores/`
**Doel:** Verificatie dat restore werkt
**Frequency:** Elke 3 maanden
**Retention:** 1 jaar

```
/backups/havunadmin/test-restores/
├── 2025-Q4-test.log      # Test restore logfile
├── 2025-Q4-SUCCESS       # Flag file
├── 2025-Q3-test.log
└── 2025-Q3-SUCCESS
```

---

## 🔐 Security & Integriteit

### SHA256 Checksums

**Bij backup creatie:**
```bash
# Automatisch door Laravel Backup
backup-file.zip         # Compressed backup
backup-file.zip.sha256  # SHA256 checksum

# Checksum format:
a1b2c3d4...  backup-file.zip
```

**Bij restore:**
```bash
# Verify checksum ALTIJD voor restore!
sha256sum -c backup-file.zip.sha256

# Output:
backup-file.zip: OK     # ✅ Safe to restore
backup-file.zip: FAILED # ❌ CORRUPTED - DO NOT USE!
```

---

### Encryptie (Optional maar Aanbevolen)

**Encryption Key Management:**
```env
# .env
BACKUP_ENCRYPTION_PASSWORD=super-secure-random-32-char-key-here-xyz123

# ⚠️ BEWAAR DIT WACHTWOORD VEILIG!
# Zonder dit wachtwoord zijn backups ONLEESBAAR
```

**Encrypted Backup Format:**
```
backup-file.zip            # Encrypted ZIP
backup-file.zip.sha256     # Checksum van encrypted file
backup-encryption-key.txt  # ⚠️ BEWAAR APART (H: drive, password manager)
```

---

## 📊 Backup Contents

### Volledige Backup Bevat:

```
havunadmin-2025-11-21.zip
├── database/
│   └── havunadmin_production.sql       # Plain SQL dump (NIET binary!)
├── storage/
│   ├── app/
│   │   ├── invoices/                   # ALLE factuur PDFs
│   │   │   ├── 2025-001.pdf
│   │   │   ├── 2025-002.pdf
│   │   │   └── ...
│   │   └── exports/                    # Tax exports
│   └── logs/
│       └── laravel.log                 # Laatste 7 dagen
├── .env.backup                         # Environment variabelen
└── backup-manifest.json                # Metadata (checksums, sizes, datum)
```

**Total Size (schatting):**
- Database: ~5-50 MB
- PDFs: ~10-500 MB (groeit per jaar)
- **Total per backup:** ~50-600 MB

**7 Jaar Storage (schatting):**
- Daily backups: 365 × 7 = 2.555 backups
- Average 100 MB per backup
- **Total:** ~255 GB (for 7 years)

---

## 🔄 Backup Schedule

### Dagelijks (Productie)

```bash
# Cron: Daily at 03:00 AM
0 3 * * * cd /var/www/havunadmin/production && php artisan backup:run

# Wat gebeurt er?
1. MySQL dump maken
2. Invoices/PDFs archiveren
3. Compressie (gzip)
4. SHA256 checksum berekenen
5. Upload naar local + Hetzner Storage Box
6. Log naar BackupLog database table
7. Cleanup oude hot backups (>30 dagen)
8. Health check (vorige backup OK?)
9. Stuur notificatie (success/failure)
```

---

### Wekelijks (Extra Verificatie)

```bash
# Cron: Sunday at 04:00 AM
0 4 * * 0 cd /var/www/havunadmin/production && php artisan backup:monitor

# Wat gebeurt er?
1. Check of laatste backup < 25 uur oud is
2. Check of backup size redelijk is (niet 0 bytes, niet gigantisch)
3. Verify SHA256 checksums
4. Test of Hetzner Storage Box toegankelijk is
5. Generate weekly backup report (email naar havun22@gmail.com)
```

---

### Quarterly (Test Restore)

```bash
# Handmatig: 1e maandag van elk kwartaal (Q1, Q2, Q3, Q4)
# Q1: Januari, Q2: April, Q3: Juli, Q4: Oktober

# Test restore procedure:
php artisan backup:test-restore --backup=latest

# Wat gebeurt er?
1. Download laatste backup van Hetzner Storage Box
2. Verify SHA256 checksum
3. Extract naar test environment
4. Restore database naar test DB
5. Verify record counts (invoices, transactions, etc.)
6. Generate test report
7. Save report to /backups/test-restores/YYYY-QX-test.log
8. Email report naar havun22@gmail.com
```

---

## 🛠️ Implementatie Stack

### Laravel Backup (Spatie)

```bash
composer require spatie/laravel-backup
```

**Waarom Spatie Laravel Backup?**
- ✅ Battle-tested (gebruikt door duizenden Laravel apps)
- ✅ Ondersteunt multiple storage drivers (local, S3, SFTP)
- ✅ Built-in monitoring en notifications
- ✅ Health checks
- ✅ Easy configuration
- ✅ Extensible (custom cleanup strategies)

---

### Storage Drivers

**Tier 1 (Local):**
- Laravel Filesystem (local driver)

**Tier 2 (Offsite - Hetzner Storage Box):**
- SFTP driver (Flysystem)
- Alternatief: S3-compatible (Backblaze B2, Wasabi)

**Configuration:**
```php
// config/filesystems.php
'disks' => [
    'hetzner-storage-box' => [
        'driver' => 'sftp',
        'host' => 'uXXXXXX.your-storagebox.de',
        'username' => 'uXXXXXX',
        'password' => env('HETZNER_STORAGE_PASSWORD'),
        'root' => '/havunadmin/archive',
        'timeout' => 30,
    ],
],
```

---

## 📝 Audit Trail & Logging

### BackupLog Database Table

```sql
CREATE TABLE backup_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    backup_name VARCHAR(255) NOT NULL,           -- "havunadmin-2025-11-21"
    backup_date DATETIME NOT NULL,                -- 2025-11-21 03:00:00
    backup_size BIGINT UNSIGNED NOT NULL,         -- Size in bytes
    backup_checksum VARCHAR(64) NOT NULL,         -- SHA256 hash
    disk_local BOOLEAN NOT NULL DEFAULT 1,        -- Stored locally?
    disk_offsite BOOLEAN NOT NULL DEFAULT 1,      -- Stored offsite?
    status ENUM('success', 'failed', 'partial') NOT NULL,
    error_message TEXT NULL,                      -- If failed
    notification_sent BOOLEAN NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_backup_date (backup_date),
    INDEX idx_status (status),
    INDEX idx_backup_name (backup_name)
);
```

**Gebruik:**
```php
// Log elke backup
BackupLog::create([
    'backup_name' => 'havunadmin-2025-11-21',
    'backup_date' => now(),
    'backup_size' => 52428800, // 50 MB
    'backup_checksum' => 'a1b2c3d4...',
    'disk_local' => true,
    'disk_offsite' => true,
    'status' => 'success',
]);

// Query laatste 10 backups
BackupLog::latest()->limit(10)->get();

// Check backup status vandaag
$todayBackup = BackupLog::whereDate('backup_date', today())->first();
if (!$todayBackup || $todayBackup->status !== 'success') {
    // ALERT: No successful backup today!
}
```

---

## 🚨 Monitoring & Alerts

### Notification Channels

**Email (Primair):**
- Naar: `havun22@gmail.com`
- Bij: Backup success (1x/dag), Backup failure (immediate), Weekly report

**Slack (Optioneel):**
- Webhook naar Havun workspace
- Channel: #havunadmin-backups

**Discord (Optioneel):**
- Webhook naar Havun server
- Channel: #backup-alerts

---

### Alert Types

| Event | Severity | Notificatie |
|-------|----------|-------------|
| **Backup Success** | Info | Email (daily digest) |
| **Backup Failed** | 🔴 Critical | Email (immediate) + Slack |
| **Backup Size Abnormal** | 🟡 Warning | Email (immediate) |
| **Offsite Upload Failed** | 🔴 Critical | Email + Slack |
| **Checksum Mismatch** | 🔴 Critical | Email + Slack |
| **No Backup >25h** | 🔴 Critical | Email + Slack |
| **Test Restore Failed** | 🟡 Warning | Email (immediate) |

---

### Health Check Command

```bash
php artisan backup:monitor

# Output example:
✅ havunadmin_production
   Latest backup: 18 hours ago (OK)
   Backup size: 52.5 MB (OK)
   Checksum: Verified (OK)
   Offsite storage: Accessible (OK)

❌ havunadmin_staging
   Latest backup: 30 hours ago (TOO OLD!)
   Action required: Check cron job
```

---

## 🔄 Restore Procedures

### Scenario 1: Quick Restore (Laatste Backup)

**Use Case:** Data corruption, accidental deletion
**Time:** ~15 minuten
**Source:** Local hot backup

```bash
# 1. Stop application (maintenance mode)
php artisan down

# 2. Find latest backup
ls -lh /backups/havunadmin/hot/

# 3. Verify checksum
sha256sum -c /backups/havunadmin/hot/2025-11-21-03-00-00.zip.sha256

# 4. Extract backup
unzip /backups/havunadmin/hot/2025-11-21-03-00-00.zip -d /tmp/restore

# 5. Restore database
mysql -u root -p havunadmin_production < /tmp/restore/database/havunadmin_production.sql

# 6. Restore files
rsync -av /tmp/restore/storage/app/invoices/ /var/www/havunadmin/production/storage/app/invoices/

# 7. Clear caches
php artisan cache:clear
php artisan config:clear

# 8. Verify restoration
php artisan backup:verify-restore

# 9. Bring application back up
php artisan up

# 10. Log restore event
php artisan backup:log-restore --backup=2025-11-21-03-00-00
```

---

### Scenario 2: Archive Restore (Oude Backup)

**Use Case:** Belastingcontrole, accountant verzoek
**Time:** ~30-60 minuten
**Source:** Hetzner Storage Box (7 jaar archief)

```bash
# 1. Download van Hetzner Storage Box
sftp uXXXXXX@uXXXXXX.your-storagebox.de
cd /havunadmin/archive/2023/05
get havunadmin-2023-05-15.zip
get havunadmin-2023-05-15.zip.sha256
exit

# 2. Verify checksum
sha256sum -c havunadmin-2023-05-15.zip.sha256

# 3. Extract naar read-only environment
unzip havunadmin-2023-05-15.zip -d /var/restore/2023-05-15

# 4. Import naar readonly database
mysql -u root -p -e "CREATE DATABASE havunadmin_archive_2023_05_15;"
mysql -u root -p havunadmin_archive_2023_05_15 < /var/restore/2023-05-15/database/havunadmin_production.sql

# 5. Setup read-only web interface (Laravel)
# (Aparte Laravel instantie met readonly DB connection)

# 6. Provide access to accountant/belastingdienst
# URL: https://archive-2023-05-15.havunadmin.havun.nl
```

---

### Scenario 3: Disaster Recovery (Complete Server Loss)

**Use Case:** Server crash, datacenter failure
**Time:** ~2-4 uur
**Source:** Hetzner Storage Box

```bash
# Nieuwe server provisioning
1. Hetzner VPS bestellen (zelfde specs als productie)
2. Ubuntu 22.04 installeren
3. LAMP stack setup
4. Laravel dependencies installeren
5. Git repository clonen
6. Download laatste backup van Hetzner Storage Box
7. Restore volgens Scenario 1 procedure
8. Update DNS (havunadmin.havun.nl → nieuw IP)
9. SSL certificaat genereren (Let's Encrypt)
10. Test volledige applicatie functionaliteit
11. Notify gebruikers van nieuwe IP (indien nodig)
```

---

## 📅 Quarterly Test Restore Procedure

**Wanneer:** Eerste maandag van elk kwartaal
**Duur:** ~1 uur
**Doel:** Verify dat backups werkbaar zijn

### Checklist:

```markdown
# Quarterly Backup Test - Q4 2025 (Oktober)

Datum: 2025-10-07
Tester: Havun
Backup: havunadmin-2025-10-06.zip

## Pre-Test
- [ ] Notificeer team (maintenance window)
- [ ] Download laatste backup van offsite storage
- [ ] Verify checksum

## Test Restore
- [ ] Extract backup naar test environment
- [ ] Restore database naar test DB
- [ ] Verify table counts match

## Verification
- [ ] Login naar test environment werkt
- [ ] Dashboard laadt correct
- [ ] Invoices zijn leesbaar
- [ ] PDFs zijn downloadbaar en intact
- [ ] Reports genereren zonder errors
- [ ] Database constraints intact

## Record Counts
- Invoices: _____ (expected: ~_____)
- Transactions: _____ (expected: ~_____)
- Customers: _____ (expected: ~_____)
- Suppliers: _____ (expected: ~_____)

## Post-Test
- [ ] Cleanup test environment
- [ ] Document bevindingen
- [ ] Update restore procedures (indien issues)
- [ ] Email report naar havun22@gmail.com
- [ ] Save test log to /backups/test-restores/2025-Q4-test.log

## Result: ✅ PASS / ❌ FAIL

Notes:
_______________________________________________
```

---

## 💰 Cost Estimation

### Hetzner Storage Box

**BX10 (100 GB):** €3,81/maand
**BX20 (1 TB):** €9,52/maand
**BX30 (5 TB):** €19,04/maand

**Aanbeveling voor HavunAdmin:**
- Start: BX10 (100 GB) - €3,81/maand
- Over 3-5 jaar: Upgrade naar BX20 indien nodig

**7-Year Total Cost:**
- €3,81 × 12 × 7 = ~€320 (voor compliance)

---

### Alternatieve Offsite Storage

| Provider | Prijs | Pro | Con |
|----------|-------|-----|-----|
| **Hetzner Storage Box** | €3,81/100GB | EU datacenter, GDPR compliant | SFTP only |
| **Backblaze B2** | $5/TB | S3-compatible, cheap | US-based |
| **Wasabi** | $6/TB | Fast, no egress fees | US-based |
| **AWS S3 Glacier** | $4/TB | Ultra cheap (archive) | Slow retrieval ($$$) |

**Aanbeveling:** Hetzner Storage Box (EU compliance, betrouwbaar, betaalbaar)

---

## 📚 Compliance Checklist

### Voor Productie Launch

- [ ] **Spatie Laravel Backup geïnstalleerd**
- [ ] **Hetzner Storage Box account aangemaakt**
- [ ] **SFTP credentials geconfigureerd**
- [ ] **Custom ComplianceCleanupStrategy (7 jaar)**
- [ ] **BackupLog database table**
- [ ] **Cron job dagelijks 03:00**
- [ ] **Email notificaties geconfigureerd**
- [ ] **Eerste test restore succesvol**
- [ ] **Backup encryption key veilig opgeslagen**
- [ ] **Documentatie compleet**

---

### Periodieke Checks

**Dagelijks (automatisch):**
- [ ] Backup run succesvol?
- [ ] Checksum verified?
- [ ] Offsite upload OK?

**Wekelijks (automatisch):**
- [ ] Health check passed?
- [ ] Backup size normaal?
- [ ] Storage space OK?

**Maandelijks (handmatig):**
- [ ] Review backup logs
- [ ] Check error rates
- [ ] Update documentatie indien nodig

**Quarterly (handmatig):**
- [ ] Test restore
- [ ] Document test resultaat
- [ ] Update procedures indien issues
- [ ] Archiveer test log

**Jaarlijks (handmatig):**
- [ ] Review 7-year archive (delete >7 jaar)
- [ ] Audit backup compliance
- [ ] Update cost estimates
- [ ] Review en update backup strategie

---

## 🎓 Best Practices

### DO ✅

1. **Automatiseer alles** - Cron jobs, geen handmatig backuppen
2. **Test restore regelmatig** - Quarterly tests zijn KRITIEK
3. **Monitor proactief** - Daily health checks
4. **Log alles** - BackupLog voor audit trail
5. **Verify checksums** - ALTIJD voor restore
6. **Encrypt sensitieve data** - Recommended voor PDFs met klantgegevens
7. **Multiple storage locations** - Local + offsite
8. **Document procedures** - Voor disaster recovery
9. **Notify on failure** - Immediate alerts
10. **Keep encryption keys safe** - Separate from backups

---

### DON'T ❌

1. **NOOIT automatisch verwijderen van archief backups** - 7 jaar bewaarplicht!
2. **NOOIT backups op zelfde server als productie** - Offsite is verplicht
3. **NOOIT binary database backups** - Plain SQL voor leesbaarheid
4. **NOOIT backups zonder checksums** - Integriteit verificatie is kritiek
5. **NOOIT restore zonder checksum verify** - Corrupted backup kan meer schade doen
6. **NOOIT encryption keys in git** - .env only, never commit
7. **NOOIT backups uitstellen** - Dagelijks is minimum
8. **NOOIT restore procedures ongetest laten** - Test quarterly!
9. **NOOIT single point of failure** - Altijd multiple copies
10. **NOOIT backups negeren bij deployment** - Include in deployment checklist

---

## 🔗 Gerelateerde Documentatie

### HavunAdmin Specifiek
- `BELASTINGDIENST-COMPLIANCE.md` - Wettelijke eisen
- `COMPLIANCE-QUICK-REFERENCE.md` - Quick reference
- `AUDIT-TRAIL-HANDLEIDING.md` - Audit logging
- `PDF-INTEGRITY-HANDLEIDING.md` - PDF checksums

### Te Maken (Implementatie)
- `BACKUP-IMPLEMENTATION-GUIDE.md` - Step-by-step implementatie
- `BACKUP-RESTORE-PROCEDURES.md` - Detailed restore procedures
- `BACKUP-MONITORING-SETUP.md` - Monitoring configuratie
- `HETZNER-STORAGE-BOX-SETUP.md` - Storage Box configuratie

---

## 📞 Support & Vragen

**Bij problemen met backups:**
1. Check BackupLog database table voor errors
2. Check Laravel logs: `storage/logs/laravel.log`
3. Test Hetzner Storage Box connectie: `sftp uXXXXXX@...`
4. Verify cron job runt: `crontab -l`
5. Manual backup test: `php artisan backup:run`

**Contact:**
- Email: havun22@gmail.com
- Documentatie: D:\GitHub\HavunCore\*.md

---

**Architectuur Versie:** 1.0.0
**Status:** 📋 Design Complete - Ready for Implementation
**Laatst bijgewerkt:** 21 november 2025
**Next Step:** Implementatie in HavunAdmin

---

🔒 **Compliance-Proof Backup = Business Continuity + Legal Protection**
