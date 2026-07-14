---
title: "Pattern: WebAuthn / Passkey biometrie (Laravel, multi-guard)"
type: pattern
scope: havuncore
last_check: 2026-07-14
---

# WebAuthn / Passkey biometrie (Laravel)

> **Referentie-implementatie:** HavunClub (live, getest op beide guards).
> **Package:** `laragear/webauthn ^4.1` — dunne controllers, standaard-migratie.
> **Past bij:** `reference/authentication-methods.md` (biometric = primair op mobiel).

## Recept

1. **Package + migratie:** `laragear/webauthn`; migratie via `WebAuthnCredential::migration()`
   (standaard `webauthn_credentials`-tabel, geen custom kolommen nodig).
   → HavunClub `database/migrations/2026_06_26_002350_create_webauthn_credentials.php`
2. **Model:** implementeer `WebAuthnAuthenticatable` + trait `WebAuthnAuthentication` op elk
   auth-model. Override `webAuthnData()` voor de display-naam.
   → HavunClub `app/Models/User.php`, `app/Models/GezinAccount.php`
3. **Controllers:** twee dunne controllers rond Laragear's request-objecten
   (`AttestationRequest`/`AttestedRequest` voor registratie, `AssertionRequest`/`AssertedRequest`
   voor login). → HavunClub `app/Http/Controllers/WebAuthn/WebAuthn{Register,Login}Controller.php`
4. **Config/env:** `config/webauthn.php` leest `WEBAUTHN_NAME`, `WEBAUTHN_ID` (rp.id),
   `WEBAUTHN_ORIGINS` (komma-gescheiden).
5. **Routes:** registratie achter `auth:<guard>`; assertion/login publiek.
6. **JS-client:** Laragear's `webauthn.js` helper + wiring die routes uit data-attributen leest
   (`data-wa-login-options`, `data-wa-login`, `data-wa-register*`) — zo werkt dezelfde JS voor
   meerdere guards/route-sets. → HavunClub `resources/js/app.js` + `resources/js/vendor/webauthn/`
7. **Feature-detectie:** toon de biometrie-knop alleen na
   `isUserVerifyingPlatformAuthenticatorAvailable()` — anders QR/magic-link als alternatief.

## Multi-guard zonder duplicatie

Middleware `UseAuthGuard` (`Auth::shouldUse('<guard>')`, alias `guard`) om dezelfde
Laragear-controllers voor een tweede guard te laten werken:

```php
Route::middleware('guard:gezin')->group(function () { /* zelfde WebAuthn-routes */ });
```

→ HavunClub `app/Http/Middleware/UseAuthGuard.php`. Zie ook `patterns/cross-guard-login.md`.

## Valkuilen (allemaal echt geraakt)

| Valkuil | Fix |
|---------|-----|
| **Passkey lukt op geen enkel toestel** | Bijna altijd domein-mismatch: `WEBAUTHN_ID` (rp.id) en `WEBAUTHN_ORIGINS` moeten exact matchen met het domein waarop wordt ingelogd. Eerste check = server-`.env`. (HavunClub-incident jul 2026) |
| Biometrische stap wordt overgeslagen | Gebruik `->secureRegistration()` (user-verification verplicht), niet `fastRegistration()` (userVerification "discouraged" slaat vingerafdruk/Face ID over) |
| Testsleutels hardcoden in E2E | Nooit — genereer at runtime; zie `runbooks/geen-hardcoded-secrets-in-tests.md` |
| Laragear's JS-helper logt publicKey naar console + is `@deprecated` | Opruimen/vervangen door `@laragear/webpass` bij een volgend project |
| E2E-testen | CDP virtual authenticator; `rp.id` móét `localhost` zijn lokaal. Zie `runbooks/playwright-e2e-webapp.md` |

## Tests (HavunClub als voorbeeld)

`tests/Feature/Auth/WebAuthnTest.php` + `WebAuthnGezinTest.php` — registratie- en
login-ceremonie per guard.

## Projectspecifiek bij hergebruik

Guard-namen, modellen en de route-set. De rest (package, migratie, dunne controllers,
data-attribuut-JS, env-vars) is 1-op-1 over te nemen.
