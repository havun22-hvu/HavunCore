# Server Reference

> Hetzner VPS configuratie

## Server Details

| | |
|---|---|
| **IP** | SERVER_IP (zie context.md) |
| **User** | root |
| **Auth** | SSH key |
| **OS** | Ubuntu |
| **Provider** | Hetzner Cloud |

## SSH Toegang

```bash
ssh root@SERVER_IP (zie context.md)
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

## Server Ports

| Port | Service |
|------|---------|
| 22 | SSH |
| 80 | HTTP (redirect to 443) |
| 443 | HTTPS |
| 3001 | HavunCore Node.js backend |
| 3306 | MySQL (localhost only) |

## Local Development Ports

Standaard poorten per project. Voorkomt conflicten bij meerdere projecten tegelijk.

### Laravel Projects (php artisan serve)

| Project | Poort | Command |
|---------|-------|---------|
| HavunCore | 8000 | `php artisan serve` |
| HavunAdmin | 8001 | `php artisan serve --port=8001` |
| Herdenkingsportaal | 8002 | `php artisan serve --port=8002` |
| Studieplanner-api | 8003 | `php artisan serve --port=8003` |
| SafeHavun | 8004 | `php artisan serve --port=8004` |
| Infosyst | 8005 | `php artisan serve --port=8005` |
| IDSee | 8006 | `php artisan serve --port=8006` |
| JudoToernooi | 8007 | `php artisan serve --port=8007` |
| HavunVet | 8008 | `php artisan serve --port=8008` |

### Node.js / Frontend Projects

| Project | Poort | Command |
|---------|-------|---------|
| havun-mcp | 3000 | MCP server |
| Havun (website) | 3001 | `npm run dev` (Next.js) |
| VPDUpdate | 3002 | `node server.js` |
| Studieplanner | 5173 | `npm run dev` (Vite/React) |
| havuncore-webapp | 5174 | `npm run dev` (Vite/React) |

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

Zie [backup.md](../runbooks/backup.md).

## Related

- [.claude/context.md](/.claude/context.md) - Credentials
- [troubleshoot.md](../runbooks/troubleshoot.md) - Problemen oplossen
