---
title: Runbook: Uptime Monitoring & SLA
type: runbook
scope: havuncore
last_check: 2026-06-07
---

# Runbook: Uptime Monitoring & SLA

> **Bron:** Externe audit Q1 2026 (VP-09)
> **Tool:** UptimeRobot (gratis tier)

## Uptime-doelen

| App | URL | Uptime-doel | Kritiek? | Toelichting |
|-----|-----|-------------|----------|-------------|
| Herdenkingsportaal | herdenkingsportaal.nl | 99.5% (~43u/jaar) | Ja | Publiek verkeer, betalingen |
| JudoToernooi | judotournament.org | 99% (~87u/jaar) | Ja | Actief tijdens toernooien (NIET judotoernooi.havun.nl — die heeft geen vhost) |
| HavunCore | havuncore.havun.nl | 99% | Ja | Andere apps zijn afhankelijk |
| HavunAdmin | havunadmin.havun.nl | 95% | Nee | Intern beheer |
| SafeHavun | safehavun.havun.nl | 95% | Nee | Beperkt gebruik |
| Infosyst | infosyst.havun.nl | 95% | Nee | Beperkt gebruik |

## Server-side Health Check (ACTIEF sinds 29-03-2026)

**Script:** `/usr/local/bin/havun-health-check.sh`
**Cron:** Elke 5 minuten (`*/5 * * * *`)
**Log:** `/var/log/havun-health.log`

> **GEEN E-MAIL meer (sinds 7 juni 2026).** Alerts worden in-app getoond, niet gemaild.
> De oude `havun-health-alert.php` (SendGrid) is uitgefaseerd (`Maximum credits exceeded`).

### Wat het doet:
- Checkt alle 6 publieke apps met `curl -sk` (elke 5 min)
- Checkt **reverb** broadcasting via `supervisorctl status` (FATAL/niet-RUNNING → alert)
- Roept per check `php artisan health:alert <key> --scope= --project= --status=down|up` aan
- Stateless: de `health_alerts`-tabel dedupet (down = open alert, up = resolved; up op
  een gezonde key is een no-op). Geen state-files, geen rate-limit-gedoe meer.

### Waar zie je de meldingen?
- **HavunCore-webapp** → 🔔 notificatie-paneel (badge + lijst), real-time via Socket.io.
  Algemene/server-meldingen staan bovenaan, daarna gegroepeerd per project.
- **Fase 2 (later):** project-meldingen ook in de betreffende app zelf, via
  `GET /api/health-alerts?project=<naam>`.
- **Totale serveruitval** (webapp zelf plat) → externe laag **UptimeRobot** (zie onder).

> **Bron in versiebeheer:** `HavunCore/scripts/havun-health-check.sh`.
> Na bewerken: scp naar `/usr/local/bin/` op de server en handmatig draaien om te verifiëren.
> Backend: migratie `health_alerts`, `HealthAlert` model, `health:alert` command,
> `HealthAlertController` (`/api/health-alerts`).

### Gemonitorde apps:
- HavunCore (`/health`) → scope=server
- Herdenkingsportaal, JudoToernooi (`judotournament.org`), HavunAdmin, SafeHavun, Infosyst → scope=project
- **reverb** (broadcasting prod + staging, supervisor) → scope=project, JudoToernooi

### Troubleshooting:
```bash
# Handmatig draaien:
/usr/local/bin/havun-health-check.sh

# Log bekijken (alleen down-events worden gelogd):
tail -20 /var/log/havun-health.log

# Huidige open meldingen (vanaf de server):
cd /var/www/havuncore/production && php artisan tinker --execute="echo \App\Models\HealthAlert::open()->count();"

# Of via de API:
curl -s https://havuncore.havun.nl/api/health-alerts | head
```

## UptimeRobot (optioneel — externe monitoring)

Extra laag bovenop de server-side check. Belangrijk als de hele server down is (dan werkt het server-script ook niet).

### Per app instellen:
1. Monitor type: HTTPS
2. Interval: 5 minuten
3. Alert contact: havun22@gmail.com
4. Keyword monitoring (optioneel): check op specifieke tekst op homepage

### Alerts:
- E-mail bij downtime > 5 minuten
- E-mail bij herstel
- Optioneel: SMS voor kritieke apps (Herdenkingsportaal, JudoToernooi)

## Bij Downtime

### Stap 1: Diagnose
```bash
ssh root@188.245.159.115

# Check nginx
systemctl status nginx

# Check PHP-FPM
systemctl status php8.3-fpm

# Check logs
tail -50 /var/log/nginx/error.log
tail -50 /var/www/[project]/production/storage/logs/laravel.log
```

### Stap 2: Snel herstel
```bash
# Herstart services
systemctl restart nginx
systemctl restart php8.3-fpm

# Clear caches
cd /var/www/[project]/production
php artisan config:clear
php artisan cache:clear
```

### Stap 3: Communicatie
- Bij downtime > 30 min op kritieke apps: klanten informeren
- Leg oorzaak en oplossing vast in handover.md

## Kwartaal Review

Bij elke kwartaal-audit:
- Check uptime-cijfers per app
- Vergelijk met doelen
- Documenteer significante incidenten
- Pas doelen aan indien nodig

---

*Aangemaakt: 29 maart 2026 — VP-09*
