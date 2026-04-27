---
title: Unified Login System
type: reference
scope: havuncore
last_check: 2026-04-27
---

# Unified Login System

> **Status:** Production (alle Havun-applicaties: web + native)
> **Versie:** 5.1
> **Laatste update:** 27 april 2026 — wachtwoord opt-in toegevoegd, Google OAuth verwijderd
> **Auth model:** Decentraal (ADR-002) — elke app beheert eigen auth

Dit is het ENIGE document dat je nodig hebt om het Havun login systeem te begrijpen.

## Overzicht — Passwordless-First, Wachtwoord Opt-in

| Methode | Platform | Wanneer |
|---------|----------|---------|
| Magic link | Alle | Eerste login + herstel (nieuw apparaat, passkey kwijt) |
| Biometrie (WebAuthn/Passkey) | Smartphone, Android, iOS | Dagelijks gebruik |
| QR code | Desktop | Dagelijks gebruik (scan met telefoon) |
| Wachtwoord (optioneel) | Alle | Alleen als gebruiker zelf heeft ingesteld in account-settings |

| Platform | Dagelijks | Eerste keer / herstel | Optioneel |
|----------|-----------|----------------------|-----------|
| **Smartphone (web)** | Biometrie (passkey) | Magic link | Wachtwoord (opt-in) |
| **Desktop (web)** | QR code scan | Magic link | Wachtwoord (opt-in) |
| **Android (native)** | Biometrie (Credential Manager) | Magic link (deep link) | — |
| **iOS (native)** | Biometrie (ASAuthorization) | Magic link (universal link) | — |

**Niet meer gebruikt:**
- PIN login (v4.0 → vervangen door biometric)
- Google OAuth / Social login (v5.1 → verwijderd 27-04-2026, incident "deleted_client" Google Cloud Console)

**Wachtwoord-flow:** Default = uit. Gebruiker activeert zelf via account-settings (security-tab). Login-pagina toont wachtwoord-veld alleen als de gebruiker een wachtwoord heeft ingesteld.

## Login Flow

```
Nieuw? → Registreer (magic link email)
  → Email ontvangen → Link klikken → Account actief
  → Biometrie koppelen (verplicht op smartphone/native)
  ↓
Terugkerend? → Login pagina
  ↓
Smartphone/native: Biometrie (passkey) → ingelogd
Desktop:           QR code scan met telefoon → ingelogd
  ↓
Probleem? → "Stuur mij een login link" → magic link → opnieuw biometrie koppelen
```

### Error Fallback (KRITIEK — gebruiker mag NOOIT vastlopen!)

Elke login methode kan falen. Bij falen ALTIJD magic link als hersteloptie aanbieden.

```
Biometrie faalt   → Toon "Stuur mij een login link" (magic link)
QR faalt/verloopt → Toon "Stuur mij een login link" + nieuwe QR knop
Magic link faalt  → Opnieuw aanvragen (rate limit: 3 per 10 min)
```

**JavaScript — toon alternatieven bij falen:**
```javascript
function showFallbackOptions(failedMethod) {
    const msg = document.getElementById('login-error');
    const magicLinkSection = document.getElementById('magic-link-section');

    switch (failedMethod) {
        case 'biometric':
            msg.textContent = 'Biometrie niet gelukt. Vraag een login link aan.';
            magicLinkSection.classList.remove('hidden');
            break;
        case 'qr':
            msg.textContent = 'QR code verlopen. Vernieuw of vraag een login link aan.';
            magicLinkSection.classList.remove('hidden');
            break;
    }
    msg.classList.remove('hidden');
}
```

**HTML — fallback opties (ALTIJD aanwezig in login.blade.php):**
```html
{{-- Error message --}}
<p id="login-error" class="text-red-600 text-sm text-center hidden"></p>

{{-- Fallback: magic link aanvragen --}}
<div id="magic-link-section" class="text-center space-y-2 mt-4 hidden">
    <p class="text-sm text-gray-600">Problemen met inloggen?</p>
    <form action="/auth/magic-link" method="POST">
        @csrf
        <input type="email" name="email" placeholder="Je email" required
               class="w-full border rounded-lg px-4 py-2 mb-2">
        <button type="submit" class="text-sm text-blue-600 underline">
            Stuur mij een login link
        </button>
    </form>
</div>
```

