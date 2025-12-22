# Pattern: Email Verificatie met Code

**Gebruikt in:** Herdenkingsportaal (account verificatie, password reset)
**Geschikt voor:** Studieplanner (pincode reset), toekomstige projecten

## Overzicht

Simpel email verificatie systeem met 6-cijferige code. Geen externe dependencies.

## Database Schema

```sql
CREATE TABLE verification_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(6) NOT NULL,
    purpose ENUM('pincode_reset', 'email_verify', 'account_delete') NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_email_purpose (email, purpose),
    INDEX idx_expires (expires_at)
);
```

## Laravel Migration

```php
Schema::create('verification_codes', function (Blueprint $table) {
    $table->id();
    $table->string('email');
    $table->string('code', 6);
    $table->string('purpose'); // pincode_reset, email_verify, etc.
    $table->timestamp('expires_at');
    $table->timestamp('used_at')->nullable();
    $table->timestamps();

    $table->index(['email', 'purpose']);
    $table->index('expires_at');
});
```

## Service Class

```php
<?php

namespace App\Services;

use App\Models\VerificationCode;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;

class EmailVerificationService
{
    /**
     * Stuur verificatie code naar email
     */
    public function sendCode(string $email, string $purpose = 'pincode_reset'): bool
    {
        // Verwijder oude codes voor deze email/purpose
        VerificationCode::where('email', $email)
            ->where('purpose', $purpose)
            ->delete();

        // Genereer 6-cijferige code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Sla op met 15 minuten geldigheid
        VerificationCode::create([
            'email' => $email,
            'code' => $code,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(15),
        ]);

        // Stuur email
        Mail::to($email)->send(new VerificationCodeMail($code, $purpose));

        return true;
    }

    /**
     * Valideer code
     */
    public function verifyCode(string $email, string $code, string $purpose = 'pincode_reset'): bool
    {
        $record = VerificationCode::where('email', $email)
            ->where('code', $code)
            ->where('purpose', $purpose)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->first();

        if (!$record) {
            return false;
        }

        // Markeer als gebruikt
        $record->update(['used_at' => now()]);

        return true;
    }

    /**
     * Cleanup verlopen codes (voor cron)
     */
    public function cleanupExpired(): int
    {
        return VerificationCode::where('expires_at', '<', now())
            ->delete();
    }
}
```

## Mail Template

```php
<?php
// app/Mail/VerificationCodeMail.php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VerificationCodeMail extends Mailable
{
    public function __construct(
        public string $code,
        public string $purpose
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'pincode_reset' => 'Je verificatiecode voor pincode reset',
            'email_verify' => 'Bevestig je emailadres',
        ];

        return new Envelope(
            subject: $subjects[$this->purpose] ?? 'Je verificatiecode',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
        );
    }
}
```

## Blade View

```blade
{{-- resources/views/emails/verification-code.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <style>
        .code {
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 8px;
            background: #f3f4f6;
            padding: 16px 24px;
            border-radius: 8px;
            display: inline-block;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <h2>Je verificatiecode</h2>

    <p>Gebruik deze code om je {{ $purpose === 'pincode_reset' ? 'pincode te resetten' : 'actie te bevestigen' }}:</p>

    <div class="code">{{ $code }}</div>

    <p>Deze code is 15 minuten geldig.</p>

    <p><small>Heb je dit niet aangevraagd? Negeer deze email dan.</small></p>
</body>
</html>
```

## Controller Voorbeeld

```php
<?php

namespace App\Http\Controllers;

use App\Services\EmailVerificationService;
use Illuminate\Http\Request;

class PincodeResetController extends Controller
{
    public function __construct(
        private EmailVerificationService $verificationService
    ) {}

    /**
     * Stap 1: Vraag code aan
     */
    public function requestCode(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $this->verificationService->sendCode($request->email, 'pincode_reset');

        return response()->json(['message' => 'Code verstuurd']);
    }

    /**
     * Stap 2: Valideer code
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        if (!$this->verificationService->verifyCode($request->email, $request->code, 'pincode_reset')) {
            return response()->json(['error' => 'Ongeldige of verlopen code'], 422);
        }

        // Genereer tijdelijke token voor pincode reset
        $token = bin2hex(random_bytes(32));
        cache()->put("pincode_reset:{$request->email}", $token, now()->addMinutes(10));

        return response()->json(['reset_token' => $token]);
    }

    /**
     * Stap 3: Stel nieuwe pincode in
     */
    public function resetPincode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'reset_token' => 'required|string',
            'new_pincode' => 'required|string|size:4',
        ]);

        $cachedToken = cache()->pull("pincode_reset:{$request->email}");

        if (!$cachedToken || $cachedToken !== $request->reset_token) {
            return response()->json(['error' => 'Ongeldige sessie'], 422);
        }

        // Update pincode
        $user = User::where('email', $request->email)->first();
        $user->update(['pincode' => bcrypt($request->new_pincode)]);

        return response()->json(['message' => 'Pincode succesvol gewijzigd']);
    }
}
```

## API Routes

```php
// routes/api.php
Route::prefix('pincode-reset')->group(function () {
    Route::post('/request', [PincodeResetController::class, 'requestCode']);
    Route::post('/verify', [PincodeResetController::class, 'verifyCode']);
    Route::post('/reset', [PincodeResetController::class, 'resetPincode']);
});
```

## Flow Diagram

```
[Gebruiker] "Pincode vergeten"
     │
     ▼
[Voer email in] ──► POST /pincode-reset/request
     │
     ▼
[Check email] ◄── Email met 6-cijferige code
     │
     ▼
[Voer code in] ──► POST /pincode-reset/verify
     │                    │
     │              (code geldig?)
     │                    │
     ▼                    ▼
[Nieuwe pincode] ──► POST /pincode-reset/reset
     │
     ▼
[Klaar!] Login met nieuwe pincode
```

## Security Overwegingen

1. **Rate limiting:** Max 3 codes per uur per email
2. **Brute force:** Max 5 pogingen per code, daarna blokkeren
3. **Code entropy:** 6 cijfers = 1.000.000 combinaties, acceptabel met rate limiting
4. **Geen user enumeration:** Altijd "code verstuurd" zeggen, ook als email niet bestaat

## Gerelateerd

- Herdenkingsportaal: `app/Services/EmailService.php`
- Laravel docs: [Email Verification](https://laravel.com/docs/verification)
