# Quick Reference - Alle Credentials & Commands

**Voor Claude AI - snel opzoeken**

---

## SSH Toegang

```bash
ssh root@188.245.159.115
```

---

## Databases

| Project | Database | User | Password |
|---------|----------|------|----------|
| HavunCore | havuncore | havuncore | HavunCore2025 |
| HavunVet | havunvet_staging | havunvet | aAOon9yeBuNTjJdKt3Q |

## Admin Logins

| Project | URL | Email | Password |
|---------|-----|-------|----------|
| BertvanderHeide | bertvanderheide.havun.nl/admin | info@bertvanderheide.nl | BertAdmin2025! |

---

## URLs

| Project | Staging | Production |
|---------|---------|------------|
| HavunCore | - | havuncore.havun.nl |
| HavunAdmin | staging subdir | havunadmin.havun.nl |
| Herdenkingsportaal | staging subdir | herdenkingsportaal.nl |
| BertvanderHeide | bertvanderheide.havun.nl | www.bertvanderheide.nl |

---

## Hetzner Storage Box (Backups)

```
Host:     u510616.your-storagebox.de
Port:     23 (SFTP)
User:     u510616
Password: G63^C@GB&PD2#jCl#1uj
```

```bash
sftp -P 23 u510616@u510616.your-storagebox.de
```

---

## Webapp Login (havuncore.havun.nl)

```
Email:    henkvu@gmail.com
Password: T3@t@Do2AEPKJBlI2Ltg
```

---

## Server Commands

### Nginx
```bash
nginx -t && systemctl reload nginx
```

### SSL Certificaat
```bash
certbot --nginx -d <domain> --non-interactive --agree-tos --email havun22@gmail.com
```

### MySQL Database Aanmaken
```bash
mysql -e "CREATE DATABASE <name>; CREATE USER '<user>'@'localhost' IDENTIFIED BY '<pass>'; GRANT ALL ON <name>.* TO '<user>'@'localhost'; FLUSH PRIVILEGES;"
```

### Laravel Deploy
```bash
cd /var/www/<project>
git pull origin master
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Task Queue Pollers
```bash
systemctl status claude-task-poller@havunadmin
systemctl status claude-task-poller@herdenkingsportaal
journalctl -u claude-task-poller@havunadmin -f
```

### Backup Health
```bash
cd /var/www/herdenkingsportaal/production
php artisan havun:backup:health
```

### PM2 (Webapp Backend)
```bash
pm2 status
pm2 logs havuncore-backend
pm2 restart havuncore-backend
```

---

## Git Bare Repo Setup (nieuw project)

```bash
# Op server
mkdir -p /var/www/<project>/{staging,production}
git init --bare /var/www/<project>/repo.git

# Post-receive hook
cat > /var/www/<project>/repo.git/hooks/post-receive << 'EOF'
#!/bin/bash
TARGET="/var/www/<project>/staging"
GIT_DIR="/var/www/<project>/repo.git"
git --work-tree=$TARGET --git-dir=$GIT_DIR checkout -f main
cd $TARGET && composer install --no-dev 2>/dev/null
EOF
chmod +x /var/www/<project>/repo.git/hooks/post-receive

# Lokaal
git remote add staging root@188.245.159.115:/var/www/<project>/repo.git
git push staging main
```

---

## Nginx Vhost Template (Laravel)

```nginx
server {
    listen 80;
    server_name <domain>;
    root /var/www/<project>/staging/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## Nieuw Project Checklist

- [ ] Directory structuur aanmaken
- [ ] MySQL database + user
- [ ] Nginx vhost
- [ ] SSL certificaat
- [ ] Git bare repo (optioneel)
- [ ] DNS record (of wildcard *.havun.nl)
- [ ] Project docs in `docs/projects/`
- [ ] Toevoegen aan INDEX.md

---

*Laatst bijgewerkt: 2025-12-02*
