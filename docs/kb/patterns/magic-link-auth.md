# Magic Link Authentication Pattern (Laravel)

> **Status:** Standaard voor alle Havun projecten
> **Laatste update:** 14 april 2026
> **Gebruikt in:** Studieplanner (referentie), Herdenkingsportaal, JudoToernooi

## Overzicht

Magic links worden gebruikt voor:
1. **Registratie** — nieuwe gebruiker voert email in, ontvangt link, account wordt aangemaakt
2. **Herstel** — bestaande gebruiker op nieuw apparaat, ontvangt link om in te loggen

**Email-first flow:** Eén endpoint voor zowel login als registratie. Backend bepaalt op basis van het emailadres of het een bestaande of nieuwe gebruiker is. Geen aparte login/registratie schermen. Zie `patterns/universal-login-screen.md`.

Geen wachtwoord nodig. Biometric + magic link dekt alle scenario's.

## Database

### Migration: magic_link_tokens

```php
Schema::create('magic_link_tokens', function (Blueprint $table) {
    $table->id();
    $table->string('email');
    $table->string('token', 64)->unique();
    $table->enum('type', ['register', 'password_reset'])->default('register');
    $table->json('metadata')->nullable(); // naam, extra velden bij registratie
    $table->timestamp('used_at')->nullable();
    $table->timestamp('expires_at');
    $table->timestamps();

    $table->index(['token', 'type']);
    $table->index('email');
});
```

