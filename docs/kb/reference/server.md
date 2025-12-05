# Server Reference

> Hetzner VPS configuratie

## Server Details

| | |
|---|---|
| **IP** | 188.245.159.115 |
| **User** | root |
| **Auth** | SSH key |
| **OS** | Ubuntu |
| **Provider** | Hetzner Cloud |

## SSH Toegang

```bash
ssh root@188.245.159.115
```

## Directory Structuur

```
/var/www/
├── development/
│   └── HavunCore/              # HavunCore Laravel
├── havuncore.havun.nl/
│   ├── public/                 # Webapp frontend
│   └── backend/                # Node.js backend
├── havunadmin/
│   ├── staging/
│   └── production/
└── herdenkingsportaal/
    ├── staging/
    └── production/
```

## Services

| Service | Command |
|---------|---------|
| Nginx | `systemctl status nginx` |
| MySQL | `systemctl status mysql` |
| PM2 | `pm2 status` |
| Task Poller | `systemctl status claude-task-poller@havunadmin` |

## Ports

| Port | Service |
|------|---------|
| 22 | SSH |
| 80 | HTTP (redirect to 443) |
| 443 | HTTPS |
| 3001 | HavunCore Node.js backend |
| 3306 | MySQL (localhost only) |

## Nginx Configs

```
/etc/nginx/sites-available/
├── havuncore.havun.nl
├── havunadmin.havun.nl
└── herdenkingsportaal.nl
```

## SSL Certificates

Let's Encrypt via Certbot.
Auto-renewal via systemd timer.

## Backups

Zie [[runbooks/backup]].

## Related

- [.claude/context.md](/.claude/context.md) - Credentials
- [[runbooks/troubleshoot]] - Problemen oplossen
