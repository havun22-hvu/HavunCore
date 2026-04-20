---
title: JudoToernooi — kritieke paden (audit-bewijs)
type: reference
scope: judotoernooi
status: BINDING
last_reviewed: 2026-04-21
follows: "test-quality-policy.md"
---

# Kritieke paden — JudoToernooi

> Deze paden moeten **100 %** gedekt zijn met zinvolle tests én
> mutation-score ≥ 80. Audit-bewijs voor JudoToernooi.
> Bij elke PR die één van deze paden raakt: update dit document.

JudoToernooi is de tournament-management app. Bugs in auth/payment/
scoring raken organisatoren en atleten tijdens een live evenement — dat
is wanneer een regressie écht schade doet. Vandaar deze paden.

Repo-pad: `D:/GitHub/JudoToernooi/laravel` (geconfigureerd in
`havuncore:config/quality-safety.php`). Test-referenties in dit doc
zijn **relatief aan die root**.

## Pad 1 — Organisator-authenticatie + magic-link

**Waarom kritiek:** de enige manier om het toernooi te beheren. Een
leak of auth-bypass = tegenstander met full-control over scores,
publicatie en financien.

**Componenten:**

- `app/Http/Controllers/Auth/*` (Laravel Breeze stack, geguard door
  `auth('organisator')`)
- `app/Http/Middleware/CheckRolSessie.php`
- `app/Http/Middleware/CheckDeviceBinding.php`
- `app/Models/MagicLinkToken.php` + `app/Mail/MagicLinkMail.php`

**Branches / edge-cases:**

- [ ] Wachtwoord-login werkt + faalt op verkeerd wachtwoord.
- [ ] Rate-limit op `/organisator/login` (5/min).
- [ ] Magic-link tokens zijn single-use + expired tokens weigeren.
- [ ] Magic-link mail bevat geen plaintext token in log-output.
- [ ] `CheckRolSessie` weigert organisatoren zonder toegekende rol.
- [ ] `CheckDeviceBinding` weert unknown devices op admin-routes.

**Tests:**

- `tests/Feature/AuthenticationTest.php`
- `tests/Feature/OrganisatorAuthTest.php`
- `tests/Feature/OrganisatorAuthExtendedTest.php`
- `tests/Unit/Models/MagicLinkTokenTest.php`
- `tests/Unit/Mail/MagicLinkMailTest.php`
- `tests/Unit/Middleware/CheckRolSessieTest.php`
- `tests/Unit/Middleware/CheckDeviceBindingTest.php` (6 tests / 10
  assertions — missing toegang / unknown toegang / rol-mismatch /
  missing-cookie redirect / wrong-cookie redirect / valid-cookie
  forward)

**Mutation-score target:** 90 %.

## Pad 2 — Mollie payment webhooks

**Waarom kritiek:** de inschrijfstroom. Foutieve webhook-afhandeling =
geld geïnd zonder toegang, of toegang zonder geld.

**Componenten:**

- `app/Services/MollieService.php`
- `app/Services/Payments/MolliePaymentProvider.php`
- `app/Http/Controllers/MollieController.php`
- `app/Models/Concerns/HasMolliePayments.php`

**Branches / edge-cases:**

- [ ] Successful webhook → payment-status 'paid' + entitlement-flip.
- [ ] Failed webhook → status 'failed', geen entitlement.
- [ ] Refund webhook → status 'refunded', entitlement ingetrokken.
- [ ] Unknown webhook-id → 404, geen side-effect.
- [ ] Webhook met invalid signature → 403 (Mollie webhook-signing).
- [ ] Idempotency: dubbele webhook-call voor zelfde payment-id =
  1 entitlement-flip, niet 2.

**Tests:**

- `tests/Unit/Services/MollieServiceTest.php`
- `tests/Unit/Services/MollieServiceExtraTest.php`
- `tests/Feature/PaymentControllersCoverageTest.php`

**Mutation-score target:** 90 %.

## Pad 3 — Score-registratie + wedstrijd-integriteit

**Waarom kritiek:** het hele doel van de app. Een dubbele score of
race-condition = oneerlijke uitslag; een judoka verliest zijn plek.

**Componenten:**

- `app/Http/Controllers/ScoreRegistrationController.php` (of equivalent)
- `app/Models/Wedstrijd.php` — optimistic locking
- `app/Services/ScoreboardService.php`
- `app/Models/Concerns/HandlesWedstrijdConflict.php`

**Branches / edge-cases:**

