# HavunCore

> Centrale orchestratie platform voor alle Havun projecten

## Overview

| | |
|---|---|
| **Type** | Standalone Laravel 11 Application |
| **URL** | https://havuncore.havun.nl |
| **Local** | D:\GitHub\HavunCore |
| **Server** | /var/www/development/HavunCore |
| **Database** | havuncore |

## Rol

HavunCore is de **centrale hub** die beheert:
- Task Queue API (remote code execution)
- Vault (secrets management)
- Backup orchestratie
- Cross-project communicatie

## Architectuur

```
HavunCore
├── Laravel API        → Task Queue, Vault, Auth
├── React Webapp       → Chat met Claude AI
└── Node.js Backend    → WebSocket, Claude API proxy
```

## Webapp

**Stack:** React + Vite + Node.js + Express + Socket.io

**Features:**
- Chat met Claude AI + tools
- Tasks beheer
- Vault configuraties
- Status monitoring

**Paths op server:**
```
Frontend:  /var/www/havuncore.havun.nl/public/
Backend:   /var/www/havuncore.havun.nl/backend/
PM2:       havuncore-backend (port 3001)
```

## Belangrijk

**HavunCore wordt ALLEEN lokaal bewerkt!**
- Te kritiek als dependency voor alle projecten
- Task Queue is NIET voor HavunCore zelf
- Na wijzigingen: handmatig git push

## Related

- [[runbooks/deploy]] - Deployen
- [[reference/api-taskqueue]] - Task Queue API
- [[reference/api-vault]] - Vault API
