# Unified Login System - Compleet

> **Status:** Production (Herdenkingsportaal, SafeHavun)
> **Versie:** 3.0
> **Laatste update:** 28 februari 2026
> **Auth model:** Decentraal (ADR-002) — elke app beheert eigen auth

Dit is het ENIGE document dat je nodig hebt om het Havun login systeem te implementeren.

## Overzicht

| Methode | Platform | Beschrijving |
|---------|----------|--------------|
| Wachtwoord | Alle | Eerste login op nieuw device |
| PIN (5 cijfers) | Alle | Numpad + keyboard support |
| Biometrie (WebAuthn) | Mobiel | Vingerafdruk/Face ID via passkey |
| QR code | Desktop | Scan met ingelogde telefoon |

**BELANGRIJK:** Login zonder registratie werkt niet! Altijd registratie meenemen.

| Platform | PIN | Biometrie | QR |
|----------|-----|-----------|-----|
| PC webapp | ja | nee | ja (toont QR) |
| Smartphone PWA | ja | ja | ja (scant QR) |

## Login Flow

```
0. Nieuw? → Registreer (email/wachtwoord)
   ↓
1. Device check (fingerprint)
   ↓
2a. Bekend device → Toon numpad
    - Desktop: + QR knop (links)
    - Mobiel: + biometrie knop (links)
2b. Onbekend device → Toon wachtwoord form + registratie link
   ↓
3. Na wachtwoord/registratie → PIN setup → (optioneel) biometrie setup
   ↓
4. Volgende keer → Direct numpad
```

### Error Fallback (KRITIEK — gebruiker mag NOOIT vastlopen!)

Elke login methode kan falen. Bij falen ALTIJD de andere methodes aanbieden.

```
Biometrie faalt ──→ Toon numpad (PIN) + "Ander account" link (wachtwoord)
PIN faalt (5x)  ──→ Toon "Probeer wachtwoord" link + QR/biometrie knoppen
QR faalt/verloopt → Toon "Probeer PIN" knop + "Ander account" link
Wachtwoord faalt ─→ Toon "Wachtwoord vergeten" + registratie link
Device onbekend  ─→ Toon wachtwoord form + "Registreer" link
```

**"Ander account" link** = altijd zichtbaar onder numpad. Gaat naar wachtwoord form.

**JavaScript — toon alternatieven bij falen:**
```javascript
function showFallbackOptions(failedMethod) {
    // Toon altijd: error message + beschikbare alternatieven
    const msg = document.getElementById('login-error');
    const fallback = document.getElementById('fallback-options');

    switch (failedMethod) {
        case 'biometric':
            msg.textContent = 'Biometrie niet gelukt. Gebruik je PIN of wachtwoord.';
            showPinSection();   // Toon numpad
            break;
        case 'pin':
            msg.textContent = 'PIN onjuist. Probeer opnieuw of gebruik een andere methode.';
            fallback.classList.remove('hidden');  // Toon wachtwoord + QR links
            break;
        case 'qr':
            msg.textContent = 'QR code verlopen. Vernieuw of gebruik een andere methode.';
            fallback.classList.remove('hidden');
            break;
    }
    msg.classList.remove('hidden');
}
```

**HTML — fallback opties (ALTIJD aanwezig in login.blade.php):**
```html
{{-- Error message --}}
<p id="login-error" class="text-red-600 text-sm text-center hidden"></p>

{{-- Fallback opties — tonen bij falen --}}
<div id="fallback-options" class="text-center space-y-2 mt-4 hidden">
    <button onclick="showPasswordSection()" class="text-blue-600 underline text-sm">
        Inloggen met wachtwoord
    </button>
    <br>
    <button onclick="showPinSection()" class="text-blue-600 underline text-sm">
        Inloggen met PIN
    </button>
</div>

{{-- "Ander account" — ALTIJD zichtbaar onder numpad --}}
<a href="#" onclick="showPasswordSection()" class="text-gray-500 text-sm block text-center mt-3">
    Ander account gebruiken
</a>
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
    $table->string('pin_hash')->nullable();
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
```php
// === PUBLIC ===
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// PIN (public)
Route::post('/auth/pin/check-device', [PinAuthController::class, 'checkDevice']);
Route::post('/auth/pin/login', [PinAuthController::class, 'loginWithPin']);

