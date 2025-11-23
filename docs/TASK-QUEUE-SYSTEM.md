# 🚀 Claude Task Queue System

**Status:** ✅ LIVE and operational
**Deployment Date:** 2025-11-23
**Version:** 1.0

---

## 🎯 Overview

The Claude Task Queue System enables **remote task execution** from anywhere - mobile, web, or API. Tasks are queued in HavunAdmin's database and automatically executed by the server's polling service.

**Use Case:**
You're on vacation 🏖️ or in the car 🚗 and want to run a task on your development server. Simply create a task via the mobile Claude app, and the server executes it automatically!

---

## 📦 Architecture

```
[Mobile Claude App / Browser]
    ↓ POST task
[HavunAdmin API: /api/claude/tasks]
    ↓ Store in database
    ↓ Status: pending
[Server Poller (runs every 30s)]
    ↓ GET /api/claude/tasks/pending/{project}
    ↓ Execute task
    ↓ Commit & push to GitHub
    ↓ POST result back to API
[Task marked as: completed]
```

---

## 🔧 Components

### 1. **Task Queue API** (HavunAdmin)

**Location:** `HavunAdmin/app/Http/Controllers/Api/ClaudeTaskController.php`

**Endpoints:**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/claude/tasks` | List all tasks |
| POST | `/api/claude/tasks` | Create new task |
| GET | `/api/claude/tasks/{id}` | Get task details |
| DELETE | `/api/claude/tasks/{id}` | Delete task |
| GET | `/api/claude/tasks/pending/{project}` | Get pending tasks for a project |
| POST | `/api/claude/tasks/{id}/start` | Mark task as started |
| POST | `/api/claude/tasks/{id}/complete` | Mark task as completed |
| POST | `/api/claude/tasks/{id}/fail` | Mark task as failed |

**Database:** `claude_tasks` table

### 2. **Task Poller** (Server-side)

**Location:** `/usr/local/bin/claude-task-poller.sh`

**Runs as:** Systemd service `claude-task-poller@{project}.service`

**What it does:**
1. Polls API every 30 seconds for pending tasks
2. Executes tasks when found
3. Commits & pushes changes to GitHub
4. Reports results back to API

**Logs:** `/var/log/claude-task-poller-{project}.log`

### 3. **Development Environment** (Server)

**Location:** `/var/www/development/`

**Projects:**
- `HavunCore/` - Shared services package
- `HavunAdmin/` - Invoice/admin system (needs SSH key)
- `Herdenkingsportaal/` - Memorial portal (needs SSH key)

---

## 📖 Usage Guide

### Creating a Task (from Mobile Claude App)

```
Hey Claude, create a task for HavunCore:
"Update the README with the latest version info"
```

Claude will internally call the API:

```bash
curl -X POST "https://havunadmin.havun.nl/api/claude/tasks" \
  -H "Content-Type: application/json" \
  -d '{
    "project": "havuncore",
    "task": "Update the README with the latest version info",
    "priority": "normal",
    "created_by": "mobile"
  }'
```

### Checking Task Status

```bash
# List all tasks
curl "https://havunadmin.havun.nl/api/claude/tasks"

# List pending tasks for a project
curl "https://havunadmin.havun.nl/api/claude/tasks/pending/havuncore"

# Get specific task
curl "https://havunadmin.havun.nl/api/claude/tasks/1"
```

### Managing the Poller Service

```bash
# Check status
systemctl status claude-task-poller@havuncore

# View logs
journalctl -u claude-task-poller@havuncore -f

# Or view log file directly
tail -f /var/log/claude-task-poller-havuncore.log

# Restart service
systemctl restart claude-task-poller@havuncore

# Stop service
systemctl stop claude-task-poller@havuncore

# Start service
systemctl start claude-task-poller@havuncore
```

---

## ⚙️ Installation

### Prerequisites

- Ubuntu server (22.04+)
- PHP 8.2+
- Composer
- Git
- jq, curl

### Quick Setup

```bash
# 1. Clone HavunCore to development directory
cd /var/www/development
git clone https://github.com/havun22-hvu/HavunCore.git

# 2. Install dependencies
cd HavunCore
composer install --no-dev

# 3. Run setup script
sudo bash scripts/setup-task-poller.sh havuncore

# 4. Service is now running!
systemctl status claude-task-poller@havuncore
```

### Adding More Projects

```bash
# Setup for HavunAdmin
sudo bash scripts/setup-task-poller.sh havunadmin

# Setup for Herdenkingsportaal
sudo bash scripts/setup-task-poller.sh herdenkingsportaal

