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

## Dagelijkse backup

Automatisch via cron om 03:00:
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

## Related

- [backup-system.md](../reference/backup-system.md) - Volledige referentie
- [.claude/context.md](/.claude/context.md) - Hetzner credentials
