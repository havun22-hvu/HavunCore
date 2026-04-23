---
title: SSL session resumption — http-level fix (2026-04-23)
type: runbook
scope: alle-projecten
last_check: 2026-04-23
status: RESOLVED
---

# SSL session resumption — http-level fix

## Symptoom

SSL Labs meldde **oranje minor**: _"Session resumption (caching): No (IDs assigned but not accepted)"_ op alle 7 productie-domeinen, ook al stond `ssl_session_cache shared:SSL:10m` + `ssl_session_tickets on` in de hardened-snippet.

## Root cause

Twee losstaande problemen:

1. **Dubbele zone-declaratie per server** — `shared:SSL:10m` werd in elke
   vhost die de snippet includeerde opnieuw gedeclareerd. Nginx accepteert
   dat zonder error, maar ID-based resumption faalt bij worker-wissel of
   cross-vhost requests: server geeft Session-ID uit maar vindt 'm niet
   terug in de cache.
2. **Let's Encrypt's `options-ssl-nginx.conf`** werd nog in 9 vhosts
   geïnclude — die zet `ssl_session_tickets off` + gebruikt een andere
   zone-naam (`le_nginx_SSL` i.p.v. `SSL`). Conflict met de hardened-snippet.

## Fix

1. **http-level** in `/etc/nginx/nginx.conf` (één bron van waarheid):
   ```
   ssl_session_cache shared:SSL:50m;
   ssl_session_timeout 1d;
   ssl_session_tickets on;
   ```
2. **Snippet opgeschoond** — `/etc/nginx/snippets/ssl-hardened.conf` bevat
   alleen nog protocols, ciphers en `ssl_conf_command`.
3. **9 vhosts gemigreerd** van `options-ssl-nginx.conf` naar
   `ssl-hardened.conf` (geparkeerde havunclub + havunvet overgeslagen).

## Verificatie (post-fix)

Op alle 7 productie-domeinen (havuncore, havunadmin, herdenkingsportaal,
judotournament.org, infosyst, demo, studieplanner):

```
openssl s_client -connect <domein>:443 -servername <domein> \
  -no_ticket -sess_out /tmp/s -tls1_2 < /dev/null
openssl s_client -connect <domein>:443 -servername <domein> \
  -no_ticket -sess_in /tmp/s -tls1_2 < /dev/null | grep Reused
```
→ `Reused, TLSv1.2, Cipher is ECDHE-ECDSA-AES256-GCM-SHA384` ✅

## Gepermaniseerd in code

- `docs/kb/templates/server-configs/nginx-http-level-ssl.conf` — nieuw template
- `docs/kb/templates/server-configs/nginx-ssl-hardened-snippet.conf` — session-regels verwijderd
- `docs/kb/reference/productie-deploy-eisen.md` §1.5 — uitgebreid met ID-based test + anti-pattern waarschuwing
- `ProjectScaffoldCommand` — README deploy-stap 2 toegevoegd voor http-level install
