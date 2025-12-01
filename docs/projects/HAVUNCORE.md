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

## Server

```
Server:     188.245.159.115
User:       root
SSH:        Key authentication
```

### Paden

```
Laravel:    /var/www/development/HavunCore
Webapp:     /var/www/havuncore.havun.nl/public/
Backend:    /var/www/havuncore.havun.nl/backend/
```

---

## Database

```
Database:   havuncore
User:       havuncore
Password:   HavunCore2025
```

---

## Git

```
GitHub:     github.com/havun22-hvu/HavunCore
Branch:     master
Lokaal:     D:\GitHub\HavunCore (ALLEEN lokaal bewerken!)
```

---

## Task Queue API

```
Endpoint:   https://havuncore.havun.nl/api/claude/tasks
```

### Poller Services
```
havuncore:          DISABLED (alleen lokaal bewerken)
havunadmin:         ACTIVE
herdenkingsportaal: ACTIVE
```

---

## Vault API

```
Endpoint:   https://havuncore.havun.nl/api/vault/
```

---

## Webapp

```
Frontend:   React SPA
Backend:    Node.js + Express + Socket.io
PM2:        havuncore-backend (port 3001)
```

### Login
```
Email:      henkvu@gmail.com
Password:   T3@t@Do2AEPKJBlI2Ltg
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
- **VPDUpdate:** Consumer van shared services
- **BertvanderHeide:** Nieuw project, optionele integratie

---

*Laatst bijgewerkt: 2025-12-02*
