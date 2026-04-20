---
title: HavunCore — kritieke paden (audit-bewijs)
type: reference
scope: havuncore
status: BINDING
last_reviewed: 2026-04-20
follows: "test-quality-policy.md"
---

# Kritieke paden — HavunCore

> Deze paden moeten **100 %** gedekt zijn met zinvolle tests én
> mutation-score ≥ 80. Dit is het audit-bewijs voor HavunCore.
> Bij elke PR die één van deze paden raakt: update dit document.

HavunCore is de centrale hub (Vault, AI Proxy, AutoFix, Observability).
Een bug hier raakt **alle** Havun-projecten. Daarom gelden deze paden
als kritiek.

## Pad 1 — Vault (credentials-brokerage)

**Waarom kritiek:** brokert API keys / DB passwords voor 7 projecten.
Een bug = lek of uitval. Auth-bypass hier is een incident.

**Componenten:**

- `app/Http/Controllers/VaultController.php`
- `app/Http/Middleware/EnsureAdminToken.php` (admin-endpoint guard)
- `app/Services/Vault/*` (encryptie, audit-log)
- `app/Models/VaultSecret.php`, `VaultAccessLog.php`, `VaultProject.php`

**Branches / edge-cases die expliciet getest moeten zijn:**

- [ ] Non-admin token → 401 op elke admin-route.
- [ ] Geldige admin token → toegang, maar correct gelogd in `VaultAccessLog`.
- [ ] Ongeldige/expired token → 401 + geen log-lekken.
- [ ] Secret decrypt-failure (corrupte ciphertext) → 500 zonder cleartext in response.
- [ ] `project` filter voorkomt cross-tenant access.
- [ ] Rate-limiter op auth-endpoints (60/min).

**Tests die dit afdekken:**

- `tests/Feature/VaultTest.php`
- `tests/Feature/VaultControllerExtendedTest.php` (admin-token gating + edge-cases)
- `tests/Unit/VaultSecretTest.php`
- `tests/Unit/VaultProjectTest.php`
- `tests/Unit/VaultAccessLogTest.php`
- `tests/Unit/VaultConfigTest.php`

**Gap (TODO):** dedicated `EnsureAdminTokenTest` als aparte middleware-unit
ontbreekt. Dekking zit nu impliciet in `VaultControllerExtendedTest`.

**Mutation-score target:** 90 %.
**Laatst geverifieerd:** _te meten — eerstvolgende mutation run._

## Pad 2 — AI Proxy (`POST /api/ai/chat`)

**Waarom kritiek:** verwerkt API key (CLAUDE_API_KEY), doet tenant-
isolatie, kost geld per call, mag niet lekken.

**Componenten:**

- `app/Http/Controllers/AiChatController.php` (of equivalent)
- `app/Services/AIProxyService.php`
- `app/Services/CircuitBreaker.php` (bescherming tegen runaway-calls)

**Branches / edge-cases:**

- [ ] Onbekende tenant → 403 (niet 500).
- [ ] Geldige tenant, ontbrekende prompt → 422 met duidelijke fout.
- [ ] Geldige call → response + token-usage gelogd.
- [ ] Anthropic API down / timeout → circuit-breaker open, geen crash.
- [ ] Rate-limit overschrijding per tenant → 429.
- [ ] Key rotation: oude key werkt niet meer, nieuwe key wel.

**Tests:**

- `tests/Feature/AIProxyControllerTest.php`
- `tests/Unit/Services/AIProxyServiceTest.php`
- `tests/Unit/Services/CircuitBreakerTest.php`

**Mutation-score target:** 90 %.

## Pad 3 — AutoFix pipeline

**Waarom kritiek:** schrijft **automatisch** code naar remote projecten.
Verkeerde check = verkeerde fix in production.

**Componenten:**

- `app/Http/Controllers/AutoFixController.php`
- `app/Services/AutoFixService.php`
- `app/Jobs/Autofix*`
- Rate-limit (60 min per unieke error, max 2 pogingen)
- `excluded_message_patterns` filter

**Branches / edge-cases:**

- [ ] Server/infra errors (EADDRINUSE, ECONNREFUSED, disk full) → **genegeerd**.
- [ ] Niet-project-file (`vendor/`, `node_modules/`) → genegeerd door
  `isProjectFile()` in **zowel** Controller als Service.
- [ ] Max 2 pogingen per unique error (fingerprint) — 3e = geweigerd.
- [ ] Rate-limit 60 min — 2e call binnen 60 min = geweigerd.
- [ ] Email-notification bij zowel success als failure (config-flag).
- [ ] Project-context (organisator / user / memorial) wordt correct meegegeven.

**Tests:**

- `tests/Feature/AutoFixApiTest.php`
- `tests/Feature/AutoFixDeliveryModeTest.php`

**Gap (TODO):** dedicated unit-level `AutoFixServiceTest` ontbreekt —
service wordt nu alleen via API-feature tests gehit. Risico: edge-cases
in `isProjectFile()` / rate-limit logica moeilijker te isoleren.

**Mutation-score target:** 85 %.

## Pad 4 — QR Auth (device-binding)

**Waarom kritiek:** mobile-device binding; spoof = account-takeover.

**Componenten:**

- `app/Http/Controllers/QrAuthController.php`
- `app/Services/DeviceTrustService.php`
- `app/Models/AuthDevice.php`

**Branches / edge-cases:**

