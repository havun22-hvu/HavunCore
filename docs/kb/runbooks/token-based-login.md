# Token-Based Login Pattern

## Probleem

AJAX-based login (passkey/biometric) kan sessies aanmaken, maar cookies worden niet correct bewaard bij redirect na AJAX response. Dit veroorzaakt login loops.

## Symptomen

- Passkey validatie succesvol (logs tonen "PASSKEY LOGIN - Success")
- Na redirect terug naar login pagina in plaats van dashboard
- `SESSION_SAME_SITE` wijzigen helpt niet
- `session()->regenerate()` na `Auth::login()` breekt cookies

## Oplossing: Token Exchange Pattern

```
[AJAX Request] → Passkey validatie → Return device_token
                                            ↓
[Browser navigeert] → /auth/token-login/{token} → Auth::login() → Dashboard
```

### 1. AuthDevice Model

```php
// app/Models/AuthDevice.php
class AuthDevice extends Model
{
    public static function createForUser(User $user, array $deviceInfo): self
    {
        return self::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(30),
            // ... device info
        ]);
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token', $token)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();
    }
}
```

### 2. Controller: Return Token (niet sessie)

```php
public function login(AssertedRequest $request): JsonResponse
{
    // Valideer credential...
    $device = AuthDevice::createForUser($user, $deviceInfo);

    return response()->json([
        'success' => true,
        'device_token' => $device->token,
    ]);
}

public function tokenLogin(string $token)
{
    $device = AuthDevice::findByToken($token);
    if (!$device) {
        return redirect()->route('login')->with('error', 'Ongeldige link');
    }

    Auth::login($device->user, true);
    return redirect()->intended('/dashboard');
}
```

### 3. Frontend: Navigate (niet fetch)

```javascript
if (loginData.success && loginData.device_token) {
    // NIET: fetch of AJAX redirect
    // WEL: echte browser navigatie
    window.location.href = '/auth/token-login/' + loginData.device_token;
}
```

### 4. Route

```php
Route::get('/auth/token-login/{token}', [PasskeyController::class, 'tokenLogin']);
```

## Waarom dit werkt

- AJAX requests kunnen geen betrouwbare session cookies zetten
- Een normale GET request (browser navigatie) kan dat wel
- Token is kort-levend en eenmalig bruikbaar
- Dezelfde pattern als HavunCore webapp

## Gerelateerd

- `docs/kb/decisions/auth-same-origin.md` - Hou authenticatie in hetzelfde domein
