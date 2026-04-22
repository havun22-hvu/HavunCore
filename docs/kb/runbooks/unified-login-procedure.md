---
title: Unified Login Procedure - Laravel Projecten
type: runbook
scope: havuncore
last_check: 2026-04-22
---

# Unified Login Procedure - Laravel Projecten

> **Status:** Actieve standaard voor alle Havun Laravel-projecten
> **Laatste update:** 17 maart 2026
> **Versie:** 4.0

## Overzicht

Stap-voor-stap procedure om de standaard login te implementeren in een Laravel project.

### Login methodes per platform

| Platform | Methode 1 | Methode 2 | Fallback |
|----------|-----------|-----------|----------|
| **Desktop** | QR code scan | Email/wachtwoord | Magic link |
| **Smartphone** | Biometrisch (passkey) | Email/wachtwoord | Magic link |

### Registratie & wachtwoord vergeten

| Functie | Methode |
|---------|---------|
| **Registratie** | Magic link email (geen wachtwoord nodig) |
| **Wachtwoord vergeten** | Magic link email |
| **Wachtwoord instellen** | Optioneel NA eerste login via magic link |

---

## Stap 1: Dependencies

```bash
composer require laragear/webauthn
php artisan vendor:publish --provider="Laragear\WebAuthn\WebAuthnServiceProvider"
```

Geen extra NPM packages nodig (WebAuthn is native browser API).

---

## Stap 2: Database Migrations

Drie tabellen aanmaken:

### 2a. magic_link_tokens
```bash
php artisan make:migration create_magic_link_tokens_table
```
Zie: `docs/kb/patterns/magic-link-auth.md` voor schema.

### 2b. qr_login_tokens
```bash
php artisan make:migration create_qr_login_tokens_table
```
Zie: `docs/kb/reference/unified-login-system.md` sectie 1 voor schema.

### 2c. webauthn_credentials
Wordt aangemaakt door Laragear publish (stap 1).

### 2d. auth_devices
```bash
php artisan make:migration create_auth_devices_table
```
Zie: `docs/kb/reference/unified-login-system.md` sectie 1 voor schema.

```bash
php artisan migrate
```

---

## Stap 3: Models

### 3a. MagicLinkToken
Kopieer van `docs/kb/patterns/magic-link-auth.md`.

### 3b. QrLoginToken
Kopieer van referentie-implementatie (SafeHavun of JudoToernooi).

### 3c. AuthDevice
Kopieer van referentie-implementatie. Bevat: token, fingerprint, has_biometric, pin_hash (legacy, niet meer actief gebruikt).

### 3d. User model aanpassen
```php
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;

class User extends Authenticatable implements WebAuthnAuthenticatable
{
    use WebAuthnAuthentication;

    public function authDevices(): HasMany
    {
        return $this->hasMany(AuthDevice::class);
    }
}
```

---

## Stap 4: Controllers

### 4a. MagicLinkController (registratie + wachtwoord vergeten)
Kopieer van `docs/kb/patterns/magic-link-auth.md`.

### 4b. LoginController (email/wachtwoord + token login)
```php
// Kernmethodes:
// - showLogin() — toon login pagina
// - login() — email/wachtwoord login
// - logout()
// - tokenLogin($token) — voor QR en biometrisch login
```

### 4c. QrAuthController (QR code login)
```php
// Kernmethodes:
// - generate() — maak QR token + return SVG/URL
// - status($token) — poll endpoint (desktop)
// - approve() — mobiel bevestigt (auth required)
// - complete($token) — desktop voltooit login
```

### 4d. PasskeyController (biometrisch login)
Gebruik Laragear's gepublishte controllers + eigen wrapper:
```php
// Kernmethodes:
// - loginOptions() — stuur WebAuthn challenge
// - login() — verifieer assertion, return device_token
// - registerOptions() — stuur registratie opties (auth required)
// - register() — sla credential op (auth required)
```

---

## Stap 5: Routes