// Token login (public) — gebruikt door PIN, passkey en QR
Route::get('/auth/token-login/{token}', [LoginController::class, 'tokenLogin']);

// QR (public)
Route::get('/auth/qr/generate', [QrAuthController::class, 'generate']);
Route::get('/auth/qr/{token}/status', [QrAuthController::class, 'status']);
Route::get('/auth/qr/complete/{token}', [QrAuthController::class, 'complete']);

// WebAuthn login (public)
Route::post('/auth/passkey/login/options', [WebAuthnLoginController::class, 'options']);
Route::post('/auth/passkey/login', [WebAuthnLoginController::class, 'login']);

// === AUTH REQUIRED ===
Route::middleware(['auth'])->group(function () {
    Route::get('/auth/setup-pin', [LoginController::class, 'setupPin'])->name('auth.setup-pin');
    Route::post('/auth/pin/setup', [PinAuthController::class, 'setupPin']);
    Route::post('/auth/pin/biometric', [PinAuthController::class, 'enableBiometric']);
    Route::get('/auth/qr/scan', [QrAuthController::class, 'scan']);
    Route::post('/auth/qr/approve', [QrAuthController::class, 'approve']);
    Route::post('/auth/passkey/register/options', [WebAuthnRegisterController::class, 'options']);
    Route::post('/auth/passkey/register', [WebAuthnRegisterController::class, 'register']);
});
```

### Views (kopieer van SafeHavun, pas styling aan)
- `resources/views/auth/login.blade.php`
- `resources/views/auth/setup-pin.blade.php`
- `resources/views/auth/qr-scan.blade.php`
- `resources/views/layouts/guest.blade.php`

### Models (kopieer van SafeHavun)
- `app/Models/AuthDevice.php`
- `app/Models/QrLoginToken.php`

### Controllers (kopieer van SafeHavun)
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Auth/PinAuthController.php`
- `app/Http/Controllers/Auth/QrAuthController.php`

WebAuthn controllers worden door Laragear package gepublished.

---

## 2. Kritieke Configuratie (bootstrap/app.php)

### CSRF Exceptions
```php
$middleware->validateCsrfTokens(except: [
    'auth/passkey/*',
    'auth/qr/*',
    'auth/biometric/*',
    'auth/pin/*',
]);
```

### JSON Exception Handlers
```php
$exceptions->render(function (AuthenticationException $e, Request $request) {
    if ($request->is('auth/passkey/*') || $request->is('auth/qr/*') || $request->is('auth/pin/*')) {
        return response()->json([
            'success' => false,
            'message' => 'Je moet ingelogd zijn.',
            'error' => 'unauthenticated'
        ], 401);
    }
});
```

Zonder deze configuratie krijg je HTML terug i.p.v. JSON bij verlopen CSRF tokens.

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

### Token-login pattern (voor PIN, passkey, QR)

AJAX login kan geen session cookies betrouwbaar zetten. Daarom: AJAX geeft device_token terug → JavaScript navigeert naar token-login endpoint → server maakt session.

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

**PWA vs browser:** Fingerprint kan verschillen! Check devices op `user_id`, niet alleen fingerprint:
```php
$hasDevice = AuthDevice::where('user_id', $user->id)
    ->where('is_active', true)
    ->where(function ($q) {
        $q->whereNotNull('pin_hash')->orWhere('has_biometric', true);
    })
    ->exists();
```

---

## 5. PIN Login

### Security
- 5 cijfers = 100.000 combinaties
- Gehashed met bcrypt + device ID als salt
- Rate limited: max 5 pogingen per minuut

### Server-side
```php
// PIN opslaan
public function setPin(string $pin): bool
{
    $this->pin_hash = Hash::make($pin . $this->id);
    return $this->save();
}

// PIN verifiëren
public function verifyPin(string $pin): bool
{
    return Hash::check($pin . $this->id, $this->pin_hash);
}

// PIN setup endpoint (auth required)
public function setupPin(Request $request): JsonResponse
{
    $user = Auth::user();
    $device = AuthDevice::findOrCreateForUser($user, $request->fingerprint, $deviceInfo);
    $device->setPin($request->pin);

    return response()->json([
        'success' => true,
        'device_token' => $device->token,
    ]);
}

// PIN login endpoint (public)
public function loginWithPin(Request $request): JsonResponse
{
    $device = AuthDevice::where('device_fingerprint', $request->fingerprint)
        ->where('is_active', true)
        ->first();

    if (!$device || !$device->verifyPin($request->pin)) {
        return response()->json([
            'success' => false,
            'message' => 'Ongeldige PIN',
            'attempts_left' => 5 - $this->getAttemptCount($request),  // Rate limit feedback
        ], 401);
    }

    return response()->json([
        'success' => true,
        'device_token' => $device->token,  // → JavaScript navigeert naar /auth/token-login/{token}
    ]);
}
```

