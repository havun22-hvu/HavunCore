# 🤖 Claude SSH Access - Havun Server Guide

**Datum:** 19 november 2025
**Voor:** Claude Code assistentie aan alle Havun projecten
**Server:** Hetzner VPS (188.245.159.115)

---

## 🎯 Doel van dit Document

Dit document beschrijft hoe Claude (AI assistant) SSH toegang gebruikt om Havun projecten te helpen met:
- SSL certificaten fixen
- Server configuraties controleren
- Deployment issues oplossen
- Log files analyseren
- Services herstarten

**BELANGRIJK:** Claude heeft read-only + safe operations toegang. Destructieve acties vereisen expliciete toestemming.

---

## 🔑 SSH Toegang

### Server Details

```yaml
Server IP:        188.245.159.115
Hostname:         havun (was: herdenkingsportaal-prod)
Provider:         Hetzner CX22 VPS
OS:               Ubuntu 22.04 LTS
SSH User:         root
Authentication:   SSH Key (pre-configured)
```

### SSH Command Format

```bash
ssh root@188.245.159.115 "COMMAND"
```

**Voorbeelden:**
```bash
# Test connection
ssh root@188.245.159.115 "whoami && hostname"

# Check Apache status
ssh root@188.245.159.115 "systemctl status apache2 | head -10"

# View logs
ssh root@188.245.159.115 "tail -20 /var/log/apache2/error.log"
```

---

## 📁 Project Locaties op Server

### Overzicht

```
/var/www/
├── havuncore.havun.nl/          # HavunCore Voice Webapp
├── bertvdh.havun.nl/            # Bert van den Hoven site
├── havunadmin.havun.nl/         # HavunAdmin dashboard
├── staging.havunadmin.havun.nl/ # HavunAdmin staging
├── vpdupdate.havun.nl/          # VPD Update tool
├── testsite.havun.nl/           # Test environment
├── production/                   # Herdenkingsportaal production
└── staging/                      # Herdenkingsportaal staging
```

### Per Project Details

#### 1. HavunCore (havuncore.havun.nl)

```yaml
Location:     /var/www/havuncore.havun.nl/
Type:         Node.js + React PWA
Web Server:   Apache2 (reverse proxy to PM2)
SSL:          Let's Encrypt (havuncore.havun.nl)
Logs:         /var/log/apache2/havuncore.havun.nl-*.log
Config:       /etc/apache2/sites-available/havuncore.havun.nl*.conf

PM2:
  - Name: havuncore-backend
  - Script: backend/src/server.js
  - Port: 3001

Common Tasks:
  - Check PM2: pm2 status
  - View logs: pm2 logs havuncore-backend
  - Restart: pm2 restart havuncore-backend
  - SSL check: certbot certificates | grep havuncore
```

#### 2. Herdenkingsportaal Production

```yaml
Location:     /var/www/production/
Type:         Laravel PHP
Web Server:   Apache2
SSL:          Let's Encrypt (production.herdenkingsportaal.nl)
Logs:         /var/log/apache2/herdenkingsportaal-*.log
Config:       /etc/apache2/sites-available/production.herdenkingsportaal.nl*.conf

Common Tasks:
  - Clear cache: php artisan cache:clear
  - View logs: tail /var/www/production/storage/logs/laravel.log
  - Check config: php artisan config:show
```

#### 3. HavunAdmin

```yaml
Location:     /var/www/havunadmin.havun.nl/
Type:         Laravel PHP
SSL:          Let's Encrypt (havunadmin.havun.nl)
Staging:      /var/www/staging.havunadmin.havun.nl/
```

---

## 🔧 Common SSH Operations

### 1. Check System Status

```bash
# Overall system health
ssh root@188.245.159.115 "uptime && free -h && df -h"

# Active services
ssh root@188.245.159.115 "systemctl list-units --type=service --state=running | grep -E 'apache2|mysql|php|pm2'"

# Memory usage
ssh root@188.245.159.115 "ps aux --sort=-%mem | head -10"
```

### 2. Web Server Operations

#### Apache2 (Most Projects)

```bash
# Status check
ssh root@188.245.159.115 "systemctl status apache2"

# Test configuration
ssh root@188.245.159.115 "apache2ctl configtest"

# Reload (safe, no downtime)
ssh root@188.245.159.115 "systemctl reload apache2"

# Restart (brief downtime)
ssh root@188.245.159.115 "systemctl restart apache2"

# View error logs
ssh root@188.245.159.115 "tail -50 /var/log/apache2/error.log"

# List virtual hosts
ssh root@188.245.159.115 "apache2ctl -S"
```

