---
title: HavunAdmin — kritieke paden (audit-bewijs)
type: reference
scope: havunadmin
status: BINDING
last_reviewed: 2026-04-21
follows: "test-quality-policy.md"
---

# Kritieke paden — HavunAdmin

> Deze paden moeten **100 %** gedekt zijn met zinvolle tests én
> mutation-score ≥ 80. Audit-bewijs voor HavunAdmin.
> Bij elke PR die één van deze paden raakt: update dit document.

HavunAdmin is de centrale admin-app waarmee Henk tijd/facturatie/projecten
beheert voor alle Havun-klanten. Financiën (facturen, betalingen) + multi-
tenant structuur = data-gevoelig + geld-gevoelig. Daarom deze paden.

Repo-pad: `D:/GitHub/HavunAdmin` (geconfigureerd in
`havuncore:config/quality-safety.php`). Test-referenties zijn **relatief
aan die root**.

## Pad 1 — Authenticatie + rol-gebaseerde toegang

**Waarom kritiek:** HavunAdmin bevat alle klantgegevens. Auth-bypass of
rol-escalatie = volledige klantdata-leak + vals ingevoerde factuur-
gegevens.

**Componenten:**

- `app/Http/Controllers/Auth/*` (Laravel Breeze)
- `app/Http/Middleware/EnsureUserIsAdmin.php`
- `app/Http/Middleware/EnsureSuperAdmin.php`
- `app/Http/Middleware/EnsureUserCanEdit.php`
- `app/Http/Middleware/EnsureUserCanExport.php`

**Branches / edge-cases:**

- [ ] Login-flow werkt + wrong password rate-limited.
- [ ] Non-admin → 403 op admin-routes (niet 302, niet 500).
- [ ] Non-superadmin → 403 op superadmin-routes.
- [ ] Edit/Export permissions afzonderlijk afgedwongen — een user met
  edit maar zonder export kan niet exporteren.
- [ ] API-token gebruik: expired/revoked tokens → 401.

**Tests:**

- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Unit/MiddlewareTest.php`
- `tests/Unit/PolicyMiddlewareTest.php`
- `tests/Unit/OAuthTokenModelTest.php`

**Mutation-score target:** 90 %.

## Pad 2 — Time-entry API (cross-project registratie)

**Waarom kritiek:** elke Havun-werkdag wordt hier vastgelegd. Een
silent-fail = dagen aan uren niet geregistreerd = facturatie-problemen.
De API is bovendien het integratiepunt met andere Havun-projecten
(`POST /api/time-entries`).

**Componenten:**

- `app/Http/Controllers/TimeEntry*Controller.php`
- `app/Models/TimeEntry.php`
- Input-validation via Form Requests (naam + datum + uren)

**Branches / edge-cases:**

- [ ] Valide entry → opgeslagen + relatie naar project.
- [ ] Ongeldige project-slug → 422 met heldere fout.
- [ ] Negatieve uren → 422.
- [ ] Overlap-detectie: dubbele entries op zelfde dag + project = 1
  entry (of expliciete error).
- [ ] Rate-limit op API-endpoint.

**Tests:**

- `tests/Feature/TimeEntryApiTest.php`
- `tests/Feature/TimeEntryTest.php`

**Mutation-score target:** 85 %.

## Pad 3 — Invoice processing (lokale facturen + templates)

**Waarom kritiek:** facturatie. Verkeerde factuur = verkeerd geld
geïncasseerd. LocalInvoiceController heeft net de FormRequest-
hardening gehad (20-04), dus elke wijziging hier moet getest.

**Componenten:**

- `app/Http/Controllers/InvoiceController.php`
- `app/Http/Controllers/InvoiceTemplateController.php`
- `app/Http/Controllers/LocalInvoiceController.php`
- `app/Http/Controllers/InvoiceArchiveController.php`
- `app/Models/Invoice.php` + `InvoiceItem.php` + `InvoiceFile.php` +
  `InvoiceTemplate.php` + `LocalInvoice.php`
- Form Requests: `AvailableTransactionsRequest`, `LocalInvoiceUploadRequest`,
  etc.

**Branches / edge-cases:**

- [ ] Invoice aanmaken + items + PDF-export.
- [ ] Template-rendering via Blade → geen XSS via tenant-input.
- [ ] Local-invoice PDF-upload → hash-deduplicatie (geen dubbele upload).
- [ ] FormRequest validation: `availableTransactions` weigert buiten
  schema (search/amount alleen, geen extra velden).
- [ ] Authorisatie: editor kan eigen facturen zien, niet die van andere
  tenants.

**Tests:**

- `tests/Feature/InvoiceControllerTest.php`
- `tests/Feature/ControllerCoverage2Test.php` (3 tests specifiek voor
  `availableTransactions` route — happy + search + amount-filter)
- `tests/Unit/InvoiceModelTest.php`
- `tests/Unit/InvoiceItemModelTest.php`
- `tests/Unit/InvoiceFileModelTest.php`
- `tests/Unit/InvoiceTemplateModelTest.php`
- `tests/Unit/LocalInvoiceModelTest.php`

**Mutation-score target:** 90 %.

## Pad 4 — Mollie webhook + transaction matching

**Waarom kritiek:** binnenkomende betalingen. Een foutieve match =
factuur staat ten onrechte open of dubbel afgeboekt. MollieWebhookController
accepteert externe input; idempotency is essentieel.

**Componenten:**

- `app/Http/Controllers/MollieWebhookController.php`
- `app/Services/TransactionMatchingService.php`
- `app/Models/Transaction.php`

**Branches / edge-cases:**

- [ ] Paid webhook → transaction.status='paid', linked factuur.
- [ ] Failed webhook → status='failed', factuur blijft open.
- [ ] Unknown payment-id → 404.
- [ ] Invalid signature → 403 (Mollie signing).
- [ ] Idempotency: duplicate event-id = 1 DB-write.
- [ ] Transaction matching: amount + reference exact matched to 1 invoice.
- [ ] Geen match → transaction status='unmatched' (niet 'failed').

**Tests:**

- `tests/Unit/TransactionMatchingServiceTest.php`
- `tests/Unit/TransactionModelTest.php`

**Gap (TODO):** geen dedicated `MollieWebhookControllerTest` gevonden op
Feature-niveau. Webhooks zijn externe attack-surface — Priority: hoog.
Volgende sessie: bouwen met `postJson(route('mollie.webhook'))` + mock
van signature-check.

**Mutation-score target:** 90 %.

## Pad 5 — Multi-tenant isolatie (TenantMiddleware)

**Waarom kritiek:** HavunAdmin beheert data van meerdere klanten (Havun,
Infosyst, etc.). Tenant-leak = factuur-data van klant A lezen als klant B.

**Componenten:**

- `app/Http/Middleware/TenantMiddleware.php`
- `app/Services/TenantService.php`
- Global scopes / query-filters op tenant-aware models.

**Branches / edge-cases:**

- [ ] Request zonder tenant-header → 400 (niet default-tenant assumptie).
- [ ] Ongeldige tenant → 403.
- [ ] Model-query zonder scope → exception of lege result (nooit
  cross-tenant records).
- [ ] `withoutTenantMiddleware()` in tests werkt correct — alleen in
  testing-context toegestaan.

**Tests:**

- `tests/Unit/TenantServiceTest.php`
- `tests/Unit/Middleware/TenantMiddlewareTest.php` (3 tests / 6
  assertions — skip-paths: anoniem request, central-DB unavailable,
  cache-reset)

**Gap (TODO):** Feature-level `TenantIsolationTest` die cross-tenant
requests forged en 403 asserteert blijft open — vereist full
factory chain + tenant-DB setup. Priority: hoog. De skip-paths zijn
nu gedekt; wat rest is de echte multi-tenant enforcement.

**Mutation-score target:** 90 %.

## Pad 6 — Security headers + session cookies

**Waarom kritiek:** Mozilla Observatory compliance + cookie-hardening.

**Componenten:**

- `app/Http/Middleware/SecurityHeaders.php`
- `config/session.php`

**Branches / edge-cases:**

- [ ] Alle security-headers op elke response (CSP, HSTS, X-Frame,
  X-Content-Type, Referrer-Policy, Permissions-Policy).
- [ ] CSP-nonce per request uniek.
- [ ] Session-cookie defaults: `secure`/`http_only`/`same_site`.

**Tests:**

- `tests/Feature/Middleware/SecurityHeadersTest.php` (7 tests / 12 assertions —
  X-Content-Type, X-Frame=SAMEORIGIN, X-XSS, Referrer-Policy,
  Permissions-Policy, CSP default-deny, nonce-per-request-uniekheid)

**Mutation-score target:** 85 %.

## Audit-checklist (externe review)

1. Klopt het aantal paden? (6).
2. Bevat elk pad componenten + branches + tests?
3. Zijn de tests actueel? → `critical-paths:verify --project=havunadmin`.
4. Wordt test-erosion gemonitord? → ja, K&V-scanner.
5. Mutation testing? → open (baseline nog op te stellen voor HA).

## Proces

- **Bij elke PR** die een kritiek pad raakt: update "branches" en "tests".
- **Maandelijks**: mutation-run + update `last_reviewed`.
- **Coverage-padding sanitization** (`runbooks/coverage-padding-sanitization.md`):
  HavunAdmin heeft (net als HP) een groeiend padding-probleem — tijdens
  sanitization-rondes deze doc syncroon houden met de saneer-uitkomst.
