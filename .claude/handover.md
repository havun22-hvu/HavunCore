# Handover

> Laatste sessie info voor volgende Claude.

## Sessie: 19 april 2026 — K&V-systeem (Kwaliteit & Veiligheid)

### Wat gedaan:
- K&V-systeem opgezet als centraal kwaliteits- en veiligheidsraamwerk voor alle projecten
- Runbook: `docs/kb/runbooks/kwaliteit-veiligheid-systeem.md` (normen → findings-log → scanner → scheduler)
- Config: `config/quality-safety.php` — 7 projecten met `enabled`/`path`/`url`/`has_composer`/`has_npm` flags + SSL-thresholds (30d warn / 7d crit) + bin-paden
- Service: `app/Services/QualitySafety/QualitySafetyScanner.php` — 3 checks: `composer audit`, `npm audit --omit=dev`, SSL-expiry via stream_socket_client
- Command: `app/Console/Commands/QualitySafetyScanCommand.php` — flags `--only`, `--project`, `--json`. Persisteert JSON-log in `storage/app/qv-scans/{date}/run-{time}.json`. Exit: 0 clean / 1 HIGH+CRIT / 2 scanner-error
- Scheduler hooks (routes/console.php) met off-minuten:
  - dagelijks 03:07 — composer audit (alle projecten)
  - dagelijks 03:17 — npm audit (alle projecten met package.json)
  - maandag 04:07 — SSL expiry (alle projecten)
- Tests: 16 passing (unit + feature) — scanner parst composer/npm JSON, severity normalization (moderate→medium), missing-path error, disabled-project filter, JSON flag, exit-codes

### Openstaande K&V-items (voor volgende sessie):
- Mozilla Observatory check-integratie (HTTP API, wekelijks)
- Server health (disk/systemd) — SSH-based, later
- `qv:log` sub-command dat HIGH/CRIT findings auto-appendt aan `security-findings.md`
- Notifications: in-app (Observability event?) — NOOIT e-mail

## Sessie: 14-18 april 2026 — Webapp fixes + Munus setup

### Wat gedaan:
- Studieplanner 500 error gefixt (ObservabilityMiddleware merge conflict + namespace + deploy key)
- HavunCore webapp: reverb detectie via supervisor (niet alleen systemd)
- HavunCore webapp: project paths gefixt (/var/www/development bestond niet → gebruikt nu remotePath)
- JudoToernooi: unsafe-eval verwijderd (Alpine.js gemigreerd naar `@alpinejs/csp`)
- Munus nieuw project opgezet met volledige HavunCore structuur (CLAUDE.md, commands, docs)

### Openstaande items — VOLGENDE SESSIE:

#### 1. HavunAdmin: Alpine.js `@alpinejs/csp` migratie
- 268 expressies, 30 inline x-data, 17 function-based
- Laatste project met unsafe-eval

#### 2. Tailwind CDN in productie (Infosyst, SafeHavun)
- Moet gebundeld via Vite i.p.v. CDN

#### 3. Munus Fase 1 — MVP development
- Docs/structuur klaar, code nog niet gestart
- Eerst: Laravel module skeleton in HavunCore monorepo

#### 4. Scheduled Agents opzetten
- Mozilla Observatory auto-check, security audits, SSL, server health

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