### Numpad Layout
```
┌─────────────────────────────┐
│    [1]    [2]    [3]        │
│    [4]    [5]    [6]        │
│    [7]    [8]    [9]        │
│   [X/Q]   [0]    [⌫]       │
│                             │
│    "Ander account" link     │
└─────────────────────────────┘
```

**Onderste rij — platformafhankelijk:**

| Positie | Desktop | Smartphone PWA |
|---------|---------|----------------|
| Links | QR knop (blauw) | Biometrie knop (paars) |
| Midden | 0 | 0 |
| Rechts | Backspace | Backspace |

**Visuele eisen:**
- Alle 3 knoppen EVEN BREED (`w-16` / `4rem`)
- `0` ALTIJD in het midden
- Links/QR/Biometrie: NOOIT beide tegelijk zichtbaar
- Grid: `grid-cols-3 gap-3`

### Numpad HTML (EXACT)
```html
{{-- Onderste rij: [Biom/QR] [0] [Backspace] --}}
<button type="button" id="qr-btn" onclick="toggleQrModal()"
    class="numpad-btn bg-blue-100 hidden">
    <svg><!-- QR icon --></svg>
</button>
<button type="button" id="biometric-btn" onclick="startBiometric()"
    class="numpad-btn bg-purple-100 hidden">
    <svg><!-- Fingerprint icon --></svg>
</button>

<button type="button" onclick="addPin('0')" class="numpad-btn">0</button>

<button type="button" onclick="removePin()" class="numpad-btn">
    <svg><!-- Backspace icon --></svg>
</button>
```

**FOUT patroon (NIET DOEN):**
```html
{{-- FOUT: wrapper div maakt layout kapot --}}
<div class="relative">
    <button id="qr-btn" class="absolute ...">
    <button id="biometric-btn" class="absolute ...">
</div>
```

### Platform Detectie
```javascript
const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

if (isMobile) {
    document.getElementById('biometric-btn').classList.remove('hidden');
    document.getElementById('qr-btn').classList.add('hidden');
} else {
    document.getElementById('qr-btn').classList.remove('hidden');
    document.getElementById('biometric-btn').classList.add('hidden');
}
```

### Keyboard Support (Desktop)
```javascript
document.addEventListener('keydown', function(e) {
    const pinSection = document.getElementById('pin-login-section');
    if (pinSection.classList.contains('hidden')) return;

    if (e.key >= '0' && e.key <= '9') {
        e.preventDefault();
        addPin(e.key);
    } else if (e.key === 'Backspace') {
        e.preventDefault();
        removePin();
    } else if (e.key === 'Enter' && currentPin.length === 5) {
        e.preventDefault();
        submitPin();
    }
});
```

---

## 6. Biometrie / WebAuthn (Passkey)

### Overzicht
Gebruikt `laragear/webauthn` package. Werkt alleen op mobiel (platform authenticator = vingerafdruk/Face ID).

Twee stappen:
1. **Registratie** — na PIN setup, gebruiker registreert passkey op device
2. **Login** — bij terugkomst, biometrie knop in numpad activeert passkey

### WebAuthn Challenge Storage (KRITIEK voor mobiel)

Mobile browsers (Safari, Chrome) verliezen session cookies bij redirects. Gebruik **DatabaseChallengeRepository** i.p.v. session:

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

### Passkey Registratie Flow (PIN setup → biometrie)

```
setup-pin.blade.php (authenticated)
  1. Gebruiker stelt PIN in → POST /auth/pin/setup → AuthDevice aangemaakt
  2. Biometrie knop verschijnt (alleen mobiel)
  3. setupBiometric() →
     a. POST /auth/passkey/register/options → server stuurt WebAuthn options
     b. navigator.credentials.create() → browser toont vingerafdruk/Face ID prompt
     c. POST /auth/passkey/register → server slaat credential op (Laragear)
     d. POST /auth/pin/biometric → AuthDevice.has_biometric = true
  4. Redirect naar dashboard
```

