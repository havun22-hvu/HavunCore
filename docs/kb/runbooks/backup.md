# Runbook: Backup

> Backup systeem beheren - ALLE Havun projecten

## Architectuur

```
Server Local (7 dagen)    →    Offsite (permanent)
/var/backups/havun/            Hetzner Storage Box
```

## Wat wordt gebackupt

**Production databases (CRITICAL):**
- havunadmin_production
- herdenkingsportaal_production
- infosyst
- safehavun
- studieplanner
- judo_toernooi

**Staging databases:**
- havunadmin_staging
- herdenkingsportaal_staging
- staging_judo_toernooi

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

## Related

- [backup-system.md](../reference/backup-system.md) - Volledige referentie
- [.claude/context.md](/.claude/context.md) - Hetzner credentials