---

## 1. Installatie

### Composer
```bash
composer require laragear/webauthn
php artisan vendor:publish --provider="Laragear\WebAuthn\WebAuthnServiceProvider"
```

### Database migrations

**auth_devices:**
```php
Schema::create('auth_devices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('token', 64)->unique();
    $table->boolean('has_biometric')->default(false);
    $table->string('device_fingerprint', 64)->nullable();
    $table->string('device_name')->nullable();
    $table->string('browser')->nullable();
    $table->string('os')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_used_at')->nullable();
    $table->timestamp('expires_at');
    $table->timestamps();

    $table->index(['token', 'is_active', 'expires_at']);
    $table->index('device_fingerprint');
});
```

**qr_login_tokens:**
```php
Schema::create('qr_login_tokens', function (Blueprint $table) {
    $table->id();
    $table->string('token', 64)->unique();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->enum('status', ['pending', 'approved', 'expired', 'used'])->default('pending');
    $table->json('device_info')->nullable();
    $table->timestamp('expires_at');
    $table->timestamp('approved_at')->nullable();
    $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['token', 'status']);
});
```

**magic_link_tokens:**
Zie: `docs/kb/patterns/magic-link-auth.md` voor schema.

**webauthn_credentials** — wordt aangemaakt door `php artisan vendor:publish` (Laragear package).

### User Model
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

### Routes

Zie: `docs/kb/runbooks/unified-login-procedure.md` stap 5 voor complete route definitie.

---

## 2. Kritieke Configuratie (bootstrap/app.php)

### CSRF Exceptions
```php
$middleware->validateCsrfTokens(except: [
    'auth/passkey/*',
    'auth/qr/*',
]);
```

### JSON Exception Handlers
```php
$exceptions->render(function (AuthenticationException $e, Request $request) {
    if ($request->is('auth/passkey/*') || $request->is('auth/qr/*')) {
        return response()->json([
            'success' => false,
            'message' => 'Je moet ingelogd zijn.',
            'error' => 'unauthenticated'
        ], 401);
    }
});
```

---

## 3. Session & Login Pattern (KRITIEK!)

### Het probleem
`session()->regenerate()` breekt cookies na token/AJAX login. Gebruiker logt succesvol in maar blijft hangen op login pagina.

### De oplossing — ALTIJD zo doen
```php
// FOUT
Auth::login($user, true);
session()->regenerate();
return redirect('/dashboard');

// GOED
Auth::guard('web')->login($user, true);
session()->save();  // KRITIEK: save VOOR redirect
return redirect()->intended('/dashboard');
```

### Token-login pattern (voor biometrie en QR)

AJAX login kan geen session cookies betrouwbaar zetten. Daarom: AJAX geeft device_token terug -> JavaScript navigeert naar token-login endpoint -> server maakt session.

```php
// Controller: tokenLogin($token)
public function tokenLogin(string $token)
{
    $device = AuthDevice::where('token', $token)
        ->where('is_active', true)
        ->where('expires_at', '>', now())
        ->firstOrFail();

    Auth::guard('web')->login($device->user, true);
    $device->update(['last_used_at' => now()]);
    session()->save();

    return redirect()->intended(route('dashboard'));
}
```

```javascript
// JavaScript na succesvolle AJAX login
if (data.success && data.device_token) {
    window.location.href = '/auth/token-login/' + data.device_token;
}
```

### Checklist voor ELKE login method
- `Auth::guard('web')->login()` (niet `Auth::login()`)
- `session()->save()` NA login, VOOR redirect
- GEEN `session()->regenerate()`
- `redirect()->intended()` voor correcte doorverwijzing

---

## 4. Device Fingerprint

Client-side SHA-256 hash van browser-eigenschappen:

