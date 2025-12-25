# Project: HavunAdmin

**Type:** Laravel 11 - Boekhoud/Administratie systeem
**Status:** Productie
**Klant:** Intern (Havun)

---

## URLs

| Omgeving | URL |
|----------|-----|
| Production | https://havunadmin.havun.nl |
| Staging | /var/www/havunadmin/staging |

---

## Server

```
Server:     SERVER_IP (zie context.md)
User:       root
SSH:        Key authentication
```

### Paden

```
Production: /var/www/havunadmin/production
Staging:    /var/www/havunadmin/staging
```

---

## Database

### Production
```
Database: havunadmin_production
```

### Staging
```
Database: havunadmin_staging
```

---

## Git

```
GitHub:   github.com/havun22-hvu/HavunAdmin
Branch:   master
```

---

## Task Queue

```
Poller:   claude-task-poller@havunadmin.service
Status:   ACTIVE
Logs:     /var/log/claude-task-poller-havunadmin.log
```

---

## Backup

```
Hot backup:     /var/www/havunadmin/production/storage/backups/havunadmin/hot/
Offsite:        /home/havunadmin/archive/ (Hetzner Storage Box)
Retentie:       30 dagen lokaal, 7 jaar offsite
```

---

## Functionaliteit

- Facturatie & boekhouding
- Mollie payment integratie
- Bunq bank koppeling
- Memorial management (via API)
- Client beheer

---

## Relatie met andere projecten

- **HavunCore:** Gebruikt als composer package
- **Herdenkingsportaal:** API provider voor memorials
- **VPDUpdate:** Geen directe relatie

---

*Laatst bijgewerkt: 2025-12-02*
