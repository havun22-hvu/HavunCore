<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/qrcode@1.5.3/build/qrcode.min.js"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-blue-100">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900">{{ config('app.name') }}</h1>
                <p class="text-gray-600 mt-2">Login met QR code of wachtwoord</p>
            </div>

            <!-- QR Login Section -->
            @if($qr_enabled)
            <div id="qr-section">
                <div class="flex flex-col items-center space-y-4">
                    <div id="qr-container" class="w-64 h-64 flex items-center justify-center bg-gray-100 rounded-lg">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                    </div>
                    <p id="qr-status" class="text-sm text-gray-600">QR code laden...</p>
                    <p id="qr-timer" class="text-xs text-gray-500 hidden">Verloopt over <span id="timer">5:00</span></p>
                </div>

                @if($password_enabled)
                <div class="my-6 flex items-center">
                    <div class="flex-1 border-t border-gray-300"></div>
                    <span class="px-4 text-sm text-gray-500">of</span>
                    <div class="flex-1 border-t border-gray-300"></div>
                </div>
                <button onclick="showPasswordForm()" class="w-full py-3 px-4 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    Login met wachtwoord
                </button>
                @endif
            </div>
            @endif

            <!-- Password Login Section -->
            <div id="password-section" class="{{ $qr_enabled ? 'hidden' : '' }}">
                <form id="login-form" method="POST" action="{{ route('havun.login') }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="browser" id="browser-input">
                    <input type="hidden" name="os" id="os-input">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" required autofocus
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="jouw@email.nl">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Wachtwoord</label>
                        <div class="relative">
                            <input type="password" name="password" id="password" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="••••••••">
                            <button type="button" onclick="togglePassword()" class="absolute right-3 top-3 text-gray-500">
                                👁️
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3 px-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                        Inloggen
                    </button>
                </form>

                @if($qr_enabled)
                <div class="mt-6">
                    <button onclick="showQrForm()" class="w-full py-3 px-4 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        Login met QR code
                    </button>
                </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        const HAVUNCORE_API = '{{ $havuncore_api }}';
        let qrCode = null;
        let pollInterval = null;
        let timerInterval = null;
        let expiresIn = 300;

        // Detect browser and OS
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
            else if (ua.includes('Android')) os = 'Android';
            else if (/iPhone|iPad/.test(ua)) os = 'iOS';

            return { browser, os };
        }

        // Set device info in form
        const deviceInfo = getDeviceInfo();
        document.getElementById('browser-input').value = deviceInfo.browser;
        document.getElementById('os-input').value = deviceInfo.os;

        // Generate QR code
        async function generateQr() {
            const container = document.getElementById('qr-container');
            const status = document.getElementById('qr-status');
            const timer = document.getElementById('qr-timer');

            container.innerHTML = '<div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>';
            status.textContent = 'QR code laden...';
            timer.classList.add('hidden');

            try {
                const response = await fetch(`${HAVUNCORE_API}/api/auth/qr/generate`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(deviceInfo)
                });

                const data = await response.json();

                if (data.success) {
                    qrCode = data.qr_code;
                    expiresIn = data.expires_in;

                    // Generate QR code image
                    container.innerHTML = '<canvas id="qr-canvas"></canvas>';
                    QRCode.toCanvas(document.getElementById('qr-canvas'), `havuncore://qr/${qrCode}`, {
                        width: 224,
                        margin: 2
                    });

                    status.textContent = 'Scan met je telefoon om in te loggen';
                    timer.classList.remove('hidden');

                    startPolling();
                    startTimer();
                }
            } catch (err) {
                container.innerHTML = '<span class="text-red-500">Fout bij laden QR</span>';
                status.textContent = 'Probeer opnieuw';
            }
        }

        // Poll for QR status
        function startPolling() {
            if (pollInterval) clearInterval(pollInterval);

            pollInterval = setInterval(async () => {
                try {
                    const response = await fetch(`${HAVUNCORE_API}/api/auth/qr/${qrCode}/status`);
                    const data = await response.json();

                    if (data.status === 'scanned') {
                        document.getElementById('qr-status').textContent = 'QR gescand! Wachten op goedkeuring...';
                        document.getElementById('qr-status').className = 'text-sm text-blue-600 font-medium';
                    } else if (data.status === 'approved') {
                        clearInterval(pollInterval);
                        clearInterval(timerInterval);
                        document.getElementById('qr-status').textContent = 'Ingelogd! Doorsturen...';
                        document.getElementById('qr-status').className = 'text-sm text-green-600 font-medium';

                        // Redirect to check status endpoint which will set cookie
                        window.location.href = `/auth/qr/${qrCode}/complete`;
                    } else if (data.status === 'expired') {
                        clearInterval(pollInterval);
                        clearInterval(timerInterval);
                        showExpired();
                    }
                } catch (err) {
                    // Ignore poll errors
                }
            }, 2000);
        }

        // Timer countdown
        function startTimer() {
            if (timerInterval) clearInterval(timerInterval);

            timerInterval = setInterval(() => {
                expiresIn--;
                const mins = Math.floor(expiresIn / 60);
                const secs = expiresIn % 60;
                document.getElementById('timer').textContent = `${mins}:${secs.toString().padStart(2, '0')}`;

                if (expiresIn <= 0) {
                    clearInterval(timerInterval);
                    clearInterval(pollInterval);
                    showExpired();
                }
            }, 1000);
        }

        // Show expired state
        function showExpired() {
            const container = document.getElementById('qr-container');
            const status = document.getElementById('qr-status');

            container.innerHTML = '<button onclick="generateQr()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Nieuwe QR code</button>';
            status.textContent = 'QR code verlopen';
            status.className = 'text-sm text-red-600';
            document.getElementById('qr-timer').classList.add('hidden');
        }

        // Toggle password visibility
        function togglePassword() {
            const input = document.getElementById('password');
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        // Show/hide forms
        function showPasswordForm() {
            document.getElementById('qr-section').classList.add('hidden');
            document.getElementById('password-section').classList.remove('hidden');
            clearInterval(pollInterval);
            clearInterval(timerInterval);
        }

        function showQrForm() {
            document.getElementById('password-section').classList.add('hidden');
            document.getElementById('qr-section').classList.remove('hidden');
            generateQr();
        }

        // Initialize QR on load if enabled
        @if($qr_enabled)
        generateQr();
        @endif
    </script>
</body>
</html>
