# 🤖 Claude Session Guide - HavunCore

**Last Updated:** 2025-12-05
**Status:** ✅ **PRODUCTION - HavunCore v1.0.0 Standalone App + Task Queue LIVE**
**Role:** 🎛️ **Centrale beheerder van alle Havun projecten, server, backups & USB reis-backup**

---

## 🚨 SECURITY RULES - READ FIRST! (KRITIEK!)

**⚠️ INCIDENT:** 23 nov 2025 - SSH key aangemaakt zonder overleg → Eigenaar schrok, Herdenkingsportaal kon niet meer pushen. Zie: `SECURITY-INCIDENT-SSH-KEY.md`

### 🔴 ABSOLUUT VERBODEN zonder expliciete toestemming:

1. **SSH keys** aanmaken/wijzigen/verwijderen
2. **GitHub credentials** wijzigen
3. **Server credentials** aanpassen (.env secrets, API keys, passwords)
4. **Deployment configuratie** wijzigen (systemd services, cron jobs)
5. **Firewall/security** regels aanpassen
6. **Gebruikersrechten** wijzigen
7. **Database migrations** runnen op productie
8. **Composer/npm** packages installeren zonder check

### ✅ BIJ TWIJFEL:

**STOP → VRAAG EERST → WACHT OP TOESTEMMING**

Vuistregel: *"Raakt het credentials, keys, of systeemtoegang? → VRAAG EERST"*

### 📢 COMMUNICATIE VERPLICHT:

**Bij elke systeemwijziging:**
1. Vraag toestemming VOORAF
2. Leg uit WAT en WAAROM
3. Informeer andere projecten NA afloop (HavunAdmin, Herdenkingsportaal, VPDUpdate)

**Dit mag NOOIT meer gebeuren!**

---

## 📢 Communication Rules (IMPORTANT!)

**Keep responses SHORT & FOCUSED:**
- ❌ Long answers (>50 lines) - user reads first half only
- ✅ Short & powerful (max 20-30 lines)
- ✅ Use bullet points
- ✅ Direct to the point
- ⚠️ If long answer needed:
  1. Give summary first (5-10 lines)
  2. Ask: "Need details on X, Y, or Z?"
  3. Then provide details on request

**Why:** User often sees questions in first half, chat continues, second half never read = wasted tokens

**Workflow Preference:**
- 🏠 **Solo projects:** Work locally (D:\GitHub\)
- ⬆️ **Always push** to server after commits
- 📱 **On the road:** Use HavunCore webapp → server
- 🏖️ **Vacation:** USB stick H: drive (after git pull locally)

---

## 💾 USB Reis-Backup (H: Drive)

**Doel:** Portable development environment voor op reis/vakantie

**Drive:** `H:\` (USB stick, alleen aangesloten tijdens reis)

**Structuur:**
```
H:\
├── GitHub\              # Mirror van D:\GitHub projecten
│   ├── HavunCore\
│   ├── HavunAdmin\
│   ├── Herdenkingsportaal\
│   └── ...
└── tools\
    └── npm-global\      # npm global packages (portable)
```

**Voor vertrek (sync naar USB):**
```powershell
# Pull latest van alle projecten
cd D:\GitHub\HavunCore && git pull
cd D:\GitHub\HavunAdmin && git pull
cd D:\GitHub\Herdenkingsportaal && git pull

