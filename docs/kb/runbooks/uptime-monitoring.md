# Runbook: Uptime Monitoring & SLA

> **Bron:** Externe audit Q1 2026 (VP-09)
> **Tool:** UptimeRobot (gratis tier)

## Uptime-doelen

| App | URL | Uptime-doel | Kritiek? | Toelichting |
|-----|-----|-------------|----------|-------------|
| Herdenkingsportaal | herdenkingsportaal.nl | 99.5% (~43u/jaar) | Ja | Publiek verkeer, betalingen |
| JudoToernooi | judotoernooi.havun.nl | 99% (~87u/jaar) | Ja | Actief tijdens toernooien |
| HavunCore | havuncore.havun.nl | 99% | Ja | Andere apps zijn afhankelijk |
| HavunAdmin | havunadmin.havun.nl | 95% | Nee | Intern beheer |
| SafeHavun | safehavun.havun.nl | 95% | Nee | Beperkt gebruik |
| Infosyst | infosyst.havun.nl | 95% | Nee | Beperkt gebruik |

## UptimeRobot Setup

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
