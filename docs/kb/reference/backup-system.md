---
title: Backup System Reference
type: reference
scope: havuncore
last_check: 2026-04-22
---

# Backup System Reference

> Complete backup architectuur voor alle Havun projecten.
> **Let op:** Config (`havun-backup.php`) bestaat, maar artisan commands (`havun:backup:run` etc.) zijn nog NIET geïmplementeerd. De 5-minuten hot backup voor kritieke databases is wel actief (cron-based).

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

## Hetzner Storage Box

**Account:** BX11 (1TB) - zie Hetzner facturen voor actuele prijzen

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

## Commands — ontwerp, niet gebouwd

> ⛔ **Geen van deze commando's bestaat.** Ze staan hier als beoogde vorm, niet als handleiding.
> `php artisan list` kent er geen enkele. **Wat er wél draait, is het shellscript**
> `/usr/local/bin/havun-backup.sh` uit roots crontab om 03:00 — zie
> [Wat er nu echt draait](#wat-er-nu-echt-draait) hieronder.
>
> Dat stond tot 06-08-2026 alleen in een waarschuwing bovenaan, twee schermen boven dit blok. De
> KB-auditor had het moeten melden en deed dat niet: een substring-bug verschoonde elk
> `havun:*`-commando. Zie `patterns/bewaking-die-niets-meet.md`.

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

## Wat er nu echt draait

| | |
|---|---|
| Script | `/usr/local/bin/havun-backup.sh` (700, root) |
| Planning | roots crontab, dagelijks 03:00 |
| Doel | Hetzner Storage Box `u510616.your-storagebox.de`, via sftp |
| Wachtwoord | `/etc/havun-backup.env` (600, root-only) — **niet** in het script; zie `reference/security-findings.md` |
| Controle | `qv:scan` → `backup-coverage`, leest het manifest dat het script wegschrijft |

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

Zie Hetzner Console/facturen voor actuele prijzen.

## Related

- [backup.md](../runbooks/backup.md) - Dagelijkse operaties
- Project-specifieke info in elk project
- `.claude/credentials.md` - Credentials (gitignored)