# Kopieer naar USB
robocopy D:\GitHub H:\GitHub /MIR /XD node_modules vendor .git
```

**Na terugkomst (sync terug):**
```powershell
# Push eventuele wijzigingen van USB naar GitHub
cd H:\GitHub\HavunCore && git push
# etc.
```

**⚠️ NPM Configuratie:**
- Thuis: `npm config set prefix "C:\Users\henkv\AppData\Roaming\npm"`
- Op reis: `npm config set prefix "H:\tools\npm-global"`
- **Let op:** Als H: niet aangesloten is, geeft npm errors!

**HavunCore beheert:** Documentatie en scripts voor USB backup sync

---

## 🎯 Current Status

**LATEST DEPLOYMENT - 26 November 2025**

✅ **Vault System** - Centraal secrets & config management
✅ **Herdenkingsportaal migratie** - Nu in `/var/www/herdenkingsportaal/`

**PREVIOUS - 25 November 2025**

✅ **HavunCore v1.0.0** - Standalone Laravel Application LIVE!
✅ **Task Queue API** migrated to HavunCore (was in HavunAdmin)
✅ **Own database** `havuncore` with dedicated MySQL user
✅ **Web interface** https://havuncore.havun.nl
✅ **SSL certificate** via Let's Encrypt
✅ **Poller services** updated - all 3 projects operational
✅ **Webapp deployed** - Unified React frontend + Laravel API op één domein
✅ **UI verbeterd** - Sticky header/footer, microfoon bij invoerveld

**PREVIOUS DEPLOYMENT - 23 November 2025**

✅ Backup system v0.6.0 deployed
✅ Task Queue System operational

---

## 🔑 Critical Information

### Production Server

```
Server: 188.245.159.115
User: root
Access: SSH key authentication

Projects Structure:
- HavunCore:                  /var/www/development/HavunCore
- HavunAdmin Staging:         /var/www/havunadmin/staging
- HavunAdmin Production:      /var/www/havunadmin/production
- Herdenkingsportaal Staging: /var/www/herdenkingsportaal/staging
- Herdenkingsportaal Production: /var/www/herdenkingsportaal/production

Databases:
- havuncore (user: havuncore, pass: HavunCore2025)
- havunadmin_production
- herdenkingsportaal

Web Interfaces:
- https://havuncore.havun.nl (Webapp + Task Queue API)
- https://havunadmin.havun.nl (Accounting)
- https://herdenkingsportaal.nl (Memorial Portal)

HavunCore Webapp Paths:
- Webapp frontend: /var/www/havuncore.havun.nl/public/
- Laravel API: /var/www/development/HavunCore/
- Nginx config: /etc/nginx/sites-available/havuncore.havun.nl
```

### Hetzner Storage Box

```
Host: u510616.your-storagebox.de
Port: 23 (SFTP)
User: u510616
Pass: G63^C@GB&PD2#jCl#1uj

Hetzner Console:
URL: https://console.hetzner.com
Email: havun22@gmail.com
Pass: G63^C@GB&PD2#jCl#1uj

⚠️ IMPORTANT: Use Hetzner CONSOLE, NOT Robot!
Storage Boxes migrated from Robot → Console
```

### Backup Encryption

```
Password: QUfTHO0hjdagrLgW10zIWLGjJelGBtrvG915IzFqIDE=

⚠️ CRITICAL: Without this password, backups CANNOT be restored!
Store securely in password manager.
```

### 🔐 Vault System

**What is it:** Centraal secrets & config management voor alle projecten.

**Features:**
- Encrypted secrets storage (AES-256)
- Per-project access control via API tokens
- Config templates voor nieuwe projecten
- Audit logging van alle toegang

**API Endpoint:** `https://havuncore.havun.nl/api/vault/`

**Quick Usage:**
```bash
# Get all secrets for a project
curl -H "Authorization: Bearer hvn_xxxxx" \
  "https://havuncore.havun.nl/api/vault/bootstrap"

# Create a secret (admin)
curl -X POST "https://havuncore.havun.nl/api/vault/admin/secrets" \
  -H "Content-Type: application/json" \
  -d '{"key": "mollie_key", "value": "live_xxx", "category": "payment"}'

# Register a project (admin)
curl -X POST "https://havuncore.havun.nl/api/vault/admin/projects" \
  -H "Content-Type: application/json" \
  -d '{"project": "nieuw-project", "secrets": ["mollie_key"]}'
```