```javascript
async function generateFingerprint() {
    const data = [
        navigator.userAgent,
        navigator.language,
        screen.width + 'x' + screen.height,
        screen.colorDepth,
        new Date().getTimezoneOffset(),
        navigator.hardwareConcurrency || 'unknown',
        navigator.platform
    ].join('|');
    const hashBuffer = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(data));
    return Array.from(new Uint8Array(hashBuffer)).map(b => b.toString(16).padStart(2, '0')).join('');
}
```

---

## 5. Biometrie / WebAuthn (Passkey)

### Overzicht
Gebruikt `laragear/webauthn` package. Werkt alleen op smartphone (platform authenticator = vingerafdruk/Face ID).

Twee stappen:
1. **Registratie** — na eerste login, gebruiker koppelt passkey op device
2. **Login** — bij terugkomst, biometrie knop activeert passkey

### WebAuthn Challenge Storage (KRITIEK voor mobiel)

Mobile browsers verliezen session cookies bij redirects. Gebruik **DatabaseChallengeRepository** i.p.v. session:

```php
// config/webauthn.php
'challenge' => [
    'repository' => \App\Auth\DatabaseChallengeRepository::class,
    'bytes' => 16,
    'timeout' => 60,
],
```

```php
// app/Auth/DatabaseChallengeRepository.php
namespace App\Auth;

use Laragear\WebAuthn\Challenge;
use Laragear\WebAuthn\ChallengeRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DatabaseChallengeRepository implements ChallengeRepository
{
    public function store(Challenge $challenge): void
    {
        DB::table('webauthn_challenges')->updateOrInsert(
            ['user_id' => Auth::id()],
            [
                'challenge' => json_encode($challenge),
                'created_at' => now(),
                'expires_at' => now()->addMinutes(2),
            ]
        );
    }

    public function pull(): ?Challenge
    {
        $row = DB::table('webauthn_challenges')
            ->where('user_id', Auth::id())
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) return null;

        DB::table('webauthn_challenges')->where('id', $row->id)->delete();

        return Challenge::fromJson($row->challenge);
    }
}
```

Migration:
```php
Schema::create('webauthn_challenges', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->text('challenge');
    $table->timestamp('created_at');
    $table->timestamp('expires_at');
});
```

### Passkey Login Flow

```
login.blade.php (public)
  1. Platform detectie → smartphone? Toon biometrie knop
  2. startBiometric() →
     a. POST /auth/passkey/login/options → server stuurt challenge
     b. navigator.credentials.get() → browser toont vingerafdruk prompt
     c. POST /auth/passkey/login → server valideert → device_token terug
  3. window.location.href = '/auth/token-login/' + device_token
```

**JavaScript login (login.blade.php):**
```javascript
async function startBiometric() {
    try {
        const optRes = await csrfFetch('/auth/passkey/login/options', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
        });
        const options = await optRes.json();

        const credential = await navigator.credentials.get({
            publicKey: {
                challenge: base64urlToBuffer(options.challenge),
                timeout: options.timeout || 60000,
                rpId: options.rpId,
                userVerification: options.userVerification || 'preferred',
                allowCredentials: options.allowCredentials.map(c => ({
                    id: base64urlToBuffer(c.id),
                    type: c.type,
                    transports: c.transports || ['internal', 'hybrid']
                }))
            }
        });

        const loginRes = await csrfFetch('/auth/passkey/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: credential.id,
                rawId: bufferToBase64url(credential.rawId),
                type: credential.type,
                response: {
                    authenticatorData: bufferToBase64url(credential.response.authenticatorData),
                    clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
                    signature: bufferToBase64url(credential.response.signature),
                    userHandle: credential.response.userHandle
                        ? bufferToBase64url(credential.response.userHandle)
                        : null,
                },
            }),
        });

        const data = await loginRes.json();
        if (data.success && data.device_token) {
            window.location.href = '/auth/token-login/' + data.device_token;
        }
    } catch (err) {
        showFallbackOptions('biometric');
    }
}
```

### Passkey Registratie Flow

