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

## Verplichte regression-tests (per Laravel-project)

Elk project met een `SecurityHeaders` middleware MOET deze 4 regression-
tests hebben in `tests/Feature/Middleware/SecurityHeadersTest.php` (of
gelijkwaardig). Doel: voorkom silent regressies op Mozilla Observatory /
SSL Labs scoring.

1. **`hsts_header_includes_preload_over_https`** — verifieert dat HSTS
   `max-age=31536000; includeSubDomains; preload` op HTTPS wordt gestuurd
   (`URL::forceScheme('https')` in test om secure-request te simuleren).
2. **`hsts_header_absent_on_http`** — geen HSTS over plain HTTP (anti
   mixed-content lockout).
3. **`csp_does_not_allow_unsafe_eval`** (of `..._after_alpine_csp_migration`
   als skipped TODO) — Mozilla Observatory penalty -10 wanneer
   `unsafe-eval` staat in CSP. Voor projecten in Alpine-migratie:
   `markTestSkipped()` met TODO-message; verwijderen na switch naar
   `@alpinejs/csp`.
4. **`csp_does_not_allow_unsafe_inline_in_script_src`** — script-src mag
   geen `unsafe-inline` bevatten (alleen `'nonce-...'`).

Status (24-04-2026): geactiveerd op HavunAdmin, Herdenkingsportaal,
JudoToernooi, Infosyst. Bij scaffold van nieuw project — toevoegen.

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

### 1.4b Signature algorithms (TLS 1.2): SHA-256 / 384 / 512 only

- **Waarom**: internet.nl flag SHA-224 als "uit te faseren" per NCSC TLS
  2025-05. OpenSSL 3 default lijst bevat SHA-224 voor backward compat.
- **Hoe**: in hardened snippet:
  ```nginx
  ssl_conf_command SignatureAlgorithms ECDSA+SHA384:ECDSA+SHA256:ECDSA+SHA512:RSA-PSS+SHA384:RSA-PSS+SHA256:RSA-PSS+SHA512:RSA+SHA384:RSA+SHA256:RSA+SHA512;
  ```
- **Scope**: alleen TLS 1.2; TLS 1.3 heeft eigen signature scheme dat niet
  via deze directive wordt gestuurd.
- **Verifieer (SHA-224 moet falen)**:
  ```bash
  echo | openssl s_client -connect <domain>:443 -servername <domain> \
      -tls1_2 -sigalgs ecdsa_secp224r1_sha224 2>&1 | grep alert
  ```
  → handshake_failure (of geen output = no connection).
- **Verifieer (SHA-384 moet werken)**:
  ```bash
  echo | openssl s_client -connect <domain>:443 -servername <domain> \
      -tls1_2 -sigalgs ecdsa_secp384r1_sha384 2>&1 | grep "Peer signing"
  ```
  → `Peer signing digest: SHA384`.

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

### 2.7 Cross-Origin Isolation Headers (CORP + COOP)

- **Waarom**: bescherming tegen Spectre/XS-Leaks cross-origin attacks. Geen
  letter-grade impact op de 5 testsites, maar tonen op SecurityHeaders.com
  als "Recommended ✓" in de Additional Information sectie. Defensieve
  best-practice.
- **Headers**:
  - `Cross-Origin-Resource-Policy` (CORP): wie mag jouw resources fetchen
  - `Cross-Origin-Opener-Policy` (COOP): isolatie van browsing-context
  - `Cross-Origin-Embedder-Policy` (COEP): vereist CORP op embedded resources

#### Beleid per projecttype

| Projecttype | CORP | COOP | COEP |
|-------------|------|------|------|
| **Intern admin/tool** (HavunAdmin, HavunCore, Studieplanner-API) | `same-origin` | `same-origin` | weggelaten |
| **Publieke site met social sharing** (Herdenkingsportaal, JudoToernooi, havun.nl) | `cross-origin` | `same-origin-allow-popups` | weggelaten |

- **CORP `same-origin`** = strict, alleen jouw eigen domain mag fetchen. Veilig
  voor admin-tools waar geen externe embed nodig is.
- **CORP `cross-origin`** = open (default). Vereist voor sites waar OG-images
  via Facebook/LinkedIn/X opgehaald worden, anders breken social previews.