**Full Documentation:** `docs/VAULT-SYSTEM.md`

### 🚀 Task Queue System

**What is it:** Central orchestration platform - remote code execution via API + automated pollers

**🏗️ Architecture:**
- **HavunCore** hosts the Task Queue API (migrated from HavunAdmin 25-nov-2025)
- **3 Poller services** run on server, one per project (havuncore, havunadmin, herdenkingsportaal)
- Pollers check API every 30 seconds for new tasks
- Tasks are executed in project directories, changes auto-committed to GitHub

**⚠️ HavunCore Editing Policy:**
```
HavunCore is ONLY edited locally (D:\GitHub\HavunCore)
- Too critical as core dependency for all projects
- Breaking HavunCore = ALL projects break
- Task Queue can be used for HavunAdmin & Herdenkingsportaal ONLY
- After local changes: manual git push
```

**API Endpoint:**
```
https://havuncore.havun.nl/api/claude/tasks
```

**Create a task from mobile:**
```bash
curl -X POST "https://havuncore.havun.nl/api/claude/tasks" \
  -H "Content-Type: application/json" \
  -d '{
    "project": "havunadmin",
    "task": "Update dashboard with new metrics",
    "priority": "normal",
    "created_by": "mobile"
  }'
```

**Check tasks:**
```bash
# Pending tasks
curl "https://havuncore.havun.nl/api/claude/tasks/pending/havunadmin"

# All tasks
curl "https://havuncore.havun.nl/api/claude/tasks?project=havunadmin"

# Specific task
curl "https://havuncore.havun.nl/api/claude/tasks/2"
```

**Server Poller Services:**
- `claude-task-poller@havuncore.service` (disabled - HavunCore local-only)
- `claude-task-poller@havunadmin.service` ✅ ACTIVE
- `claude-task-poller@herdenkingsportaal.service` ✅ ACTIVE

**Poller Commands:**
```bash
# Check status
systemctl status claude-task-poller@havunadmin
systemctl status claude-task-poller@herdenkingsportaal

# View logs
tail -f /var/log/claude-task-poller-havunadmin.log
journalctl -u claude-task-poller@havunadmin -f

# Restart service
systemctl restart claude-task-poller@havunadmin
```

**Full Documentation:** `docs/TASK-QUEUE-SYSTEM.md`

**Use Case:** On vacation or mobile? Create tasks via API and the server executes them automatically!

---

### 🌐 HavunCore Webapp

**URL:** https://havuncore.havun.nl

**Login:**
- Username: `henkvu@gmail.com`
- Password: `T3@t@Do2AEPKJBlI2Ltg`

**Architecture (Updated 25-nov-2025):**
- React SPA frontend + **Node.js backend** (Express + Socket.io)
- Claude API integration voor AI chat
- PM2 process manager

**Nginx Routes:**
- `/` → React SPA (static files)
- `/api/*` → Node.js backend (port 3001)
- `/socket.io/` → WebSocket (port 3001)

**Features:**
- ✅ Chat met Claude AI + **Tools** (kan bestanden lezen, tasks aanmaken)
- ✅ "test" commando = instant status check
- ✅ Status tab - server/database health
- ✅ Tasks tab - takenlijst
- ✅ Vault tab - project configuraties
- ✅ Mobile-friendly (sticky input, paste/eye icons)
- ✅ Chat history blijft bewaard (localStorage)
- ✅ Auto update check bij start + elke 5 min
- ✅ Voice input (spraakherkenning)

**Server Paths:**
```
Frontend:  /var/www/havuncore.havun.nl/public/
Backend:   /var/www/havuncore.havun.nl/backend/
PM2:       havuncore-backend (port 3001)
Nginx:     /etc/nginx/sites-available/havuncore.havun.nl
```

