# Handover voor na herstart — 11 april 2026

## ✅ Klaar (gecommit en gepusht)

### JudoToernooi
1. **PIN systeem VOLLEDIG verwijderd** (commit 71e3046a)
   - DeviceToegangController PIN verify weg
   - Nieuwe flow: first-device-wins auto-binding via 12-char role code
   - Tweede apparaat krijgt foutmelding, organisator moet resetten in beheer UI
   - `throttle:30,1` op toegang.show route
   - `pincode` kolom is nullable (niet gedropt voor prod data compat)
   - **BELANGRIJK**: `Club::generatePincodeForToernooi`/`checkPincodeForToernooi` blijven staan — dit is een APART systeem (5-cijferige coach portal PIN), gebruikt door `CoachPortalController::login()`. Niet verwarren met de nu-verwijderde DeviceToegang PIN.
   - CoachKaart PIN (4-cijferig voor coach kaart activatie): ook apart systeem, niet aangeraakt
   - Test suite: 2229 passed, 4 skipped, 1 pre-existing fail (mollie webhook handles mollie exception — bestond al op master)
   - Commits: 71e3046a, 95ef43e9

2. **Webhook security + idempotency** (commits 2a7b6aae, c3a6f43e, 9464d9d3)
   - Mollie + Stripe signature verificatie toegevoegd
   - Idempotency via `payment_processed_at` check + `isFinalStatus()` helpers
   - DB transactions om multi-step operaties
   - 61/61 webhook tests slagen

### Herdenkingsportaal
3. **Payment idempotency + transaction wrapping** (commits 9bb7d2e, a205ecc, c81cfbc)
   - `processSuccessfulPayment` idempotent via `payment_processed_at` + `isFullyProcessed()`
   - `lockForUpdate()->find()` voor race-safety
   - Wrapped in `DB::transaction()` — alle side effects of niks
   - Silent catches vervangen voor kritieke ops (upgradeToPremium, publish, invoice)
   - Tests in PaymentFlowFunctionalTest.php uitgebreid (test_webhook_idempotency, test_double_webhook, test_transaction_stays_pending_on_mid_flow_failure)
   - Nieuwe column: `payment_processed_at` op payment_transactions

4. **unserialize() RCE fix + .env whitelist** (commits 9b855ba, 68fe674)
   - `DatabaseChallengeRepository` gebruikt nu HMAC + `allowed_classes` unserialize restrictie
   - `AdminController::updateEnvValue` heeft whitelist van toegestane keys
   - Character validation op values (geen `\r\n"'\\\\$\``)
   - Backup vóór write naar `storage/app/env-backups/`
   - Proper escaping voor values met spaties

## 🟡 Nog niet gedaan (potentiële volgende stappen)

### Herdenkingsportaal
- **CSP `unsafe-eval` evalueren** — SecurityHeaders.php:63-64
  - Nodig voor Fabric.js, maar wel risico
  - Optie: alleen unsafe-eval op /memorial/*/edit routes, strict op rest
- **Memorial photo storage memory leak** — MemorialController.php:1520-1534
  - Loopt door alle foto's, roept `filesize()` op elk per request
  - Fix: cachen of totaal-kolom op Memorial model
- **Race condition monument generatie** — MemorialController.php:2548-2582
  - Twee edits kunnen elkaars monument overschrijven
  - Fix: lock op memorial tijdens generatie
- **MemorialController splitsen** (4602 regels → 5-6 controllers)

### JudoToernooi
- **Sync conflict resolution upgraden** — SyncApiController.php:108-120
  - Nu last-write-wins, kan scores verliezen
  - Nodig: 3-way merge of operational transforms
- **AutoFix git operations sandbox** — app/Services/AutoFixService.php
  - Kan nog steeds echte branches maken
  - Fix: dry-run mode voor test omgeving
- **N+1 queries in PubliekController** — line 130-135
- **Scoreboard caching** — WedstrijddagController
- **Environment file rewrite** — LocalSyncController.php:203-219

### Beide
- **Fat services refactoren** (EliminatieService 1652 lines, etc.)
- **40+ generic `catch (\Exception)`** vervangen door specifieke types

## Coverage status (laatst gemeten)

| Project | Coverage |
|---------|----------|
| HavunCore | 98.4% |
| SafeHavun | 95.9% |
| Studieplanner API | 94.1% |
| Infosyst | 92.0% |
| HavunVet | 90.9% |
| HavunAdmin | 90.2% |
| JudoToernooi | 89.6% (was 80% start sessie) |
| Herdenkingsportaal | 83.56% (was 53.5% start sessie) |

## Bugs gevonden (niet gefixt, op to-do lijst)
- PaymentTransaction $fillable mist crypto velden (Herdenkingsportaal)
- UserSubscription model ontbreekt (Herdenkingsportaal)
- package_type enum vs string migratie mismatch (Herdenkingsportaal)
- TaxExportService Collection.merge() bug (HavunAdmin)
- ChatContentFilter all-caps dead code (Herdenkingsportaal)

## Context voor vervolgsessie

Alles is gepusht naar remote. Je kunt safe herstarten. Na herstart:
- Git status moet clean zijn in JudoToernooi en Herdenkingsportaal
- Alle tests draaien groen (behalve 1 pre-existing JudoToernooi mollie webhook test)
- Handover in HavunCore/.claude/handover.md heeft context over hele sessie
