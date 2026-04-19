# Runbook: Security Headers & Mozilla Observatory

> **Frequentie:** Bij elke deploy + kwartaallijkse volledige check
> **Geldt voor:** Alle publieke webapps

## Online test-sites (handmatig, na deploy)

| Test | URL | Wat checkt het |
|------|-----|----------------|
| Mozilla Observatory | https://observatory.mozilla.org | CSP, SRI, X-Content-Type, HSTS — scored 0-100 |
| SecurityHeaders.com | https://securityheaders.com | Alle security headers — letter grade A-F |
| SSL Labs | https://www.ssllabs.com/ssltest | SSL certificaat, cipher suites, protocol — letter grade A-F |

## Waar staan security headers?

**In Laravel SecurityHeaders middleware** — NIET in nginx.

```
app/Http/Middleware/SecurityHeaders.php  → alle security headers
bootstrap/app.php                        → middleware registratie
```

Nginx mag ALLEEN `Cache-Control` headers zetten op static assets.
Dubbele headers (nginx + Laravel) veroorzaken test failures.

## Snelle check na deploy

```bash
for domain in herdenkingsportaal.nl havunadmin.havun.nl infosyst.havun.nl safehavun.havun.nl judotournament.org; do
  echo "=== $domain ==="
  curl -skI "https://$domain" | grep -ic "x-content-type" | xargs -I{} echo "X-Content-Type-Options count: {}"
  curl -skI "https://$domain" | grep -i "content-security-policy" | sed 's/; /;\n/g' | grep -E "default-src|script-src|object-src|base-uri|form-action"
  echo ""
done
```

## Mozilla Observatory CSP Sub-tests (6 stuks, elk -20 bij failure)

### 1. Blocks inline JavaScript
- **Check:** Geen `unsafe-inline` in `script-src`
- **Fix:** Gebruik `'nonce-{$nonce}'` voor alle `<script>` tags
- **Blade:** `<script @nonce>` voor inline, `<script src="..." @nonce>` voor extern

### 2. Blocks eval()
- **Check:** Geen `unsafe-eval` in `script-src`
- **Oorzaak:** Alpine.js (via Vite) gebruikt `new Function()` voor expressies
- **Fix:** Migreer naar `@alpinejs/csp` package:
  ```bash
  npm install @alpinejs/csp
  ```
  ```js
  // app.js — VOOR:
  import Alpine from 'alpinejs';
  // app.js — NA:
  import Alpine from '@alpinejs/csp';
  ```
  Let op: inline x-data objecten (`x-data="{ open: false }"`) moeten worden
  geregistreerd via `Alpine.data('naam', () => ({ open: false }))`.
  Function-call x-data (`x-data="myComponent()"`) werkt al.

### 3. Blocks inline styles
- **Check:** Geen `unsafe-inline` in `style-src`
- **Oorzaak:** Inline `style=""` attributen op HTML elementen
- **Fix:** Refactor `style=""` naar Tailwind CSS utility classes
- **Voorbeeld:**
  ```html
  <!-- VOOR: -->
  <div style="display: none;">
  <!-- NA: -->
  <div class="hidden">
  ```

### 4. Deny by default
- **Check:** `default-src 'none'`
- **Fix:** Eerste directive in CSP, daarna elk type expliciet

### 5. Restricts base tag
- **Check:** `base-uri 'self'` aanwezig
- **Fix:** Toevoegen aan CSP directives

### 6. Restricts form submissions
- **Check:** `form-action 'self'` aanwezig
- **Fix:** Toevoegen aan CSP directives

## SRI (Subresource Integrity) Test (-5 bij failure)

Elk extern `<script src="https://...">` MOET een `integrity` attribuut hebben.

```html
<script src="https://cdn.example.com/lib@1.2.3/lib.min.js"
        integrity="sha384-HASH"
        crossorigin="anonymous"
        @nonce></script>
```

**Hash genereren:**
```bash
curl -skL "https://cdn.example.com/lib@1.2.3/lib.min.js" | \
  openssl dgst -sha384 -binary | openssl base64 -A
```

**Google Analytics:** Self-host via `php artisan gtag:refresh` (zie Herdenkingsportaal).

## X-Content-Type-Options Test (-5 bij failure)

Header moet exact 1x voorkomen als `nosniff`.
Dubbel = "cannot be recognized" = FAIL.

**Check:** `curl -skI https://domain | grep -c "X-Content-Type"` moet `1` zijn.

## Permissions-Policy

Blokkeert browser-features per origin. Standaard alles dicht, **open alleen wat nodig is**.

```php
// Standaard (geen camera/mic/geo nodig):
$response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

// Met camera (bijv. QR scanner):
$response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(self)');
```

**Per project:**

| Project | camera | Reden |
|---------|--------|-------|
| HavunAdmin | `(self)` | QR scanner login op `/scan` |
| Overige | `()` | Niet nodig |

**Let op:** `camera=()` blokkeert `getUserMedia()` volledig — ook voor eigen domein.

## media-src in CSP

Als een project camera/video/audio gebruikt, moet `media-src` in de CSP staan:

```php
"media-src 'self' blob:",  // voor camera stream (getUserMedia)
```

Zonder `media-src` valt het terug op `default-src 'none'` → camera-stream geblokkeerd.

## Verplichte CSP template voor nieuwe projecten

```php
$csp = implode('; ', [
    "default-src 'none'",
    "script-src 'self' 'nonce-{$nonce}' https://specifieke-cdns...",
    "style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com",
    "img-src 'self' data: https: blob:",
    "font-src 'self' https://fonts.gstatic.com data:",
    "connect-src 'self' https://specifieke-apis...",
    "form-action 'self'",
    "frame-ancestors 'none'",
    "base-uri 'self'",
    "object-src 'none'",
    "manifest-src 'self'",
    "upgrade-insecure-requests",
]);
```

**Regels:**
- CDN domeinen altijd met `https://` prefix
- Geen `unsafe-inline` in script-src (gebruik nonces)
- Geen `unsafe-eval` tenzij Alpine.js via Vite (migreer naar @alpinejs/csp)
- `<style>` tags: `<style @nonce>`, `<script>` tags: `<script @nonce>`

---

*Bijgewerkt: 13 april 2026*
