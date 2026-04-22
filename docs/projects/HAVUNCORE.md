---
title: Project: HavunCore
type: reference
scope: havuncore
last_check: 2026-04-22
---

# Project: HavunCore

**Type:** Laravel 11 - Centraal orchestration platform
**Status:** Productie (v1.0.0)
**Klant:** Intern (alle projecten)

---

## URLs

| Omgeving | URL |
|----------|-----|
| Production | https://havuncore.havun.nl |
| Webapp | https://havuncore.havun.nl (React + Node.js) |

---

## Server & Credentials

> Zie `.claude/context.md` voor server info, database credentials en login gegevens.

### Paden

```
Laravel:    /var/www/havuncore/production
Webapp:     /var/www/havuncore/webapp/public/
Backend:    /var/www/havuncore/webapp/backend/
```

---

## Git

```
GitHub:     github.com/havun22-hvu/HavunCore
Branch:     master
Lokaal:     D:\GitHub\HavunCore (ALLEEN lokaal bewerken!)
```

---

## APIs

```
Task Queue: https://havuncore.havun.nl/api/claude/tasks
Vault:      https://havuncore.havun.nl/api/vault/
```

### Poller Services
```
havuncore:          DISABLED (alleen lokaal bewerken)
havunadmin:         ACTIVE
herdenkingsportaal: ACTIVE
```

---

## Webapp

```
Frontend:   React SPA
Backend:    Node.js + Express + Socket.io
PM2:        havuncore-backend (port 8009)
```

---

## Functionaliteit

- Task Queue API (centrale taak orchestratie)
- Vault (secrets management)
- Snippet Library (herbruikbare code)
- Backup orchestratie
- Shared services (composer package)
- Webapp met Claude AI chat

---

## BELANGRIJK

```
⚠️ HavunCore wordt ALLEEN lokaal bewerkt (D:\GitHub\HavunCore)
⚠️ Te kritiek - breekt ALLE projecten als het misgaat
⚠️ Na wijzigingen: handmatig git push
```

---

## Relatie met andere projecten

- **HavunAdmin:** Consumer van Task Queue + Vault
- **Herdenkingsportaal:** Consumer van Task Queue + Vault
- **SafeHavun:** Consumer van Vault
- **Studieplanner:** Consumer van Vault

---

*Laatst bijgewerkt: 2025-12-25*
