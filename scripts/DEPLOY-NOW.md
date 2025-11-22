# 🚀 DEPLOY NOW - Copy-Paste Ready Commands

**Status:** Ready to deploy
**Time:** ~10 minutes
**Date:** 2025-11-22

---

## Step 1: SSH to Server

```bash
ssh your-server
```

---

## Step 2: Pull Latest Changes

```bash
# Navigate to HavunCore
cd /var/www/havuncore

# Pull latest
git pull origin master

# Verify deployment script exists
ls -l scripts/deploy-backup-system.sh
```

---

## Step 3: Run Deployment

```bash
# Make script executable (if needed)
chmod +x scripts/deploy-backup-system.sh

# Run deployment
bash scripts/deploy-backup-system.sh
```

**This will take ~10 minutes and will:**
- ✅ Auto-detect HavunAdmin & Herdenkingsportaal
- ✅ Install SFTP driver
- ✅ Configure filesystems
- ✅ Update .env files
- ✅ Create Storage Box directories
- ✅ Run test backups
- ✅ Setup cron jobs

---

## Step 4: Verify Deployment

```bash
# Check local backups
ls -lh /var/www/havunadmin/storage/backups/havunadmin/hot/
ls -lh /var/www/herdenkingsportaal/storage/backups/herdenkingsportaal/hot/

# Check cron jobs
crontab -l | grep havun

# Health check
cd /var/www/havunadmin
php artisan havun:backup:health
```

---

## Step 5: Verify Offsite Upload

```bash
# Connect to Storage Box
sftp -P 23 u510616@u510616.your-storagebox.de
# Password: G63^C@GB&PD2#jCl#1uj

# Check backups
ls -lh havun-backups/havunadmin/hot/
ls -lh havun-backups/herdenkingsportaal/hot/

# Exit
exit
```

---

## ✅ Expected Success Output

```
✓ HavunAdmin backup: Created (~50 MB)
✓ Herdenkingsportaal backup: Created (~150 MB)
✓ Both uploaded to Hetzner Storage Box
✓ Cron jobs configured
✓ Health monitoring active
```

---

## 🚨 If Something Goes Wrong

**Check logs:**
```bash
tail -100 /var/log/havun-backup.log
```

**Manual deployment:**
See `docs/backup/QUICK-SERVER-IMPLEMENTATION.md`

---

**Ready? Copy commands above and run on server!** 🚀
