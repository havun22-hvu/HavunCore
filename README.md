# 🚀 HavunCore - Central Orchestration Platform

**v1.1.0** - Standalone Laravel 11 Application voor centrale coördinatie van alle Havun projecten

📚 **[Complete Documentation Index →](INDEX.md)**

---

## 🎯 What is HavunCore?

**Central orchestration platform** die alle Havun projecten coördineert:
- **Task Queue API** - Remote code execution voor HavunAdmin & Herdenkingsportaal
- **Backup Orchestration** - Multi-project backups met 7-jaar compliance
- **Shared Services** - Vault, API contracts, push notifications
- **Integration Hub** - Mollie, Bunq, Gmail services

**Live:** https://havuncore.havun.nl

---

## 🏗️ Architecture

### **Standalone Laravel 11 App** (since 25-nov-2025)
Previously a Composer package, now a full Laravel application with:
- Own database: `havuncore`
- Web interface: https://havuncore.havun.nl
- SSL: Let's Encrypt
- Server: `/var/www/development/HavunCore`

### **Why the transformation?**
HavunCore is the central orchestrator for all projects. It made no sense for HavunAdmin (accounting software) to host the Task Queue API. HavunCore now coordinates everything from a central position.

---

## 📦 Core Features

### 🔄 Task Queue System
**Remote code execution via API + automated pollers**

```bash
# Create task from mobile/web
curl -X POST "https://havuncore.havun.nl/api/claude/tasks" \
  -H "Content-Type: application/json" \
  -d '{
    "project": "havunadmin",
    "task": "Update dashboard metrics",
    "priority": "normal"
  }'

# Check pending tasks
curl "https://havuncore.havun.nl/api/claude/tasks/pending/havunadmin"
```

**Active Pollers:**
- ✅ `havunadmin` - Checks every 30s
- ✅ `herdenkingsportaal` - Checks every 30s

**Use case:** On vacation or mobile? Create tasks via API, server executes automatically!

---

### 💾 Backup System
**Enterprise-grade backup met 7-year retention**

**Features:**
- Multi-project orchestration (HavunAdmin, Herdenkingsportaal)
- Local + offsite storage (Hetzner Storage Box)
- AES-256 encryption
- SHA256 checksums
- Automated cleanup
- Compliance logging

```bash
# Health check
php artisan havun:backup:health

# Manual backup
php artisan havun:backup:run

# List backups
php artisan havun:backup:list
```

**Automated:** Daily backups at 03:00 UTC via cron

---

### 🔐 Vault System
**Centralized secrets & config management**

Store API keys, passwords, configs with per-project access control:

```bash
# Create a secret (admin)
curl -X POST "https://havuncore.havun.nl/api/vault/admin/secrets" \
  -H "Content-Type: application/json" \
  -d '{"key": "mollie_key", "value": "live_xxx", "category": "payment"}'

# Register project with access
curl -X POST "https://havuncore.havun.nl/api/vault/admin/projects" \
  -d '{"project": "havunadmin", "secrets": ["mollie_key"]}'
# → Returns: hvn_xxxxx token

# Fetch in project (with token)
curl -H "Authorization: Bearer hvn_xxxxx" \
  "https://havuncore.havun.nl/api/vault/bootstrap"
```

**Features:** AES-256 encryption, audit logging, masked admin view

**Docs:** `docs/VAULT-SYSTEM.md`

---

### 🔔 Push Notifications
**Real-time notifications voor gebruikers**

```php
use Havun\Core\Services\PushNotificationService;

$service = new PushNotificationService();

// Send notification
$service->sendPushNotification(
    user: $user,
    title: 'Betaling ontvangen',
    body: 'Standaard monument actief',
    data: ['memorial_id' => 123]
);
```

---

## 🛠️ Deployment

### Production Server

```
Server: 188.245.159.115
Path: /var/www/development/HavunCore
Database: havuncore (user: havuncore)
URL: https://havuncore.havun.nl
```

### Deploy Changes