```php
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\QrAuthController;
use App\Http\Controllers\Auth\PasskeyController;

// === LOGIN ===
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/auth/token-login/{token}', [LoginController::class, 'tokenLogin']);

// === REGISTRATIE (magic link) ===
Route::get('/register', [MagicLinkController::class, 'showRegister'])->name('register');
Route::post('/register', [MagicLinkController::class, 'sendRegisterLink']);
Route::get('/register/sent', [MagicLinkController::class, 'registerSent'])->name('register.sent');
Route::get('/register/verify/{token}', [MagicLinkController::class, 'verifyRegister'])->name('register.verify');

// === WACHTWOORD VERGETEN (magic link) ===
Route::get('/forgot-password', [MagicLinkController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [MagicLinkController::class, 'sendResetLink'])->name('password.email');
Route::get('/forgot-password/sent', [MagicLinkController::class, 'resetSent'])->name('password.sent');
Route::get('/reset-password/{token}', [MagicLinkController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [MagicLinkController::class, 'resetPassword'])->name('password.update');

// === QR LOGIN ===
Route::get('/auth/qr/generate', [QrAuthController::class, 'generate']);
Route::get('/auth/qr/{token}/status', [QrAuthController::class, 'status']);
Route::get('/auth/qr/complete/{token}', [QrAuthController::class, 'complete']);

// === PASSKEY/BIOMETRISCH ===
Route::post('/auth/passkey/login/options', [PasskeyController::class, 'loginOptions']);
Route::post('/auth/passkey/login', [PasskeyController::class, 'login']);

// === AUTH REQUIRED ===
Route::middleware(['auth'])->group(function () {
    Route::get('/auth/setup-password', [MagicLinkController::class, 'showSetupPassword'])->name('password.setup');
    Route::post('/auth/setup-password', [MagicLinkController::class, 'setupPassword']);
    Route::post('/auth/qr/approve', [QrAuthController::class, 'approve']);
    Route::post('/auth/passkey/register/options', [PasskeyController::class, 'registerOptions']);
    Route::post('/auth/passkey/register', [PasskeyController::class, 'register']);
});
```

---

## Stap 6: CSRF Exceptions

In `bootstrap/app.php`:
```php
$middleware->validateCsrfTokens(except: [
    'auth/passkey/*',
    'auth/qr/*',
]);
```

---

## Stap 7: Views

### Login pagina structuur (login.blade.php)

```
+--------------------------------------------------+
|                   [Logo/Titel]                    |
|                                                   |
|  +----------------------------------------------+ |
|  |  [INLOGGEN]  |  [REGISTREREN]   (pill tabs)  | |
|  +----------------------------------------------+ |
|                                                   |
|  === INLOGGEN TAB ===                             |
|                                                   |
|  [ Email                              ]           |
|  [ Wachtwoord                     [eye]]          |
|                                                   |
|  [      Inloggen (primary button)      ]          |
|                                                   |
|  Wachtwoord vergeten?                             |
|                                                   |
|  ─── of ───                                       |
|                                                   |
|  Desktop:    [QR code inloggen]                   |
|  Smartphone: [Biometrisch inloggen]               |
|                                                   |
|  === REGISTREREN TAB ===                          |
|                                                   |
|  [ Naam                               ]           |
|  [ Email                              ]           |
|  (+ project-specifieke velden)                    |
|                                                   |
|  [   Registratielink versturen         ]          |
|                                                   |
+--------------------------------------------------+
```

### Magic link sent pagina (magic-link-sent.blade.php)

```
+--------------------------------------------------+
|                                                   |
|                    [Email icon]                    |
|                                                   |
|              Check je inbox                       |
|                                                   |
|  We hebben een link gestuurd naar                 |
|  gebruiker@email.nl                               |
|                                                   |
|  De link is 15 minuten geldig.                    |
|                                                   |
|  [   Open e-mailapp (primary button)   ]          |
|                                                   |
|  Ander e-mailadres gebruiken?                     |
|                                                   |
+--------------------------------------------------+
```

### Wachtwoord reset pagina (reset-password.blade.php)

```
+--------------------------------------------------+
|                                                   |
|           Nieuw wachtwoord instellen              |
|                                                   |
|  [ Nieuw wachtwoord               [eye]]          |
|  [ Wachtwoord bevestigen          [eye]]          |
|                                                   |
|  [   Wachtwoord opslaan (primary)      ]          |
|                                                   |
+--------------------------------------------------+
```

### QR Modal (in login.blade.php)