# Setup all three at once
sudo bash scripts/setup-task-poller.sh havuncore havunadmin herdenkingsportaal
```

---

## 🔐 GitHub SSH Setup

**Required for:** HavunAdmin and Herdenkingsportaal (private repos)

### Add SSH Key to GitHub

1. Get the public key from server:
   ```bash
   ssh root@188.245.159.115 'cat /root/.ssh/id_ed25519_github.pub'
   ```

2. Go to https://github.com/settings/keys

3. Click "New SSH key"

4. Paste the public key

5. Save

6. Clone private repos:
   ```bash
   cd /var/www/development
   git clone git@github.com:havun22-hvu/HavunAdmin.git
   git clone git@github.com:havun22-hvu/Herdenkingsportaal.git
   ```

---

## 📊 Task Lifecycle

```
pending → running → completed
                 ↳ failed
```

**Statuses:**

- **pending**: Task created, waiting for execution
- **running**: Task picked up by poller, executing
- **completed**: Task finished successfully
- **failed**: Task encountered an error

**Priority levels:** `urgent` > `high` > `normal` > `low`

---

## 🧪 Testing

### Test 1: Create a Simple Task

```bash
curl -X POST "https://havunadmin.havun.nl/api/claude/tasks" \
  -H "Content-Type: application/json" \
  -d '{
    "project": "havuncore",
    "task": "echo Test at $(date) > /tmp/test.txt",
    "priority": "high",
    "created_by": "test"
  }'
```

### Test 2: Monitor Execution

```bash
# Wait 35 seconds (poller interval + processing)
sleep 35

# Check task status
curl "https://havunadmin.havun.nl/api/claude/tasks?project=havuncore&limit=1"
```

### Test 3: Verify Result

```bash
ssh root@188.245.159.115 'cat /tmp/test.txt'
```

---

## 🚨 Troubleshooting

### Problem: Poller not picking up tasks

**Check:**
```bash
# 1. Is service running?
systemctl status claude-task-poller@havuncore

# 2. Check logs
tail -50 /var/log/claude-task-poller-havuncore.log

# 3. Test API manually
curl "https://havunadmin.havun.nl/api/claude/tasks/pending/havuncore"

# 4. Restart service
systemctl restart claude-task-poller@havuncore
```

### Problem: "Project directory not found"

**Fix:**
```bash
# Check if project exists
ls -la /var/www/development/

# Clone if missing
cd /var/www/development
git clone https://github.com/havun22-hvu/HavunCore.git
```

### Problem: "Invalid JSON response from API"

**Check:**
```bash
# 1. Is HavunAdmin in maintenance mode?
cd /var/www/havunadmin/production
php artisan up

# 2. Is Apache running?
systemctl status apache2

# 3. Test API directly
curl "https://havunadmin.havun.nl/api/health"
```

### Problem: Tasks fail to execute

**Check logs:**
```bash
# Poller log
tail -100 /var/log/claude-task-poller-havuncore.log

# Error log
tail -100 /var/log/claude-task-poller-havuncore-error.log
```

---

## 📝 API Examples

### Create Task with Metadata

```bash
curl -X POST "https://havunadmin.havun.nl/api/claude/tasks" \
  -H "Content-Type: application/json" \
  -d '{
    "project": "havuncore",
    "task": "Update backup documentation",
    "priority": "normal",
    "created_by": "mobile",
    "metadata": {
      "requester": "henk",
      "reason": "outdated info",
      "deadline": "2025-11-30"
    }
  }'
```

### Filter Tasks by Status

```bash
# Get all pending tasks
curl "https://havunadmin.havun.nl/api/claude/tasks?status=pending"

# Get all completed tasks
curl "https://havunadmin.havun.nl/api/claude/tasks?status=completed"

# Get all failed tasks
curl "https://havunadmin.havun.nl/api/claude/tasks?status=failed"
```

### Delete Old Tasks

```bash
# Delete task by ID
curl -X DELETE "https://havunadmin.havun.nl/api/claude/tasks/1"
```

---

## 🔮 Future Enhancements

**Planned features:**

1. **Claude Code CLI Integration** - Execute actual Claude Code commands instead of shell commands
2. **Email Notifications** - Get notified when tasks complete/fail
3. **MCP Integration** - Send task results to Havun MCP server
4. **Web Dashboard** - View/manage tasks via web UI
5. **Task Templates** - Pre-defined tasks for common operations
6. **Scheduled Tasks** - Cron-like task scheduling
7. **Task Dependencies** - Chain tasks together

---

## 📞 Support

**Questions?**
- Check logs: `/var/log/claude-task-poller-*.log`
- Review this documentation
- Check CLAUDE.md in repository root

**Server Access:**
- SSH: `root@188.245.159.115`
- API: `https://havunadmin.havun.nl/api/claude/tasks`

---

**Last updated:** 2025-11-23
**Maintained by:** HavunCore Team
**Documentation version:** 1.0
