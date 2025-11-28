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
                <p class="text-gray-600 mt-2">Login via email link</p>
            </div>

            <div id="email-section">
                <div id="email-form">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email adres</label>
                        <input type="email" id="email"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="jouw@email.nl">
                    </div>

                    <button onclick="sendLoginEmail()" id="send-btn"
                        class="w-full py-3 px-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                        Verstuur login link
                    </button>
                </div>

                <div id="email-sent" class="hidden text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Check je email!</h3>
                    <p class="text-gray-600 mb-4">We hebben een login link gestuurd naar:<br><strong id="sent-email"></strong></p>
                    <p id="status-text" class="text-sm text-gray-500">Wachten op goedkeuring...</p>
                    <div id="success-redirect" class="hidden mt-4">
                        <p class="text-green-600 font-medium">Ingelogd! Doorsturen...</p>
                    </div>
                </div>

                <p id="error-msg" class="mt-4 text-center text-red-600 hidden"></p>
            </div>

            <div class="mt-6 pt-6 border-t">
                <a href="/login" class="block text-center text-gray-500 hover:text-gray-700 text-sm">
                    Login met wachtwoord
                </a>
            </div>
        </div>
    </div>

    <script>
        const API = '{{ $havuncore_api }}';
        const CALLBACK_URL = '{{ $havuncore_api }}/auth/approve';
        const SITE_NAME = '{{ config("app.name") }}';
        let qrCode = null;
        let pollInterval = null;

        async function sendLoginEmail() {
            const email = document.getElementById('email').value.trim();
            const btn = document.getElementById('send-btn');
            const errorMsg = document.getElementById('error-msg');

            if (!email || !email.includes('@')) {
                errorMsg.textContent = 'Vul een geldig email adres in';
                errorMsg.classList.remove('hidden');
                return;
            }

            errorMsg.classList.add('hidden');
            btn.disabled = true;
            btn.textContent = 'Bezig...';

            try {
                // Detect browser and OS
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
                else if (ua.includes('Android')) os = 'Android';
                else if (ua.includes('iPhone') || ua.includes('iPad')) os = 'iOS';

                // First generate a QR session
                const genResponse = await fetch(API + '/api/auth/qr/generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ browser, os })
                });

                const genData = await genResponse.json();

                if (!genData.success) {
                    throw new Error('Kon sessie niet aanmaken');
                }

                qrCode = genData.qr_code;

                // Send email
                const emailResponse = await fetch(API + '/api/auth/qr/' + qrCode + '/send-email', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: email,
                        callback_url: CALLBACK_URL,
                        site_name: SITE_NAME
                    })
                });

                const emailData = await emailResponse.json();

                if (!emailData.success) {
                    throw new Error(emailData.message || 'Kon email niet versturen');
                }

                // Show success state
                document.getElementById('email-form').classList.add('hidden');
                document.getElementById('email-sent').classList.remove('hidden');
                document.getElementById('sent-email').textContent = email;

                // Start polling for approval
                startPolling();

            } catch (err) {
                errorMsg.textContent = err.message;
                errorMsg.classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = 'Verstuur login link';
            }
        }

        function startPolling() {
            if (pollInterval) clearInterval(pollInterval);

            pollInterval = setInterval(async () => {
                try {
                    const response = await fetch(API + '/api/auth/qr/' + qrCode + '/status');
                    const data = await response.json();

                    if (data.status === 'approved' && data.device_token) {
                        clearInterval(pollInterval);

                        // Set cookie
                        document.cookie = 'havun_device_token=' + data.device_token + '; path=/; max-age=' + (30*24*60*60) + '; secure; samesite=strict';

                        // Show success
                        document.getElementById('status-text').classList.add('hidden');
                        document.getElementById('success-redirect').classList.remove('hidden');

                        // Redirect
                        setTimeout(() => {
                            window.location.href = '{{ config("havun-auth.redirect_after_login", "/dashboard") }}';
                        }, 1000);
                    }
                } catch (e) {
                    // Ignore poll errors
                }
            }, 2000);
        }
    </script>
</body>
</html>
