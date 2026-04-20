---
title: Herdenkingsportaal — kritieke paden (audit-bewijs)
type: reference
scope: herdenkingsportaal
status: BINDING
last_reviewed: 2026-04-21
follows: "test-quality-policy.md"
---

# Kritieke paden — Herdenkingsportaal

> Deze paden moeten **100 %** gedekt zijn met zinvolle tests én
> mutation-score ≥ 80. Audit-bewijs voor Herdenkingsportaal.
> Bij elke PR die één van deze paden raakt: update dit document.

Herdenkingsportaal is het memorial-platform voor overleden personen. De
data is **extreem gevoelig** (families, overledenen, foto's, notities),
permanente blockchain-uploads kosten geld, betalingen gaan via meerdere
providers. Iedere regressie hier kan een familie raken op een moment
waarop ze dat niet kunnen velen. Daarom deze paden.

Repo-pad: `D:/GitHub/Herdenkingsportaal` (geconfigureerd in
`havuncore:config/quality-safety.php`). Test-referenties zijn **relatief
aan die root**.

## Pad 1 — User-authenticatie + 2FA

**Waarom kritiek:** directe toegang tot memorial-data. Admin-accounts
hebben 2FA verplicht (`EnforceTwoFactorForAdmins`); een regressie van
die enforcement is een serieus auth-gat.

**Componenten:**

- Laravel Breeze auth-stack (`app/Http/Controllers/Auth/*`)
- `app/Http/Middleware/AdminMiddleware.php`
- `app/Http/Middleware/EnforceTwoFactorForAdmins.php`
- `app/Http/Middleware/AccessLevelMiddleware.php`
- `app/Services/TwoFactorAuthService.php`
- `app/Models/AuthDevice.php` — device-binding

**Branches / edge-cases:**

- [ ] Login-flow werkt + wrong password rate-limited.
- [ ] Admin zonder 2FA → geforceerd naar 2FA-setup.
- [ ] Magic-link / password-reset tokens zijn single-use + expiry.
- [ ] Access-level scheiding (user/editor/admin) op alle beschermde
  routes.

**Tests:**

- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Feature/AccessLevelTest.php`
- `tests/Unit/TwoFactorAuthServiceTest.php`
- `tests/Unit/AuthDeviceTest.php`

**Mutation-score target:** 90 %.

## Pad 2 — Memorial lifecycle (create → publish → trash → restore)

**Waarom kritiek:** het centrale data-model. Een fout in lifecycle-
transities kan een gepubliceerde memorial per ongeluk verbergen of een
getrashed memorial per ongeluk publiceren.

**Componenten:**

- `app/Http/Controllers/MemorialController.php` (+ MemorialPublishController,
  MonumentController, TrashManagementController)
- `app/Models/Memorial.php`
- `app/Observers/MemorialObserver.php`

**Branches / edge-cases:**

- [ ] Create → default `status=draft`, niet publiek zichtbaar.
- [ ] Publish → `status=published`, publiek toegankelijk (tenzij
  private).
- [ ] Trash → `deleted_at` gezet, uit reguliere queries.
- [ ] Restore → terug naar laatste status (draft/published),
  `deleted_at` leeg.
- [ ] Force-delete → cascade naar photos/frames/guestbook/payments.
- [ ] Authoriation: alleen owner of admin mag status wijzigen.

**Tests:**

- `tests/Feature/MemorialControllerTest.php`
- `tests/Feature/MemorialLifecycleTest.php` (7 tests / 28 assertions —
  model-level transitions: preview default, upgrade-to-premium,
  publish-from-premium, publish-from-preview guarded, rollback-to-
  preview, rollback blocked on complete_custom, rollback blocked when
  arweave_transaction_id set)
- `tests/Feature/Memorial/PublishFlowTest.php` (4 tests / 9 assertions
  — HTTP-layer guards: owner-success, unpaid-error, non-owner-denial,
  basic-package-immediate-upload)

**Mutation-score target:** 90 %.

## Pad 3 — Payments (Mollie + Bunq + XRP)

**Waarom kritiek:** drie onafhankelijke payment-providers. Een failure
in één provider mag niet de andere twee raken; webhook-afhandeling mag
nooit een tweede keer crediteren.

**Componenten:**

- `app/Services/PaymentServiceFactory.php` (dispatcher)
- `app/Services/RealMollieService.php` + `MockMollieService.php`
- `app/Services/BunqApiService.php`
- `app/Services/XrpPaymentService.php` + `XrpPriceService.php`
- `app/Services/TikkiePaymentService.php`
- `app/Http/Controllers/PaymentController.php`
- `app/Http/Controllers/Api/AdminPaymentsController.php`
- `app/Models/PaymentTransaction.php`

**Branches / edge-cases:**

- [ ] Mollie: paid / failed / refunded / unknown-webhook-id.
- [ ] Bunq: SEPA-verification, API-unconfigured pad, dubbele webhook.
- [ ] XRP: price-feed + conversion, mismatch tolerance.
- [ ] Factory: unsupported provider → duidelijke exception.
- [ ] Webhook-idempotency: dubbel ontvangen event = 1 credit.
- [ ] Refund-flow: entitlement ingetrokken.

**Tests:**

- `tests/Feature/PaymentTest.php`
- `tests/Feature/PaymentFlowFunctionalTest.php`
- `tests/Feature/AdminPaymentsControllerTest.php`
- `tests/Unit/PaymentServiceFactoryTest.php`
- `tests/Unit/PaymentTransactionModelTest.php`
- `tests/Unit/RealMollieServiceTest.php`
- `tests/Unit/MockMollieServiceTest.php`
- `tests/Unit/BunqApiServiceTest.php`

**Mutation-score target:** 90 %.

## Pad 4 — Arweave permanent upload

**Waarom kritiek:** zodra data op Arweave staat is het **permanent** en
**onomkeerbaar**. Een bug die verkeerde data uploadt = eeuwig zichtbaar
en niet te verwijderen. Upload kost bovendien AR-token (geld).

**Componenten:**

- `app/Services/ArweaveService.php` + `ArweaveProductionService.php` +
  `ArweaveNodeBridgeService.php`
- `app/Services/ArweaveCrypto.php`
- `app/Services/ArweaveServiceFactory.php`
- `app/Jobs/UploadToArweaveJob.php` (of equivalent)

**Branches / edge-cases:**

- [ ] Factory: mode-switch (mock in local, productie in prod) → juiste
  service-class.
- [ ] Crypto: ondertekening werkt + faalt op corrupte key.
- [ ] Production-upload: commit-flag logica — geen upload zonder
  expliciete approval van eigenaar.
- [ ] Node-bridge: timeout / API-error → geen half-upload-state.
- [ ] Retry-logica bij transient failure: max N pogingen, geen
  duplicate-upload.

**Tests:**

- `tests/Unit/ArweaveServiceTest.php`
- `tests/Unit/ArweaveServiceRealModeTest.php`
- `tests/Unit/ArweaveProductionServiceTest.php`
- `tests/Unit/ArweaveNodeBridgeServiceTest.php`
- `tests/Unit/ArweaveCryptoTest.php`
- `tests/Unit/ArweaveCryptoSigningTest.php`
- `tests/Unit/ArweaveServiceFactoryTest.php`

**Mutation-score target:** 95 % — hoger dan elders, omdat fouten hier
permanent zijn.

## Pad 5 — AutoFix integratie (HavunCore proxy)

**Waarom kritiek:** HP stuurt uitzonderingen naar HavunCore's AutoFix-
pipeline. Een fout in dat contract = of crashes gaan verloren (onzichtbare
bugs), of elke mini-fout triggert code-wijzigingen (chaos).

**Componenten:**

- `app/Services/AutoFixService.php`
- Observability error-report hook (bootstrap/app.php reportable-section)

**Branches / edge-cases:**

- [ ] Non-project-file (vendor/) → NIET naar HavunCore gestuurd.
- [ ] excluded_message_patterns (EADDRINUSE etc) → NIET gestuurd.
- [ ] Rate-limit per fingerprint (60 min) werkt.
- [ ] Max 2 pogingen per unieke error.
- [ ] AutoFix-payload bevat memorial_id / user_id context.

**Tests:**

- `tests/Unit/AutoFixServiceTest.php` (TBC — als bestaand)

**Gap (TODO):** AutoFix-tests zijn verspreid over `AutoFixServiceCoverage2..4Test`
(padding-candidates). Na sanitization: één dedicated
`AutoFixServiceTest` met expliciete scenario's voor elke branch
hierboven.

**Mutation-score target:** 85 %.

## Pad 6 — Security headers + session cookies

**Waarom kritiek:** Mozilla Observatory compliance; session-cookie-
hardening zorgt dat memorial-cookies niet via JS / HTTP kunnen lekken.

**Componenten:**

- `app/Http/Middleware/SecurityHeaders.php`
- `config/session.php`
- `app/Http/Middleware/ProductionRedirect.php`

**Branches / edge-cases:**

- [ ] CSP, HSTS, X-Frame-Options, Referrer-Policy op alle responses.
- [ ] CSP-nonce per request uniek.
- [ ] `SESSION_SECURE_COOKIE=true` default (eerder in 20-04 sessie al
  cross-project gefixt).
- [ ] Production-redirect: HTTP → HTTPS.

**Tests:**

- `tests/Feature/Middleware/SecurityHeadersTest.php` (als bestaand)
- `tests/Feature/MiddlewareCoverage2Test.php` (middleware sanity)

**Gap (TODO):** geen dedicated `SecurityHeadersTest` op Feature-niveau
gevonden — coverage zit impliciet in algemene feature-tests. Volgende
sessie: dedicated test dat alle 5 headers valideert op één request.

**Mutation-score target:** 85 %.

## Audit-checklist (externe review)

1. Klopt het aantal paden? (6).
2. Bevat elk pad componenten + branches + tests?
3. Zijn de tests actueel? → `critical-paths:verify --project=herdenkingsportaal`.
4. Wordt test-erosion gemonitord? → ja, K&V-scanner.
5. Mutation testing? → open (baseline nog op te stellen voor HP).

## Proces

- **Bij elke PR** die een kritiek pad raakt: update "branches" en "tests".
- **Maandelijks**: mutation-run + update `last_reviewed`.
- **Coverage-padding sanitization**: volgens
  `runbooks/coverage-padding-sanitization.md`. Deze doc blijft de
  gouden standaard — na elke sanitization-ronde controleren dat de
  genoemde tests nog bestaan en groen zijn.
