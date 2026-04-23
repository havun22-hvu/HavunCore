---
title: TLS group restrict via per-process OpenSSL config (geen upgrade nodig)
type: runbook
scope: server-prod
status: COMPLETED
last_check: 2026-04-23
---

# TLS group restrict — nginx-only OpenSSL config

> **Aanleiding:** SSL Labs Key Exchange 90/100 omdat OpenSSL 3.0.2
> + nginx 1.28 `ssl_conf_command Groups` directive negeert. x25519
> blijft in offered TLS groups staan.
>
> **Originele plan**: OpenSSL upgrade naar 3.2+ via Ondrej PPA.
> **Realisatie**: Ondrej PPA distribueert geen OpenSSL packages,
> Ubuntu 22.04 stuck op 3.0.2 series. Alternatief gevonden:
> per-process OpenSSL config via systemd env var. Geen upgrade nodig.

## Plan

### Fase 1 — Voorbereiding

1. Backup nginx-config + OpenSSL-config:
   ```bash
   tar czf /root/openssl-nginx-backup-2026-04-23.tar.gz /etc/nginx /etc/ssl
   ```
2. Controleer welke packages OpenSSL gebruiken (apt rdepends openssl)
3. Verifieer Ondrej heeft OpenSSL 3.2+ of we moeten ondrej/openssl PPA toevoegen

### Fase 2 — OpenSSL upgrade

4. `add-apt-repository ppa:ondrej/nginx` (al toegevoegd 23-04 ochtend)
5. `apt list --upgradable | grep -i openssl` — check available versions
6. Indien nodig: `add-apt-repository ppa:ondrej/php` (heeft eigen libssl-build)
   of dedicated PPA voor moderne OpenSSL
7. `apt install libssl3 openssl -y` upgrade
8. `openssl version` verifieer ≥ 3.2

### Fase 3 — Nginx ssl_conf_command werkend

9. Test of `ssl_conf_command Groups secp384r1:secp521r1` nu effectief is:
   ```bash
   echo | openssl s_client -connect herdenkingsportaal.nl:443 \
       -servername herdenkingsportaal.nl 2>&1 | grep 'Server Temp Key'
   ```
   Verwacht: `secp384r1, 384 bits` (was `X25519, 253 bits`)

10. Voeg `ssl_conf_command Groups secp384r1:secp521r1;` toe aan
    `/etc/nginx/snippets/ssl-hardened.conf`

### Fase 4 — Validatie

11. nmap cipher scan toont alleen secp384r1 in offered curves
12. SSL Labs UI rescan (door Henk) → Key Exchange 100/100

## Risico's

| Risico | Kans | Impact | Mitigatie |
|--------|------|--------|-----------|
| OpenSSL upgrade breekt PHP/Laravel | Laag | Hoog | Ondrej PPA test patches integraal — gebruikt door miljoenen prod-servers |
| Outbound TLS breekt naar oude clients | Zeer laag | Mid | Restriction is op INBOUND only, outbound is openSSL-default |
| nginx hercompile nodig | Mogelijk | Mid | Apt cascade afhandelen via package-manager |

## Rollback

```bash
ssh root@188.245.159.115
cd /etc/nginx && tar xzf /root/openssl-nginx-backup-2026-04-23.tar.gz
apt install --reinstall libssl3=3.0.2-* nginx=1.28.1-*
systemctl reload nginx
```

## Akkoord

Henk: "haha wat een vraag, je kent me toch" — bevestiging optie B
(enterprise-niveau, geen cosmetic, geen system-wide risk).

## Uitgevoerde fix (creative pivot, geen OpenSSL upgrade)

**Stap 1**: nieuw config-file `/etc/nginx/openssl-restricted.cnf`:
```
openssl_conf = openssl_init

[openssl_init]
ssl_conf = ssl_sect

[ssl_sect]
system_default = ssl_default_sect

[ssl_default_sect]
Groups = secp384r1:secp521r1
```

**Stap 2**: systemd override `/etc/systemd/system/nginx.service.d/openssl-restricted.conf`:
```
[Service]
Environment=OPENSSL_CONF=/etc/nginx/openssl-restricted.cnf
```

**Stap 3**: `systemctl daemon-reload && systemctl restart nginx`.

**Resultaat (3× openssl s_client + nmap):**
- Server Temp Key: ECDH secp384r1, 384 bits (was X25519, 253 bits)
- TLS 1.2 ciphers offered: alleen ECDHE_ECDSA met secp384r1
- nmap warning "lower strength than certificate key" verdwenen

**Scope-isolatie:**
- Andere apps op de server (PHP outbound, Composer, MySQL, etc.)
  blijven `/etc/ssl/openssl.cnf` system-default gebruiken (incl. x25519).
- Geen breakage-risk voor outbound TLS naar Mollie/Stripe/Anthropic API's.

SSL Labs UI rescan vereist voor 100/100 confirmation.
