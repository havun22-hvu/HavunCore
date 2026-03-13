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
│   ├── public/                 # Webapp frontend (React SPA build)
│   └── backend/                # Node.js backend
├── havuncore-webapp/
│   └── frontend/               # React SPA (Vite dev server, poort 8000)
├── havunadmin/
│   ├── staging/
│   └── production/
├── herdenkingsportaal/
│   ├── staging/
│   └── production/
├── infosyst/
│   └── production/
├── judotoernooi/
│   └── laravel/                # Production (judotournament.org)
├── staging.judotoernooi/
│   └── laravel/                # Staging (geen publiek domein)
└── studieplanner/
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
| 3001 | havuncore-webapp Node.js backend (pm2) |
| 3306 | MySQL (localhost only) |

## Local Development Ports

Standaard poorten per project. Voorkomt conflicten bij meerdere projecten tegelijk.

### Laravel Projects (php artisan serve)

| Project | Poort | Command |
|---------|-------|---------|
| HavunCore | - | geen lokale dev nodig |
| HavunAdmin | 8001 | `php artisan serve --port=8001` |
| Herdenkingsportaal | 8002 | `php artisan serve --port=8002` |
| Studieplanner-api | 8003 | `php artisan serve --port=8003` |
| SafeHavun | 8004 | `php artisan serve --port=8004` |
| Infosyst | 8005 | `php artisan serve --port=8005` |
| IDSee | 8006 | `php artisan serve --port=8006` |
| JudoToernooi | 8007 | `php artisan serve --port=8007` |
| HavunVet | 8008 | `php artisan serve --port=8008` (obsoleet) |

### Node.js / Frontend Projects

| Project | Poort | Command |
|---------|-------|---------|
| Havun (website) | 3001 | `npm run dev` (Next.js) |
| VPDUpdate | 3002 | `node server.js` |
| Studieplanner | 5173 | `npm run dev` (Vite/React) |
| havuncore-webapp frontend | 8000 | `npm run dev` (Vite/React) |
| havuncore-webapp backend | 8009 | `node src/server.js` (Express) |

## Frontend Build Tool per Project

Sommige projecten gebruiken **Vite** (npm, lokale compilatie), andere gebruiken **CDN** (direct in HTML, geen build stap).

### Vite (npm + lokale compilatie)

Deze projecten hebben `package.json`, `vite.config.js` en `npm run dev`:

| Project | Tailwind via | Vite config |
|---------|-------------|-------------|
| HavunAdmin | npm | `vite.config.js` |
| Herdenkingsportaal | npm | `vite.config.js` |
| Infosyst | npm | `vite.config.js` |
| SafeHavun | npm | `vite.config.js` |
| JudoToernooi | npm | `vite.config.js` |
| havuncore-webapp | npm | `vite.config.js` (Vite IS de dev server) |

In Blade templates herkenbaar aan: `@vite(['resources/css/app.css', 'resources/js/app.js'])`

### CDN (geen Vite, geen npm build)

| Project | Tailwind via | Reden |
|---------|-------------|-------|
| HavunCore | - | Geen eigen frontend/views |
| Studieplanner-api | CDN | Alleen API backend |

### Wanneer Vite, wanneer CDN?

- **Vite**: Als je Tailwind klassen wil purgen (kleinere bundle), React/Vue gebruikt, of TypeScript compileert
- **CDN**: Snel prototypen, kleine interne tools, of als build tooling te veel overhead is
- **havuncore-webapp**: Bijzonder geval — pure React SPA, Vite is zowel build tool als dev server (poort 8000)

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
