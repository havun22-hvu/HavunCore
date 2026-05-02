---
title: Runbook: Backup
type: runbook
scope: havuncore
last_check: 2026-04-22
---

# Runbook: Backup

> Backup systeem beheren - ALLE Havun projecten

## Architectuur

```
Server Local (7 dagen)    →    Offsite (permanent)
/var/backups/havun/            Hetzner Storage Box
```

## Wat wordt gebackupt

**Production databases (CRITICAL — alle projecten):**
- havunadmin_production
- herdenkingsportaal_production
- infosyst
- safehavun
- studieplanner
- judo_toernooi
- havuncore
- havunclub_production

**Staging databases:**
- havunadmin_staging
- herdenkingsportaal_staging
- staging_judo_toernooi
- havunvet_staging

**Storage folders:**
- HavunAdmin: `/var/www/havunadmin/production/storage/invoices`
- Herdenkingsportaal: `/var/www/herdenkingsportaal/production/storage/app/public`

## Hot Backup (elke 5 minuten)

**Kritieke production databases** worden elke 5 minuten gebackupt:
- havunadmin_production
- herdenkingsportaal_production
- judo_toernooi

```bash
*/5 * * * * /usr/local/bin/havun-hotbackup.sh
```

**Locatie:** `/var/backups/havun/hot/`
**Retentie:** Laatste 2 uur (automatisch opgeschoond)

```bash
# Check hot backups
ssh root@188.245.159.115 "ls -lh /var/backups/havun/hot/"
```

## Dagelijkse backup (volledig)

Automatisch via cron om 03:00 - ALLE databases + storage:
```bash
0 3 * * * /usr/local/bin/havun-backup.sh
```

## Handmatige backup

```bash
ssh root@188.245.159.115
/usr/local/bin/havun-backup.sh
```

## Pre-migration backup

**VERPLICHT voor elke migration op production:**
```bash
ssh root@188.245.159.115
/usr/local/bin/havun-backup.sh
# Wacht tot "BACKUP COMPLETE" in log
tail -f /var/log/havun-backup.log
```

## Logs bekijken

```bash
ssh root@188.245.159.115 "tail -50 /var/log/havun-backup.log"
```

Verwachte output:
```
✓ havunadmin_production (2.1M)
✓ herdenkingsportaal_production (156K)
✓ infosyst (89K)
...
========== BACKUP COMPLETE (45M) ==========
```

## Offsite verificatie

```bash
sftp -P 23 u510616@u510616.your-storagebox.de
# Password: zie .claude/context.md

cd backups/2026/01
ls -la
```

## Restore

### Enkele database herstellen

```bash
ssh root@188.245.159.115

# Van lokale backup
gunzip -c /var/backups/havun/2026-01-24/production/havunadmin_production.sql.gz | mysql havunadmin_production

# Van Hetzner (eerst downloaden)
cd /tmp
sftp -P 23 u510616@u510616.your-storagebox.de
get backups/2026/01/2026-01-24/production/havunadmin_production.sql.gz
bye
gunzip -c havunadmin_production.sql.gz | mysql havunadmin_production
```

### Storage folder herstellen

```bash
cd /var/www/havunadmin/production/storage
tar -xzf /var/backups/havun/2026-01-24/production/havunadmin_storage.tar.gz
```

## Troubleshooting

### Backup faalt

```bash
# Check log
tail -50 /var/log/havun-backup.log

# Test database connectie
mysql -e "SELECT 1"

# Test Hetzner connectie
sshpass -p 'PASSWORD' sftp -P 23 u510616@u510616.your-storagebox.de
```

### Disk space

```bash
# Server backup dir
du -sh /var/backups/havun/*

# Hetzner usage
sftp -P 23 u510616@u510616.your-storagebox.de
df -h
```

## Lokale Backup (Windows Dev Machine)

Script: `D:\GitHub\HavunCore\tools\local-backup.ps1`

```powershell
# Handmatig uitvoeren (start Laragon eerst voor database backups)
powershell -ExecutionPolicy Bypass -File "D:\GitHub\HavunCore\tools\local-backup.ps1"

# Met aangepaste backup locatie
.\local-backup.ps1 -BackupPath "E:\Backups"
```

**Wat wordt gebackupt:**
- Databases (als Laragon draait): havunadmin, herdenkingsportaal, infosyst, safehavun, studieplanner, judotoernooi
- Storage folders: HavunAdmin facturen, Herdenkingsportaal uploads
- .env files van alle projecten

