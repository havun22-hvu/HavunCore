<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Scan om in te loggen
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-md mx-auto px-4">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl rounded-2xl">
                <!-- Scanner Container -->
                <div id="scanner-container" class="relative">
                    <video id="scanner-video" class="w-full aspect-square object-cover rounded-t-2xl"></video>

                    <!-- Overlay with scanning frame -->
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-48 h-48 border-4 border-white rounded-2xl shadow-lg"></div>
                    </div>

                    <!-- Status indicator -->
                    <div id="scanner-status" class="absolute bottom-4 left-0 right-0 text-center">
                        <span class="bg-black/50 text-white px-4 py-2 rounded-full text-sm">
                            Richt camera op QR code
                        </span>
                    </div>
                </div>

                <!-- Success State (hidden by default) -->
                <div id="success-state" class="hidden p-8 text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Ingelogd!</h3>
                    <p id="success-device" class="text-gray-600 dark:text-gray-400"></p>
                </div>

                <!-- Error State (hidden by default) -->
                <div id="error-state" class="hidden p-8 text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Oeps!</h3>
                    <p id="error-message" class="text-gray-600 dark:text-gray-400 mb-4"></p>
                    <button onclick="restartScanner()" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Opnieuw proberen
                    </button>
                </div>

                <!-- Info -->
                <div id="info-section" class="p-6 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-600 dark:text-gray-400 text-center">
                        Scan de QR code op het inlogscherm van je computer om daar automatisch in te loggen.
                    </p>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://unpkg.com/qr-scanner@1.4.2/qr-scanner.umd.min.js"></script>
    <script>
        const HAVUNCORE_API = 'https://havuncore.havun.nl';
        const USER_EMAIL = '{{ auth()->user()->email }}';
        let qrScanner = null;

        document.addEventListener('DOMContentLoaded', () => {
            initScanner();
        });

        function initScanner() {
            const video = document.getElementById('scanner-video');

            qrScanner = new QrScanner(video, result => {
                handleScan(result.data);
            }, {
                highlightScanRegion: true,
                highlightCodeOutline: true,
            });

            qrScanner.start().catch(err => {
                console.error('Camera error:', err);
                showError('Geen toegang tot camera. Geef toestemming in je browserinstellingen.');
            });
        }

        async function handleScan(data) {
            // Check if it's a valid HavunCore approve URL
            if (!data.includes('havuncore.havun.nl/approve')) {
                return; // Ignore non-HavunCore QR codes
            }

            // Stop scanning
            qrScanner.stop();

            // Extract token from URL
            const url = new URL(data);
            const token = url.searchParams.get('token');

            if (!token) {
                showError('Ongeldige QR code');
                return;
            }

            // Update status
            document.getElementById('scanner-status').innerHTML =
                '<span class="bg-purple-500 text-white px-4 py-2 rounded-full text-sm">Bezig met inloggen...</span>';

            try {
                // Send approve request with user's email (trusted from session)
                const response = await fetch(HAVUNCORE_API + '/api/auth/qr/approve-from-app', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        token: token,
                        email: USER_EMAIL
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showSuccess(result.device_name || 'Onbekend apparaat');
                } else {
                    showError(result.message || 'Kon niet inloggen');
                }
            } catch (err) {
                console.error('Approve error:', err);
                showError('Verbindingsfout. Probeer opnieuw.');
            }
        }

        function showSuccess(deviceName) {
            document.getElementById('scanner-container').classList.add('hidden');
            document.getElementById('info-section').classList.add('hidden');
            document.getElementById('success-state').classList.remove('hidden');
            document.getElementById('success-device').textContent = 'Je bent nu ingelogd op: ' + deviceName;
        }

        function showError(message) {
            document.getElementById('scanner-container').classList.add('hidden');
            document.getElementById('info-section').classList.add('hidden');
            document.getElementById('error-state').classList.remove('hidden');
            document.getElementById('error-message').textContent = message;
        }

        function restartScanner() {
            document.getElementById('error-state').classList.add('hidden');
            document.getElementById('scanner-container').classList.remove('hidden');
            document.getElementById('info-section').classList.remove('hidden');
            document.getElementById('scanner-status').innerHTML =
                '<span class="bg-black/50 text-white px-4 py-2 rounded-full text-sm">Richt camera op QR code</span>';
            qrScanner.start();
        }
    </script>
    @endpush
</x-app-layout>