**Server-side registratie:**
```php
// WebAuthnRegisterController (gepublished door Laragear)
public function options(AttestationRequest $request): Responsable
{
    return $request->fastRegistration()->toCreate();
}

public function register(AttestedRequest $request): Response
{
    $request->save();
    return response()->noContent();  // 204
}
```

**Server-side biometric enable:**
```php
// PinAuthController
public function enableBiometric(Request $request): JsonResponse
{
    $user = Auth::user();
    $device = AuthDevice::findByFingerprint($user->id, $request->fingerprint);

    if (!$device) {
        $device = AuthDevice::findOrCreateForUser($user, $request->fingerprint, $deviceInfo);
    }

    $device->update(['has_biometric' => true]);

    return response()->json(['success' => true]);
}
```

**JavaScript registratie (setup-pin.blade.php):**
```javascript
async function setupBiometric() {
    try {
        // 1. Haal registratie-opties op van server
        const optRes = await csrfFetch('/auth/passkey/register/options', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        });
        const options = await optRes.json();

        // 2. Maak credential aan via WebAuthn API (toont vingerafdruk prompt)
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

        // 3. Stuur credential naar server
        const regRes = await csrfFetch('/auth/passkey/register', {
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

        // 4. Markeer device als biometric-enabled
        if (regRes && regRes.ok) {
            await csrfFetch('/auth/pin/biometric', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ fingerprint: deviceFingerprint }),
            });
            window.location.href = '/';
        }
    } catch (err) {
        // Bij falen: biometrie overslaan, PIN is al ingesteld → redirect naar dashboard
        if (err.name === 'NotAllowedError') {
            showMessage('Biometrie overgeslagen. Je kunt altijd je PIN gebruiken.', 'info');
        } else if (err.name === 'InvalidStateError') {
            showMessage('Er is al een passkey geregistreerd op dit device.', 'info');
        } else {
            showMessage('Biometrie instellen niet gelukt. Je kunt altijd je PIN gebruiken.', 'warning');
        }
        // Na 2 sec redirect naar dashboard (PIN werkt al)
        setTimeout(() => { window.location.href = '/'; }, 2000);
    }
}
```

### Passkey Login Flow

```
login.blade.php (public)
  1. checkDevice() → fingerprint lookup
  2. Als has_biometric → startBiometric() (auto na 500ms op mobiel)
  3. startBiometric() →
     a. POST /auth/passkey/login/options → server stuurt challenge + allowCredentials
     b. navigator.credentials.get() → browser toont vingerafdruk prompt
     c. POST /auth/passkey/login → Laragear valideert → Auth::login()
     d. Server geeft device_token terug
  4. window.location.href = '/auth/token-login/' + device_token
```

**JavaScript login (login.blade.php):**
```javascript
async function startBiometric() {
    try {
        // 1. Haal login opties op
        const optRes = await csrfFetch('/auth/passkey/login/options', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
        });
        const options = await optRes.json();

        // 2. Haal credential op via WebAuthn (toont vingerafdruk prompt)
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

        // 3. Stuur assertion naar server
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
        // KRITIEK: bij ELKE fout → fallback naar andere methodes
        if (err.name === 'NotAllowedError') {
            showFallbackOptions('biometric');  // Geannuleerd → toon PIN + wachtwoord
        } else {
            showFallbackOptions('biometric');  // Server/netwerk error → zelfde fallback
        }
    }
}
```

### Base64url Helpers (VEREIST in beide views)
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

## 7. QR Code Login

### Flow
```
1. Desktop: GET /auth/qr/generate → krijgt token + QR image URL
2. Desktop: pollt GET /auth/qr/{token}/status elke 2 sec
3. Mobiel: scant QR → opent /auth/qr/scan?token=xxx (moet ingelogd zijn)
4. Mobiel: klikt "Goedkeuren" → POST /auth/qr/approve
5. Desktop: ontvangt status "approved" → navigeert naar /auth/qr/complete/{token}
6. Complete endpoint: Auth::guard('web')->login() + session()->save() → dashboard
```

**Belangrijk:**
- QR token verloopt na 5 minuten
- User moet AL ingelogd zijn op telefoon om goed te keuren
- Status + complete endpoints zijn CSRF-exempt
- QR image via `api.qrserver.com`

