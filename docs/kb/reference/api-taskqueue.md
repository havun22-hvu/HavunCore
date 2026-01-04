# Task Queue API Reference

> Central orchestration - remote code execution

## Base URL

```
https://havuncore.havun.nl/api/claude/tasks
```

## Endpoints

### Create Task

```bash
POST /api/claude/tasks
Content-Type: application/json

{
  "project": "havunadmin",
  "task": "Update dashboard with new metrics",
  "priority": "normal",
  "created_by": "mobile"
}
```

**Response:**
```json
{
  "id": 123,
  "project": "havunadmin",
  "task": "Update dashboard...",
  "status": "pending",
  "created_at": "2025-12-05T10:00:00Z"
}
```

### Get Pending Tasks

```bash
GET /api/claude/tasks/pending/{project}
```

### Get All Tasks

```bash
GET /api/claude/tasks?project=havunadmin
```

### Get Single Task

```bash
GET /api/claude/tasks/{id}
```

### Update Task Status

```bash
PATCH /api/claude/tasks/{id}
Content-Type: application/json

{
  "status": "completed",
  "result": "Task completed successfully"
}
```

## Statuses

| Status | Betekenis |
|--------|-----------|
| pending | Wacht op uitvoering |
| in_progress | Wordt uitgevoerd |
| completed | Afgerond |
| failed | Mislukt |

## Poller Services

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

## Related

- HavunCore - Host van Task Queue
- [troubleshoot.md](../runbooks/troubleshoot.md) - Problemen oplossen