**Deploy Webapp Updates:**
```bash
# 1. Build locally
cd D:\GitHub\havuncore-webapp\frontend
npm run build

# 2. Upload to server
scp -r dist/* root@188.245.159.115:/var/www/havuncore.havun.nl/public/

# 3. For backend changes:
scp backend/src/*.js root@188.245.159.115:/var/www/havuncore.havun.nl/backend/src/
ssh root@188.245.159.115 "pm2 restart havuncore-backend"
```

**PM2 Commands:**
```bash
pm2 status
pm2 logs havuncore-backend
pm2 restart havuncore-backend
```

---

### 🔐 Auth Architectuur (Updated 05-dec-2025)

**Principe: Elke app beheert zijn eigen authenticatie!**

| App | Auth Systeem | Package/Implementatie |
|-----|--------------|----------------------|
| **Herdenkingsportaal** | Eigen Laravel auth + WebAuthn | `laragear/webauthn` |
| **HavunAdmin** | Eigen Laravel auth + WebAuthn | `laragear/webauthn` |
| **HavunCore Webapp** | Device tokens via HavunCore API | Custom implementation |
| **VPDUpdate** | QR login (planned) | Via HavunCore API |

**Waarom decentraal:**
- ✅ Geen CORS issues (auth op zelfde domein)
- ✅ Elke app is onafhankelijk
- ✅ Makkelijker te debuggen per app
- ✅ Geen single point of failure

**HavunCore Auth API** (`/api/auth/*`):
- Alleen voor HavunCore webapp zelf
- Device token systeem
- Niet bedoeld als centrale auth voor andere apps

**Herdenkingsportaal Auth:**
- Gebruikt `laragear/webauthn` package
- Eigen database tabellen voor credentials
- QR login + biometrische login
- Volledig onafhankelijk van HavunCore

---

**Deploy Changes to Production:**
```bash
# After testing in staging:
ssh root@188.245.159.115

# Production pull from GitHub
cd /var/www/havunadmin/production
git pull origin master
php artisan config:clear

cd /var/www/herdenkingsportaal/production
git pull origin master
php artisan config:clear
```

**Full Documentation:** `docs/TASK-QUEUE-SYSTEM.md`

---

## 🏗️ HavunCore Architecture Evolution

### 📦 Before (Until 24-nov-2025):
- **Type:** Composer package
- **Purpose:** Shared services library
- **Task Queue:** Hosted in HavunAdmin
- **Database:** None

### 🚀 Now (Since 25-nov-2025):
- **Type:** Standalone Laravel 11 Application
- **Purpose:** Central orchestration platform
- **Task Queue:** Hosted in HavunCore (migrated!)
- **Database:** MySQL `havuncore` with own user
- **Web Interface:** https://havuncore.havun.nl
- **SSL:** Let's Encrypt certificate
- **Deployment:** `/var/www/development/HavunCore`

### 🎯 Why the Change:
HavunCore is the **central orchestration platform** that coordinates all projects. It makes no sense for HavunAdmin (accounting software) to host the Task Queue API. HavunCore now has:
- Central Task Queue API for all projects
- Backup orchestration system
- Shared services and utilities
- Future: monitoring, logging, webhooks

### 🚫 TASK QUEUE RESTRICTIONS:

**NEVER edit via Task Queue:**
- ❌ **HavunCore** - Too critical, breaks ALL projects!
  - ONLY edit locally (D:\GitHub\HavunCore)
  - Manual testing and git push

**Production vs Staging:**
- ✅ **HavunCore:** Production only (/var/www/development/HavunCore)
- ✅ **HavunAdmin:** Production (/var/www/havunadmin/production)
- ✅ **Herdenkingsportaal:** Production (/var/www/herdenkingsportaal/production)
- ✅ HavunAdmin staging
- ✅ Client sites staging
- ⚠️ Test before deploying to production!

---

## 📦 What Was Deployed

### Backup System Architecture

**Local Backups (30 days):**
- HavunAdmin: `/var/www/havunadmin/production/storage/backups/havunadmin/hot/`
- Herdenkingsportaal: `/var/www/herdenkingsportaal/production/storage/backups/herdenkingsportaal/hot/`

