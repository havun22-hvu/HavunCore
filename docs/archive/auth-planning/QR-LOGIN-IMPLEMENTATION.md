# QR Login Implementatie Guide

**Laatst bijgewerkt:** 2025-11-29
**Status:** Production Ready

## Overzicht

QR Login maakt het mogelijk om op een desktop in te loggen door een QR code te scannen met een mobiele app waar je al bent ingelogd. Geen wachtwoord nodig!

## Flow

```
Desktop (login pagina)          HavunCore API              Telefoon (app)
        │                            │                           │
        │ 1. POST /api/auth/qr/generate                          │
        │ ──────────────────────────>│                           │
        │ { browser, os }            │                           │
        │                            │                           │
        │ 2. { qr_code, email_token }│                           │
        │ <──────────────────────────│                           │
        │                            │                           │
        │ 3. Toon QR code            │                           │
        │    (URL met email_token)   │                           │
        │                            │                           │
        │ 4. Poll elke 2 sec         │                           │
        │ ──────────────────────────>│                           │
        │ GET /api/auth/qr/{code}/status                         │
        │                            │                           │
        │                            │    5. Scan QR code        │
        │                            │ <─────────────────────────│
        │                            │                           │
        │                            │    6. POST /api/auth/qr/approve-from-app
        │                            │ <─────────────────────────│
        │                            │    { token, email }       │
        │                            │                           │
        │                            │    7. { success: true }   │
        │                            │ ─────────────────────────>│
        │                            │                           │
        │ 8. status: 'approved'      │                           │
        │ <──────────────────────────│                           │
        │    { user: { email } }     │                           │
        │                            │                           │
        │ 9. Redirect naar           │                           │
        │    /auth/qr/complete/{code}│                           │
        │                            │                           │
        │ 10. Laravel Auth::login()  │                           │
        │                            │                           │
        │ 11. Redirect dashboard     │                           │
        └────────────────────────────┴───────────────────────────┘
```

## Implementatie in Client App

### 1. Login Pagina (Desktop)

Voeg toe aan `resources/views/auth/login.blade.php`:

```html
{{-- QR Login Section --}}
<div id="qr-login-section" class="mb-6">
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 rounded-xl p-4 border border-blue-200 dark:border-blue-700">
        <div class="flex items-center gap-4">
            <div id="qr-container" class="w-32 h-32 bg-white rounded-lg flex items-center justify-center flex-shrink-0">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            </div>
            <div class="flex-1">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Scan om in te loggen</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                    Open de app op je telefoon en scan deze QR code
                </p>
                <p id="qr-status" class="text-xs text-gray-500 dark:text-gray-400">QR code laden...</p>
                <p id="qr-timer" class="text-xs text-blue-600 dark:text-blue-400 hidden">
                    Verloopt over <span id="timer">5:00</span>
                </p>
            </div>
        </div>
    </div>
    <div class="my-4 flex items-center">
        <div class="flex-1 border-t border-gray-300 dark:border-gray-600"></div>
        <span class="px-3 text-sm text-gray-500 dark:text-gray-400">of login met wachtwoord</span>
        <div class="flex-1 border-t border-gray-300 dark:border-gray-600"></div>
    </div>
</div>
```

### 2. JavaScript (Login Pagina)

```javascript
const HAVUNCORE_API = 'https://havuncore.havun.nl';
let qrCode = null;
let pollInterval = null;
let timerInterval = null;
let expiresIn = 300;

function getDeviceInfo() {
    const ua = navigator.userAgent;
    let browser = 'Unknown Browser';
    let os = 'Unknown OS';

    if (ua.includes('Firefox')) browser = 'Firefox';
    else if (ua.includes('Edg')) browser = 'Edge';
    else if (ua.includes('Chrome')) browser = 'Chrome';
    else if (ua.includes('Safari')) browser = 'Safari';

    if (ua.includes('Windows')) os = 'Windows';
    else if (ua.includes('Mac')) os = 'macOS';
    else if (ua.includes('Linux')) os = 'Linux';

    return { browser, os };
}

async function generateQr() {
    const container = document.getElementById('qr-container');
    const status = document.getElementById('qr-status');
    const timer = document.getElementById('qr-timer');

    try {
        const response = await fetch(`${HAVUNCORE_API}/api/auth/qr/generate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(getDeviceInfo())
        });

        const data = await response.json();

        if (data.success) {
            qrCode = data.qr_code;
            expiresIn = data.expires_in;

            // Generate QR image URL
            const approveUrl = `${HAVUNCORE_API}/approve?token=${data.email_token}`;
            const qrImageUrl = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(approveUrl)}`;
            container.innerHTML = `<img src="${qrImageUrl}" alt="QR Code" class="rounded" width="120" height="120">`;

            status.textContent = 'Scan met je telefoon';
            timer.classList.remove('hidden');

            startPolling();
            startTimer();
        }
    } catch (err) {
        console.error('QR generate error:', err);
        status.textContent = 'QR code kon niet geladen worden';
    }
}

function startPolling() {
    pollInterval = setInterval(async () => {
        try {
            const response = await fetch(`${HAVUNCORE_API}/api/auth/qr/${qrCode}/status`);
            const data = await response.json();

            if (data.status === 'approved') {
                clearInterval(pollInterval);
                clearInterval(timerInterval);
                document.getElementById('qr-status').textContent = 'Ingelogd! Doorsturen...';
                // Redirect to complete login
                window.location.href = `/auth/qr/complete/${qrCode}`;
            }
        } catch (err) {}
    }, 2000);
}

// Initialize
generateQr();
```

