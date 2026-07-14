---
title: "Pattern: Cross-guard login — twee guards, één login-ervaring"
type: pattern
scope: havuncore
last_check: 2026-07-14
---

# Cross-guard login (twee guards, één login-ervaring)

> **Referentie-implementatie:** HavunClub — `web`-guard (beheer, `User`) + `gezin`-guard
> (leden-PWA, `GezinAccount`), elke login-methode werkt voor beide.
> **Wanneer:** een app met twee gebruikerspopulaties met eigen sessies/dashboards die
> tóch één set login-methodes delen (wachtwoord, magic link, WebAuthn, QR).

## Kernstuk: CrossGuardLogin

`app/Auth/CrossGuardLogin.php` (HavunClub): `attempt($primaryGuard, $credentials, $remember, $request)`
probeert eerst de guard van de pagina, valt terug op de andere guard met dezelfde credentials.
Bij succes: `session()->regenerate()` + redirect naar het juiste dashboard. `redirectFor()`
handelt de cross-guard `intended()`-valkuil af (intended van guard A niet gebruiken voor guard B).

Beide login-controllers delen dit: `Auth/LoginController` (primair `web`) en
`Gezin/AuthController` (primair `gezin`). Normaliseer e-mail (lowercase + trim — mobiele
toetsenborden voegen spaties/hoofdletters toe).

## Bijpassende recepten (allemaal guard-agnostisch)

| Recept | Hoe | Bron (HavunClub) |
|--------|-----|------------------|
| **Magic link zonder tabel** | `URL::temporarySignedRoute('magic.login', +15 min, ['type','id'])` + `hasValidSignature()`-check; geen `magic_link_tokens`-tabel nodig | `Auth/MagicLinkController.php` |
| **Single-use magic link** | extra `nonce` in querystring, opgeslagen in Cache (`magic_nonce:<40>`); `Cache::pull()` bij gebruik → tweede klik = 403 | `Services/DunningService.php` |
| **Wachtwoord-reset zonder broker-tabel** | signed link 30 min met `sha1(huidig wachtwoord)` in de handtekening (`hash_equals`-check) — link sterft automatisch zodra het wachtwoord wijzigt | `Auth/WachtwoordResetController.php` |
| **QR-login** (desktop ↔ telefoon) | Cache-entry `qrlogin:<40char>` TTL 120s; ingelogde telefoon keurt goed, desktop pollt en doet `loginUsingId` op de goedgekeurde guard | `Auth/QrLoginController.php` |
| **WebAuthn multi-guard** | middleware `UseAuthGuard` (`Auth::shouldUse`) rond dezelfde routes | zie `patterns/webauthn-passkey-laravel.md` |
| **CSRF-refresh vóór submit** | forms met `data-csrf-refresh` halen vlak vóór submit een vers token op (`GET /csrf-token`) — lost 419 op bij PWA's/uit-cache geladen loginpagina's | `resources/js/app.js` (`initCsrfRefresh`) |

## Rolgelaagdheid binnen één guard

Meerdere accounttypes op één guard (HavunClub: Hoofdverzorger/TweedeVerzorger/Judoka via
rol-enum + `ouder_id`/`judoka_id`): bevoegdheden via helper-methodes op het model
(`magFinancieelInzien()` e.d.) + route-middleware-gates. Wachtwoord optioneel per account —
zonder wachtwoord logt het lid in via magic link.

## Security-basis (op alle credential-endpoints)

- `throttle:login` (5/min per IP) — definieer limiters centraal in `AppServiceProvider`
  (`login` 5/min, `form-submit` 10/min, `api` 60/min, `webhook` 100/min).
- Anti-enumeration: onbekend e-mailadres → zelfde "verzonden"-view.
- `session()->regenerate()` bij elke succesvolle (re)login; logout = `invalidate()` + `regenerateToken()`.

## Gaten in de referentie (meenemen bij hergebruik)

HavunClub's open registratiepaden hebben **geen honeypot/captcha en geen dubbele opt-in**;
de beheer-`/register` heeft **geen throttle**. Bij een nieuwe app: throttle op álle publieke
POST-routes + overweeg honeypot. Zie ook `patterns/magic-link-auth.md` §Security.

## Zie ook

- `patterns/magic-link-auth.md` — token-tabel-variant (Studieplanner/HP/JT)
- `patterns/universal-login-screen.md` — UX/layout-standaard
- `reference/authentication-methods.md` — welke methode op welk apparaat
