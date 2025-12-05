# Vault API Reference

> Centraal secrets & config management

## Base URL

```
https://havuncore.havun.nl/api/vault/
```

## Endpoints

### Bootstrap (Get all secrets for project)

```bash
GET /api/vault/bootstrap
Authorization: Bearer hvn_xxxxx
```

**Response:**
```json
{
  "project": "havunadmin",
  "secrets": {
    "mollie_key": "live_xxx",
    "database_url": "mysql://..."
  }
}
```

### Create Secret (Admin)

```bash
POST /api/vault/admin/secrets
Content-Type: application/json

{
  "key": "mollie_key",
  "value": "live_xxx",
  "category": "payment"
}
```

### Register Project (Admin)

```bash
POST /api/vault/admin/projects
Content-Type: application/json

{
  "project": "nieuw-project",
  "secrets": ["mollie_key", "database_url"]
}
```

## Features

- AES-256 encryption
- Per-project access control
- API token authenticatie
- Audit logging

## Related

- [[projects/havuncore]] - Host van Vault
- [.claude/context.md](/.claude/context.md) - Credentials
