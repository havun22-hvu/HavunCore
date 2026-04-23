---
title: Productie-deploy eisen — A+ / 10 op alle testsites
type: reference
scope: alle-projecten
status: BINDING
last_check: 2026-04-23
---

# Productie-deploy eisen

> **Eis:** elke nieuwe Havun-productie-deploy moet **A+ / 100** scoren
> op alle externe testsites uit `externe-testpages.md`. Geen 90,
> geen A, geen "goed genoeg". Dit document is de canonical checklist.
>
> **Groei:** nieuwe eisen worden hier toegevoegd per ontdekte sub-test
> die we nog niet dekken. Per eis staat: _waarom_, _hoe implementeren_,
> _hoe verifiëren_.

## Algemeen

- **Geen uitzondering** zonder schriftelijk akkoord van Henk.
- Bij conflict tussen eisen: meest strikt wint, behalve als een eis
  expliciet een andere overrulend.
- Per sub-eis staat een template-file of runbook voor implementatie.

---

## 1. SSL Labs → A+ / 100 / 100 / 100 / 100

Overall grade A+ + **100 op alle 4 sub-scores** (Certificate, Protocol
Support, Key Exchange, Cipher Strength).

### 1.1 Certificate: ECDSA P-384

- **Waarom**: RSA 2048 geeft max 90 Key Exchange. ECDSA P-384 (secp384r1)
  geeft 100. Kleiner + sneller dan RSA 4096, zelfde security-level.
- **Hoe**: certbot met `--key-type ecdsa --elliptic-curve secp384r1`.
  Bij re-issue van bestaande cert: ook `--no-reuse-key --force-renewal`.
- **Verifieer**: `certbot certificates --cert-name <domain>` → "Key Type: ECDSA".

### 1.2 Protocol: TLS 1.2 + 1.3 only

- **Waarom**: TLS 1.0/1.1 zijn deprecated. TLS 1.3 heeft modern PFS en
  cleaner handshake.
- **Hoe**: in nginx vhost of hardened-snippet: `ssl_protocols TLSv1.2 TLSv1.3;`
- **Verifieer**: `nmap --script ssl-enum-ciphers -p 443 <domain>` toont
  geen TLSv1 / TLSv1.1 sections.

### 1.3 Cipher Strength: alleen 256-bit AEAD

- **Waarom**: SSL Labs scoort 90 als 128-bit ciphers acceptabel zijn,
  100 als minimum 256-bit. Moderne doelgroep ondersteunt AES-256 overal.
- **Hoe (TLS 1.2)**:
  ```nginx
  ssl_ciphers ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305;
  ssl_prefer_server_ciphers on;
  ```
- **Hoe (TLS 1.3)**: TLS 1.3 cipher restriction via nginx:
  ```nginx
  ssl_conf_command Ciphersuites TLS_AES_256_GCM_SHA384:TLS_CHACHA20_POLY1305_SHA256;
  ```
- **Verifieer**: `echo | openssl s_client -connect <domain>:443 -tls1_3 -servername <domain> 2>&1 | grep Cipher`
  → output bevat `AES_256_GCM` of `CHACHA20`, **nooit** AES_128.

### 1.4 Key Exchange: alleen secp384r1 / secp521r1 curves

- **Waarom**: x25519 curve = 128-bit security level. Server-offered curve
  moet ≥ cert-key strength (P-384 = 192-bit) voor 100 score. SSL Labs
  warning: "Key exchange of lower strength than certificate key".
- **Hoe**: nginx + OpenSSL 3.0.2 heeft bug in `ssl_conf_command Groups`
  (directive wordt genegeerd). **Workaround**: per-process OpenSSL config
  via systemd env var:

  `/etc/nginx/openssl-restricted.cnf`:
  ```
  openssl_conf = openssl_init

  [openssl_init]
  ssl_conf = ssl_sect

  [ssl_sect]
  system_default = ssl_default_sect

  [ssl_default_sect]
  Groups = secp384r1:secp521r1
  ```

  `/etc/systemd/system/nginx.service.d/openssl-restricted.conf`:
  ```
  [Service]
  Environment=OPENSSL_CONF=/etc/nginx/openssl-restricted.cnf
  ```

  Dan: `systemctl daemon-reload && systemctl restart nginx`.

  **Scope**: alleen nginx-proces gebruikt deze config. Andere apps
  (PHP/Composer/MySQL outbound) blijven `/etc/ssl/openssl.cnf` default
  met x25519 — **geen risk voor outbound TLS**.
- **Verifieer**: `echo | openssl s_client -connect <domain>:443 -servername <domain> 2>&1 | grep 'Server Temp Key'`
  → `ECDH, secp384r1, 384 bits` (NIET `X25519`).
- **Bron**: `runbooks/openssl-upgrade-2026-04-23.md`.

### 1.5 Session resumption aan (caching + tickets)

