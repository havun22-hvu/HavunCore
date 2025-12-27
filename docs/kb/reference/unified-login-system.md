# Unified Login System

> **Status:** Geimplementeerd in Herdenkingsportaal, SafeHavun
> **Versie:** 2.0
> **Laatste update:** 26 december 2025

## Overzicht

Standaard login systeem voor alle Havun apps met:
- **Registratie** - account aanmaken met email/wachtwoord
- PIN code (5 cijfers) per device - PC & mobiel
- Biometrische login (passkey/WebAuthn) - alleen mobiel
- QR code login (PC toont QR, mobiel scant) - alleen PC
- Wachtwoord als fallback

**BELANGRIJK:** Login zonder registratie werkt niet! Altijd registratie functie meenemen.

| Platform | PIN | Biometrie | QR |
|----------|-----|-----------|-----|
| PC webapp | ✅ | ❌ | ✅ (toont QR) |
| Smartphone PWA | ✅ | ✅ | ✅ (scant QR) |

## Architectuur

```
┌─────────────────────────────────────────────────────────┐
│                    LOGIN FLOW                           │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  0. Nieuw? → Registreer (email/wachtwoord)             │
│     ↓                                                   │
│  1. Device check (fingerprint)                          │
│     ↓                                                   │
│  2a. Bekend device → Toon numpad                        │
│      - Desktop: + QR knop                               │
│      - Mobiel: + biometrie knop                         │
│  2b. Onbekend device → Toon wachtwoord form            │
│      + link naar registratie                            │
│     ↓                                                   │
│  3. Na wachtwoord/registratie login → PIN setup         │
│     ↓                                                   │
│  4. Volgende keer → Direct numpad                       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

## Installatie

### 1. Composer package

```bash
composer require laragear/webauthn
php artisan vendor:publish --provider="Laragear\WebAuthn\WebAuthnServiceProvider"
```

### 2. Database migrations

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

### 3. Models

**AuthDevice.php** - Kopieer van SafeHavun:
- `D:\GitHub\SafeHavun\app\Models\AuthDevice.php`

**QrLoginToken.php** - Kopieer van SafeHavun:
- `D:\GitHub\SafeHavun\app\Models\QrLoginToken.php`

**User.php** - Voeg WebAuthn trait toe:
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

### 4. Controllers

Kopieer van SafeHavun:
- `app/Http/Controllers/Auth/PinAuthController.php`
- `app/Http/Controllers/Auth/QrAuthController.php`
- `app/Http/Controllers/Auth/LoginController.php`

WebAuthn controllers worden door package gepublished.

### 5. Routes

```php
// Auth Routes (public)
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// PIN Auth (public)
Route::post('/auth/pin/check-device', [PinAuthController::class, 'checkDevice']);
Route::post('/auth/pin/login', [PinAuthController::class, 'loginWithPin']);

// QR Auth (public)
Route::get('/auth/qr/generate', [QrAuthController::class, 'generate']);
Route::get('/auth/qr/{token}/status', [QrAuthController::class, 'status']);

// WebAuthn (public)
Route::post('/auth/passkey/login/options', [WebAuthnLoginController::class, 'options']);
Route::post('/auth/passkey/login', [WebAuthnLoginController::class, 'login']);

// Auth required
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

### 6. Views

Kopieer van SafeHavun en pas styling aan:
- `resources/views/auth/login.blade.php`
- `resources/views/auth/setup-pin.blade.php`
- `resources/views/auth/qr-scan.blade.php`
- `resources/views/layouts/guest.blade.php`

## Device Fingerprint

Client-side gegenereerd via SHA-256:

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

## PIN Security

- 5 cijfers = 100.000 combinaties
- Gehashed met bcrypt + device ID als salt
- Rate limited: max 5 pogingen per minuut

```php
// Hash opslaan
$hash = Hash::make($pin . $device->id);

// Verifiëren
Hash::check($inputPin . $device->id, $storedHash);
```

## QR Login Flow

1. PC genereert QR token via `/auth/qr/generate`
2. PC pollt `/auth/qr/{token}/status` elke 2 sec
3. Mobiel scant QR → opent `/auth/qr/scan?token=xxx`
4. Mobiel (ingelogd) bevestigt via `/auth/qr/approve`
5. PC ontvangt `approved` status → redirect naar dashboard

## Referentie Implementaties

| Project | Locatie |
|---------|---------|
| SafeHavun | `D:\GitHub\SafeHavun` |
| Herdenkingsportaal | `D:\GitHub\Herdenkingsportaal` |

## Troubleshooting

### PIN login werkt niet
1. Check device fingerprint stabiliteit
2. Check rate limiting (5 pogingen/min)
3. Check auth_devices tabel

### Biometrie werkt niet
1. Moet HTTPS zijn
2. Check webauthn_credentials tabel
3. Alleen mobiel ondersteund

### QR verlopen
- Default 5 minuten geldig
- Vernieuw knop in modal

## Gerelateerde docs

- [Passkey Mobile Fix](../runbooks/passkey-mobile-fix.md)
- [QR Login CSRF Fix](../runbooks/fix-qr-login-csrf.md)
