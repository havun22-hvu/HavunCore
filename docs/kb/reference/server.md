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
├── havuncore/
│   ├── production/             # HavunCore Laravel (havuncore.havun.nl)
│   └── webapp/                 # Webapp (React SPA + Node.js backend)
│       ├── public/             # Frontend build (Nginx served)
│       └── backend/            # Node.js backend (PM2)
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
│   ├── laravel/                # Production (judotournament.org)
│   └── staging/                # Staging (staging.judotournament.org)
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

## Poorten — Compleet Overzicht

> **Gereserveerde range: 8000–8009** — alle Havun projecten gebruiken deze range lokaal.
> Bij nieuwe projecten: pak de eerstvolgende vrije poort in deze range.

### Lokale Development Poorten (Henk's PC)

Elke app heeft een vaste poort zodat meerdere projecten tegelijk kunnen draaien zonder conflicten.

| Poort | Project | Type | Command |
|-------|---------|------|---------|
| 8000 | havuncore-webapp frontend | Vite/React SPA | `npm run dev` |
| 8001 | HavunAdmin | Laravel | `php artisan serve --port=8001` |
| 8002 | Herdenkingsportaal | Laravel | `php artisan serve --port=8002` |
| 8003 | Studieplanner-api | Laravel | `php artisan serve --port=8003` |
| 8004 | SafeHavun | Laravel + React | `php artisan serve --port=8004` |
| 8005 | Infosyst | Laravel | `php artisan serve --port=8005` |
| 8006 | IDSee | Node.js/Express | `node server.js` (PORT=8006) |
| 8007 | JudoToernooi | Laravel | `php artisan serve --port=8007` |
| 8008 | HavunVet | Laravel | `php artisan serve --port=8008` (obsoleet) |
| 8009 | havuncore-webapp backend | Node.js/Express | `node src/server.js` |

**Buiten de range** (legacy, niet gewijzigd):

| Poort | Project | Type | Command |
|-------|---------|------|---------|
| 3001 | Havun (website) | Next.js | `npm run dev` (legacy) |
| 3002 | VPDUpdate | Node.js | `node server.js` |
| 5173 | Studieplanner | Vite/React Native | `npm run dev` |
| 11434 | Ollama | Lokale AI LLM | auto-start |

**Niet nodig lokaal:**

| Project | Reden |
|---------|-------|
| HavunCore | Pure backend API, geen eigen dev server nodig |

### Hetzner Server Poorten (188.245.159.115)

Op productie draait Nginx als reverse proxy. Bezoekers gaan via HTTPS (443), Nginx stuurt door naar de juiste backend.

| Poort | Service | Bereikbaar van buiten? |
|-------|---------|----------------------|
| 22 | SSH | Ja |
| 80 | HTTP → redirect naar 443 | Ja |
| 443 | HTTPS (Nginx) | Ja |
| 8009 | havuncore-webapp Node.js backend (pm2) | Nee (alleen via Nginx proxy) |
| 3306 | MySQL | Nee (localhost only) |

### Hoe het samenhangt

```
LOKAAL (development):
  Browser → localhost:8000 (Vite frontend) → localhost:8009 (Node.js backend)

PRODUCTIE (Hetzner):
  Browser → havuncore.havun.nl:443 (Nginx)
            ├── statische bestanden → /var/www/.../public/
            └── /api/* en /socket.io/* → localhost:8009 (Node.js backend via pm2)
```

> **Let op:** Vite (poort 8000) draait NIET op productie. De frontend wordt gebuild (`npm run build`) en als statische bestanden door Nginx geserveerd.

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
