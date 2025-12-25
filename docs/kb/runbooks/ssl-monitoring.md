# SSL Certificate Monitoring

> Automatische controle van alle SSL certificaten op de server

## Overzicht

- **Script:** `/usr/local/bin/check-ssl-certs`
- **Log:** `/var/log/ssl-check.log`
- **Schedule:** Elke maandag 09:00 UTC
- **Waarschuwing:** Bij <14 dagen geldigheid

## Handmatig uitvoeren

```bash
ssh root@SERVER_IP (zie context.md) "/usr/local/bin/check-ssl-certs"
```

## Output voorbeeld

```
✅ OK | havuncore.havun.nl | 61 dagen
✅ OK | herdenkingsportaal.nl | 42 dagen
⚠️  WAARSCHUWING | example.nl | 7 dagen
```

## Bij waarschuwing

Certificaat handmatig vernieuwen:

```bash
ssh root@SERVER_IP (zie context.md) "certbot certonly --nginx -d <domein> --force-renewal"
```

## Alle certificaten

Locatie: `/etc/letsencrypt/live/`

| Domein | Project |
|--------|---------|
| havuncore.havun.nl | HavunCore |
| havunadmin.havun.nl | HavunAdmin |
| herdenkingsportaal.nl | Herdenkingsportaal |
| staging.herdenkingsportaal.nl | Herdenkingsportaal staging |
| judotournament.org | Judotoernooi |
| havun.nl | Havun website |

## Troubleshooting

**Renewal faalt met Apache error:**
```bash
# Gebruik nginx in plaats van apache
certbot certonly --nginx -d <domein> --force-renewal
```

**Certificaat niet in lijst:**
```bash
# Nieuw certificaat aanvragen
certbot --nginx -d <domein>
```

## Cron configuratie

File: `/etc/cron.d/ssl-monitor`
```
0 9 * * 1 root /usr/local/bin/check-ssl-certs >> /var/log/ssl-check.log 2>&1
```
