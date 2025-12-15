# Unified Login System

> **Status:** Geimplementeerd in Herdenkingsportaal
> **Versie:** 1.0
> **Laatste update:** 7 december 2025

## Overzicht

Een vereenvoudigd login systeem voor alle Havun apps met:
- PIN code (5 cijfers) per device
- Biometrische login (passkey/WebAuthn)
- QR code login (desktop via smartphone)
- Wachtwoord als fallback

## Architectuur

```
┌─────────────────────────────────────────────────────────┐
│                    LOGIN FLOW                           │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  1. Device check (fingerprint)                          │
│     ↓                                                   │
│  2a. Bekend device → Toon numpad + biometrisch         │
│  2b. Onbekend device → Toon wachtwoord form            │
│     ↓                                                   │
│  3. Na wachtwoord login → PIN setup pagina              │
│     ↓                                                   │
│  4. Volgende keer → Direct numpad                       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

## Database

### Tabel: auth_devices

```sql
CREATE TABLE auth_devices (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    pin_hash VARCHAR(255) NULL,           -- PIN gehashed met device id
    has_biometric BOOLEAN DEFAULT FALSE,
    device_fingerprint VARCHAR(64) NULL,
    device_name VARCHAR(255) NULL,
    browser VARCHAR(50) NULL,
    os VARCHAR(50) NULL,
    ip_address VARCHAR(45) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX (device_fingerprint),
    INDEX (token, is_active, expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Migratie

```php
Schema::table('auth_devices', function (Blueprint $table) {
    $table->string('pin_hash')->nullable()->after('token');
    $table->boolean('has_biometric')->default(false)->after('pin_hash');
    $table->string('device_fingerprint', 64)->nullable()->after('has_biometric');
    $table->index('device_fingerprint');
});
```

## Model: AuthDevice

Key methods:

```php
// PIN instellen (gehashed met device id als salt)
$device->setPin('12345');

// PIN verifiëren
$device->verifyPin('12345'); // returns bool

// Check of PIN is ingesteld
$device->hasPin(); // returns bool

// Find device by fingerprint
AuthDevice::findActiveByFingerprint($fingerprint);

// Find or create device
AuthDevice::findOrCreateForUser($user, $fingerprint, $deviceInfo);
```

## API Endpoints

### Geen auth vereist

| Method | Route | Functie |
|--------|-------|---------|
| POST | `/auth/pin/check-device` | Check of device PIN heeft |
| POST | `/auth/pin/login` | Login met PIN |

### Auth vereist

| Method | Route | Functie |
|--------|-------|---------|
| GET | `/auth/setup-pin` | PIN setup pagina |
| POST | `/auth/pin/setup` | PIN instellen |
| POST | `/auth/pin/biometric` | Biometrisch markeren |
| POST | `/auth/pin/remove` | Device verwijderen |

## Device Fingerprint

Client-side gegenereerd via SHA-256 hash van:
- User agent
- Language
- Screen resolution
- Color depth
- Timezone offset
- Hardware concurrency
- Platform

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

    const encoder = new TextEncoder();
    const dataBuffer = encoder.encode(data);
    const hashBuffer = await crypto.subtle.digest('SHA-256', dataBuffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
}
```

## PIN Security

- 5 cijfers = 100.000 combinaties
- Gehashed met bcrypt + device ID als salt
- Rate limited: max 5 pogingen per minuut
- Na 5 fouten: 60 seconden wachten

```php
// Hash opslaan
$hash = Hash::make($pin . $device->id);

// Verifiëren
Hash::check($inputPin . $device->id, $storedHash);
```

## Implementatie per Project

### Bestanden kopiëren

1. **Migration:** `database/migrations/2025_12_07_120000_add_pin_to_auth_devices_table.php`
2. **Model uitbreiden:** `app/Models/AuthDevice.php` - voeg PIN methods toe
3. **Controller:** `app/Http/Controllers/Auth/PinAuthController.php`
4. **Views:**
   - `resources/views/auth/login.blade.php` - nieuwe login UI
   - `resources/views/auth/setup-pin.blade.php` - PIN setup flow

### Routes toevoegen

```php
use App\Http\Controllers\Auth\PinAuthController;

// PIN Login Routes
Route::post('/auth/pin/check-device', [PinAuthController::class, 'checkDevice']);
Route::post('/auth/pin/login', [PinAuthController::class, 'loginWithPin']);

Route::middleware(['auth'])->group(function () {
    Route::get('/auth/setup-pin', fn() => view('auth.setup-pin'))->name('auth.setup-pin');
    Route::post('/auth/pin/setup', [PinAuthController::class, 'setupPin']);
    Route::post('/auth/pin/biometric', [PinAuthController::class, 'enableBiometric']);
    Route::post('/auth/pin/remove', [PinAuthController::class, 'removeDevice']);
});
```

### Login controller aanpassen

```php
// In AuthenticatedSessionController::store()
if ($request->has('setup_pin') && $request->input('setup_pin') === '1') {
    return redirect()->route('auth.setup-pin');
}
```

## Troubleshooting

### PIN login werkt niet

1. Check of device fingerprint stabiel is (niet bij elke request anders)
2. Check rate limiting - max 5 pogingen per minuut
3. Check of auth_devices tabel de nieuwe kolommen heeft

### Biometrisch werkt niet

1. Moet HTTPS zijn (localhost uitgezonderd)
2. Check of passkey correct geregistreerd is in webauthn_credentials
3. Safari heeft specifieke WebAuthn quirks

### Device niet herkend na browser update

- Device fingerprint kan veranderen bij browser/OS updates
- User moet opnieuw PIN instellen via wachtwoord login

## Gerelateerde docs

- [Passkey Mobile Fix](../runbooks/passkey-mobile-fix.md)
- [QR Login CSRF Fix](../runbooks/fix-qr-login-csrf.md)
- [Token Based Login](../runbooks/token-based-login.md)
