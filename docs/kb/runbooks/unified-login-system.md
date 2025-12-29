# Unified Login System - Complete Runbook

> **Status:** Production-ready (v3.0.61)
> **Getest op:** Herdenkingsportaal (december 2025)
> **Toepasbaar op:** HavunAdmin, HavunCore, Judo Toernooi

## Overzicht Login Methoden

| Methode | Platform | Beschrijving |
|---------|----------|--------------|
| PIN login | Alle | 5-cijferige PIN via numpad + keyboard |
| Biometric | Mobile | Passkey/WebAuthn via vingerafdruk/Face ID |
| QR code | Desktop | Scan met ingelogde telefoon |
| OAuth (Google) | Desktop only | Te traag/onbetrouwbaar op mobile |
| Wachtwoord | Alle | Fallback voor nieuwe devices |

## Kritieke Lessen & Fixes

### 1. Session Cookie Issues (BELANGRIJKSTE!)

**Probleem:** Gebruiker blijft hangen op login pagina na succesvolle login.

**Symptomen:**
- PIN login succesvol (server zegt OK) maar user blijft op login page
- Passkey/biometrie werkt maar redirect faalt
- QR login approved maar desktop blijft hangen

**Oorzaak:** `session()->regenerate()` breekt cookies na OAuth/token login.

**Oplossing (ALLE login methods):**
```php
// FOUT - breekt session cookies
Auth::login($user, true);
session()->regenerate();
return redirect('/dashboard');

// GOED - werkt correct
Auth::guard('web')->login($user, true);
session()->save();  // KRITIEK: save VOOR redirect
return redirect()->intended('/dashboard');
```

**Checklist voor elke login method:**
- [ ] `Auth::guard('web')->login()` (niet `Auth::login()`)
- [ ] `session()->save()` NA login, VOOR redirect
- [ ] GEEN `session()->regenerate()`
- [ ] `redirect()->intended()` voor correcte doorverwijzing
- [ ] Check `Auth::guard('web')->check()` voordat je redirect

**Token-login pattern (voor PIN/passkey/QR):**
```php
// Controller: tokenLogin($token)
public function tokenLogin(string $token)
{
    $device = AuthDevice::where('token', $token)
        ->where('is_active', true)
        ->firstOrFail();

    Auth::guard('web')->login($device->user, true);

    $device->update(['last_used_at' => now()]);

    session()->save();  // KRITIEK!

    return redirect()->intended(route('dashboard'));
}
```

### 2. CSRF Token Exceptions

**Probleem:** PIN/passkey/QR endpoints geven HTML terug i.p.v. JSON.

**Oorzaak:** CSRF token verlopen, Laravel redirect naar login met HTML.

**Oplossing in `bootstrap/app.php`:**
```php
$middleware->validateCsrfTokens(except: [
    'payments/webhook',
    'auth/passkey/*',
    'auth/qr/*',
    'auth/biometric/*',
    'auth/pin/*',
]);
```

### 3. OAuth Stateless Mode

**Probleem:** Google OAuth traag en onbetrouwbaar op mobile/PWA.

**Oplossing:**
```php
// Stateless mode - geen session state validatie
return Socialite::driver($provider)->stateless()->redirect();

// Callback ook stateless
$socialUser = Socialite::driver($provider)->stateless()->user();
```

**Besluit:** Google OAuth alleen op desktop tonen (`hidden md:block`).

### 4. Device Fingerprint Mismatch

**Probleem:** PWA fingerprint verschilt van browser fingerprint.

**Oplossing:** Check devices op user_id, niet alleen fingerprint:
```php
// Voor bestaande gebruikers: check ANY device by user_id
$hasDevice = AuthDevice::where('user_id', $user->id)
    ->where('is_active', true)
    ->where(function ($q) {
        $q->whereNotNull('pin_hash')->orWhere('has_biometric', true);
    })
    ->exists();
```

### 5. Token-Based Login Pattern

**Probleem:** Direct session login werkt niet altijd na passkey/biometric.

**Oplossing:** Gebruik device token als tussenstap:
```php
// 1. Passkey login geeft device_token terug
return response()->json([
    'success' => true,
    'device_token' => $device->token,
]);

// 2. JavaScript redirect naar token-login endpoint
window.location.href = '/auth/token-login/' + data.device_token;

// 3. Token-login endpoint maakt session
public function tokenLogin(string $token)
{
    $device = AuthDevice::findByToken($token);
    Auth::guard('web')->login($device->user, true);
    session()->save();
    return redirect()->route('dashboard');
}
```

### 6. JSON Response Exception Handlers

