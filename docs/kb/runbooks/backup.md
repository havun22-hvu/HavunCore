# Runbook: Backup

> Backup systeem beheren

## Architectuur

```
Local (30 dagen)     →     Offsite (7 jaar)
/storage/backups/          Hetzner Storage Box
```

## Dagelijkse backup

Automatisch via cron om 03:00:
```bash
0 3 * * * cd /var/www/herdenkingsportaal/production && php artisan havun:backup:run
```

## Health check

```bash
ssh root@SERVER_IP (zie context.md)
cd /var/www/herdenkingsportaal/production
php artisan havun:backup:health
```

Verwachte output:
```
✅ havunadmin (CRITICAL)
   Last backup: 2025-11-22 22:24

✅ herdenkingsportaal (CRITICAL)
   Last backup: 2025-11-22 22:24
```

## Handmatige backup

```bash
php artisan havun:backup:run
```

## Lijst backups

```bash
php artisan havun:backup:list
```

## Offsite verificatie

```bash
sftp -P 23 u510616@u510616.your-storagebox.de
# Password: zie context.md

cd havunadmin/archive/2025/12
ls -la
```

## Troubleshooting

### "Offsite: ❌"

1. Test SFTP connectie
2. Check `'root' => ''` in filesystems.php
3. Check SSH host key:
   ```bash
   ssh-keyscan -p 23 -H u510616.your-storagebox.de >> ~/.ssh/known_hosts
   ```

### Logs bekijken

```bash
tail -f /var/log/havun-backup.log
tail -f /var/www/herdenkingsportaal/production/storage/logs/laravel.log | grep backup
```

## Related

- [server.md](../reference/server.md) - Server details
- [.claude/context.md](/.claude/context.md) - Hetzner credentials
