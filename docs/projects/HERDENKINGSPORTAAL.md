# Project: Herdenkingsportaal

**Type:** Laravel 11 - Memorial/Gedenkpagina platform
**Status:** Productie
**Klant:** Publiek (B2C)

---

## URLs

| Omgeving | URL |
|----------|-----|
| Production | https://herdenkingsportaal.nl |
| Staging | /var/www/herdenkingsportaal/staging |

---

## Server

```
Server:     SERVER_IP (zie context.md)
User:       root
SSH:        Key authentication
```

### Paden

```
Production: /var/www/herdenkingsportaal/production
Staging:    /var/www/herdenkingsportaal/staging
```

---

## Database

```
Database: herdenkingsportaal
```

---

## Git

```
GitHub:   github.com/havun22-hvu/Herdenkingsportaal
Branch:   master
```

---

## Task Queue

```
Poller:   claude-task-poller@herdenkingsportaal.service
Status:   ACTIVE
Logs:     /var/log/claude-task-poller-herdenkingsportaal.log
```

---

## Backup

```
Hot backup:     /var/www/herdenkingsportaal/production/storage/backups/herdenkingsportaal/hot/
Offsite:        /home/herdenkingsportaal/archive/ (Hetzner Storage Box)
Retentie:       30 dagen lokaal, 7 jaar offsite
Cron:           0 3 * * * (dagelijks 03:00)
```

---

## Functionaliteit

- Gedenkpagina's aanmaken
- Foto/video uploads
- Condoleances
- QR-code voor grafsteen
- Betaling via Mollie

---

## Relatie met andere projecten

- **HavunCore:** Gebruikt als composer package
- **HavunAdmin:** API consumer voor memorial sync
- **VPDUpdate:** Geen directe relatie

---

*Laatst bijgewerkt: 2025-12-02*
