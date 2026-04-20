---
title: Infosyst — kritieke paden (audit-bewijs)
type: reference
scope: infosyst
status: BINDING
last_reviewed: 2026-04-21
follows: "test-quality-policy.md"
---

# Kritieke paden — Infosyst

> Deze paden moeten **100 %** gedekt zijn met zinvolle tests én
> mutation-score ≥ 80. Audit-bewijs voor Infosyst.
> Bij elke PR die één van deze paden raakt: update dit document.

Infosyst is een medisch informatie-systeem (articles, training data, chat
met AI). Medisch betekent data-gevoelig; foute of gefabriceerde
informatie kan echte gezondheidsgevolgen hebben. Daarom strikte auth,
training-data-validatie en CSP op elke response.

Repo-pad: `D:/GitHub/Infosyst` (via `havuncore:config/quality-safety.php`).
Test-referenties zijn **relatief aan die root**.

## Pad 1 — Authenticatie (PIN + QR + API-token)

**Waarom kritiek:** drie auth-paden, elk een eigen attack-surface.
- PIN: korte code, moet rate-limited + brute-force-resistent zijn.
- QR: device-binding, moet vervalsing weigeren.
- API-token: server-to-server, moet token-rotation ondersteunen.

**Componenten:**

- `app/Http/Controllers/Auth/PinAuthController.php`
- `app/Http/Controllers/Auth/QrAuthController.php`
- `app/Http/Middleware/ApiTokenAuth.php`
- `app/Models/AuthDevice.php`

**Branches / edge-cases:**

- [ ] PIN: rate-limit op verkeerde pogingen.
- [ ] PIN: expired PIN weigert inloggen.
- [ ] QR: unknown device → nieuwe binding (of geweigerd, afh. config).
- [ ] QR: gespoofde signature → 403.
- [ ] API-token: revoked/expired token → 401.
- [ ] API-token: geen token → 401 (niet 500, niet default-allow).

**Tests:**

- `tests/Feature/Auth/PinAuthControllerTest.php`
- `tests/Feature/Auth/QrAuthControllerTest.php`
- `tests/Unit/Middleware/ApiTokenAuthTest.php`
- `tests/Unit/Models/AuthDeviceTest.php`

**Mutation-score target:** 90 %.

## Pad 2 — Chat-controller (Groq-call + vector-search)

**Waarom kritiek:** hier wordt een extern LLM (Groq) aangeroepen met
gebruikers-input. Prompt-injection + lekken van een API-key = hoge
impact. Vector-search bepaalt welke trainingsdata als context wordt
meegestuurd — fout hier = hallucinatie-risico.

**Componenten:**

- `app/Http/Controllers/Frontend/ChatController.php`
- `app/Services/GroqService.php` (of equivalent)
- `app/Services/Embeddings/*` (vector-search)

**Branches / edge-cases:**

- [ ] Valide vraag → response + usage-log.
- [ ] Ontbrekende/ongeldige input → 422, niet 500.
- [ ] Groq API-timeout → graceful fallback (user-error message,
  niet een backtrace).
- [ ] Groq API-key ontbreekt → 503 met neutraal bericht (geen lek).
- [ ] Vector-search filter: alleen goedgekeurde training-data wordt
  meegestuurd als context.
- [ ] Rate-limit per user/device.

**Tests:**

- `tests/Feature/Frontend/ChatControllerTest.php`
- `tests/Feature/Frontend/ChatControllerVectorTest.php`

**Mutation-score target:** 90 %.

## Pad 3 — Training-data + validation-review

**Waarom kritiek:** hier wordt bepaald wélke medische content het LLM
als bron mag gebruiken. Een bug in validatie = ongevalideerde content
wordt als feit gepresenteerd aan eindgebruikers.

**Componenten:**

- `app/Http/Controllers/Admin/TrainingDataController.php`
- `app/Http/Controllers/Admin/ValidationReviewController.php`
- `app/Http/Controllers/Admin/ArticleController.php`
- `app/Models/TrainingData.php` + `Article.php` + `ArticleRevision.php`

**Branches / edge-cases:**

- [ ] Nieuwe training-data → default `status='pending_review'`.
- [ ] Review-approve → `status='approved'`, beschikbaar voor
  vector-search.
- [ ] Review-reject → `status='rejected'`, uit vector-search.
- [ ] Revision-tracking: iedere edit maakt een `ArticleRevision`.
- [ ] Source-credibility check geraadpleegd vóór auto-approve.

**Tests:**

- `tests/Feature/Admin/TrainingDataControllerTest.php`
- `tests/Feature/Admin/TrainingDataControllerExtraTest.php`
- `tests/Feature/Admin/ValidationReviewControllerTest.php`
- `tests/Feature/Admin/ValidationReviewControllerExtraTest.php`
- `tests/Feature/Admin/ArticleControllerTest.php`
- `tests/Feature/Admin/ArticleControllerExtraTest.php`
- `tests/Unit/Models/ArticleTest.php`
- `tests/Unit/Models/ArticleExtraTest.php`
- `tests/Unit/Models/ArticleRevisionTest.php`
- `tests/Unit/Models/TrainingDataTest.php`

**Mutation-score target:** 85 %.

## Pad 4 — Security headers + session cookies

**Waarom kritiek:** Mozilla Observatory, CSP op elk antwoord, cookies
veilig op HTTPS.

**Componenten:**

- `app/Http/Middleware/SecurityHeaders.php`
- `config/session.php`

**Branches / edge-cases:**

- [ ] Alle security-headers op elke response (CSP, HSTS,
  X-Frame-Options, X-Content-Type-Options, Referrer-Policy,
  Permissions-Policy).
- [ ] CSP-nonce per request uniek.
- [ ] Session-cookie `secure`/`http_only`/`same_site`.

**Tests:**

- `tests/Feature/Middleware/SecurityHeadersTest.php` (7 tests / 13
  assertions — X-Content-Type, X-Frame=DENY, X-XSS, Referrer-Policy,
  Permissions-Policy, CSP default-deny + frame-ancestors='none',
  nonce-per-request-uniekheid)

**Mutation-score target:** 85 %.

## Audit-checklist (externe review)

1. Klopt het aantal paden? (4).
2. Bevat elk pad componenten + branches + tests?
3. Zijn de tests actueel? → `critical-paths:verify --project=infosyst`.
4. Wordt test-erosion gemonitord? → ja, K&V-scanner.

## Proces

- **Bij elke PR** die een kritiek pad raakt: update "branches" en "tests".
- **Maandelijks**: mutation-run + update `last_reviewed`.