```
+--------------------------------------------------+
|                                                   |
|           Scan met je telefoon                    |
|                                                   |
|          +------------------+                     |
|          |                  |                     |
|          |    [QR CODE]     |                     |
|          |                  |                     |
|          +------------------+                     |
|                                                   |
|          Verloopt over 4:32                       |
|                                                   |
|  [Annuleren]          [Nieuwe QR code]            |
|                                                   |
+--------------------------------------------------+
```

---

## Stap 8: Styling Richtlijnen

Elk project gebruikt zijn **eigen kleurenpalet en layout**. De login pagina volgt het bestaande design system van het project.

### Studieplanner-stijl elementen (inspiratie):
- **Pill tabs** voor login/registreren toggle
- **Centered form** met maximale breedte (~400px)
- **Glass morphism** achtergronden (subtiel transparant)
- **44px** input hoogte, afgeronde hoeken
- **Loading states** op buttons (spinner + disabled)
- **Smooth transitions** tussen tabs/modals

### Per project aanpassen:

| Element | JudoToernooi | Herdenkingsportaal |
|---------|-------------|-------------------|
| Primary color | Blue (#2563eb) | Purple/Indigo (#a855f7/#818cf8) |
| Background | Solid white/gray | Gradient + kleurschema's |
| Dark mode | Nee | Ja (class-based) |
| Guard | `organisator` | `web` |
| Extra reg. velden | organisatie_naam, telefoon | - |

---

## Stap 9: JavaScript

### Platform detectie
```javascript
const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

if (isMobile) {
    // Toon biometrie knop, verberg QR knop
} else {
    // Toon QR knop, verberg biometrie knop
}
```

### QR code flow (desktop)
```javascript
async function startQrLogin() {
    const res = await fetch('/auth/qr/generate');
    const data = await res.json();
    showQrModal(data.qr_svg, data.token);

    // Poll elke 2 seconden
    const poll = setInterval(async () => {
        const status = await fetch(`/auth/qr/${data.token}/status`);
        const result = await status.json();
        if (result.status === 'approved') {
            clearInterval(poll);
            window.location.href = `/auth/qr/complete/${data.token}`;
        } else if (result.status === 'expired') {
            clearInterval(poll);
            showQrExpired();
        }
    }, 2000);
}
```

### Biometrisch login (smartphone)
Zie: `docs/kb/reference/unified-login-system.md` sectie 6 voor complete WebAuthn JavaScript.

---

## Stap 10: Testen

### Handmatige test checklist

- [ ] **Registratie:** Email invoeren → magic link ontvangen → link klikken → account actief
- [ ] **Login:** Email + wachtwoord → redirect naar dashboard
- [ ] **QR login (desktop):** QR tonen → scannen met telefoon → desktop ingelogd
- [ ] **Biometrisch (smartphone):** Vingerafdruk/Face ID → ingelogd
- [ ] **Wachtwoord vergeten:** Email → magic link → nieuw wachtwoord instellen
- [ ] **Verlopen link:** Na 15 min → foutmelding + "vraag nieuwe aan"
- [ ] **Rate limiting:** 4e verzoek binnen 10 min → geblokkeerd
- [ ] **Email enumeration:** Niet-bestaand email → zelfde success bericht

---

## Checklist

Voordat je live gaat:

- [ ] Migrations gerund
- [ ] MagicLinkToken model aangemaakt
- [ ] MagicLinkController aangemaakt
- [ ] QrAuthController aangemaakt
- [ ] PasskeyController aangemaakt
- [ ] CSRF exceptions in bootstrap/app.php
- [ ] Email template aangemaakt
- [ ] Views: login, register, magic-link-sent, forgot-password, reset-password
- [ ] Platform detectie (QR vs biometrie)
- [ ] Rate limiting actief
- [ ] HTTPS in productie
- [ ] `session()->save()` in ALLE login methodes (GEEN regenerate!)
- [ ] Cleanup command voor verlopen tokens

---

## Referentie Documenten

| Document | Inhoud |
|----------|--------|
| `patterns/magic-link-auth.md` | Magic link model, controller, email template |
| `reference/unified-login-system.md` | QR, biometrie, device fingerprint, troubleshooting |
| `standards/unified-auth-strategy.md` | Strategisch overzicht, security best practices |
| `decisions/002-decentrale-auth.md` | Waarom decentrale auth (geen SSO) |