- [ ] Score opgeslagen → event broadcast naar ScoreboardEvent.
- [ ] Dubbele score-submit (form-refresh) → 409 / replay-protection.
- [ ] Optimistic locking: 2 gelijktijdige updates → 1 wint, ander 409.
- [ ] 1s clock-drift tolerance werkt (HandlesWedstrijdConflict).
- [ ] Score-validatie weigert negatieve waarden / out-of-range.

**Tests:**

- `tests/Feature/ScoreRegistrationTest.php` (8 tests / 18 assertions —
  registreerUitslag sets winnaar / score-placement per winnende kant
  wit/blauw / isEchtGespeeld / isGelijk / getVerliezerId — alle
  model-niveau)
- `tests/Unit/Concerns/HandlesWedstrijdConflictTest.php`

**Mutation-score target:** 90 %.

De markTestIncomplete-placeholders uit de 20-04 reconstructie zijn
ontgrendeld op 2026-04-21 — rescoped naar model-niveau omdat JT geen
enkele `score.update` HTTP-endpoint heeft (score-registratie verloopt
via MatController etc. die alle `Wedstrijd::registreerUitslag()`
aanroepen). Het model-niveau test de atomic unit; HTTP-mocks zouden
alleen Laravel's routing testen.

## Pad 4 — Security headers + session-cookie

**Waarom kritiek:** Mozilla Observatory grade + XSS-verdediging +
cookie-afscherming. JT is de enige van de 5 webprojecten die nog
Alpine-CSP migratie loopt (VP-18); de middleware zelf draait wel.

**Componenten:**

- `app/Http/Middleware/SecurityHeaders.php`
- `config/session.php`

**Branches / edge-cases:**

- [x] Elke response krijgt CSP, HSTS, X-Frame-Options,
  X-Content-Type-Options, Referrer-Policy, Permissions-Policy.
- [x] CSP-nonce is per-request random.
- [x] HSTS alleen op `$request->secure()` + production.
- [x] `SESSION_SECURE_COOKIE` default `true` (na 20-04 commit
  6f8454d5).
- [ ] Alpine `'unsafe-eval'` verwijderen zodra VP-18 branch
  `feat/vp18-alpine-csp-migration` merged is.

**Tests:**

- `tests/Feature/SecurityHeadersTest.php`

**Mutation-score target:** 85 %.

## Pad 5 — Multi-tenant isolatie (organisator_id)

**Waarom kritiek:** elke organisator ziet alléén zijn eigen toernooi /
judokas / wedstrijden. Een lek daar = concurrerende bonden zien
deelnemers/scores van elkaar.

**Componenten:**

- `app/Http/Middleware/CheckToernooiRol.php`
- `app/Http/Middleware/CheckRolSessie.php`
- Query-scopes op relevante models (Wedstrijd, Poule, Judoka).

**Branches / edge-cases:**

- [ ] Route-access zonder juiste rol → 403 (niet 404 — 404 lekt
  bestaan).
- [ ] Query-scope: organisator A kan bij direct-ID-lookup geen
  organisator-B-record zien → 404 of auth fail.
- [ ] Superadmin-override pad: expliciet getest (niet alleen
  default-happy-path).

**Tests:**

- `tests/Unit/Middleware/CheckRolSessieTest.php`
- `tests/Unit/Models/OrganisatorTenantIsolationTest.php` (5 tests / 8
  assertions — cross-tenant denial, pivot-required, eigenaar vs
  beheerder roles, sitebeheerder-override, legacy organisator_id
  does not grant access)

**Gap (TODO):** query-scope tenant-isolatie op Wedstrijd/Poule/Judoka
(cross-request forging via direct-ID-lookup) heeft nog geen dedicated
test. Priority: middel — de gate zelf is nu gedekt op model-niveau;
wat rest is een Feature-level test die bewijst dat alle write-routes
die gate aanroepen. Kan in een aparte sessie.

## Audit-checklist (externe review)

1. Klopt het aantal paden? (5 actieve + TODO's).
2. Bevat elk pad componenten + branches + tests?
3. Zijn de tests actueel? → `critical-paths:verify --project=judotoernooi`.
4. Wordt test-erosion gemonitord? → ja, K&V-scanner.
5. Mutation testing? → open (baseline in HavunCore
   `mutation-baseline-2026-04-17.md` — JT nog niet uitgerold).

## Proces

- **Bij elke PR** die een kritiek pad raakt: update "branches" en "tests"
  in dit doc.
- **Maandelijks**: mutation-run op alle paden + update `last_reviewed`.
- **Bij nieuwe kritieke functionaliteit**: pad toevoegen vóór de
  eerste productie-deploy.