- [ ] Nieuwe device → binding + QR-code + expiry.
- [ ] Bekende device → silent-approve.
- [ ] Expired QR → 410 Gone.
- [ ] Rate-limit (5/min per IP).
- [ ] Invalid signature → 403 + audit log.

**Tests:**

- `tests/Feature/QrAuthControllerTest.php`
- `tests/Unit/Services/DeviceTrustServiceTest.php`

**Mutation-score target:** 90 %.

## Pad 5 — Observability-events + K&V findings

**Waarom kritiek:** dit is de **monitoring-laag zelf**. Als het stil blijft
terwijl er iets stuk is, weten we het niet.

**Componenten:**

- `app/Http/Controllers/Api/ObservabilityController.php`
- `app/Services/ObservabilityService.php` (incl. `getQualityFindings()`)
- `app/Services/QualitySafety/QualitySafetyScanner.php`
- `app/Services/QualitySafety/LatestRunFinder.php`

**Branches / edge-cases:**

- [ ] Dashboard-API zonder auth → 401.
- [ ] Met auth → error-counts, request-counts, slow-queries, **quality_findings**.
- [ ] Geen qv:scan runs → `quality_findings = null`, geen crash.
- [ ] Corrupte scan-JSON → null, geen crash.
- [ ] `latest_scan_at` reflecteert echte mtime (staleness-signaal).
- [ ] `qv:scan` + scheduler blijft draaien; findings raken dashboard binnen 60 s
  (cache-window).

**Tests:**

- `tests/Unit/Services/ObservabilityServiceTest.php`
- `tests/Unit/QualitySafety/QualitySafetyScannerTest.php`
- `tests/Unit/Services/QualitySafety/LatestRunFinderTest.php`

**Mutation-score target:** 85 %.

## Pad 6 — Security headers & CSP (alle responses)

**Waarom kritiek:** Mozilla Observatory, XSS-verdediging, cookie-afscherming.
Één middleware-regressie en alle pagina's lekken headers.

**Componenten:**

- `app/Http/Middleware/SecurityHeaders.php`
- `config/session.php` (`SESSION_SECURE_COOKIE` default `true`)

**Branches:**

- [ ] Elke response krijgt CSP, HSTS, X-Frame-Options, X-Content-Type-Options,
  Referrer-Policy.
- [ ] CSP nonce is per-request random, niet hergebruikt.
- [ ] Session-cookie: `secure`, `http_only`, `same_site=lax`.
- [ ] HSTS alleen op HTTPS-requests (niet op HTTP).
- [ ] `app()->environment('local')` geeft ontwikkel-CSP (localhost toegestaan),
  production géén localhost-origins.

**Tests:**

**Gap (TODO — must-build):** er bestaat geen dedicated test voor
`SecurityHeaders` middleware in HavunCore. Security headers zijn
kritiek (Mozilla Observatory grade, CSP, HSTS) — zonder eigen test is
een regressie pas zichtbaar bij de externe scan. Prioriteit: hoog.
Zie `stubs/SecurityHeaders.php` voor het contract dat de tests
moeten afdwingen.

**Mutation-score target:** 85 % (zodra test bestaat).

## Pad 7 — Audit infrastructure (`critical-paths:verify`)

**Waarom kritiek:** deze command bewaakt of de andere 6 paden actueel
blijven. Als hij roest, worden doc-rot en ongemerkt-verwijderde tests
onzichtbaar.

**Componenten:**

- `app/Console/Commands/CriticalPathsVerifyCommand.php`
- `app/Services/CriticalPaths/DocParser.php`
- `app/Services/CriticalPaths/ReferenceChecker.php`
- `app/Services/CriticalPaths/TestRunner.php`

**Branches / edge-cases:**

- [x] Ontbrekend bestand → exit 1, gerapporteerd.
- [x] Glob zonder match → exit 1.
- [x] Ontbrekende doc → exit 2.
- [x] `--project` en `--all` conflict → exit 2.
- [x] `--json` output is valide + bevat totals.
- [x] `--run` triggert TestRunner (via Artisan::call).
- [x] `--run` met falende test → exit 1.
- [x] `--all` ontdekt alle `critical-paths-*.md` via glob.

**Tests:**

- `tests/Unit/CriticalPaths/DocParserTest.php`
- `tests/Unit/CriticalPaths/ReferenceCheckerTest.php`
- `tests/Unit/CriticalPaths/TestRunnerTest.php`
- `tests/Feature/Commands/CriticalPathsVerifyCommandTest.php`

**Mutation-score target:** 85 %.

## Audit-checklist (externe review)

Gebruik deze bij een audit-review:

1. Klopt het aantal paden? (6) → dit doc.
2. Bevat elk pad: componenten + branches + tests + mutation-target? → ja.
3. Zijn de genoemde tests actueel? (bestanden bestaan, methods bestaan) →
   controleer elke PR die het pad raakt.
4. Wordt test-erosion gemonitord? → ja, `qv:scan --only=test-erosion`.
5. Wordt mutation testing uitgevoerd? → baseline
   `mutation-baseline-2026-04-17.md`; periodieke runs op kritieke paden.

## Proces — hoe houden we dit levend

- **Bij elke PR** die een kritiek pad raakt: update "branches / edge-cases"
  en "tests"-sectie in dit doc.
- **Maandelijks**: één sessie aan mutation-run op alle kritieke paden + update
  `last_reviewed`.
- **Bij nieuwe kritieke functionaliteit**: pad toevoegen aan dit doc **vóór**
  de eerste productie-deploy.
