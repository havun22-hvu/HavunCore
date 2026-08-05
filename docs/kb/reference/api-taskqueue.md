---
title: Task Queue API Reference
type: reference
scope: havuncore
last_check: 2026-04-22
---

# Task Queue API Reference

> Central orchestration - remote code execution

## Base URL

```
https://havuncore.havun.nl/api/claude/tasks
```

## Authenticatie — verplicht op elke route

**Deze API voert code uit op andere machines.** Elk verzoek heeft een Bearer-token nodig; zonder
token of met een verkeerd token volgt `401`, ongeacht de route. Ook lezen is dicht: de taakinhoud
verraadt projectstructuur en serverpaden.

```bash
curl -H "Authorization: Bearer $HAVUNCORE_TASKS_TOKEN" \
  https://havuncore.havun.nl/api/claude/tasks/pending/havuncore
```

Het token staat **gehasht** in de config (`CLAUDE_TASKS_TOKEN_HASH`, SHA-256) — de server kent de
oorspronkelijke waarde dus niet. Die staat in de Vault en in `.claude/credentials.md`.

Rate limit: 60 verzoeken per minuut per IP.

> **Waarom dit er pas sinds 06-08-2026 is.** Tot die datum had de hele groep geen enkele
> authenticatie: een `curl` vanaf internet kon een taak plaatsen die een poller zou uitvoeren.
> Gemeten en bevestigd 05-08. Dat het geen incident werd, kwam doordat de pollers toevallig stuk
> waren. Zie `reference/security-findings.md` en `plans/autofix-naar-claude-cli-plan.md`.

## Endpoints

### Create Task

```bash
POST /api/claude/tasks
Authorization: Bearer <token>
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