**Locatie:** `D:\Backups\Havun\YYYY-MM-DD\`

**Scheduled Task (optioneel):**
```powershell
# Als admin uitvoeren:
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-ExecutionPolicy Bypass -File D:\GitHub\HavunCore\tools\local-backup.ps1"
$trigger = New-ScheduledTaskTrigger -Daily -At "12:00"
Register-ScheduledTask -TaskName "HavunLocalBackup" -Action $action -Trigger $trigger -Description "Daily Havun backup"
```

## Monitoring (StatusView)

De havuncore-webapp StatusView toont automatisch de backup status in server mode:
- **Dagelijks** — leeftijd van de laatste backup (groen < 25u, geel >= 25u)
- **Hot (5 min)** — of de hot backup actief draait
- **Hetzner Offsite** — of de laatste upload geslaagd is
- **Databases** — hoeveel databases in de backup zitten, ontbrekende DBs als error

Endpoint: `GET /health/backup` op de webapp backend.

## Incident Log

### 15 maart 2026 — Backup script kapot sinds deploy
- **Oorzaak:** `awk {print }` ipv `awk '{print $5}'` + `set -e` crashte script na 1e DB
- **Gevolg:** Alleen havunadmin werd gebackupt, alle andere DBs ontbraken
- **Hot backup:** Kapot sinds 24 jan door `#\!` shebang fout
- **Fix:** awk quoting gefixt, `set -e` verwijderd, shebang gefixt, ontbrekende DBs toegevoegd
- **Impact:** Geen backup van herdenkingsportaal/judotoernooi/etc beschikbaar voor restore

## Project-level backup (Laravel artisan)

> **Sinds 2 mei 2026 (HP).** Aanvulling op de globale `/usr/local/bin/havun-backup.sh`.
> Per-project artisan-pipeline met encryption + offsite via Laravel.

### Pipeline

```
mysqldump → .sql.gz (local)
         → .sql.gz.enc (AES-256-CBC + pbkdf2, openssl CLI)
         → SFTP push naar Hetzner storage box
         → retention prune (>30 dagen lokaal)
```

### Configuratie (.env op productie)

```ini
BACKUP_ENCRYPTION_ENABLED=true
BACKUP_ENCRYPTION_PASSWORD=<sterk wachtwoord — bewaar in vault!>
BACKUP_OFFSITE_ENABLED=true
HETZNER_STORAGE_HOST=u510616.your-storagebox.de
HETZNER_STORAGE_USERNAME=u510616
HETZNER_STORAGE_PASSWORD=<storage box wachtwoord>
```

### Run

```bash
ssh root@188.245.159.115 "cd /var/www/herdenkingsportaal/production && php artisan havun:backup:run"

# Skip offsite voor lokale-only run:
php artisan havun:backup:run --no-offsite

# Skip prune voor 1-malige extra backup:
php artisan havun:backup:run --no-prune
```

### Restore van encrypted offsite-backup

```bash
# 1. Download .sql.gz.enc van Hetzner
sftp -P 23 u510616@u510616.your-storagebox.de
sftp> cd havun-backups
sftp> get herdenkingsportaal-prod-20260502-040000.sql.gz.enc /tmp/
sftp> bye

# 2. Decrypt (vereist BACKUP_ENCRYPTION_PASSWORD)
read -s -p "Encryption password: " ENC_PASS
echo "$ENC_PASS" > /tmp/enc-pass && chmod 600 /tmp/enc-pass
openssl enc -d -aes-256-cbc -pbkdf2 \
  -in /tmp/herdenkingsportaal-prod-20260502-040000.sql.gz.enc \
  -out /tmp/herdenkingsportaal-prod-20260502-040000.sql.gz \
  -pass file:/tmp/enc-pass
shred -u /tmp/enc-pass

# 3. Restore (BELANGRIJK: backup eerst de huidige DB!)
mysqldump --single-transaction herdenkingsportaal_prod | gzip > /tmp/pre-restore-$(date +%Y%m%d-%H%M%S).sql.gz
gunzip -c /tmp/herdenkingsportaal-prod-20260502-040000.sql.gz | mysql herdenkingsportaal_prod
```

### Restore van lokaal niet-encrypted backup

```bash
# Direct gunzip → mysql, geen openssl-stap
gunzip -c /var/www/herdenkingsportaal/production/storage/backups/herdenkingsportaal-prod-*.sql.gz \
  | mysql herdenkingsportaal_prod
```

### Tests

12 testen in `tests/Unit/BackupServiceTest.php` + `tests/Feature/HavunBackupRunPipelineTest.php` —
encryption-roundtrip + offsite-stream + pipeline-failure scenarios.

## Related

- [backup-system.md](../reference/backup-system.md) - Volledige referentie
- [.claude/context.md](../../../.claude/context.md) - Hetzner credentials