- **Waarom**: SSL Labs test twee mechanismen apart:
  1. **Tickets** (RFC 5077, ook TLS 1.3 PSK).
  2. **Session-IDs** (klassieke cache). Als IDs uitgedeeld worden maar niet
     geaccepteerd → SSL Labs meldt: _"IDs assigned but not accepted"_ (oranje).
- **Hoe**: directives op **http-level** in `/etc/nginx/nginx.conf` (NIET per
  server), anders faalt ID-resumption bij worker-wissel of dubbele
  zone-declaraties:
  ```nginx
  # in http { } block:
  ssl_session_cache shared:SSL:50m;
  ssl_session_timeout 1d;
  ssl_session_tickets on;
  ```
  De hardened-snippet mag GEEN session directives meer bevatten — alleen
  protocols, ciphers en conf_command.
- **Verifieer (tickets)**:
  ```bash
  echo | openssl s_client -connect <domain>:443 -tls1_2 -sess_out /tmp/s1
  echo | openssl s_client -connect <domain>:443 -tls1_2 -sess_in /tmp/s1 | grep Reused
  ```
- **Verifieer (IDs — belangrijker voor SSL Labs)**:
  ```bash
  echo | openssl s_client -connect <domain>:443 -tls1_2 -no_ticket -sess_out /tmp/s2
  echo | openssl s_client -connect <domain>:443 -tls1_2 -no_ticket -sess_in /tmp/s2 | grep Reused
  ```
  → beide tests moeten `Reused, TLSv1.2` tonen.
- **Anti-pattern**: Let's Encrypt's `options-ssl-nginx.conf` include in
  vhosts. Die zet `ssl_session_tickets off` + andere zone-naam → vervangen
  door de hardened-snippet.

### 1.6 DNS CAA records

- **Waarom**: SSL Labs "DNS CAA: Yes" vereist. Restricts welke CA's certs
  mogen uitgeven voor je domein.
- **Hoe**: bij DNS-provider (mijnhost.nl voor Havun) per zone-root:
  ```
  CAA  @  0 issue "letsencrypt.org"
  CAA  @  0 iodef "mailto:havun22@gmail.com"
  ```
  Subdomeinen erven automatisch van parent zone.
- **Verifieer**: `dig @8.8.8.8 CAA <domain> +short` → 2 regels.

### 1.7 ssl_reject_handshake voor non-SNI

- **Waarom**: SSL Labs "Certificate #2 No SNI" finding. Non-SNI clients
  (Windows XP IE6, Android 2.x) krijgen nu een fallback-cert met
  warnings. Weigeren = cleaner.
- **Hoe**: vereist nginx ≥ 1.19.4. `/etc/nginx/conf.d/00-default-https.conf`:
  ```nginx
  server {
      listen 443 ssl default_server;
      listen [::]:443 ssl default_server;
      ssl_reject_handshake on;
  }
  ```
- **Verifieer**: `echo | openssl s_client -connect <ip>:443 -noservername 2>&1 | grep alert`
  → `handshake_failure` of `unrecognized name`.

---

## 2. SecurityHeaders.com → A+

Alle 6 **recommended headers** aanwezig met strikte waarden.

### 2.1 Content-Security-Policy

- **Waarom**: XSS-bescherming + script/style origin control.
- **Hoe**: per-request nonce (niet unsafe-inline). Basic template:
  ```
  default-src 'self';
  script-src 'self' 'nonce-{$nonce}';
  style-src 'self' 'nonce-{$nonce}';
  img-src 'self' data:;
  object-src 'none';
  base-uri 'self';
  form-action 'self';
  upgrade-insecure-requests;
  ```
  Extend per-project met specifieke CDN-domains (enforce integrity).
- **Verifieer**: https://csp-evaluator.withgoogle.com → geen "High"-severity findings.

### 2.2 Strict-Transport-Security (HSTS)

- **Waarom**: voorkomt downgrade-aanvallen.
- **Hoe**: `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`
- **Plus**: domain inschrijven op https://hstspreload.org (na verificatie
  dat ALLE subdomeinen HTTPS doen).

### 2.3 X-Content-Type-Options

- **Hoe**: `X-Content-Type-Options: nosniff`
- **Waarom**: browser niet MIME-sniffen (XSS-mitigation).

### 2.4 X-Frame-Options

- **Hoe**: `X-Frame-Options: SAMEORIGIN` (of `DENY` als niet iframed wordt).
- **Waarom**: clickjacking-bescherming.

### 2.5 Referrer-Policy

- **Hoe**: `Referrer-Policy: strict-origin-when-cross-origin`
- **Waarom**: geen leak van interne URLs via referrer.

### 2.6 Permissions-Policy

- **Hoe**: `Permissions-Policy: camera=(), microphone=(), geolocation=(), fullscreen=(self)` — restrict tot wat nodig is.
- **Waarom**: disable features die we niet gebruiken (privacy + attack surface).

---

## 3. Mozilla Observatory → A+ (score 100)

### 3.1 CSP zonder unsafe-inline

