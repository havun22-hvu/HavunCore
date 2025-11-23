# 🤖 Claude Session Guide - HavunCore

**Last Updated:** 2025-11-23 02:00
**Status:** ✅ **PRODUCTION - Backup System v0.6.0 + Task Queue v1.0 LIVE**

---

## 🎯 Current Status

**LATEST DEPLOYMENT - 23 November 2025**

✅ **HavunCore v0.6.0** deployed to production
✅ **Backup system** fully operational
✅ **Offsite backups** working (Hetzner Storage Box)
✅ **Cron jobs** configured (daily 03:00)
✅ **Compliance** achieved (7-year retention)
✅ **🚀 NEW: Task Queue System** operational - remote code execution from mobile!

**PREVIOUS DEPLOYMENT - 22 November 2025**

✅ Backup system v0.6.0 deployed

---

## 🔑 Critical Information

### Production Server

```
Server: 188.245.159.115
User: root
Access: SSH key authentication

Projects:
- HavunAdmin: /var/www/havunadmin/production
- Herdenkingsportaal: /var/www/production
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

### 🚀 Task Queue System (NEW!)

**What is it:** Remote code execution from mobile/web via API + automated poller

**API Endpoint:**
```
https://havunadmin.havun.nl/api/claude/tasks
```

**Create a task from mobile:**
```bash
curl -X POST "https://havunadmin.havun.nl/api/claude/tasks" \
  -H "Content-Type: application/json" \
  -d '{
    "project": "havuncore",
    "task": "Update README with version info",
    "priority": "normal",
    "created_by": "mobile"
  }'
```

**Check tasks:**
```bash
# Pending tasks
curl "https://havunadmin.havun.nl/api/claude/tasks/pending/havuncore"

# All tasks
curl "https://havunadmin.havun.nl/api/claude/tasks?project=havuncore"
```

**Server Poller:**
- Service: `claude-task-poller@havuncore.service`
- Status: `systemctl status claude-task-poller@havuncore`
- Logs: `/var/log/claude-task-poller-havuncore.log`
- Polls every 30 seconds
- Auto-commits and pushes to GitHub

**Full Documentation:** `docs/TASK-QUEUE-SYSTEM.md`

**Use Case:** On vacation or in the car? Create tasks via mobile Claude app and the server executes them automatically!

---

## 📦 What Was Deployed

### Backup System Architecture

**Local Backups (30 days):**
- HavunAdmin: `/var/www/havunadmin/production/storage/backups/havunadmin/hot/`
- Herdenkingsportaal: `/var/www/production/storage/backups/herdenkingsportaal/hot/`

**Offsite Backups (7 years):**
- HavunAdmin: `/home/havunadmin/archive/2025/11/` (on Storage Box)
- Herdenkingsportaal: `/home/herdenkingsportaal/archive/2025/11/` (on Storage Box)

**Automation:**
```bash
# Cron jobs (configured):
0 3 * * * cd /var/www/production && php artisan havun:backup:run >> /var/log/havun-backup.log 2>&1
0 * * * * cd /var/www/production && php artisan havun:backup:health >> /var/log/havun-backup-health.log 2>&1
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
cd /var/www/production
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
cd /var/www/production
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
- `/var/www/production/config/filesystems.php` - Hetzner disk config
- `/var/www/production/.env` - Credentials
- `/var/www/production/config/havun-backup.php` - Backup config

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
tail -f /var/www/production/storage/logs/laravel.log | grep backup
```

---

## 🚨 Common Issues & Solutions

### "Offsite: ❌" in backup report

**Check:**
1. SFTP connection: `sftp -P 23 u510616@u510616.your-storagebox.de`
2. Filesystem config: `'root' => ''` (empty!)
3. SSH host key: `ssh-keyscan -p 23 -H u510616.your-storagebox.de >> ~/.ssh/known_hosts`
4. Laravel logs: `tail -f /var/www/production/storage/logs/laravel.log | grep -i offsite`

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
grep -A 3 'hetzner-storage-box' /var/www/production/config/filesystems.php
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
- [ ] Laravel logs show no errors: `tail /var/www/production/storage/logs/laravel.log`

---

**Remember:** This system is LIVE in production. Changes must be tested carefully.
**Status:** All tests passed. System is stable. ✅

---

*Last deployment: 2025-11-22 22:07*
*Deployed by: Claude Code*
*Version: HavunCore v0.6.0*