### 3. Route voor Login Completion

Voeg toe aan `routes/web.php`:

```php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

// QR Login callback
Route::get('/auth/qr/complete/{code}', function (string $code) {
    // Check QR status at HavunCore
    $response = Http::get("https://havuncore.havun.nl/api/auth/qr/{$code}/status");
    $data = $response->json();

    if (($data['status'] ?? '') !== 'approved' || !isset($data['user']['email'])) {
        return redirect()->route('login')->with('error', 'QR sessie niet goedgekeurd');
    }

    // Find user by email
    $user = \App\Models\User::where('email', $data['user']['email'])->first();

    if (!$user) {
        return redirect()->route('login')->with('error', 'Gebruiker niet gevonden');
    }

    // Log the user in
    Auth::login($user, true); // remember = true

    return redirect()->intended('/dashboard');
})->name('auth.qr.complete');
```

### 4. QR Scanner Pagina (Mobiel)

Maak `resources/views/auth/qr-scanner.blade.php`:

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Scan om in te loggen</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-md mx-auto px-4">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl rounded-2xl">
                <div id="scanner-container" class="relative">
                    <video id="scanner-video" class="w-full aspect-square object-cover rounded-t-2xl"></video>
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="w-48 h-48 border-4 border-white rounded-2xl shadow-lg"></div>
                    </div>
                </div>

                <div id="success-state" class="hidden p-8 text-center">
                    <h3 class="text-xl font-bold text-green-600 mb-2">Ingelogd!</h3>
                    <p id="success-device" class="text-gray-600"></p>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://unpkg.com/qr-scanner@1.4.2/qr-scanner.umd.min.js"></script>
    <script>
        const HAVUNCORE_API = 'https://havuncore.havun.nl';
        const USER_EMAIL = '{{ auth()->user()->email }}';

        document.addEventListener('DOMContentLoaded', () => {
            const video = document.getElementById('scanner-video');
            const qrScanner = new QrScanner(video, result => handleScan(result.data));
            qrScanner.start();
        });

        async function handleScan(data) {
            if (!data.includes('havuncore.havun.nl/approve')) return;

            const url = new URL(data);
            const token = url.searchParams.get('token');

            const response = await fetch(HAVUNCORE_API + '/api/auth/qr/approve-from-app', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token, email: USER_EMAIL })
            });

            const result = await response.json();
            if (result.success) {
                document.getElementById('scanner-container').classList.add('hidden');
                document.getElementById('success-state').classList.remove('hidden');
            }
        }
    </script>
    @endpush
</x-app-layout>
```

### 5. CSP Headers (indien nodig)

Als je Content Security Policy headers gebruikt, voeg toe aan `connect-src`:

```
https://havuncore.havun.nl
```

## HavunCore API Endpoints

| Endpoint | Method | Beschrijving |
|----------|--------|--------------|
| `/api/auth/qr/generate` | POST | Genereer nieuwe QR sessie |
| `/api/auth/qr/{code}/status` | GET | Check sessie status |
| `/api/auth/qr/approve-from-app` | POST | Approve sessie vanuit mobiele app |

### Generate Request

```json
POST /api/auth/qr/generate
{
    "browser": "Chrome",
    "os": "Windows"
}
```

### Generate Response

```json
{
    "success": true,
    "qr_code": "qr_xxx...",
    "email_token": "xxx...",
    "expires_at": "2025-11-29T12:00:00.000000Z",
    "expires_in": 300
}
```

### Status Response (approved)

```json
{
    "status": "approved",
    "user": {
        "email": "user@example.com"
    }
}
```

## Geïmplementeerd in

- ✅ Herdenkingsportaal (staging.herdenkingsportaal.nl)
- ✅ HavunAdmin (havunadmin.havun.nl)

## Security Notes

1. QR sessie verloopt na 5 minuten
2. Alleen de email van de ingelogde mobiele gebruiker wordt vertrouwd
3. Desktop krijgt geen wachtwoord - alleen email verificatie via HavunCore
4. Rate limiting op API endpoints
