# QR + Device Trust Auth Setup Guide

Deze guide legt uit hoe je HavunCore's QR login systeem integreert in je Laravel project.

## Overzicht

Het systeem biedt:
- **QR Code Login** - Scan met telefoon om in te loggen op desktop
- **Device Trust** - Devices worden 30 dagen onthouden
- **Password Fallback** - Optionele wachtwoord login
- **Centrale Auth** - Eén account voor alle Havun sites

## Snelle Setup (5 minuten)

### 1. Kopieer de bestanden

```bash
# Vanuit je project root
cp /path/to/HavunCore/stubs/havun-auth-config.php config/havun-auth.php
cp /path/to/HavunCore/stubs/HavunAuthMiddleware.php app/Http/Middleware/HavunAuthMiddleware.php
cp /path/to/HavunCore/stubs/HavunAuthController.php app/Http/Controllers/HavunAuthController.php
mkdir -p resources/views/auth
cp /path/to/HavunCore/stubs/views/auth/login.blade.php resources/views/auth/login.blade.php
```

### 2. Voeg toe aan .env

```env
# HavunCore Auth
HAVUNCORE_AUTH_URL=https://havuncore.havun.nl
HAVUNCORE_QR_ENABLED=true
HAVUNCORE_PASSWORD_ENABLED=true
HAVUNCORE_TRUST_DAYS=30
HAVUNCORE_REDIRECT=/dashboard
```

### 3. Registreer de middleware

**Laravel 11 (bootstrap/app.php):**
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'havun.auth' => \App\Http\Middleware\HavunAuthMiddleware::class,
    ]);
})
```

**Laravel 10 en eerder (app/Http/Kernel.php):**
```php
protected $middlewareAliases = [
    // ... andere middleware
    'havun.auth' => \App\Http\Middleware\HavunAuthMiddleware::class,
];
```

### 4. Voeg routes toe aan routes/web.php

```php
use App\Http\Controllers\HavunAuthController;

// Auth routes
Route::get('/login', [HavunAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [HavunAuthController::class, 'login'])->name('havun.login');
Route::post('/auth/qr/generate', [HavunAuthController::class, 'generateQr']);
Route::get('/auth/qr/{code}/status', [HavunAuthController::class, 'checkQrStatus']);
Route::get('/auth/qr/{code}/complete', [HavunAuthController::class, 'checkQrStatus']);
Route::match(['get', 'post'], '/logout', [HavunAuthController::class, 'logout'])->name('logout');

// Beschermde routes
Route::middleware('havun.auth')->group(function () {
    Route::get('/dashboard', function () {
        $user = request()->attributes->get('havun_user');
        return view('dashboard', compact('user'));
    });
});
```

### 5. Clear cache

```bash
php artisan config:clear
php artisan route:clear
```

## Klaar!

Ga naar `/login` om het te testen.

---

## Hoe het werkt

### QR Login Flow

```
1. Desktop opent /login
   → Toont QR code

2. Telefoon (met HavunCore app) scant QR
   → "Wil je Chrome Windows toegang geven?"
   → Bevestig

3. Desktop automatisch ingelogd
   → Cookie wordt gezet
   → Redirect naar /dashboard
```

### Device Trust

- Na eerste login wordt device 30 dagen onthouden
- Cookie: `havun_device_token`
- Automatisch verlengen bij gebruik

### User Data in Controllers

```php
public function dashboard(Request $request)
{
    $user = $request->attributes->get('havun_user');

    // $user = [
    //     'id' => 1,
    //     'name' => 'Henk',
    //     'email' => 'henk@havun.nl',
    //     'is_admin' => true,
    // ]

    return view('dashboard', compact('user'));
}
```

---

## Configuratie Opties

| Optie | Default | Beschrijving |
|-------|---------|--------------|
| `HAVUNCORE_AUTH_URL` | `https://havuncore.havun.nl` | HavunCore API URL |
| `HAVUNCORE_QR_ENABLED` | `true` | QR login aan/uit |
| `HAVUNCORE_PASSWORD_ENABLED` | `true` | Wachtwoord login aan/uit |
| `HAVUNCORE_TRUST_DAYS` | `30` | Hoe lang device onthouden |
| `HAVUNCORE_REDIRECT` | `/dashboard` | Redirect na login |
| `HAVUNCORE_COOKIE_NAME` | `havun_device_token` | Cookie naam |
| `HAVUNCORE_COOKIE_DOMAIN` | `null` | Cookie domain (voor subdomain sharing) |

---

## Gebruikers Beheren

Gebruikers worden beheerd via HavunCore. Eerste gebruiker registreren:

```bash
curl -X POST https://havuncore.havun.nl/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "henk@havun.nl",
    "name": "Henk",
    "password": "secure_password"
  }'
```

---

## API Endpoints

De auth API draait op HavunCore:

| Endpoint | Method | Beschrijving |
|----------|--------|--------------|
| `/api/auth/qr/generate` | POST | Genereer QR sessie |
| `/api/auth/qr/{code}/status` | GET | Check QR status |
| `/api/auth/qr/{code}/approve` | POST | Keur QR goed (mobiel) |
| `/api/auth/login` | POST | Password login |
| `/api/auth/logout` | POST | Logout |
| `/api/auth/verify` | POST | Verify device token |
| `/api/auth/devices` | GET | List trusted devices |
| `/api/auth/devices/{id}` | DELETE | Revoke device |

---

## Troubleshooting

### "Unauthenticated" na inloggen
- Check of cookie wordt gezet (DevTools > Application > Cookies)
- Check of `HAVUNCORE_AUTH_URL` correct is
- Check of HavunCore API bereikbaar is

### QR code laadt niet
- Check console voor errors
- Check of HavunCore API CORS headers heeft
- Test met: `curl https://havuncore.havun.nl/api/health`

### Device niet onthouden
- Check of secure cookies werken (HTTPS vereist in productie)
- Check of cookie domain correct is

---

## Beveiliging

1. **HTTPS verplicht** in productie
2. **Device tokens** verlopen na 30 dagen inactiviteit
3. **QR codes** verlopen na 5 minuten
4. **IP logging** voor audit trail
5. **Device revoke** mogelijk via instellingen