### Model: MagicLinkToken

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MagicLinkToken extends Model
{
    protected $fillable = ['email', 'token', 'type', 'metadata', 'used_at', 'expires_at'];

    protected $casts = [
        'metadata' => 'array',
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Create a new magic link token.
     */
    public static function generate(string $email, string $type = 'register', array $metadata = []): self
    {
        // Clean up old unused tokens for this email + type
        static::where('email', $email)
            ->where('type', $type)
            ->whereNull('used_at')
            ->delete();

        return static::create([
            'email' => strtolower(trim($email)),
            'token' => Str::random(64),
            'type' => $type,
            'metadata' => $metadata,
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    /**
     * Find a valid (unused, not expired) token.
     */
    public static function findValid(string $token, string $type): ?self
    {
        return static::where('token', $token)
            ->where('type', $type)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Mark token as used (single-use).
     */
    public function markUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
```

## Routes

```php
// Public routes
Route::get('/register', [MagicLinkController::class, 'showRegister'])->name('register');
Route::post('/register', [MagicLinkController::class, 'sendRegisterLink']);
Route::get('/register/sent', [MagicLinkController::class, 'registerSent'])->name('register.sent');
Route::get('/register/verify/{token}', [MagicLinkController::class, 'verifyRegister'])->name('register.verify');

Route::get('/forgot-password', [MagicLinkController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [MagicLinkController::class, 'sendResetLink'])->name('password.email');
Route::get('/forgot-password/sent', [MagicLinkController::class, 'resetSent'])->name('password.sent');
Route::get('/reset-password/{token}', [MagicLinkController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [MagicLinkController::class, 'resetPassword'])->name('password.update');
```

## Controller: MagicLinkController

```php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MagicLinkToken;
use App\Models\User; // or Organisator for JudoToernooi
use App\Mail\MagicLinkMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class MagicLinkController extends Controller
{
    // ========== REGISTRATIE ==========

    public function showRegister()
    {
        return view('auth.register');
    }

    public function sendRegisterLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'required|string|max:255',
        ]);

        // Rate limit: max 3 per 10 minuten
        $key = 'magic-link:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors([
                'email' => __('Te veel verzoeken. Probeer het over :seconds seconden opnieuw.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }
        RateLimiter::hit($key, 600); // 10 min window

        // Check of email al bestaat
        $userExists = User::where('email', strtolower($request->email))->exists();
        if ($userExists) {
            // Stuur GEEN foutmelding (security: email enumeration prevention)
            // Stuur in plaats daarvan een login link
            $token = MagicLinkToken::generate($request->email, 'register', [
                'name' => $request->name,
                'existing_user' => true,
            ]);
        } else {
            $token = MagicLinkToken::generate($request->email, 'register', [
                'name' => $request->name,
            ]);
        }

        Mail::to($request->email)->send(new MagicLinkMail($token));

        return redirect()->route('register.sent')->with('email', $request->email);
    }

    public function registerSent()
    {
        return view('auth.magic-link-sent', [
            'email' => session('email'),
            'type' => 'register',
        ]);
    }

    public function verifyRegister(string $token)
    {
        $magicToken = MagicLinkToken::findValid($token, 'register');

        if (!$magicToken) {
            return redirect()->route('register')
                ->withErrors(['token' => __('Link is verlopen of al gebruikt. Vraag een nieuwe aan.')]);
        }

        $magicToken->markUsed();
        $metadata = $magicToken->metadata ?? [];

        // Bestaande gebruiker? Direct inloggen
        $user = User::where('email', $magicToken->email)->first();

        if ($user) {
            Auth::guard('web')->login($user, true);
            session()->save();
            return redirect()->intended('/dashboard');
        }

        // Nieuwe gebruiker aanmaken
        $user = User::create([
            'name' => $metadata['name'] ?? 'Gebruiker',
            'email' => $magicToken->email,
            'email_verified_at' => now(), // Verified via magic link
            'password' => null, // No password yet - user can set one later
        ]);

        Auth::guard('web')->login($user, true);
        session()->save();

        // Redirect naar optionele wachtwoord setup of dashboard
        return redirect()->route('password.setup');
    }

    // ========== WACHTWOORD VERGETEN ==========

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Rate limit
        $key = 'password-reset:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors([
                'email' => __('Te veel verzoeken. Probeer het over :seconds seconden opnieuw.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }
        RateLimiter::hit($key, 600);

        // Always show success (email enumeration prevention)
        $user = User::where('email', strtolower($request->email))->first();

        if ($user) {
            $token = MagicLinkToken::generate($request->email, 'password_reset');
            Mail::to($request->email)->send(new MagicLinkMail($token));
        }

        return redirect()->route('password.sent')->with('email', $request->email);
    }

    public function resetSent()
    {
        return view('auth.magic-link-sent', [
            'email' => session('email'),
            'type' => 'password_reset',
        ]);
    }

    public function showResetPassword(string $token)
    {
        $magicToken = MagicLinkToken::findValid($token, 'password_reset');

        if (!$magicToken) {
            return redirect()->route('password.request')
                ->withErrors(['token' => __('Link is verlopen of al gebruikt. Vraag een nieuwe aan.')]);
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $magicToken->email,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $magicToken = MagicLinkToken::findValid($request->token, 'password_reset');

        if (!$magicToken) {
            return redirect()->route('password.request')
                ->withErrors(['token' => __('Link is verlopen of al gebruikt.')]);
        }

        $user = User::where('email', $magicToken->email)->first();

        if (!$user) {
            return redirect()->route('password.request')
                ->withErrors(['email' => __('Gebruiker niet gevonden.')]);
        }

        $user->update(['password' => $request->password]); // Cast 'hashed' handles hashing
        $magicToken->markUsed();

        Auth::guard('web')->login($user, true);
        session()->save();

        return redirect()->intended('/dashboard');
    }
}
```

## Mail: MagicLinkMail

```php
namespace App\Mail;

use App\Models\MagicLinkToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public MagicLinkToken $token) {}

    public function envelope(): Envelope
    {
        $subject = $this->token->type === 'register'
            ? __('Bevestig je registratie')
            : __('Wachtwoord resetten');

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $route = $this->token->type === 'register'
            ? route('register.verify', $this->token->token)
            : route('password.reset', $this->token->token);

        return new Content(
            markdown: 'emails.magic-link',
            with: [
                'url' => $route,
                'type' => $this->token->type,
                'name' => $this->token->metadata['name'] ?? null,
                'expiresIn' => 15, // minutes
            ],
        );
    }
}
```

## Email Template

```blade
{{-- resources/views/emails/magic-link.blade.php --}}
<x-mail::message>
@if($type === 'register')
# {{ __('Welkom!') }}

{{ __('Hallo :name, klik op de knop hieronder om je account te activeren.', ['name' => $name]) }}

<x-mail::button :url="$url">
{{ __('Account activeren') }}
</x-mail::button>
@else
# {{ __('Wachtwoord resetten') }}

{{ __('Klik op de knop hieronder om een nieuw wachtwoord in te stellen.') }}

<x-mail::button :url="$url">
{{ __('Nieuw wachtwoord instellen') }}
</x-mail::button>
@endif

{{ __('Deze link is :minutes minuten geldig en kan maar een keer gebruikt worden.', ['minutes' => $expiresIn]) }}

{{ __('Als je dit niet hebt aangevraagd, kun je deze email negeren.') }}

{{ __('Groetjes,') }}<br>
{{ config('app.name') }}
</x-mail::message>
```

## Security Overwegingen

| Aspect | Implementatie |
|--------|---------------|
| Token lengte | 64 tekens (Str::random) |
| Geldigheid | 15 minuten |
| Single-use | `used_at` timestamp, daarna onbruikbaar |
| Rate limiting | 3 verzoeken per 10 min per IP |
| Email enumeration | Altijd success tonen, nooit "email niet gevonden" |
| Cleanup | Oude tokens verwijderd bij nieuwe aanvraag |
| HTTPS | Vereist in productie (links bevatten token) |

## Cleanup Command (optioneel)

```php
// app/Console/Commands/CleanupMagicLinks.php
// Schedule: daily
MagicLinkToken::where('expires_at', '<', now()->subDay())->delete();
```

## Aanpassen per Project

| Project | User model | Guard | Extra registratie velden |
|---------|-----------|-------|--------------------------|
| Herdenkingsportaal | `User` | `web` | name, email |
| JudoToernooi | `Organisator` | `organisator` | organisatie_naam, naam, email, telefoon |

Voor JudoToernooi: vervang `User` door `Organisator` en `Auth::guard('web')` door `Auth::guard('organisator')` in de controller.
