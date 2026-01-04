# Backup System Reference

> Complete backup architectuur voor alle Havun projecten.

## Architectuur

```
Local (30 dagen)     →     Offsite (7 jaar)
/backups/hot/              Hetzner Storage Box
```

### Per Project

| Project | Schedule | Prioriteit | Retention |
|---------|----------|------------|-----------|
| HavunAdmin | Daily 03:00 | CRITICAL | 7 jaar |
| Herdenkingsportaal | Daily 04:00 | CRITICAL | 7 jaar |
| Studieplanner | Daily 05:00 | MEDIUM | 1 jaar |
| HavunCore | Weekly zo 05:00 | HIGH | 3 jaar |
| havun-mcp | Weekly zo 06:00 | MEDIUM | 1 jaar |

## Hetzner Storage Box

**Account:** BX30 (5 TB) - €19,04/maand

```
Host: u510616.your-storagebox.de
Port: 23 (SFTP)
User: u510616
```

### Directory structuur

```
/havunadmin/
  ├── hot/           # Laatste 30 dagen
  └── archive/2025/  # Langdurig
/herdenkingsportaal/
  ├── hot/
  └── archive/2025/
```

### Laravel config

```php
// config/filesystems.php
'hetzner-storage-box' => [
    'driver' => 'sftp',
    'host' => env('HETZNER_STORAGE_HOST'),
    'port' => 23,
    'username' => env('HETZNER_STORAGE_USERNAME'),
    'password' => env('HETZNER_STORAGE_PASSWORD'),
    'root' => '',  // Belangrijk: leeg laten!
    'timeout' => 60,
],
```

## Commands

```bash
# Backup uitvoeren
php artisan havun:backup:run
php artisan havun:backup:run --project=havunadmin

# Status checken
php artisan havun:backup:health
php artisan havun:backup:list

# Restore
php artisan havun:backup:restore --project=havunadmin --latest
php artisan havun:backup:restore --project=havunadmin --date=2025-11-21

# Cleanup
php artisan havun:backup:cleanup --all --dry-run
```

## Compliance

### Belastingdienst eisen

| Eis | Implementatie |
|-----|---------------|
| 7 jaar bewaren | Hetzner archive, NOOIT auto-delete |
| Offsite | Hetzner Storage Box (EU) |
| Integriteit | SHA256 checksums |
| Audit trail | backup_logs database tabel |
| Leesbaarheid | Plain SQL dumps |

### Wat wordt gebackupt

**HavunAdmin (fiscaal kritiek):**
- Database (facturen, klanten, BTW)
- PDF facturen (`storage/invoices/`)
- Config files

**Herdenkingsportaal (GDPR):**
- Database (profielen, monumenten)
- Uploads (foto's)
- Config files

## Monitoring

### Cron jobs

```bash
# /etc/crontab
0 3 * * * cd /var/www/herdenkingsportaal/production && php artisan havun:backup:run
0 * * * * cd /var/www/herdenkingsportaal/production && php artisan havun:backup:health
```

### Health check output

```
✅ havunadmin (CRITICAL)
   Last backup: 18 hours ago
   Offsite: ✅ Accessible

❌ havun-mcp (MEDIUM)
   Last backup: 9 days ago
   ⚠️ WARNING: Expected weekly backup
```

## Troubleshooting

### "Offsite: ❌"

1. Test SFTP connectie:
   ```bash
   sftp -P 23 u510616@u510616.your-storagebox.de
   ```

2. Check `'root' => ''` in filesystems.php (moet leeg zijn!)

3. SSH host key toevoegen:
   ```bash
   ssh-keyscan -p 23 -H u510616.your-storagebox.de >> ~/.ssh/known_hosts
   ```

### Backup faalt

```bash
# Check logs
tail -f storage/logs/laravel.log | grep backup

# Test database connectie
php artisan tinker
>>> DB::connection()->getPdo();

# Test Storage Box
>>> Storage::disk('hetzner-storage-box')->files('test');
```

## Kosten

| Item | Kosten |
|------|--------|
| Hetzner BX30 (5TB) | €19,04/maand |
| Per jaar | €228,48 |
| 7 jaar compliance | €1.599,36 |
| Per project/jaar | ~€57 |

## Related

- [backup.md](../runbooks/backup.md) - Dagelijkse operaties
- Project-specifieke info in elk project
- [context.md](/.claude/context.md) - Credentials
