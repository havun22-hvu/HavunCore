---
title: "Bibliotheek: herbruikbare K&V-bouwstenen (referentie: HavunClub)"
type: pattern
scope: havuncore
last_check: 2026-07-14
---

# Herbruikbare K&V-bouwstenen — referentie HavunClub

> **Doel:** één plek die per Havun-kwaliteitsnorm-bouwsteen de beste beproefde
> implementatie aanwijst, zodat een nieuw project (bv. Vusista) ze kopieert i.p.v.
> herbouwt. Alle paden relatief aan `D:\GitHub\HavunClub`.

| Bouwsteen | Bestand(en) | Kern |
|-----------|-------------|------|
| **Circuit breaker** | `app/Services/CircuitBreaker.php` | Cache-gebaseerd, threshold 3 / cooldown 30s, `call($operation, $fallback)`. Volledig generiek, geen projectafhankelijkheid. |
| **Audit log** | `app/Services/AuditLogger.php` + `app/Models/AuditLog.php` | Statisch `AuditLogger::log($actie, $beschrijving, $clubId, $reden, $properties)` — wie/wat/wanneer/waarom + IP. |
| **Custom exceptions** | `app/Exceptions/HavunClubException.php` (+ subclasses `MollieException`, `ExternalServiceException`, ...) | Basisklasse met `getUserMessage()` (veilig voor gebruiker) gescheiden van `getContext()` (intern). Zie ook `patterns/error-handling-strategies.md`. |
| **Security headers + CSP** | `app/Http/Middleware/SecurityHeaders.php` (globaal in `bootstrap/app.php`) | X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy (camera=self alleen als je QR/camera gebruikt!), HSTS op https, `Cache-Control: no-store` op HTML (CSRF/419). **Let op:** CSP daar nog permissief (`unsafe-inline`/`unsafe-eval` voor Alpine) — nieuw project: direct nonce-based beginnen i.p.v. later migreren. |
| **Rate limiters** | `app/Providers/AppServiceProvider.php` | Named limiters: `login` 5/min·IP, `form-submit` 10/min·IP, `api` 60/min·user→IP, `webhook` 100/min·IP. Toepassen op álle publieke POST-routes (HavunClub's beheer-`/register` vergat dit — niet overnemen). |
| **Health endpoint** | `app/Http/Controllers/SystemController.php` (`/health`) | Test DB + cache actief (write-then-read), `200 ok`/`503 degraded`. Controller i.p.v. closure zodat `route:cache` werkt. Naast Laravel's `/up`. |
| **Exception→redirect-hook** | `bootstrap/app.php` (respond-hook) | Centrale mapping 419/401/403/404 → redirect + flash, per guard een eigen login-route; JSON/API blijft ongemoeid. Onmisbaar bij multi-guard. |
| **Form Request-conventie** | `app/Http/Requests/JudokaRequest.php` | `authorize()`, context-afhankelijke rules (store vs update), `withValidator()` voor businessregels, NL `messages()`, tenant-scoped `Rule::exists(...)->where(...)`. |
| **Test-wachtwoord runtime** | `tests/TestCase.php` (`testPassword()`), `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php` | Nooit literals: `'pw-'.Str::random(32)` per proces; factory cachet `Hash::make(Str::password(20))`; seeder gebruikt `SEED_PASSWORD` env of print eenmalig. Plus `withoutVite()` in `setUp()`. Zie `runbooks/geen-hardcoded-secrets-in-tests.md`. |
| **Deploy-script (app-lokaal)** | `deploy/staging.sh` | git pull → composer --no-dev → npm build → migrate → caches. Valkuil uit comments: **Vite-build overslaan = 500** (ontbrekend manifest). Nieuwe projecten: liever centraal `/root/deploy-havun.sh` (zie `runbooks/nieuw-project-opzetten.md`). |

## Gerelateerde patterns (uitgewerkt in eigen doc)

- `patterns/cross-guard-login.md` — twee guards, één login; magic link zonder tabel; QR-login
- `patterns/webauthn-passkey-laravel.md` — passkey/biometrie (Laragear)
- `patterns/pwa-blade-laravel.md` — PWA-schil (SW-cacheregels, iOS, push)
- `patterns/multi-tenant-scoping.md` — ClubScope-middleware + valkuilen
- `patterns/mollie-payments.md` — incl. mock-checkout + key-beheer/OAuth-randvoorwaarde

## Wat HavunClub NIET heeft (niet zoeken, vers bouwen)

- **File-upload/media-verwerking** — nergens aanwezig (voor Vusista: Spatie Media Library, vers)
- **Gebruikersquota** — bestaat niet
- **Playwright/E2E** — alleen Laravel Feature-tests (71); E2E-blauwdruk = `runbooks/playwright-e2e-laravel.md`
- **Honeypot/captcha/dubbele opt-in** op open registratie — bekend gat, bij nieuw project meteen meenemen