**Note:** Backups run from production (data safety), Task Queue works in staging (code safety)!

**Offsite Backups (7 years):**
- HavunAdmin: `/home/havunadmin/archive/2025/11/` (on Storage Box)
- Herdenkingsportaal: `/home/herdenkingsportaal/archive/2025/11/` (on Storage Box)

**Automation:**
```bash
# Cron jobs (configured):
0 3 * * * cd /var/www/herdenkingsportaal/production && php artisan havun:backup:run >> /var/log/havun-backup.log 2>&1
0 * * * * cd /var/www/herdenkingsportaal/production && php artisan havun:backup:health >> /var/log/havun-backup-health.log 2>&1
```

### Filesystem Configuration (CRITICAL!)

**⚠️ IMPORTANT - This was the main issue:**

```php
// config/filesystems.php
'hetzner-storage-box' => [
    'driver' => 'sftp',
    'host' => env('HETZNER_STORAGE_HOST'),
    'port' => 23,
    'username' => env('HETZNER_STORAGE_USERNAME'),
    'password' => env('HETZNER_STORAGE_PASSWORD'),
    'root' => '', // ← EMPTY STRING! Not '/havun-backups'!
    'timeout' => 60,
    'directoryPerm' => 0755,
    'visibility' => 'private',
    'throw' => false,
],
```

**Why root is empty:**
- Storage Box starts at `/home`
- BackupOrchestrator uploads to: `{project}/archive/{year}/{month}/`
- Full path becomes: `/home/havunadmin/archive/2025/11/filename.zip`

---

## 🔧 Lessons Learned (CRITICAL!)

### Issue #1: Filesystem Root Path
**Problem:** Documentation said `'root' => '/havun-backups'`
**Reality:** Should be `'root' => ''` (empty string)
**Reason:** Storage Box root is `/home`, not customizable
**Fix:** Set root to empty string, BackupOrchestrator handles full path

### Issue #2: SSH Host Key
**Problem:** "Connection refused" on SFTP
**Cause:** SSH host key not in known_hosts
**Fix:** `ssh-keyscan -p 23 -H u510616.your-storagebox.de >> ~/.ssh/known_hosts`

### Issue #3: Hetzner Robot vs Console
**Problem:** Documentation referenced Robot everywhere
**Reality:** Storage Boxes are managed via Hetzner CONSOLE (not Robot!)
**Fix:** All docs updated to reference console.hetzner.com

### Issue #4: SSH Already Enabled
**Problem:** Thought SSH needed to be activated
**Reality:** SSH was already enabled, just needed host key
**Fix:** Added host key, everything worked immediately

---

## 📊 Verification Commands

### Check Backup Health
```bash
ssh root@188.245.159.115
cd /var/www/herdenkingsportaal/production
php artisan havun:backup:health
```

Expected output:
```
✅ havunadmin (CRITICAL)
   Last backup: 2025-11-22 22:24 (-0.2h ago)
   Size: 17.15 KB

✅ herdenkingsportaal (CRITICAL)
   Last backup: 2025-11-22 22:24 (-0.2h ago)
   Size: 221.5 KB
```

### Check Offsite Backups
```bash
sshpass -p 'G63^C@GB&PD2#jCl#1uj' sftp -P 23 u510616@u510616.your-storagebox.de <<EOF
cd havunadmin/archive/2025/11
ls -la
cd ../../herdenkingsportaal/archive/2025/11
ls -la
bye
EOF
```

### Run Manual Backup
```bash
ssh root@188.245.159.115
cd /var/www/herdenkingsportaal/production
php artisan havun:backup:run
```

---

## 📁 Important Files & Locations

### Documentation (all in `/docs/backup/`)