### 3. SSL Certificate Operations

```bash
# List all certificates
ssh root@188.245.159.115 "certbot certificates"

# Check specific certificate
ssh root@188.245.159.115 "certbot certificates | grep -A10 'havuncore'"

# Verify certificate details
ssh root@188.245.159.115 "openssl x509 -in /etc/letsencrypt/live/havuncore.havun.nl/fullchain.pem -noout -subject -dates"

# Test SSL from server
ssh root@188.245.159.115 "echo | openssl s_client -servername havuncore.havun.nl -connect localhost:443 2>/dev/null | openssl x509 -noout -subject"

# Renew certificates (dry-run)
ssh root@188.245.159.115 "certbot renew --dry-run"

# Force renew specific certificate
ssh root@188.245.159.115 "certbot renew --cert-name havuncore.havun.nl --force-renewal"
```

### 4. PM2 Operations (Node.js Projects)

```bash
# List all PM2 processes
ssh root@188.245.159.115 "pm2 list"

# View specific app status
ssh root@188.245.159.115 "pm2 describe havuncore-backend"

# View logs (last 50 lines)
ssh root@188.245.159.115 "pm2 logs havuncore-backend --lines 50 --nostream"

# Restart app
ssh root@188.245.159.115 "pm2 restart havuncore-backend"

# Reload app (zero-downtime)
ssh root@188.245.159.115 "pm2 reload havuncore-backend"

# Monitor resources
ssh root@188.245.159.115 "pm2 monit"
```

### 5. Database Operations

```bash
# MySQL status
ssh root@188.245.159.115 "systemctl status mysql"

# Show databases
ssh root@188.245.159.115 "mysql -e 'SHOW DATABASES;'"

# Database size
ssh root@188.245.159.115 "mysql -e 'SELECT table_schema AS Database, ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS Size_MB FROM information_schema.tables GROUP BY table_schema;'"
```

### 6. Log File Analysis

```bash
# Apache error logs
ssh root@188.245.159.115 "tail -100 /var/log/apache2/error.log"

# Apache access logs (last 50 requests)
ssh root@188.245.159.115 "tail -50 /var/log/apache2/access.log"

# Project-specific logs
ssh root@188.245.159.115 "tail -100 /var/log/apache2/havuncore.havun.nl-error.log"

# Laravel logs
ssh root@188.245.159.115 "tail -100 /var/www/production/storage/logs/laravel.log"

# System logs
ssh root@188.245.159.115 "journalctl -xe --no-pager -n 50"

# Search logs for errors
ssh root@188.245.159.115 "grep -i error /var/log/apache2/havuncore.havun.nl-error.log | tail -20"
```

---

## 🚨 Common Issues & Solutions

### Issue 1: SSL Certificate Wrong Domain

**Symptom:** `ERR_CERT_COMMON_NAME_INVALID`

**Diagnosis:**
```bash
# Check which cert Apache is using
ssh root@188.245.159.115 "cat /etc/apache2/sites-enabled/*.conf | grep SSLCertificate"

# Check actual certificate
ssh root@188.245.159.115 "openssl x509 -in /etc/letsencrypt/live/havuncore.havun.nl/fullchain.pem -noout -subject"

# Test from outside
echo | openssl s_client -servername havuncore.havun.nl -connect havuncore.havun.nl:443 2>/dev/null | openssl x509 -noout -subject
```

**Solution:**
```bash
# Generate new certificate
ssh root@188.245.159.115 "certbot certonly --apache -d havuncore.havun.nl --force-renewal"

# Update Apache config if needed
ssh root@188.245.159.115 "nano /etc/apache2/sites-available/havuncore.havun.nl-le-ssl.conf"

# Reload Apache
ssh root@188.245.159.115 "systemctl reload apache2"
```

### Issue 2: Site Not Loading / 500 Error

**Diagnosis:**
```bash
# Check if Apache is running
ssh root@188.245.159.115 "systemctl status apache2"

# Check error logs
ssh root@188.245.159.115 "tail -50 /var/log/apache2/error.log"

# Test Apache config
ssh root@188.245.159.115 "apache2ctl configtest"

# Check disk space
ssh root@188.245.159.115 "df -h"

# Check permissions
ssh root@188.245.159.115 "ls -la /var/www/havuncore.havun.nl/"
```