### OAuth (optioneel)

Google OAuth alleen op desktop tonen (`hidden md:block`). Te traag/onbetrouwbaar op mobiel.

```php
// Altijd stateless mode voor OAuth
return Socialite::driver($provider)->stateless()->redirect();
$socialUser = Socialite::driver($provider)->stateless()->user();
```

---

## 8. Troubleshooting

| Probleem | Oorzaak | Oplossing |
|----------|---------|-----------|
| Login redirect loop | `session()->regenerate()` | Gebruik `session()->save()` |
| HTML i.p.v. JSON response | CSRF exceptions ontbreken | Voeg auth routes toe aan CSRF exceptions |
| Passkey niet herkend | Challenge verloren (session) | Gebruik DatabaseChallengeRepository |
| Device niet gevonden | Fingerprint verschilt (PWA vs browser) | Check op user_id, niet alleen fingerprint |
| OAuth traag op mobiel | Session state validatie | Gebruik stateless mode, verberg op mobiel |
| Biometrie doet niks | Geen HTTPS | WebAuthn vereist HTTPS |
| QR verlopen | Token > 5 min oud | Vernieuw knop in QR modal |
| Gebruiker zit vast | Geen fallback opties | Toon altijd "Ander account" + alternatieve methodes |
| PIN rate limited | 5 pogingen/min bereikt | Toon "Probeer wachtwoord" + wachttijd |

## 9. Implementatie Checklist

- [ ] `composer require laragear/webauthn` + publish
- [ ] Migrations: auth_devices, qr_login_tokens, webauthn_challenges
- [ ] CSRF exceptions in `bootstrap/app.php`
- [ ] JSON exception handlers in `bootstrap/app.php`
- [ ] DatabaseChallengeRepository voor WebAuthn
- [ ] Token-login route + controller method
- [ ] `session()->save()` in ALLE login methods (GEEN regenerate)
- [ ] Device check op user_id + fingerprint
- [ ] Keyboard support voor PIN (desktop)
- [ ] QR knop op desktop, biometrie knop op mobiel
- [ ] Base64url helpers in beide views
- [ ] OAuth stateless mode + verbergen op mobiel
- [ ] Fallback opties bij falen (biometrie → PIN, PIN → wachtwoord, etc.)
- [ ] "Ander account" link altijd zichtbaar onder numpad
- [ ] Error messages tonen beschikbare alternatieven

## Project Status (maart 2026)

| Project | Wachtwoord | PIN | QR | Biometrie | Compleet? |
|---------|:---:|:---:|:---:|:---:|:---:|
| JudoToernooi | ✅ | ✅ | ✅ | ✅ | ✅ |
| SafeHavun | ✅ | ✅ | ✅ | ✅ | ✅ |
| Herdenkingsportaal | ✅ | ✅ | ❌ | ✅ | QR mist |
| HavunAdmin | ✅ | ❌ | ✅ | ✅ | PIN mist |
| Infosyst | ✅ | ✅ | ✅ | ❌ | Biometrie mist |
| HavunClub | ✅ | ❌ | ❌ | ❌ | Alleen wachtwoord |

**Standaard:** Elk project MOET alle 4 methodes hebben (wachtwoord, PIN, QR, biometrie).

**Login page keuze:** Gebruiker kiest methode op login pagina:
- **PC:** Wachtwoord, PIN, QR code
- **Smartphone/PWA:** Wachtwoord, PIN, Biometrie
- **Tablet/iPad:** Wachtwoord, PIN, QR code (biometrie alleen op nieuwste modellen)

## Referentie Implementaties

| Project | Locatie | Status |
|---------|---------|--------|
| SafeHavun | `D:\GitHub\SafeHavun` | Compleet (alle 4 methodes) |
| JudoToernooi | `D:\GitHub\Judotoernooi\laravel` | Compleet (alle 4 methodes) |
| Herdenkingsportaal | `D:\GitHub\Herdenkingsportaal` | Mist QR |
| HavunAdmin | `D:\GitHub\HavunAdmin` | Mist PIN |
| Infosyst | `D:\GitHub\infosyst` | Mist Biometrie |
| HavunClub | `D:\GitHub\HavunClub` | Mist PIN, QR, Biometrie |