```
Na eerste login (authenticated):
  1. Biometrie koppelen prompt verschijnt (alleen smartphone)
  2. setupBiometric() →
     a. POST /auth/passkey/register/options → WebAuthn options
     b. navigator.credentials.create() → vingerafdruk/Face ID prompt
     c. POST /auth/passkey/register → credential opgeslagen
  3. Volgende keer: biometrie knop beschikbaar op login pagina
```

**JavaScript registratie:**
```javascript
async function setupBiometric() {
    try {
        const optRes = await csrfFetch('/auth/passkey/register/options', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        });
        const options = await optRes.json();

        const credential = await navigator.credentials.create({
            publicKey: {
                challenge: base64urlToBuffer(options.challenge),
                rp: options.rp,
                user: {
                    id: base64urlToBuffer(options.user.id),
                    name: options.user.name,
                    displayName: options.user.displayName
                },
                pubKeyCredParams: options.pubKeyCredParams,
                timeout: 60000,
                authenticatorSelection: {
                    authenticatorAttachment: 'platform',
                    userVerification: 'required'
                },
            }
        });

        await csrfFetch('/auth/passkey/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: credential.id,
                rawId: bufferToBase64url(credential.rawId),
                type: credential.type,
                response: {
                    clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
                    attestationObject: bufferToBase64url(credential.response.attestationObject),
                },
            }),
        });

        window.location.href = '/dashboard';
    } catch (err) {
        if (err.name === 'NotAllowedError') {
            showMessage('Biometrie overgeslagen.', 'info');
        }
        setTimeout(() => { window.location.href = '/dashboard'; }, 2000);
    }
}
```

### Base64url Helpers (VEREIST in views)
```javascript
function base64urlToBuffer(b64) {
    const padding = '='.repeat((4 - b64.length % 4) % 4);
    const base64 = b64.replace(/-/g, '+').replace(/_/g, '/') + padding;
    return Uint8Array.from(atob(base64), c => c.charCodeAt(0)).buffer;
}

function bufferToBase64url(buf) {
    const bytes = new Uint8Array(buf);
    let str = '';
    for (const b of bytes) str += String.fromCharCode(b);
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}
```

---

## 6. QR Code Login

### Flow
```
1. Desktop: GET /auth/qr/generate → krijgt token + QR image
2. Desktop: pollt GET /auth/qr/{token}/status elke 2 sec
3. Mobiel: scant QR → opent approve pagina (moet ingelogd zijn)
4. Mobiel: klikt "Goedkeuren" → POST /auth/qr/approve
5. Desktop: ontvangt status "approved" → navigeert naar /auth/qr/complete/{token}
6. Complete endpoint: Auth::guard('web')->login() + session()->save() → dashboard
```

**Belangrijk:**
- QR token verloopt na 5 minuten
- User moet AL ingelogd zijn op telefoon om goed te keuren
- Status + complete endpoints zijn CSRF-exempt
- QR image via `api.qrserver.com` of SimpleSoftwareIO/QrCode

---

## 7. Magic Link (Eerste Login + Herstel)

Volledig uitgewerkt in: `docs/kb/patterns/magic-link-auth.md`

### Samenvatting
- **Registratie:** Email + naam invoeren → magic link ontvangen → link klikken → account actief → biometrie koppelen
- **Herstel:** Email invoeren → magic link → direct ingelogd → opnieuw biometrie koppelen
- **Token:** 64 tekens, 15 min geldig, single-use
- **Rate limiting:** 3 per 10 min per IP
- **Email enumeration preventie:** Altijd success tonen
- **Native apps:** Magic link opent de app via deep link (Android) / universal link (iOS)

---

## 8. Troubleshooting

| Probleem | Oorzaak | Oplossing |
|----------|---------|-----------|
| Login redirect loop | `session()->regenerate()` | Gebruik `session()->save()` |
| HTML i.p.v. JSON response | CSRF exceptions ontbreken | Voeg auth routes toe aan CSRF exceptions |
| Passkey niet herkend | Challenge verloren (session) | Gebruik DatabaseChallengeRepository |
| Biometrie knop doet niks | `allowCredentials` ontbreekt | Voeg alle credentials toe in loginOptions (v5.0 fix) |
| Biometrie doet niks | Geen HTTPS | WebAuthn vereist HTTPS |
| QR verlopen | Token > 5 min oud | Vernieuw knop in QR modal |
| Magic link werkt niet | Token verlopen of al gebruikt | Vraag nieuwe aan |
| Gebruiker zit vast | Geen fallback opties | Toon altijd magic link als hersteloptie |

