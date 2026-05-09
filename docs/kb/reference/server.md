---
title: Server Reference
type: reference
scope: havuncore
last_check: 2026-05-02
---

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

## Poorten

> **Single source of truth**: [`poort-register.md`](poort-register.md).
> Daar staat de complete map van productie + lokale dev ports per project,
> bind-addresses en UFW firewall policy.

## Server-hardening

> **Eis** ligt vast in [`productie-deploy-eisen.md`](productie-deploy-eisen.md) sectie 8.
> Status per server in [`security.md`](security.md) tabel "Server-hardening status".

Gedekt: UFW firewall, fail2ban, SSH pubkey-only, APP_DEBUG=false op productie,
SESSION_LIFETIME ≤ 120, `.env` perms 640.

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

- [.claude/context.md](../../../.claude/context.md) - Credentials
- [troubleshoot.md](../runbooks/troubleshoot.md) - Problemen oplossen