**Deployment Status:**
- `DEPLOYMENT-STATUS.md` - Complete production status ⭐ **READ THIS FIRST**
- `TEAM-NOTIFICATION-SHORT.md` - Short team notification
- `EMAIL-TEAM-SHORT.txt` - Email template
- `SLACK-MESSAGE.txt` - Slack announcement

**Setup Guides:**
- `HETZNER-STORAGE-BOX-SETUP.md` - Storage Box setup (updated for Console)
- `SERVER-SETUP-BACKUP.md` - Detailed server setup
- `QUICK-SERVER-IMPLEMENTATION.md` - Quick 30-min setup
- `DEPLOY-NOW.md` - Copy-paste deployment commands

**Architecture:**
- `COMPLIANCE-BACKUP-ARCHITECTURE.md` - System architecture
- `MULTI-PROJECT-BACKUP-SYSTEM.md` - Multi-project setup

### Configuration Files

**HavunAdmin:**
- `/var/www/havunadmin/production/config/filesystems.php` - Hetzner disk config
- `/var/www/havunadmin/production/.env` - Credentials
- `/var/www/havunadmin/production/config/havun-backup.php` - Backup config

**Herdenkingsportaal:**
- `/var/www/herdenkingsportaal/production/config/filesystems.php` - Hetzner disk config
- `/var/www/herdenkingsportaal/production/.env` - Credentials
- `/var/www/herdenkingsportaal/production/config/havun-backup.php` - Backup config

---

## ⚡ Quick Commands Reference

### Backup Operations
```bash
# Health check
php artisan havun:backup:health

# List backups
php artisan havun:backup:list

# Run backup manually
php artisan havun:backup:run

# Clear config cache (after .env changes)
php artisan config:clear
```

### Server Access
```bash
# SSH to server
ssh root@188.245.159.115

# SFTP to Storage Box
sftp -P 23 u510616@u510616.your-storagebox.de
# Password: G63^C@GB&PD2#jCl#1uj
```

### Logs
```bash
# Backup logs
tail -f /var/log/havun-backup.log

# Health check logs
tail -f /var/log/havun-backup-health.log

# Laravel logs
tail -f /var/www/herdenkingsportaal/production/storage/logs/laravel.log | grep backup
```

---

## 🚨 Common Issues & Solutions

### "Offsite: ❌" in backup report

**Check:**
1. SFTP connection: `sftp -P 23 u510616@u510616.your-storagebox.de`
2. Filesystem config: `'root' => ''` (empty!)
3. SSH host key: `ssh-keyscan -p 23 -H u510616.your-storagebox.de >> ~/.ssh/known_hosts`
4. Laravel logs: `tail -f /var/www/herdenkingsportaal/production/storage/logs/laravel.log | grep -i offsite`

### "Connection refused" on SFTP

**Solution:**
```bash
ssh root@188.245.159.115
ssh-keyscan -p 23 -H u510616.your-storagebox.de >> ~/.ssh/known_hosts
```

### Backups not appearing on Storage Box

**Check path:**
- NOT `/havun-backups/...`
- USE `/home/havunadmin/archive/2025/11/`

**Verify filesystem root:**
```bash
grep -A 3 'hetzner-storage-box' /var/www/herdenkingsportaal/production/config/filesystems.php
```
Should show: `'root' => '',`

---

## 📅 Maintenance Schedule

### Daily (Automated)
- 03:00 - Full backup (both projects)
- Every hour - Health check

### Weekly (Manual)
- Check backup logs: `tail -100 /var/log/havun-backup.log`
- Verify offsite: `sftp -P 23 u510616@...`

### Monthly (Manual)
- Review backup sizes
- Check Storage Box usage
- Update retention if needed

### Quarterly (CRITICAL!)
- **Test restore procedure**
- Download backup from offsite
- Verify data integrity
- Document in restore log

---

## 🔄 Next Actions / TODO