- **COOP `same-origin`** = strict popup-isolation. Window.opener wordt `null`
  bij cross-origin popup. Veilig voor admin.
- **COOP `same-origin-allow-popups`** = behoudt opener-relatie voor popups die
  je zelf opent (OAuth-flow, social sharing). Veilig voor publieke sites.
- **COEP weggelaten**: `require-corp` breekt externe fonts (Google Fonts, Bunny
  Fonts) en CDN-resources die geen CORP-header sturen. Alleen overwegen als
  je SharedArrayBuffer of high-precision timers nodig hebt — voor onze
  use-cases niet relevant.

#### Implementatie (Laravel SecurityHeaders middleware)

```php
// In app/Http/Middleware/SecurityHeaders.php, na de bestaande headers:

$isPublic = in_array($appSlug, ['herdenkingsportaal', 'judotoernooi', 'havun']);

$response->headers->set(
    'Cross-Origin-Resource-Policy',
    $isPublic ? 'cross-origin' : 'same-origin'
);
$response->headers->set(
    'Cross-Origin-Opener-Policy',
    $isPublic ? 'same-origin-allow-popups' : 'same-origin'
);
// COEP bewust weggelaten — breekt externe fonts/CDN
```

#### Verifieer

```bash
curl -skI https://<domain>/ | grep -iE 'cross-origin-(resource|opener)-policy'
```

Verwacht voor admin: `Cross-Origin-Resource-Policy: same-origin` +
`Cross-Origin-Opener-Policy: same-origin`.

Verwacht voor publiek: `Cross-Origin-Resource-Policy: cross-origin` +
`Cross-Origin-Opener-Policy: same-origin-allow-popups`.

#### Test op SecurityHeaders.com

Na deploy → https://securityheaders.com/?q=<domain> → "Additional Information"
sectie toont CORP en COOP met blue ✓. Letter-grade blijft A+ (geen change).

#### Test of social sharing niet kapot is (publieke sites)

Voor Herdenkingsportaal/JT/havun.nl na deploy:
- Facebook Sharing Debugger: https://developers.facebook.com/tools/debug/
- LinkedIn Post Inspector: https://www.linkedin.com/post-inspector/
- Beide moeten OG-image kunnen ophalen. Als CORP=cross-origin staat: groen.
  Als per ongeluk same-origin: image laadt niet.

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
- **Uitzondering**: Google-scripts (gtag.js, cast_sender.js) roteren
  inhoud → geen stabiele SRI-hash mogelijk. **Oplossing**: weghalen of
  self-hosten (zie §3.2.1).

### 3.2.1 Analytics-policy — Google Analytics default WEG

- **Default**: geen Google Analytics in nieuwe Havun-projecten.
- **Reden**:
  1. SRI niet mogelijk op externe gtag.js → -5 Observatory penalty
  2. AVG-risico: GA stuurt IP/sessiedata naar Google (VS)
  3. Cookie-banner verplicht zodra actief → UX-last
  4. Voor tools/admin-panels: marketing-analytics heeft geen functie
- **Beoordeling per project**: GA mag alleen wanneer
  (a) site is expliciet content/marketing-gericht, EN
  (b) geen gevoelige persoonsgegevens (minderjarigen, overlijdens,
      medisch, financieel), EN
  (c) data-eigenaar legt AVG-compliance vast (DPA, cookie-consent).
  Zo nee: GA mag niet.
- **Alternatieven (cookieless)**:
  - **Niks** (default voor tools/admin-panels)
  - **Umami self-host** op `umami.havun.nl` — LIVE sinds 2026-04-23,
    zie `runbooks/umami-analytics-setup-2026-04-23.md` voor setup +
    integratie + nieuwe site toevoegen
  - **Plausible** (hosted, €9/mnd, niet in gebruik)
- **Beslissingshistorie**: 2026-04-23 verwijderd uit JudoToernooi (tool,
  geen zin) + Herdenkingsportaal (AVG-risk overleden-data).

### 3.3 Secure cookies + `__Host-` prefix

- **Waarom**: SecurityHeaders warning _"There is no Cookie Prefix on this
  cookie"_ en _"domain attribute set"_ → cookie voldoet niet aan modern
  best-practice. `__Host-` prefix verplicht: `secure` + `path=/` +
  **geen** `domain` attribute.
