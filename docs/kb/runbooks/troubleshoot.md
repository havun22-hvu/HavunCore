# Runbook: Troubleshooting

> Veelvoorkomende problemen oplossen

## Server niet bereikbaar

```bash
# Test SSH
ssh root@188.245.159.115

# Als dat faalt, check Hetzner console:
# https://console.hetzner.com
```

## Laravel errors

```bash
# Logs bekijken
tail -100 /var/www/herdenkingsportaal/production/storage/logs/laravel.log

# Config cache clearen
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

## Webapp niet bereikbaar

```bash
# Check PM2 status
pm2 status

# Restart backend
pm2 restart havuncore-backend

# Logs bekijken
pm2 logs havuncore-backend
```

## Nginx errors

```bash
# Check config syntax
nginx -t

# Reload nginx
systemctl reload nginx

# Logs
tail -f /var/log/nginx/error.log
```

## Database problemen

```bash
# Test MySQL connectie
mysql -u havuncore -p -e "SELECT 1"

# Check running queries
mysql -u root -p -e "SHOW PROCESSLIST"
```

## Task Queue stopt

```bash
# Check poller status
systemctl status claude-task-poller@havunadmin

# Restart
systemctl restart claude-task-poller@havunadmin

# Logs
journalctl -u claude-task-poller@havunadmin -f
```

## SFTP/Backup faalt

```bash
# Test connectie
sftp -P 23 u510616@u510616.your-storagebox.de

# SSH host key toevoegen
ssh-keyscan -p 23 -H u510616.your-storagebox.de >> ~/.ssh/known_hosts
```

## Disk space vol

```bash
# Check disk usage
df -h

# Grote files vinden
du -sh /var/www/* | sort -h

# Oude logs opruimen
find /var/log -name "*.log" -mtime +30 -delete
```