## 9. Implementatie Checklist

- [ ] `composer require laragear/webauthn` + publish
- [ ] Migrations: magic_link_tokens, auth_devices, qr_login_tokens, webauthn_challenges
- [ ] CSRF exceptions in `bootstrap/app.php`
- [ ] JSON exception handlers in `bootstrap/app.php`
- [ ] DatabaseChallengeRepository voor WebAuthn
- [ ] MagicLinkToken model + MagicLinkController
- [ ] MagicLinkMail + email template
- [ ] Token-login route + controller method
- [ ] `session()->save()` in ALLE login methods (GEEN regenerate)
- [ ] QR code generate + poll + approve + complete
- [ ] Passkey login + register endpoints
- [ ] Platform detectie (QR op desktop, biometrie op smartphone)
- [ ] Base64url helpers in views
- [ ] Fallback opties bij falen
- [ ] Rate limiting op magic link endpoints
- [ ] Views: login (pill tabs), register, magic-link-sent, forgot-password, reset-password

## Project Status (maart 2026)

| Project | Biometrie | QR | Magic Link | Wachtwoord | v5.0 Status |
|---------|:---:|:---:|:---:|:---:|:---:|
| JudoToernooi | ✅ | ✅ | ❌ | ✅ (verwijderen) | Magic link toevoegen, wachtwoord verwijderen |
| Herdenkingsportaal | ✅ | ✅ | ❌ | ✅ (verwijderen) | Magic link toevoegen, wachtwoord verwijderen |
| HavunAdmin | ✅ | ✅ | ❌ | ✅ (verwijderen) | Magic link toevoegen, wachtwoord verwijderen |
| SafeHavun | ✅ | ✅ | ❌ | ✅ (verwijderen) | Magic link toevoegen, wachtwoord verwijderen |
| Infosyst | ❌ | ✅ | ❌ | ✅ (verwijderen) | Alles toevoegen |
| JudoScoreBoard | ❌ | ❌ | ❌ | ❌ | Native Android passkey implementatie |
| VPDUpdate | ✅ | ❌ | ❌ | ✅ (verwijderen) | Magic link toevoegen, wachtwoord verwijderen |

**Standaard v5.0:** Passwordless — Magic link (eerste keer + herstel) + Biometrie (dagelijks, smartphone/native) + QR (dagelijks, desktop). Geen wachtwoorden.

## Referentie Implementaties

| Project | Locatie | Status |
|---------|---------|--------|
| VPDUpdate | `D:\GitHub\VPDUpdate` | Biometrie werkend (raw WebAuthn, Node.js) |
| JudoToernooi | `D:\GitHub\JudoToernooi\laravel` | Biometrie werkend (laragear, allowCredentials fix) |
| SafeHavun | `D:\GitHub\SafeHavun` | QR + biometrie compleet |

## Changelog

### v5.0 (31 maart 2026)
- **BREAKING:** Wachtwoorden verwijderd als login methode
- Magic link als primaire onboarding + herstel methode
- Biometrie (passkey) als enige dagelijkse login op mobiel
- Native app support (Android Credential Manager, iOS ASAuthorization)
- `allowCredentials` fix in loginOptions (laragear retourneert dit niet zonder bekende user)
- Fallback: magic link i.p.v. wachtwoord

### v4.0 (17 maart 2026)
- **Verwijderd:** PIN login (alle projecten)
- **Toegevoegd:** Magic link voor registratie en wachtwoord vergeten
- **Vereenvoudigd:** Desktop = QR + wachtwoord, Smartphone = biometrie + wachtwoord
- **Geconsolideerd:** Alle auth docs verwijzen naar dit document + magic-link-auth.md pattern

### v3.0 (28 februari 2026)
- Originele versie met PIN, QR, biometrie, wachtwoord
