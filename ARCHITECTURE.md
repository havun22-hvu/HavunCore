# 🏗️ HavunCore - Architecture & Design

**System architecture en design decisions voor HavunCore v1.0.0**

---

## 📐 System Overview

HavunCore is een **standalone Laravel 11 application** die fungeert als centrale orchestrator voor alle Havun projecten.

### **Core Principles:**

1. **Central Orchestration** - Eén platform dat alle projecten coördineert
2. **Single Responsibility** - Elke service heeft één duidelijke taak
3. **Security First** - Critical system, breaking it breaks ALL projects
4. **Compliance** - 7-year backup retention (Belastingdienst requirement)
5. **Automation** - Remote task execution via API + automated pollers

---

## 🚀 Architecture Evolution

### Phase 1: Composer Package (Until 24-nov-2025)

**Type:** Shared Laravel package
**Purpose:** Reusable services library
**Database:** None
**Deployment:** Via Composer in other projects

**Problems:**
- Task Queue API lived in HavunAdmin (wrong place!)
- No central database for orchestration
- Limited coordination capabilities
- Composer package complexity

### Phase 2: Standalone App (Since 25-nov-2025)

**Type:** Laravel 11 application
**Purpose:** Central orchestration platform
**Database:** MySQL `havuncore`
**Deployment:** `/var/www/development/HavunCore`
**Web Interface:** https://havuncore.havun.nl

**Benefits:**
- ✅ Task Queue API in the right place
- ✅ Own database for orchestration data
- ✅ Web interface for monitoring
- ✅ Central coordination point
- ✅ Future: webhooks, monitoring, logging

---

## 🎯 Key Design Decisions

### Decision 1: Standalone App vs Composer Package

**Why Standalone?**

HavunCore is the **central orchestrator** - it makes no sense to distribute it as a package.

**Rationale:**
- HavunCore coordinates ALL projects (HavunAdmin, Herdenkingsportaal, VPDUpdate)
- Needs own database for Task Queue, backups, audit logs
- Needs web interface for API endpoints
- Task Queue API should not live in HavunAdmin (accounting software)