```bash
# Local changes
cd D:\GitHub\HavunCore
git add .
git commit -m "Description"
git push

# Server update
ssh root@188.245.159.115
cd /var/www/development/HavunCore
git pull origin master
php artisan config:clear
php artisan migrate
```

---

## 📊 Artisan Commands

### Backup Commands
```bash
php artisan havun:backup:run           # Run backup
php artisan havun:backup:health        # Health check
php artisan havun:backup:list          # List all backups
```

### Task Queue Commands
```bash
php artisan havun:task:create          # Create task
php artisan havun:task:list            # List tasks
php artisan havun:task:status {id}     # Task status
```

### Vault Commands
```bash
php artisan havun:vault:set {key}      # Store secret
php artisan havun:vault:get {key}      # Retrieve secret
php artisan havun:vault:list           # List all keys
```

### Notification Commands
```bash
php artisan havun:push:send            # Send test notification
php artisan havun:push:test            # Test configuration
```

---

## 🔗 Connected Projects

HavunCore orchestrates:
- **HavunAdmin** - Accounting system (`/var/www/havunadmin/production`)
- **Herdenkingsportaal** - Memorial portal (`/var/www/production`)
- **VPDUpdate** - Update service (`/var/www/vpdupdate`)

---

## 📁 Project Structure

```
HavunCore/
├── app/
│   ├── Http/Controllers/Api/  # Task Queue API
│   ├── Models/                # ClaudeTask, BackupLog, etc.
│   └── Console/Commands/      # 20+ Artisan commands
├── database/
│   └── migrations/            # Database schema
├── routes/
│   ├── api.php               # API endpoints
│   └── web.php               # Web routes
├── scripts/
│   ├── claude-task-poller.sh       # Poller script
│   └── claude-task-poller.service  # Systemd service
├── storage/
│   ├── vault/                # Encrypted credentials
│   ├── backups/              # Backup storage
│   └── api/                  # OpenAPI specs
├── docs/                     # Documentation
│   ├── backup/              # Backup docs
│   ├── api/                 # API reference
│   └── setup/               # Setup guides
├── config/
│   ├── havun-backup.php     # Backup config
│   └── filesystems.php      # Hetzner Storage Box
├── bootstrap/app.php         # Laravel bootstrap
├── artisan                   # CLI entry point
├── CLAUDE.md                # Claude session guide
├── ARCHITECTURE.md          # System design
├── CHANGELOG.md             # Version history
└── INDEX.md                 # Documentation index
```

---

## 📚 Documentation

**Essential Guides:**
- 🤖 [CLAUDE.md](CLAUDE.md) - Claude Code session guide
- 🏗️ [ARCHITECTURE.md](ARCHITECTURE.md) - System architecture
- 📖 [INDEX.md](INDEX.md) - Complete documentation index
- 💾 [Backup System](docs/backup/) - Backup documentation
- 🔌 [API Reference](docs/kb/reference/) - API docs (Task Queue, Vault)

---

## ⚙️ System Services

### Poller Services
```bash
# Status
systemctl status claude-task-poller@havunadmin
systemctl status claude-task-poller@herdenkingsportaal

# Logs
tail -f /var/log/claude-task-poller-havunadmin.log
journalctl -u claude-task-poller@havunadmin -f

# Restart
systemctl restart claude-task-poller@havunadmin
```

### Cron Jobs
```
0 3 * * * php artisan havun:backup:run    # Daily backups
0 * * * * php artisan havun:backup:health # Hourly health check
```

---

## 🔒 Security

**⚠️ HavunCore is CRITICAL - Breaking it breaks ALL projects!**

**Editing Policy:**
- ✅ ONLY edit locally (D:\GitHub\HavunCore)
- ✅ Test thoroughly before pushing
- ✅ Manual git push after testing
- ❌ NEVER via Task Queue (too risky)

See [CLAUDE.md](CLAUDE.md) for complete security rules.

---

## 📖 License

Proprietary - Havun projects only

---

**Version:** 1.0.0
**Last Updated:** 2025-11-25
**Author:** Henk van Unen
**Production:** https://havuncore.havun.nl