- **Eis**: script-src en style-src met nonce of sha-hash, **nooit**
  unsafe-inline.
- **Technisch**: zie sectie 2.1 + per-inline `<script nonce="{$nonce}">`.

### 3.2 Subresource Integrity (SRI) op externe CDN-scripts

- **Hoe**: `<script src="https://cdn..." integrity="sha384-..." crossorigin="anonymous"></script>`
- **Verifieer**: alle `<script src="https://..."` regels moeten
  integrity-attr hebben.

### 3.3 Secure cookies

- **Hoe (Laravel)**: `config/session.php`:
  ```php
  'secure' => env('SESSION_SECURE_COOKIE', true),
  'http_only' => true,
  'same_site' => 'strict',
  ```
  Env: `SESSION_SECURE_COOKIE=true` in productie .env.

### 3.4 Object-src 'none' + base-uri 'self'

- **Waarom**: voorkomt plugin-injectie + base-tag hijack.
- **Hoe**: in CSP zoals sectie 2.1.

---

## 4. Hardenize → alle groene checkmarks

### 4.1 DNSSEC actief

- **Hoe**: bij DNS-provider (mijnhost.nl) DNSSEC aanzetten per zone.
  Automatisch DS-record bij NIC wordt gepubliceerd.
- **Verifieer**: `dig DS <domain> +short` → hash-output (niet leeg).

### 4.2 SPF + DKIM + DMARC (indien domain mail verstuurt)

- **SPF**: `v=spf1 include:<provider> ~all` (bij email-provider, bv. Brevo).
- **DKIM**: CNAME-records naar provider (Brevo/Mailgun/SendGrid).
- **DMARC**: start met `v=DMARC1; p=none; rua=mailto:dmarc@<domain>`.
  Na weken monitoring → `p=quarantine` → `p=reject`.

### 4.3 CAA (zie 1.6) + HSTS (zie 2.2)

---

## 5. Internet.nl → 100%

### 5.1 IPv6 AAAA-record

- **Hoe**: bij DNS-provider AAAA record toevoegen met server IPv6.
  Hetzner VPS heeft standaard IPv6 beschikbaar.
- **Verifieer**: `dig AAAA <domain> +short` → IPv6-adres (niet leeg).

### 5.2 STARTTLS + DANE (als domain mail verstuurt)

- **Hoe**: TLSA-record in DNS koppelt aan cert-hash. Complexer setup.
- **Deferred**: only if we host email; bij Brevo/Mailgun is dat door hen geregeld.

### 5.3 DMARC met reject policy

- **Hoe**: uiteindelijke DMARC policy = `p=reject` (na monitoring-fase
  met `p=none` → `p=quarantine`).

---

## 6. Cross-cutting checks

### 6.1 Cipher Strength TLS 1.3 geen AES_128

Zie 1.3 — `ssl_conf_command Ciphersuites`.

### 6.2 Per-process OpenSSL Groups restrict (nginx-only)

Zie 1.4 — via systemd env var.

### 6.3 Automatische cert-renewal

- **Hoe**: certbot installeert zichzelf een systemd-timer voor renewal.
  Verifieer: `systemctl list-timers | grep certbot`.
- **Bij ECDSA**: renewal respecteert key-type automatisch.

---

## Verificatie-sequence voor nieuwe productie-deploy

1. `dig CAA <domain> +short` — CAA records gepubliceerd
2. `dig AAAA <domain> +short` — IPv6
3. `certbot certificates --cert-name <domain>` — Key Type: ECDSA
4. `nginx -t && systemctl reload nginx` — config valid
5. `echo | openssl s_client -connect <domain>:443 -servername <domain> 2>&1 | grep -E 'Cipher|Server Temp Key'` — AES-256 + secp384r1
6. **SSL Labs UI** https://www.ssllabs.com/ssltest/analyze.html?d=<domain> → A+ / 100 / 100 / 100 / 100
7. **SecurityHeaders** https://securityheaders.com/?q=<domain> → A+
8. **Mozilla Observatory** https://observatory.mozilla.org/analyze/<domain> → A+
9. **Hardenize** https://www.hardenize.com/report/<domain> — alle secties groen
10. **Internet.nl** https://internet.nl/site/<domain> → 100%

Elk vinkje noteren in deploy-checklist per project.

## Template-files (in repo)

Zie `docs/kb/templates/server-configs/`:
- `nginx-ssl-hardened-snippet.conf`
- `openssl-restricted.cnf`
- `systemd-nginx-openssl-override.conf`
- `nginx-vhost-hardened.conf.template`

## Zie ook

- `externe-testpages.md` — welke testsites we gebruiken
- `security-headers-check.md` — CSP/SecurityHeaders detail-runbook
- `nginx-upgrade-2026-04-23.md` — history: nginx 1.18 → 1.28
- `ssl-100-100-2026-04-23.md` — history: ECDSA + hardening iteraties
- `openssl-upgrade-2026-04-23.md` — per-process Groups restrict