**Trade-offs:**
- ✅ Proper separation of concerns
- ✅ Central database for orchestration
- ✅ Web API for remote control
- ⚠️ No longer shareable via Composer (but that's intentional!)

---

### Decision 2: Task Queue Architecture

**Architecture:** Central API + Distributed Pollers

```
┌─────────────┐
│ Mobile/Web  │ → Create task via API
└──────┬──────┘
       │
       ▼
┌─────────────────────────────┐
│  HavunCore API              │
│  https://havuncore.havun.nl │
│  Database: claude_tasks     │
└──────────┬──────────────────┘
           │
     ┌─────┴─────┬──────────┐
     ▼           ▼          ▼
┌─────────┐ ┌─────────┐ ┌──────────┐
│ Poller  │ │ Poller  │ │ Poller   │
│ HAdmin  │ │ Herdenk │ │ HCore    │
│ (30s)   │ │ (30s)   │ │ (OFF)    │
└────┬────┘ └────┬────┘ └────┬─────┘
     │           │           │
     ▼           ▼           ▼
 Execute     Execute     Local-only
 in /var/    in /var/    editing
 havunadmin  production  policy
```

**Why this design?**
- API needs to be accessible from anywhere (mobile, web, other servers)
- Pollers run on same server as projects (fast, secure)
- Tasks execute in correct project context
- Changes auto-commit to GitHub

**HavunCore Local-Only Policy:**
- Too critical to allow remote execution
- Breaking HavunCore = ALL projects break
- Manual testing required before push

---

### Decision 3: Backup System Design

**Architecture:** Multi-Project Orchestration

```
┌──────────────────────────────┐
│  BackupOrchestrator          │
│  (HavunCore)                 │
└────┬────────┬────────────┬───┘
     │        │            │
     ▼        ▼            ▼
┌─────────┐ ┌──────┐ ┌─────────┐
│HavunAdm│ │Herdenk│ │HavunCore│
│  .env  │ │ .env  │ │  .env   │
│  DB    │ │  DB   │ │  DB     │
│  Files │ │ Files │ │ Files   │
└────┬───┘ └───┬──┘ └────┬────┘
     │         │         │
     └────┬────┴────┬────┘
          ▼         ▼
    ┌─────────┐ ┌──────────────┐
    │  Local  │ │   Hetzner    │
    │  Hot    │ │   Storage    │
    │ 30 days │ │   7 years    │
    └─────────┘ └──────────────┘
```

**Features:**
- SHA256 checksums for integrity
- AES-256 encryption for compliance
- Automatic cleanup (retention policy)
- Audit trail in database

---

### Decision 4: Memorial Reference = 12 Characters

**Waarom?**
- UUID's (36 chars) zijn te lang voor handmatige invoer
- Eerste 12 chars bieden voldoende uniciteit (2^48 = 281 trillion combinaties)
- Korter = makkelijker te communiceren (telefoon, email)

**Implementatie:**
```php
// UUID: 550e8400-e29b-41d4-a716-446655440000
// Reference: 550e8400e29b (eerste 12 chars zonder hyphens)
```

**Trade-offs:**
- ✅ Kortere codes
- ✅ Betere UX
- ⚠️ Zeer kleine kans op collision (verwaarloosbaar bij <1M monuments)

---

## 🗄️ Database Schema

### HavunCore Database

```sql
-- Task Queue
claude_tasks (
    id, project, task, status, priority,
    result, error, created_by,
    started_at, completed_at, execution_time_seconds,
    metadata, created_at, updated_at
)

-- Backup System
backup_logs (
    id, project, backup_date, backup_type,
    file_path, file_size, checksum,
    offsite_path, offsite_uploaded_at,
    status, encryption_enabled, retention_years,
    created_at, updated_at
)

restore_logs (
    id, backup_log_id, restore_date, restore_type,
    restored_by, success, error_message,
    created_at, updated_at
)

backup_test_logs (
    id, backup_log_id, test_date, test_type,
    tested_by, success, notes,
    created_at, updated_at
)

-- Vault
vault_entries (
    id, key, value (encrypted),
    created_at, updated_at
)
```

---

## 🔒 Security Architecture

### Critical System Protection

**HavunCore = CRITICAL**
- Breaking HavunCore breaks ALL projects
- Must be edited locally only
- No remote Task Queue execution
- Manual testing before deployment

### Security Layers

1. **Application Security**
   - SSL/TLS (Let's Encrypt)
   - Nginx security headers
   - Laravel CSRF protection
   - Input validation

2. **Data Security**
   - AES-256 encryption (backups)
   - Vault encryption (credentials)
   - SHA256 checksums (integrity)

3. **Access Control**
   - SSH key authentication only
   - Root user access (controlled)
   - No public registration
   - API token auth (future)

4. **Audit Trail**
   - All backups logged
   - All tasks logged
   - All restores logged
   - Timestamps + metadata

---

## 📊 System Integration

### Connected Projects

```
HavunCore (Central)
    │
    ├─► HavunAdmin
    │   └─ Accounting, invoicing
    │
    ├─► Herdenkingsportaal
    │   └─ Memorial portal, payments
    │
    └─► VPDUpdate (future)
        └─ Update service
```

### Integration Points

1. **Task Queue API**
   - Endpoint: `https://havuncore.havun.nl/api/claude/tasks`
   - Pollers: 30-second interval
   - Execution: In project context

2. **Backup Orchestration**
   - Daily: 03:00 UTC
   - Offsite: Hetzner Storage Box
   - Retention: 7 years

3. **Shared Services** (future)
   - Vault for credentials
   - Push notifications
   - API contracts
   - Monitoring/logging

---

## 🚀 Deployment Architecture

### Production Setup

```
Server: 188.245.159.115 (Hetzner VPS)

/var/www/
├── development/
│   └── HavunCore/          ← Standalone app
├── havunadmin/
│   ├── staging/
│   └── production/
├── production/             ← Herdenkingsportaal
└── staging/                ← Herdenkingsportaal staging
```

### Services

```systemd
claude-task-poller@havunadmin.service       (ACTIVE)
claude-task-poller@herdenkingsportaal.service (ACTIVE)
claude-task-poller@havuncore.service        (DISABLED)
```

### Cron Jobs

```cron
0 3 * * * php artisan havun:backup:run       # Daily backup
0 * * * * php artisan havun:backup:health    # Hourly health
```

---

## 🔄 Development Workflow

### Local Development

```bash
# 1. Local changes
cd D:\GitHub\HavunCore
# ... edit files ...
git add .
git commit -m "Description"
git push

# 2. Deploy to server
ssh root@188.245.159.115
cd /var/www/development/HavunCore
git pull origin master
php artisan config:clear
php artisan migrate
```

### Testing Policy

- ✅ Test locally first
- ✅ Commit to GitHub
- ✅ Deploy to production
- ❌ Never test in production

---

## 📈 Future Architecture

### Planned Enhancements

1. **Web Dashboard**
   - Backup monitoring
   - Task Queue overview
   - System health metrics

2. **Webhooks**
   - Task completion webhooks
   - Backup status webhooks
   - Error notifications

3. **API Authentication**
   - Token-based auth
   - Rate limiting
   - IP whitelisting

4. **Monitoring**
   - Uptime monitoring
   - Performance metrics
   - Error tracking

5. **Multi-Server**
   - Support for multiple servers
   - Load balancing
   - Failover strategy

---

## 📖 References

**Documentation:**
- [CLAUDE.md](CLAUDE.md) - Claude session guide
- [README.md](README.md) - Project overview
- [CHANGELOG.md](CHANGELOG.md) - Version history
- [INDEX.md](INDEX.md) - Documentation index

**External:**
- Laravel 11: https://laravel.com/docs/11.x
- Hetzner Storage Box: https://docs.hetzner.com/storage/storage-box/

---

**Version:** 1.0.0
**Last Updated:** 2025-11-25
**Architecture:** Standalone Laravel 11 Application
