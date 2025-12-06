# Passkey/WebAuthn op Mobiel - Complete Oplossing

> **Status:** Werkend op Herdenkingsportaal staging (dec 2025)
> **Package:** `laragear/webauthn` v3.x
> **Getest op:** Android Chrome, iOS Safari

## TL;DR

Mobiele browsers hebben problemen met sessie cookies bij fetch() requests. De oplossing:
1. Sla challenges op in database (niet sessie)
2. Na succesvolle biometrische login → genereer QR token → redirect via normale browser navigatie
3. Die redirect zet een verse sessie cookie die wél werkt

## Problemen (3 lagen)

### Probleem 1: HTML response i.p.v. JSON
Laragear WebAuthn's FormRequest classes (`AttestationRequest`, `AssertionRequest`) gooien exceptions bij auth failures die Laravel als HTML redirect afhandelt.

### Probleem 2: "Challenge does not exist"
Mobiele browsers (vooral Android Chrome) sturen sessie cookies niet altijd mee bij JavaScript fetch() requests, waardoor elke request een nieuwe sessie krijgt. De challenge wordt opgeslagen in sessie A, maar de verificatie komt binnen op sessie B.

### Probleem 3: Sessie cookie na login werkt niet
Zelfs als de biometrische verificatie slaagt en `Auth::login()` wordt aangeroepen, wordt de sessie cookie niet correct meegestuurd bij de redirect. De gebruiker blijft op de login pagina.

### Probleem 4: Oude passkeys op telefoon
Telefoons kunnen meerdere passkeys opslaan voor dezelfde site. Bij login kiest de browser soms een oude/verkeerde passkey, waardoor de credential ID niet matcht met de database.

## Oplossing

### Stap 1: Exception Handlers (bootstrap/app.php)

```php
->withExceptions(function (Exceptions $exceptions): void {
    // Force JSON responses for passkey routes
    $exceptions->render(function (AuthenticationException $e, Request $request) {
        if ($request->is('auth/passkey/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Je moet ingelogd zijn.',
                'error' => 'unauthenticated'
            ], 401);
        }
    });

    $exceptions->render(function (ValidationException $e, Request $request) {
        if ($request->is('auth/passkey/*')) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    });

    $exceptions->render(function (AuthorizationException $e, Request $request) {
        if ($request->is('auth/passkey/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Geen toegang.',
            ], 403);
        }
    });
})
```

### Stap 2: Database Challenge Repository

De sessie-gebaseerde challenge storage werkt niet op mobiel. Gebruik database/cache opslag.

**Maak `app/WebAuthn/DatabaseChallengeRepository.php`:**

```php
<?php

namespace App\WebAuthn;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidation;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidation;
use Laragear\WebAuthn\Challenge\Challenge;
use Laragear\WebAuthn\Contracts\WebAuthnChallengeRepository;
use Laragear\WebAuthn\Assertion\Creator\AssertionCreation;
use Laragear\WebAuthn\Attestation\Creator\AttestationCreation;

class DatabaseChallengeRepository implements WebAuthnChallengeRepository
{
    public function store(AttestationCreation|AssertionCreation $ceremony, Challenge $challenge): void
    {
        $token = base64_encode((string) $challenge->data);

        DB::table('webauthn_challenges')->insert([
            'token' => $token,
            'challenge_data' => serialize($challenge),
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('WebAuthn challenge stored', ['token' => substr($token, 0, 20)]);
    }

    public function pull(AttestationValidation|AssertionValidation $validation): ?Challenge
    {
        // Cleanup oude challenges
        DB::table('webauthn_challenges')->where('expires_at', '<', now())->delete();

        // Haal alle geldige challenges op en probeer te matchen
        $challenges = DB::table('webauthn_challenges')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get();

        Log::info('WebAuthn pull: found challenges', ['count' => $challenges->count()]);

        foreach ($challenges as $row) {
            $challenge = unserialize($row->challenge_data);
            if ($challenge && $challenge->isValid()) {
                DB::table('webauthn_challenges')->where('token', $row->token)->delete();
                Log::info('WebAuthn challenge matched and returned');
                return $challenge;
            }
        }

        return null;
    }
}
```

**Maak migration voor challenges tabel:**

```php
Schema::create('webauthn_challenges', function (Blueprint $table) {
    $table->string('token')->primary();
    $table->text('challenge_data');
    $table->timestamp('expires_at');
    $table->timestamps();
});
```

### Stap 3: Service Provider

**Maak `app/Providers/WebAuthnServiceProvider.php`:**

```php
<?php

namespace App\Providers;

use App\WebAuthn\DatabaseChallengeRepository;
use Illuminate\Support\ServiceProvider;
use Laragear\WebAuthn\Contracts\WebAuthnChallengeRepository;

class WebAuthnServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            WebAuthnChallengeRepository::class,
            DatabaseChallengeRepository::class
        );
    }
}
```

**Registreer in `bootstrap/providers.php`:**
```php
App\Providers\WebAuthnServiceProvider::class,
```

### Stap 4: QR Token Login Redirect (CRUCIAAL!)

