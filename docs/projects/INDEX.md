# Projecten Overzicht

Centrale documentatie voor alle Havun projecten.

**[→ Quick Reference (credentials & commands)](QUICK-REFERENCE.md)**

---

## Interne Projecten

| Project | Type | Status | URL |
|---------|------|--------|-----|
| [HavunCore](HAVUNCORE.md) | Orchestration Platform | Productie | havuncore.havun.nl |
| [HavunAdmin](HAVUNADMIN.md) | Boekhouding | Productie | havunadmin.havun.nl |
| [Herdenkingsportaal](HERDENKINGSPORTAAL.md) | Memorial Platform | Productie | herdenkingsportaal.nl |
| [VPDUpdate](VPDUPDATE.md) | Sync Tool | Productie | - |

---

## Klant Projecten

| Project | Type | Status | URL |
|---------|------|--------|-----|
| [BertvanderHeide](BERTVANDERHEIDE.md) | Uitvaart Website | Setup compleet | bertvanderheide.havun.nl |

---

## Server Overzicht

Alle projecten draaien op: **188.245.159.115**

```
/var/www/
├── development/
│   └── HavunCore/
├── havunadmin/
│   ├── staging/
│   └── production/
├── herdenkingsportaal/
│   ├── staging/
│   └── production/
├── vpdupdate/
├── bertvanderheide/
│   ├── staging/
│   └── production/
└── havuncore.havun.nl/
    ├── public/          (webapp)
    └── backend/         (node.js)
```

---

## Quick Reference

### Task Queue Pollers
```
havunadmin:         systemctl status claude-task-poller@havunadmin
herdenkingsportaal: systemctl status claude-task-poller@herdenkingsportaal
```

### Backup Health
```
ssh root@188.245.159.115 "cd /var/www/herdenkingsportaal/production && php artisan havun:backup:health"
```

### SSL Certificaten
```
certbot certificates
```

---

*Laatst bijgewerkt: 2025-12-02*
