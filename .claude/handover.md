# Handover

> Laatste sessie info voor volgende Claude.

## Sessie: 13-14 april 2026 — Mozilla Observatory CSP/SRI compliance

### Wat gedaan:
- Mozilla Observatory CSP/SRI compliance voor ALLE 5 webprojecten
- SRI hashes op alle externe CDN scripts (Alpine, Fabric, Chart.js, SortableJS, html5-qrcode, CropperJS, html2canvas, QRCode.js)
- Nonces op alle `<script>` en `<style>` tags (alle projecten)
- `default-src 'none'` + `object-src 'none'` + `base-uri 'self'` + `form-action 'self'` overal
- `unsafe-inline` uit style-src verwijderd: **579 inline styles** gerefactord naar Tailwind/CSS classes
- Self-hosted GA4 gtag.js met SRI + dagelijkse refresh (Herdenkingsportaal `php artisan gtag:refresh`)
- Broken qrcode CDN URLs gefixt (jsdelivr 404 → cdnjs qrcodejs) in HP, JT
- Nginx dubbele security headers opgeruimd (10 site configs)
- Preventieve maatregelen: `stubs/SecurityHeaders.php`, `new-laravel-site.md` stap 9, CLAUDE.md regels
- Uitgebreide docs: `security.md` + `security-headers-check.md` runbook
- Studieplanner 500 error gefixt (ObservabilityMiddleware namespace)

### Openstaande items — VOLGENDE SESSIE:

#### 1. Alpine.js `@alpinejs/csp` migratie (verwijdert unsafe-eval)
- HP: 156 expressies, 21 inline x-data, 10 function-based
- HA: 268 expressies, 30 inline x-data, 17 function-based
- JT: 784 expressies, 66 inline x-data, 34 function-based
- Fix: `npm install @alpinejs/csp` + import wijzigen + inline x-data → `Alpine.data()`
- Function-call x-data werkt al — alleen inline objecten en expressies omzetten
- Docs: `security-headers-check.md` sectie "Blocks eval()"

#### 2. Tailwind CDN in productie (Infosyst, SafeHavun)
- Moet gebundeld via Vite i.p.v. CDN (performance + security)

#### 3. Overige items (uit vorige sessies)
- Webapp login page GOED doen (via /mpc) — docs staan klaar
- Coverage 85.9% → 90% (Herdenkingsportaal)
- HavunAdmin Observability UI (chaos resultaten)
- doc-intelligence tests in CI (306 tests lokaal-only)

### KRITIEKE WERKWIJZE
- **ALTIJD /mpc:** MD docs → Plan → Code
- **NOOIT code op production testen**
- **NOOIT deployen zonder lokaal testen**
- **NOOIT code schrijven zonder tests**