### Immediate (Done ✅)
- [x] Deploy backup system to production
- [x] Configure HavunAdmin backups
- [x] Configure Herdenkingsportaal backups
- [x] Setup cron jobs
- [x] Test offsite upload
- [x] Update all documentation
- [x] Create team notifications

### Short Term (This Week)
- [ ] Monitor first automated backup (tonight 03:00)
- [ ] Verify daily backup email reports arrive
- [ ] Send team notification email
- [ ] Add backup status to monitoring dashboard

### Medium Term (This Month)
- [ ] Test restore procedure (staging)
- [ ] Document restore steps
- [ ] Setup alerting for failed backups
- [ ] Create quarterly restore test procedure

### Long Term (This Quarter)
- [ ] Implement restore commands in HavunCore
- [ ] Add web dashboard for backup monitoring
- [ ] Consider adding more projects to backup system

---

## 🎓 Key Learnings for Future Claude Sessions

### When Working with Hetzner Storage Box:

1. **Always use Hetzner CONSOLE** (console.hetzner.com), NOT Robot
2. **Filesystem root MUST be empty string** (`'root' => ''`)
3. **SSH host key needed** before SFTP works
4. **Port 23 for SFTP**, not 22
5. **Storage Box root is /home**, paths are relative to that

### When Deploying Backups:

1. **Test SFTP first** before running backups
2. **Clear config cache** after .env changes
3. **Check Laravel logs** for detailed error messages
4. **Directories auto-created** by BackupOrchestrator
5. **Verify offsite upload** after first backup

### Documentation:

1. **DEPLOYMENT-STATUS.md** = single source of truth
2. **Update docs** with ACTUAL production config (not theory!)
3. **Remove obsolete info** (like Robot references)
4. **Document lessons learned** while fresh in memory

---

## 💡 Tips for Next Claude Session

### If User Asks About Backups:

1. **Read DEPLOYMENT-STATUS.md first** - it has everything
2. **Check current health**: `php artisan havun:backup:health`
3. **Verify offsite works**: Check logs or run manual backup
4. **Don't assume** - verify actual config on server

### If Something Doesn't Work:

1. **Check filesystem root** - most common issue!
2. **Check SSH host key** - second most common issue
3. **Check Laravel logs** - detailed error messages
4. **Test SFTP manually** - isolate the problem

### If User Wants to Add Another Project:

1. **Copy HavunAdmin setup** - it's working perfectly
2. **Use empty root** in filesystem config
3. **Add project** to `config/havun-backup.php`
4. **Test manually** before adding to cron

---

## 📞 Support & Resources

**Primary Contact:** havun22@gmail.com

**Documentation:**
- `/docs/backup/DEPLOYMENT-STATUS.md` ⭐ **Main reference**
- `/docs/backup/` - All backup docs
- `CHANGELOG.md` - Version history

**External:**
- Hetzner Console: https://console.hetzner.com
- Hetzner Docs: https://docs.hetzner.com/storage/storage-box/
- Server: 188.245.159.115

**Git Repository:**
- GitHub: https://github.com/havun22-hvu/HavunCore
- Latest commit: `3a31afb` (team notifications)

---

## ✅ Checklist for Verification

Before telling user "everything works":

- [ ] `php artisan havun:backup:health` shows all ✅
- [ ] `php artisan havun:backup:list` shows recent backups
- [ ] Offsite column shows ✅ for recent backups
- [ ] SFTP login works: `sftp -P 23 u510616@u510616.your-storagebox.de`
- [ ] Files exist on Storage Box in `/home/{project}/archive/{year}/{month}/`
- [ ] Cron jobs configured: `crontab -l | grep havun`
- [ ] Laravel logs show no errors: `tail /var/www/herdenkingsportaal/production/storage/logs/laravel.log`

---

**Remember:** This system is LIVE in production. Changes must be tested carefully.
**Status:** All tests passed. System is stable. ✅

---

*Last deployment: 2025-11-22 22:07*
*Deployed by: Claude Code*
*Version: HavunCore v0.6.0*
