# Handover

> Laatste sessie info voor volgende Claude.

## Sessie: 13 april 2026 — Coverage push, CI fixes, SRI, Chaos Engineering, Mozilla Observatory

### Resultaten deze sessie:

**HavunCore CI — OPGELOST na 10+ pogingen:**
- phpseclib 3.0.50→3.0.51 (CVE-2026-40194) ✅
- DocCommandsTest: mock DocIndexer om Ollama calls te voorkomen ✅
- doc_intelligence migraties → CreatesDocIntelligenceTables trait + temp file ✅
- PHPUnit 11 auto-coverage: `pcov.enabled=0 + --no-coverage` ✅
- doc-intelligence tests excluded in CI via `#[Group]` attribute (306 tests lokaal-only) ✅
- **CI groen: 472 tests in 34 seconden** ✅

**Herdenkingsportaal coverage push (83.4% → 85.9%):**
- 891 nieuwe tests in 7 batches
- CI groen, 6464+ tests, 85.9% coverage
- Flaky CoverageBoostFinal2Test gefixt
- Coverage artifacts uit git + .gitignore

**Mozilla Observatory / SRI:**
- SRI integrity hashes op alle externe scripts (AlpineJS 3.14.9, QRCode, qrcodejs, qr-scanner, fabric.js)
- AlpineJS gepin @3.x.x → @3.14.9, QRCode gepin naar @1.5.3
- 4/5 projecten clean, JudoToernooi heeft unsafe-eval (Alpine CSP build migratie nodig)

**Chaos Engineering — 13 experimenten (was 5):**
- 9 monitoring checks: health-deep, endpoint-probe, error-flood, db-slow, api-timeout, disk-pressure, payment-provider, dns-resolution, backup-integrity
- 4 actieve chaos: db-disconnect, latency-injection, cache-corruption, memory-pressure
- API endpoints: GET /api/observability/chaos + POST /api/observability/chaos/run
- `php artisan chaos:run all` of individueel

### Fouten en lessen (kwaliteitslog):

| Fout | Root cause | Tijd | Les |
|------|-----------|------|-----|
| 10x CI timeout 30 min | PHPUnit 11 auto-coverage (PCOV + `<source>`) | ~3 uur | pcov.enabled=0 + --no-coverage |
| Rode haring: doc_intelligence migraties | Was traag maar niet de hoofdoorzaak | ~1 uur | Trait-aanpak is alsnog goed |
| doc-intelligence :memory: SQLite hang | Specifiek CI probleem, temp file lost het op | ~30 min | Temp file per PID i.p.v. :memory: |
| Coverage artifacts in git | `git add -A` zonder .gitignore | 15 min | Altijd .gitignore checken |
| Flaky upload_template test | Synthetische JPEG omgevingsafhankelijk | 30 min | Geen strikte asserties met fake files |

### Openstaande items — VOLGENDE SESSIE:

#### 1. HavunAdmin Observability UI
- Chaos resultaten toevoegen aan observability pagina
- "Project Status" sectie fixen (data ophalen werkt niet, ververs knop doet niets)
- HavunCore API is klaar, HavunAdmin view moet nog

#### 2. Coverage 85.9% → 90% (Herdenkingsportaal)
- 691 statements nodig — zit in exception handlers/catch blocks
- Opties: unreachable code excluden, integration tests, of accepteer 86%

#### 3. doc-intelligence tests in CI
- 306 tests draaien alleen lokaal (excluded in CI via Group attribute)
- Root cause: SQLite :memory: + DB::purge hangt in GitHub Actions
- Toekomstige fix: aparte CI job met file-based SQLite

#### 4. JudoToernooi Alpine CSP build migratie
- unsafe-eval nodig door Alpine.js standaard build
- Migratie naar @alpinejs/csp = apart project

#### 5. Overig
- [ ] firebase/php-jwt v6→v7 (blocked door laravel/socialite ^6.4)
- [ ] Arweave testnet werkt niet (geen test tokens beschikbaar)

### VP-02 deadline: 31 mei 2026 — Coverage 85.9%, doel 90%