Dit is de belangrijkste fix. Na biometrische verificatie NIET vertrouwen op sessie cookies, maar een token-based redirect gebruiken.

**PasskeyController login methode:**

```php
public function login(AssertedRequest $request): JsonResponse
{
    try {
        $user = $request->login();

        // Fallback: handmatige credential lookup
        if (!$user) {
            $credId = $request->input('id');
            $credential = WebAuthnCredential::find($credId);
            if ($credential) {
                $user = $credential->authenticatable;
            }
        }

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Passkey niet herkend'], 401);
        }

        // NIET Auth::login() gebruiken - sessie cookie werkt niet op mobiel!
        // In plaats daarvan: maak een pre-approved QR token
        $qrToken = QrLoginToken::create([
            'token' => bin2hex(random_bytes(32)),
            'user_id' => $user->id,
            'status' => 'approved',
            'device_info' => ['method' => 'passkey'],
            'approved_at' => now(),
            'expires_at' => now()->addMinutes(2),
        ]);

        // Return redirect URL - browser navigeert hier naartoe (normale navigatie, geen fetch)
        return response()->json([
            'success' => true,
            'redirect' => route('auth.qr.complete', $qrToken->token),
        ]);

    } catch (\Throwable $e) {
        Log::error('Passkey login error', ['error' => $e->getMessage()]);
        return response()->json(['success' => false, 'message' => 'Fout: ' . $e->getMessage()], 500);
    }
}
```

**De qrComplete methode (hergebruikt van QR login):**

```php
public function qrComplete(string $token)
{
    $qrToken = QrLoginToken::where('token', $token)->first();

    if (!$qrToken || !$qrToken->isApproved()) {
        return redirect()->route('login')->with('error', 'Ongeldige link');
    }

    $qrToken->markAsUsed();

    // HIER werkt Auth::login() WEL - dit is een normale browser navigatie
    Auth::login($qrToken->user, true);

    return redirect()->intended('/dashboard');
}
```

### Stap 5: Frontend Fetch Calls

Voeg `credentials: 'same-origin'` toe aan ALLE fetch calls:

```javascript
const response = await fetch('/auth/passkey/login', {
    method: 'POST',
    credentials: 'same-origin',  // <-- BELANGRIJK!
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
    },
    body: JSON.stringify(credentialData),
});

const data = await response.json();

if (data.success) {
    // BELANGRIJK: Gebruik window.location.href, niet fetch!
    window.location.href = data.redirect;
}
```

### Stap 6: CSRF Exclusion

In `bootstrap/app.php`:
```php
$middleware->validateCsrfTokens(except: [
    'auth/passkey/*',
]);
```

## Waarom dit werkt

1. **Challenge storage:** Database ipv sessie - onafhankelijk van cookies
2. **QR token redirect:** Na biometrische verificatie wordt een one-time token gegenereerd
3. **Browser navigatie:** `window.location.href` triggert een normale browser request die cookies WEL correct afhandelt
4. **Verse sessie:** De `qrComplete` route zet een verse sessie cookie via normale HTTP response

## Debugging Tips

### Logs toevoegen
```php
Log::info('LOGIN - Browser sent credential', ['id' => $request->input('id')]);
Log::info('LOGIN - Manual credential lookup', ['found' => $credential ? 'yes' : 'no']);
Log::info('LOGIN - Laragear result', ['user' => $user ? $user->id : null]);
```

### Credential ID mismatch
Als de browser een andere credential ID stuurt dan in de database staat:
- Gebruiker heeft meerdere passkeys opgeslagen
- Verwijder ALLE passkeys op telefoon: Instellingen → Google → Wachtwoorden
- Registreer opnieuw

### Database cleanen
```php
DB::table('webauthn_credentials')->truncate();
DB::table('webauthn_challenges')->truncate();
```

## Checklist

- [ ] Exception handlers toegevoegd in bootstrap/app.php
- [ ] DatabaseChallengeRepository gemaakt
- [ ] Migration voor webauthn_challenges tabel
- [ ] WebAuthnServiceProvider gemaakt en geregistreerd
- [ ] PasskeyController gebruikt QR token redirect (niet Auth::login direct)
- [ ] Frontend fetch calls hebben `credentials: 'same-origin'`
- [ ] Frontend gebruikt `window.location.href` voor redirect (niet fetch)
- [ ] Passkey routes uitgesloten van CSRF
- [ ] Oude passkeys verwijderd van test telefoon
- [ ] Getest op Android Chrome
- [ ] Getest op iOS Safari

## Vergelijking met HavunCore

HavunCore gebruikt JWT tokens (stateless) - geen sessie cookies nodig. Herdenkingsportaal gebruikt sessie-based auth (Laravel/Breeze standaard). De QR token redirect is een pragmatische oplossing die de voordelen van token-based auth combineert met de bestaande sessie architectuur.

## Referentie

- Package: `laragear/webauthn` v3.x
- Getest op: Herdenkingsportaal staging (6 dec 2025)
- Werkende versie: 3.0.30
- Tijd om op te lossen: ~4 uur debugging