**Solution:**
```bash
# Restart Apache
ssh root@188.245.159.115 "systemctl restart apache2"

# Fix permissions (Laravel)
ssh root@188.245.159.115 "chown -R www-data:www-data /var/www/production/storage /var/www/production/bootstrap/cache"

# Clear Laravel cache
ssh root@188.245.159.115 "cd /var/www/production && php artisan cache:clear && php artisan config:clear"
```

### Issue 3: PM2 App Down

**Diagnosis:**
```bash
# Check PM2 status
ssh root@188.245.159.115 "pm2 status"

# Check app logs
ssh root@188.245.159.115 "pm2 logs havuncore-backend --lines 100 --nostream"

# Check if port is in use
ssh root@188.245.159.115 "netstat -tlnp | grep 3001"
```

**Solution:**
```bash
# Restart app
ssh root@188.245.159.115 "pm2 restart havuncore-backend"

# If that fails, delete and restart
ssh root@188.245.159.115 "pm2 delete havuncore-backend && cd /var/www/havuncore.havun.nl && pm2 start ecosystem.config.js --env production"

# Save PM2 config
ssh root@188.245.159.115 "pm2 save"
```

### Issue 4: Database Connection Error

**Diagnosis:**
```bash
# Check MySQL status
ssh root@188.245.159.115 "systemctl status mysql"

# Test database connection
ssh root@188.245.159.115 "mysql -u root -p -e 'SELECT 1;'"

# Check Laravel .env
ssh root@188.245.159.115 "grep DB_ /var/www/production/.env"
```

**Solution:**
```bash
# Restart MySQL
ssh root@188.245.159.115 "systemctl restart mysql"

# Check MySQL error log
ssh root@188.245.159.115 "tail -50 /var/log/mysql/error.log"
```

---

## 🔒 Security Guidelines

### What Claude CAN Do

✅ **Read-Only Operations:**
- View logs
- Check status
- List files
- View configurations
- Test connections

✅ **Safe Operations:**
- Reload services (apache2, nginx)
- Restart PM2 apps
- Clear cache
- Renew SSL certificates

✅ **With Permission:**
- Edit configuration files
- Restart services
- Deploy code
- Database operations

### What Claude CANNOT Do (Without Explicit Permission)

❌ **Destructive Operations:**
- Delete files
- Drop databases
- Remove users
- Modify firewall
- Change passwords

❌ **System Changes:**
- Install packages
- Modify system files
- Change permissions
- Update OS

### Approval Process

**For safe operations:** Proceed automatically
**For impactful operations:** Explain what will be done and ask for confirmation
**For destructive operations:** Require explicit user approval

**Example:**
```
Claude: "I found the issue. The Apache config needs to be updated.
        I will change SSLCertificateFile from bertvdh.havun.nl to havuncore.havun.nl
        and reload Apache. Is this OK?"
User: "ja graag"
Claude: [Proceeds with change]
```

---

## 📋 Project-Specific Procedures

### HavunCore Webapp Deployment

```bash
# 1. Check current status
ssh root@188.245.159.115 "pm2 status havuncore-backend"

# 2. Pull latest code (if using git)
ssh root@188.245.159.115 "cd /var/www/havuncore.havun.nl && git pull"

# 3. Install dependencies
ssh root@188.245.159.115 "cd /var/www/havuncore.havun.nl/backend && npm ci --production"
ssh root@188.245.159.115 "cd /var/www/havuncore.havun.nl/frontend && npm ci && npm run build"

# 4. Restart app
ssh root@188.245.159.115 "pm2 restart havuncore-backend"

# 5. Verify
ssh root@188.245.159.115 "pm2 logs havuncore-backend --lines 20 --nostream"
curl -I https://havuncore.havun.nl/health
```

### Laravel Project Deployment

```bash
# 1. Navigate to project
ssh root@188.245.159.115 "cd /var/www/production"

# 2. Pull code
ssh root@188.245.159.115 "cd /var/www/production && git pull"

# 3. Install dependencies
ssh root@188.245.159.115 "cd /var/www/production && composer install --no-dev --optimize-autoloader"

# 4. Run migrations
ssh root@188.245.159.115 "cd /var/www/production && php artisan migrate --force"

# 5. Clear cache
ssh root@188.245.159.115 "cd /var/www/production && php artisan config:cache && php artisan route:cache && php artisan view:cache"

# 6. Fix permissions
ssh root@188.245.159.115 "chown -R www-data:www-data /var/www/production/storage /var/www/production/bootstrap/cache"

# 7. Reload PHP
ssh root@188.245.159.115 "systemctl reload php8.2-fpm"
```

