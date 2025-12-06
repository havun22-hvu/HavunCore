# Fix: QR Login "Onverwacht antwoord van server"

## Symptoom
- QR code wordt gegenereerd op desktop
- Telefoon scant QR code
- Error: "Server gaf onverwacht antwoord. Probeer opnieuw."
- Biometrische login werkt WEL

## Oorzaak
De QR login routes (`/auth/qr/*`) waren niet uitgezonderd van Laravel's CSRF middleware. Wanneer de telefoon een POST request doet naar `/auth/qr/approve/{token}`, faalt de CSRF check en geeft Laravel een HTML redirect terug in plaats van JSON.

## Diagnose

### 1. Check logs voor QR activiteit
```bash
ssh root@188.245.159.115 "grep -i 'qr' /var/www/herdenkingsportaal/staging/storage/logs/laravel.log | tail -20"
```

### 2. Test approve endpoint direct
```bash
ssh root@188.245.159.115 "curl -s -X POST 'https://staging.herdenkingsportaal.nl/auth/qr/approve/testtoken' -H 'Accept: application/json'"
```

**Fout:** HTML redirect response
**Goed:** `{"success":false,"message":"Token verlopen"}`

### 3. Check CSRF exceptions in bootstrap/app.php
```php
$middleware->validateCsrfTokens(except: [
    'payments/webhook',
    'auth/passkey/*',
    // auth/qr/* ONTBREEKT!
]);
```

## Oplossing

### 1. Voeg QR routes toe aan CSRF exceptions

**File:** `bootstrap/app.php`

```php
$middleware->validateCsrfTokens(except: [
    'payments/webhook',
    'auth/passkey/*',
    'auth/qr/*',        // <-- TOEVOEGEN
    'auth/biometric/*', // <-- TOEVOEGEN
]);
```

### 2. Voeg JSON exception handling toe voor QR routes

```php
$exceptions->render(function (AuthenticationException $e, \Illuminate\Http\Request $request) {
    if ($request->is('auth/passkey/*') || $request->is('auth/qr/*')) {  // <-- QR toevoegen
        return response()->json([
            'success' => false,
            'message' => 'Je moet ingelogd zijn.',
            'error' => 'unauthenticated'
        ], 401);
    }
});
```

### 3. Deploy en test

```bash
# Deploy
ssh root@188.245.159.115 "cd /var/www/herdenkingsportaal/staging && git pull origin main && php artisan config:clear && php artisan cache:clear"

# Test
ssh root@188.245.159.115 "curl -s -X POST 'https://staging.herdenkingsportaal.nl/auth/qr/approve/test' -H 'Accept: application/json'"
# Verwacht: {"success":false,"message":"Token verlopen"}
```

## QR Login Flow

```
Desktop                          Telefoon (PWA)
   |                                  |
   |-- POST /auth/qr/generate ------->|
   |<-- {token, approve_url} ---------|
   |                                  |
   |-- Show QR code (approve_url) --->|
   |                                  |
   |-- Poll /auth/qr/{token}/status ->|
   |                                  |
   |                    Scan QR code -|
   |                                  |
   |          POST /auth/qr/approve/{token}
   |                    (CSRF EXCLUDED!)
   |                                  |
   |<-- status: approved -------------|
   |                                  |
   |-- GET /auth/qr/complete/{token} -|
   |-- Session created, redirect ---->|
```

## Gerelateerde bestanden
- `bootstrap/app.php` - CSRF exceptions
- `app/Http/Controllers/Auth/PasskeyController.php` - QR endpoints
- `resources/views/auth/login.blade.php` - Desktop QR generatie
- `resources/views/auth/qr-scanner.blade.php` - Telefoon scanner
- `resources/views/auth/qr-approve.blade.php` - Approve pagina

## Datum
6 december 2025 - Herdenkingsportaal v3.0.49
