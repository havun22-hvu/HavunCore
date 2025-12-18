# Biometrische Login (Face ID / Vingerafdruk)

**Geïmplementeerd:** 2025-12-01
**Status:** ✅ LIVE op alle projecten

---

## Overzicht

Passwordless login via WebAuthn/Passkeys voor alle Havun webapps op smartphones.

### Ondersteunde Apps
- https://havuncore.havun.nl (webapp)
- https://havunadmin.havun.nl
- https://herdenkingsportaal.nl

### Flow
1. **Desktop** toont QR code op login pagina
2. **Mobiel** (ingelogd) scant QR → desktop wordt ingelogd
3. **Mobiel** kan ook direct inloggen met Face ID/vingerafdruk

---

## Architectuur

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│  havuncore.nl   │     │  havunadmin.nl   │     │ herdenkings-    │
│  (React webapp) │     │  (Laravel)       │     │ portaal.nl      │
└────────┬────────┘     └────────┬─────────┘     └────────┬────────┘
         │                       │                        │
         └───────────────────────┼────────────────────────┘
                                 │
                    ┌────────────▼────────────┐
                    │   HavunCore API         │
                    │   /api/auth/webauthn/*  │
                    │                         │
                    │   - register-options    │
                    │   - register            │
                    │   - login-options       │
                    │   - login               │
                    │   - credentials         │
                    └─────────────────────────┘
```

---

## Database Schema

### webauthn_credentials
```sql
CREATE TABLE webauthn_credentials (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES auth_users(id),
    credential_id VARCHAR(512) UNIQUE,
    public_key TEXT,
    name VARCHAR(255),
    counter INT DEFAULT 0,
    transports JSON,
    device_type VARCHAR(50),
    last_used_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### webauthn_challenges
```sql
CREATE TABLE webauthn_challenges (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES auth_users(id),
    challenge VARCHAR(128) UNIQUE,
    type VARCHAR(20),  -- 'register' or 'login'
    expires_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## API Endpoints

### Registratie (vereist authenticatie)

**GET /api/auth/webauthn/register-options**
```bash
curl -H "Authorization: Bearer {token}" \
  "https://havuncore.havun.nl/api/auth/webauthn/register-options"
```

**POST /api/auth/webauthn/register**
```bash
curl -X POST -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"credential": {...}, "name": "iPhone"}' \
  "https://havuncore.havun.nl/api/auth/webauthn/register"
```

### Login (geen authenticatie nodig)

**GET /api/auth/webauthn/login-options**
```bash
curl "https://havuncore.havun.nl/api/auth/webauthn/login-options"
```

**POST /api/auth/webauthn/login**
```bash
curl -X POST -H "Content-Type: application/json" \
  -d '{"credential": {...}}' \
  "https://havuncore.havun.nl/api/auth/webauthn/login"
```

Response:
```json
{
  "success": true,
  "device_token": "xxx...",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "name": "Henk"
  }
}
```

### Credential Management

**GET /api/auth/webauthn/credentials** - Lijst geregistreerde passkeys
**DELETE /api/auth/webauthn/credentials/{id}** - Verwijder passkey
**GET /api/auth/webauthn/available** - Check of passkeys beschikbaar zijn

---

## Implementatie per Project

### HavunCore (API + Webapp)

**Bestanden:**
- `app/Http/Controllers/Api/WebAuthnController.php`
- `app/Models/WebAuthnCredential.php`
- `app/Models/WebAuthnChallenge.php`
- `database/migrations/2025_12_01_010000_create_webauthn_credentials_table.php`
- `routes/api.php` (WebAuthn routes)

**Webapp (React):**
- `frontend/src/components/Login.jsx` - Biometrische login knop
- `frontend/src/components/BiometricSetup.jsx` - Passkey registratie UI

### HavunAdmin (Laravel)

**Bestanden:**
- `resources/views/auth/login.blade.php` - Biometrische login sectie (mobiel only)
- `resources/views/auth/qr-scanner.blade.php` - QR scanner voor desktop login
- `routes/web.php` - Routes voor `/auth/biometric/complete` en `/scan`
- `resources/views/layouts/navigation.blade.php` - QR Scanner link in menu

### Herdenkingsportaal (Laravel)

**Bestanden:**
- `resources/views/auth/login.blade.php` - Biometrische login sectie (mobiel only)
- `resources/views/auth/qr-scanner.blade.php` - QR scanner voor desktop login
- `routes/web.php` - Routes voor `/auth/biometric/complete` en `/scan`

---

## Gebruikershandleiding

### Passkey Registreren (eenmalig)

1. Open https://havuncore.havun.nl op je telefoon
2. Login met email/wachtwoord
3. Tik op menu (☰) → "Face ID / Vingerafdruk"
4. Tik "Face ID / Vingerafdruk instellen"
5. Bevestig met je gezicht of vinger
6. Klaar! Naam wordt automatisch ingevuld (bijv. "iPhone" of "Pixel")

### Inloggen met Biometrie (mobiel)

1. Open een van de apps op je telefoon
2. Tik op de grote "Face ID / Vingerafdruk" knop
3. Bevestig met je gezicht of vinger
4. Je bent ingelogd!

### Desktop Inloggen via QR (mobiel → desktop)

1. Open de app op je **desktop** → QR code verschijnt
2. Op je **telefoon** (ingelogd): Menu → "QR Scanner"
3. Scan de QR code
4. Desktop is automatisch ingelogd!

---

## Technische Details

### WebAuthn Flow

**Registratie:**
1. Client vraagt `register-options` aan server
2. Server genereert challenge en slaat op in `webauthn_challenges`
3. Browser toont biometrische prompt via `navigator.credentials.create()`
4. Client stuurt credential naar server
5. Server valideert challenge en slaat public key op in `webauthn_credentials`

**Login:**
1. Client vraagt `login-options` aan server
2. Server genereert challenge en retourneert beschikbare credentials
3. Browser toont biometrische prompt via `navigator.credentials.get()`
4. Client stuurt signed assertion naar server
5. Server valideert signature met opgeslagen public key
6. Server maakt `auth_devices` record en retourneert device_token

### Counter Check

WebAuthn gebruikt een counter om replay attacks te voorkomen. De implementatie is versoepeld voor mobiele authenticators die niet altijd betrouwbaar incrementeren:

```php
// Alleen falen als counter ACHTERUIT gaat (niet als gelijk)
if ($newCounter !== null && $credential->counter > 0 && $newCounter < $credential->counter) {
    return error('counter mismatch');
}
```

### Device Hash

Bij login wordt een `device_hash` gegenereerd voor het `auth_devices` record:

```php
$deviceHash = hash('sha256', implode('|', [
    $request->ip(),
    $userAgent,
    $user->id,
    $credential->credential_id,
]));
```

---

## Troubleshooting

### "Biometrische login mislukt"
- Check server logs: `tail -f /var/www/development/HavunCore/storage/logs/laravel.log`
- Meestal database gerelateerd (ontbrekende velden, verkeerde tabelnamen)

### "Counter mismatch"
- Normaal bij eerste login na registratie
- Fix: counter check versoepeld (zie code hierboven)

### "Geen passkeys gevonden"
- Gebruiker moet eerst passkey registreren via havuncore.havun.nl
- Check: `GET /api/auth/webauthn/available`

### QR Scanner werkt niet
- Camera permissies nodig in browser
- HTTPS vereist (werkt niet op HTTP)

---

## Deployment Checklist

Bij nieuwe installatie:

1. **Migratie draaien:**
   ```bash
   php artisan migrate
   ```

2. **Models hebben juiste tabelnamen:**
   ```php
   protected $table = 'webauthn_credentials';
   protected $table = 'webauthn_challenges';
   ```

3. **Routes geregistreerd in `routes/api.php`**

4. **CORS geconfigureerd** voor cross-origin requests van andere domeinen

5. **Frontend gebuild en gedeployed** (havuncore-webapp)

---

## Gerelateerde Documentatie

- [QR-LOGIN-IMPLEMENTATION.md](./QR-LOGIN-IMPLEMENTATION.md)
- [TASK-QUEUE-SYSTEM.md](./TASK-QUEUE-SYSTEM.md)
- [VAULT-SYSTEM.md](./VAULT-SYSTEM.md)

---

*Laatst bijgewerkt: 2025-12-01*