- **Hoe (Laravel)** — productie `.env`:
  ```
  SESSION_COOKIE=__Host-<slug>-session
  SESSION_DOMAIN=
  SESSION_SECURE_COOKIE=true
  SESSION_SAME_SITE=lax
  ```
  `SESSION_DOMAIN` expliciet leeg (niet `null` als string). Dit bricked
  cross-subdomain-sessies — acceptabel, want elk Havun-project heeft
  z'n eigen (sub)domein + eigen app-instance.
- **Default in `config/session.php`**:
  ```php
  'secure' => env('SESSION_SECURE_COOKIE', true),
  'http_only' => true,
  'same_site' => 'lax',
  'domain' => env('SESSION_DOMAIN'),  // default null = no domain attr
  ```
- **Verifieer**:
  ```
  curl -skI -L https://<domain>/login | grep -i '^set-cookie:'
  ```
  → `__Host-<slug>-session=...; path=/; secure; httponly; samesite=lax`
  (GEEN `domain=` attribute).
- **Bijwerking**: bestaande sessies worden ongeldig bij naamswijziging
  → users loggen één keer opnieuw in. Plan rollout buiten piek.
- **Open**: XSRF-TOKEN cookie heeft `XSRF-TOKEN` naam hardcoded in
  Laravel's `VerifyCsrfToken` middleware. Prefix `__Secure-XSRF-TOKEN`
  vereist custom middleware-override per project + axios-defaults
  aanpassing. Separate follow-up.

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

## 4.4 security.txt (RFC 9116) — beveiligingscontact

- **Eis**: `/.well-known/security.txt` op elke productie-(sub)domein.
- **Hoe (centraal patroon)**:
  - `/var/www/security.txt` met `Contact:`, `Expires:` (max 1 jaar in de
    toekomst), `Preferred-Languages:`, `Canonical:` (lijst van alle URI's
    waar dit bestand wordt geserveerd).
  - `/etc/nginx/snippets/security-txt.conf`:
    ```nginx
    location = /.well-known/security.txt {
        alias /var/www/security.txt;
        default_type "text/plain; charset=utf-8";
    }
    ```
  - In elke 443-vhost: `include /etc/nginx/snippets/security-txt.conf;`
    (na de `ssl-hardened.conf` include).
- **Verifieer**:
  ```bash
  curl -sk https://<domain>/.well-known/security.txt | head -3
  ```
  → Contact: + Expires: regels.
- **Onderhoud**: vóór `Expires`-datum (typisch 1 jaar) bestand verlengen.
  Eerstvolgende rotatie: zie de header van `/var/www/security.txt`.

---

## 5. Internet.nl → 100%

### 5.1 IPv6 AAAA-record + nginx IPv6 listen

- **Eis**: zowel DNS AAAA-record als nginx vhost IPv6 listen. Eén zonder de
  ander = "site niet bereikbaar over IPv6" op internet.nl.
- **Server IPv6**: `2a01:4f8:1c1a:457f::1` (Hetzner /64-prefix host).
- **DNS hoe**: AAAA-record per (sub)domein bij mijnhost.nl. Wildcard
  `AAAA *.<zone>.` werkt alleen voor namen die geen expliciete A-record
  hebben — voor expliciete subdomeinen ook expliciete AAAA toevoegen.
  CNAME-value MOET een hostname zijn, nooit een IPv6-string (DNS-fout).
- **nginx hoe**: per vhost in `/etc/nginx/sites-enabled/`:
  ```nginx
  listen 443 ssl;
  listen [::]:443 ssl;   # IPv6 — verplicht naast IPv4
  listen 80;
  listen [::]:80;
  ```
- **Verifieer (DNS)**: `dig @1.1.1.1 +short AAAA <domain>` → IPv6-adres.
- **Verifieer (TCP/HTTPS)**: vanaf externe host:
  ```bash
  curl -sk --resolve <domain>:443:[2a01:4f8:1c1a:457f::1] https://<domain>/
  ```
  → HTTP/200 (of 302 bij login-redirect). Geen connection-error.
- **Watch out**: `getent ahosts <domain>` op de server zelf kan tijdelijk
  alleen IPv4 tonen door glibc resolver-cache. Niet relevant voor externe
  tests; lost vanzelf op na TTL (typisch 15 min) of via
  `systemd-resolve --flush-caches`.

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