**Probleem:** Auth errors geven HTML redirect i.p.v. JSON voor API calls.

**Oplossing in `bootstrap/app.php`:**
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

## Numpad Layout

```
┌─────────────────────────────┐
│    [1]    [2]    [3]        │
│    [4]    [5]    [6]        │
│    [7]    [8]    [9]        │
│    [X]    [0]    [⌫]        │
│                             │
│    "Ander account" link     │
└─────────────────────────────┘
```

### Onderste rij specs (KRITIEK!)

| Positie | Desktop | Smartphone PWA |
|---------|---------|----------------|
| **Links** | QR knop (blauw) | Biometrie knop (paars) |
| **Midden** | 0 | 0 |
| **Rechts** | Backspace ⌫ | Backspace ⌫ |

**Visuele eisen:**
- Alle 3 knoppen EVEN BREED (w-16 / 4rem)
- 0 ALTIJD in het midden
- Backspace met ⌫ icoon (niet tekst)
- Links/QR/Biometrie: NOOIT beide tegelijk zichtbaar
- Grid: `grid-cols-3 gap-3`

### Platform detectie (EXACT zo implementeren)

```javascript
// CORRECT - alleen userAgent check
const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

if (isMobile) {
    // Smartphone PWA: toon biometrie, VERBERG qr
    document.getElementById('biometric-btn').classList.remove('hidden');
    document.getElementById('qr-btn').classList.add('hidden');
} else {
    // Desktop: toon QR, VERBERG biometrie
    document.getElementById('qr-btn').classList.remove('hidden');
    document.getElementById('biometric-btn').classList.add('hidden');
}
```

### HTML structuur onderste rij (EXACT)

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
    <svg><!-- Backspace ⌫ icon --></svg>
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

## Keyboard Support (Desktop)

```javascript
document.addEventListener('keydown', function(e) {
    const pinSection = document.getElementById('pin-login-section');
    if (pinSection.classList.contains('hidden')) return;

    if ((e.key >= '0' && e.key <= '9')) {
        e.preventDefault();
        addPin(e.key);
    }
    else if (e.key === 'Backspace') {
        e.preventDefault();
        removePin();
    }
    else if (e.key === 'Enter' && currentPin.length === 5) {
        e.preventDefault();
        submitPin();
    }
});
```

## QR Code Login Flow

1. Desktop toont QR code (via api.qrserver.com)
2. User scant met telefoon waarop al ingelogd
3. Telefoon opent `/auth/qr/approve/{token}` pagina
4. User klikt "Goedkeuren"
5. Desktop pollt `/auth/qr/{token}/status` elke 2 sec
6. Bij status `approved`: redirect naar `/auth/qr/complete/{token}`
7. Complete endpoint logt user in en redirect naar dashboard

**Belangrijk:**
- QR token verloopt na 5 minuten
- User moet AL ingelogd zijn op telefoon om goed te keuren
- Status endpoint is CSRF-exempt

## Device Fingerprint Generatie

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

## Checklist voor Nieuwe Implementatie

- [ ] CSRF exceptions voor auth routes in `bootstrap/app.php`
- [ ] JSON exception handlers voor auth routes
- [ ] Stateless OAuth mode
- [ ] `session()->save()` i.p.v. `session()->regenerate()` na login
- [ ] Token-based login voor passkey/biometric
- [ ] Device check op user_id + fingerprint (niet alleen fingerprint)
- [ ] Google OAuth verbergen op mobile (`hidden md:block`)
- [ ] Keyboard support voor PIN op desktop
- [ ] QR knop op desktop, biometric knop op mobile

## Gerelateerde Bestanden

### Herdenkingsportaal
- `bootstrap/app.php` - CSRF exceptions, JSON handlers
- `app/Http/Controllers/Auth/PasskeyController.php` - Token login, QR flow
- `app/Http/Controllers/Auth/PinAuthController.php` - PIN verificatie
- `app/Http/Controllers/Auth/SocialiteController.php` - OAuth flow
- `app/Models/AuthDevice.php` - Device management
- `resources/views/auth/login.blade.php` - Login UI + JavaScript
- `resources/views/auth/setup-pin.blade.php` - PIN setup

## Debugging Tips

1. **Login redirect loop:** Check `session()->regenerate()` calls
2. **HTML i.p.v. JSON:** Check CSRF exceptions
3. **Passkey niet herkend:** Log credential IDs in `loginOptions()`
4. **Device niet gevonden:** Log fingerprint in console + server
5. **OAuth traag:** Gebruik stateless mode, of verberg op mobile