---

## 📊 Monitoring Commands

### Quick Health Check

```bash
# Run this for any project to get overview
ssh root@188.245.159.115 "
echo '=== SYSTEM ===' && \
uptime && \
free -h && \
df -h / && \
echo -e '\n=== APACHE ===' && \
systemctl status apache2 | head -3 && \
echo -e '\n=== PM2 ===' && \
pm2 list && \
echo -e '\n=== SSL CERTS ===' && \
certbot certificates | grep -E 'Certificate Name|Expiry' && \
echo -e '\n=== RECENT ERRORS ===' && \
tail -5 /var/log/apache2/error.log
"
```

### Performance Monitoring

```bash
# CPU and Memory usage
ssh root@188.245.159.115 "top -bn1 | head -20"

# Disk I/O
ssh root@188.245.159.115 "iostat -x 1 3"

# Network connections
ssh root@188.245.159.115 "netstat -an | grep ESTABLISHED | wc -l"

# Apache connections
ssh root@188.245.159.115 "netstat -an | grep :80 | wc -l"
```

---

## 🎯 Workflow for Helping Projects

### Standard Procedure

1. **Identify the Project**
   ```bash
   User: "havuncore werkt niet"
   Claude: Checks /var/www/havuncore.havun.nl/
   ```

2. **Diagnose**
   ```bash
   # Check logs, status, config
   ssh root@188.245.159.115 "pm2 status && tail /var/log/apache2/havuncore.havun.nl-error.log"
   ```

3. **Explain Finding**
   ```
   "Ik zie dat PM2 app havuncore-backend is crashed.
    Error in logs: 'Connection to database failed'.
    Waarschijnlijk database credentials issue."
   ```

4. **Propose Solution**
   ```
   "Ik kan:
    1. Check database status
    2. Check .env file
    3. Restart MySQL
    Zal ik dit doen?"
   ```

5. **Execute (After Approval)**
   ```bash
   ssh root@188.245.159.115 "systemctl status mysql && pm2 restart havuncore-backend"
   ```

6. **Verify Fix**
   ```bash
   curl https://havuncore.havun.nl/health
   ```

7. **Document**
   - Update project README if needed
   - Note in CODE-REVIEW.md if code issue
   - Create incident report if major

---

## 📝 Documentation Standards

### After Each Server Intervention

Create/update these files in the project repo:

**SERVER-INTERVENTION.md:**
```markdown
# Server Intervention - [Date]

## Issue
[Description]

## Diagnosis
[What was checked]

## Solution
[What was done]

## Commands Used
[Exact SSH commands]

## Prevention
[How to prevent in future]
```

### Project-Specific Server Docs

Each project should have:
```
/docs/
  SERVER-SETUP.md      # How project is deployed
  SERVER-ACCESS.md     # SSH access info
  TROUBLESHOOTING.md   # Common issues
  DEPLOYMENT.md        # Deployment procedures
```

---

## 🚀 Quick Reference

### Most Used Commands

```bash
# Check everything
ssh root@188.245.159.115 "systemctl status apache2 && pm2 list && certbot certificates | grep -E 'Name|Expiry'"

# Fix SSL
ssh root@188.245.159.115 "certbot renew --cert-name DOMAIN --force-renewal && systemctl reload apache2"

# Restart services
ssh root@188.245.159.115 "systemctl reload apache2 && pm2 restart all"

# View logs
ssh root@188.245.159.115 "tail -100 /var/log/apache2/error.log"

# Check disk space
ssh root@188.245.159.115 "df -h && du -sh /var/www/*"
```

---

## 📞 Emergency Contacts

**Server Issues:**
- Owner: Henk van Uum
- Provider: Hetzner
- IP: 188.245.159.115

**Projects:**
- HavunCore: [contact]
- Herdenkingsportaal: [contact]
- HavunAdmin: [contact]

---

## 🔄 Updates

**Last Updated:** 19 november 2025
**Next Review:** When new project is added

**Change Log:**
- 2025-11-19: Initial creation after fixing HavunCore SSL issue
- [Future updates here]

---

**This document is for Claude Code's reference when helping Havun projects with server-related issues.**
