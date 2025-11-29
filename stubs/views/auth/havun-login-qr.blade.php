<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-blue-100">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">{{ config('app.name') }}</h1>
            </div>

            <!-- QR Section (hidden on mobile) -->
            <div id="qr-section" class="hidden md:block">
                <div id="qr-container" class="text-center">
                    <p class="text-gray-600 mb-4">Scan met je telefoon om in te loggen</p>
                    <div id="qr-code" class="flex justify-center mb-4">
                        <div class="w-48 h-48 bg-gray-100 rounded-lg flex items-center justify-center">
                            <span class="text-gray-400">Laden...</span>
                        </div>
                    </div>
                    <p id="qr-status" class="text-sm text-gray-500">Wachten op scan...</p>
                </div>

                <div id="qr-success" class="hidden text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <p class="text-green-600 font-medium">Ingelogd! Doorsturen...</p>
                </div>

                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">of login met wachtwoord</span>
                    </div>
                </div>
            </div>

            <!-- Mobile notice -->
            <div id="mobile-notice" class="md:hidden text-center mb-4">
                <p class="text-gray-600 text-sm">Login met je account</p>
            </div>

            <!-- Password form -->
            <form method="POST" action="{{ route('login') }}" id="login-form">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="jouw@email.nl" required>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Wachtwoord</label>
                    <input type="password" name="password"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="********" required>
                </div>

                <div class="flex items-center justify-between mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-600">Onthoud mij</span>
                    </label>
                    @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:text-blue-800">Wachtwoord vergeten?</a>
                    @endif
                </div>

                @if ($errors->any())
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-red-600 text-sm">{{ $errors->first() }}</p>
                </div>
                @endif

                <button type="submit"
                    class="w-full py-3 px-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                    Inloggen
                </button>
            </form>

            @if (Route::has('register'))
            <p class="mt-6 text-center text-sm text-gray-600">
                Nog geen account? <a href="{{ route('register') }}" class="text-blue-600 hover:text-blue-800 font-medium">Registreren</a>
            </p>
            @endif
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    <script>
        const API = '{{ config("havun-auth.api_url", "https://havuncore.havun.nl") }}';
        const APPROVE_URL = 'https://havuncore.havun.nl/approve';
        let sessionCode = null;
        let emailToken = null;
        let pollInterval = null;

        document.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth >= 768) {
                generateQR();
            }
        });

        async function generateQR() {
            try {
                const ua = navigator.userAgent;
                let browser = 'Browser';
                if (ua.includes('Edg/')) browser = 'Edge';
                else if (ua.includes('Chrome')) browser = 'Chrome';
                else if (ua.includes('Firefox')) browser = 'Firefox';
                else if (ua.includes('Safari')) browser = 'Safari';

                let os = 'Unknown';
                if (ua.includes('Windows NT 10')) os = 'Windows 10/11';
                else if (ua.includes('Windows')) os = 'Windows';
                else if (ua.includes('Mac OS X')) os = 'macOS';
                else if (ua.includes('Linux')) os = 'Linux';

                const response = await fetch(API + '/api/auth/qr/generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ browser, os })
                });

                const data = await response.json();
                if (!data.success) throw new Error('Kon QR niet genereren');

                sessionCode = data.qr_code;
                emailToken = data.email_token;

                const approveUrl = APPROVE_URL + '?token=' + emailToken;

                document.getElementById('qr-code').innerHTML = '';
                QRCode.toCanvas(document.createElement('canvas'), approveUrl, {
                    width: 192,
                    margin: 2,
                    color: { dark: '#1f2937', light: '#ffffff' }
                }, function(error, canvas) {
                    if (error) throw error;
                    canvas.classList.add('rounded-lg');
                    document.getElementById('qr-code').appendChild(canvas);
                });

                startPolling();

            } catch (err) {
                document.getElementById('qr-code').innerHTML = '<p class="text-red-500 text-sm">Fout bij laden QR</p>';
                console.error('QR Error:', err);
            }
        }

        function startPolling() {
            if (pollInterval) clearInterval(pollInterval);

            pollInterval = setInterval(async () => {
                try {
                    const response = await fetch(API + '/api/auth/qr/' + sessionCode + '/status');
                    const data = await response.json();

                    if (data.status === 'approved' && data.device_token) {
                        clearInterval(pollInterval);

                        document.cookie = 'havun_device_token=' + data.device_token + '; path=/; max-age=' + (30*24*60*60) + '; secure; samesite=strict';

                        document.getElementById('qr-container').classList.add('hidden');
                        document.getElementById('qr-success').classList.remove('hidden');

                        setTimeout(() => {
                            window.location.href = '{{ config("havun-auth.redirect_after_login", "/dashboard") }}';
                        }, 1000);
                    }
                } catch (e) {}
            }, 2000);
        }
    </script>
</body>
</html>
