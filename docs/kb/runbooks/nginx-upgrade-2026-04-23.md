---
title: Nginx upgrade 1.18 → 1.27 + ssl_reject_handshake
type: runbook
scope: server-prod
status: COMPLETED
last_check: 2026-04-23
---

# Nginx upgrade 1.18 → 1.27 + ssl_reject_handshake

> **Aanleiding:** SSL Labs op herdenkingsportaal.nl meldt
> "Certificate #2: RSA 2048 bits (SHA256withRSA) No SNI". Fix vereist
> `ssl_reject_handshake on;` in nginx default vhost. Die directive
> bestaat sinds nginx 1.19.4 (2020-10-27). Server draait nginx 1.18.0.

## Scope

Server: **188.245.159.115** (Hetzner VPS, Ubuntu 22.04.5 LTS).
Raakt **alle** vhosts in `/etc/nginx/sites-enabled/` (17 sites).

## Plan

### Fase 1 — Voorbereiding ✅ (al gedaan)

1. ✅ Backup `/etc/nginx` → `/root/nginx-backup-2026-04-23.tar.gz` (14K)
2. ✅ PPA `ppa:ondrej/nginx` toegevoegd
3. ✅ `apt update` gedraaid (89 upgradable, geen uitvoering)

### Fase 2 — Nginx upgrade (uit te voeren)

4. `apt list --upgradable | grep nginx` → check welke nginx-pakketten
5. `apt install nginx nginx-common nginx-core -y` → upgrade (verwacht 1.18 → 1.27)
6. `nginx -v` → verifieer nieuwe versie
7. `nginx -t` → config-test (alle 17 vhosts moeten parsen op 1.27)

### Fase 3 — Default vhost + ssl_reject_handshake (uit te voeren)

8. Identificeer default https vhost (zoek `default_server` in
   `/etc/nginx/sites-enabled/*` en `/etc/nginx/conf.d/*`)
9. Indien geen expliciete default https vhost bestaat: maak
   `/etc/nginx/conf.d/00-default-https.conf`:
   ```nginx
   server {
       listen 443 ssl default_server;
       listen [::]:443 ssl default_server;
       ssl_reject_handshake on;
   }
   ```
10. Indien wel een default vhost bestaat: voeg `ssl_reject_handshake on;`
    toe in dat server-block
11. `nginx -t` → config-test
12. `systemctl reload nginx` → reload zonder downtime
13. Verifieer: `systemctl status nginx` → moet active running zijn

### Fase 4 — Validatie (uit te voeren)

14. Smoke-test alle 7 productie-domeinen:
    ```bash
    for d in havuncore.havun.nl havunadmin.havun.nl infosyst.havun.nl \
             safehavun.havun.nl api.studieplanner.havun.nl \
             herdenkingsportaal.nl judotournament.org; do
        echo -n "$d: "; curl -ksI "https://$d" -o /dev/null -w "%{http_code}\n"
    done
    ```
    Alle moeten 200/301/302 returnen, geen 502/503.

15. Smoke-test ssl_reject_handshake:
    ```bash
    openssl s_client -connect 188.245.159.115:443 -noservername </dev/null 2>&1 | head -10
    ```
    Verwacht: `tls_process_server_certificate:tlsv1 alert handshake failure`

## Rollback

Bij ANY storing:
```bash
ssh root@188.245.159.115
apt install nginx=1.18.0-6ubuntu14.* nginx-common=1.18.0-6ubuntu14.* -y
cd /etc/nginx && tar xzf /root/nginx-backup-2026-04-23.tar.gz --strip-components=2
nginx -t && systemctl reload nginx
```

Of via apt downgrade naar Ubuntu-default:
```bash
apt install nginx=1.18.0-* -t jammy --allow-downgrades
```

## Risico's

| Risico | Kans | Impact | Mitigatie |
|--------|------|--------|-----------|
| Een vhost-config gebruikt deprecated 1.18-syntax | Laag | Mid (1 site down) | `nginx -t` vóór reload — vangt het |
| nginx-php-fpm sock-pad wijzigt | Zeer laag | Hoog (alle PHP-sites down) | Ondrej PPA respecteert Ubuntu-defaults |
| Reload blijft hangen | Zeer laag | Hoog | Restart als fallback (~5s downtime) |
| Cert-paden veranderen | Geen | — | Cert-files staan in /etc/letsencrypt, nginx config blijft pointen |

## Akkoord

Henk gaf 22-04 om 23:55 expliciet toestemming met:
> "ga maar upgraden, ik kijk morgen vroeg naar het resultaat,
>  geen vragen meer, je hebt alle toestemming"

Plus: "/mpc principe toe" — daarom dit plan-doc geschreven vóór Fase 2-4.

Status na uitvoering wordt bijgewerkt naar `COMPLETED` met meting-resultaten.

## Uitvoer-resultaat (23-04-2026, ~00:35)

**Fase 2 — nginx upgrade ✅**
- nginx 1.18.0 → **1.28.1** (Ondrej PPA, jammy stream)
- 9 packages upgraded (nginx, nginx-common, nginx-core + 6 mods)
- Auto-reload tijdens install via apt-trigger, geen handmatige restart nodig
- `nginx -t` config-test OK met alle 17 vhosts

**Fase 3 — ssl_reject_handshake ✅**
- Geen bestaande default vhost gevonden → nieuwe `/etc/nginx/conf.d/00-default-https.conf` aangemaakt met `listen 443 ssl default_server` + `ssl_reject_handshake on;`
- `nginx -t` OK + `systemctl reload nginx` zonder downtime

**Fase 4 — Validatie ✅**

7/7 domeinen functioneel (curl HEAD):
| Domein | Status |
|--------|--------|
| havuncore.havun.nl | 200 |
| havunadmin.havun.nl | 302 |
| infosyst.havun.nl | 302 |
| safehavun.havun.nl | 302 |
| api.studieplanner.havun.nl | 200 |
| herdenkingsportaal.nl | 200 |
| judotournament.org | 200 |

No-SNI handshake correct geweigerd:
```
SSL routines:ssl3_read_bytes:tlsv1 unrecognized name (alert 112)
Cipher is (NONE)
```

Geen fallback-cert meer → SSL Labs zal volgende run "Certificate #2 No SNI" niet meer tonen.

**Rollback niet nodig.** Backup `/root/nginx-backup-2026-04-23.tar.gz`
blijft beschikbaar voor 30 dagen voor zekerheid.
