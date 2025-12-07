<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Face ID / Vingerafdruk
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Status Card --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-xl mb-6">
                <div class="p-6">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Biometrische Login</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Log in met je gezicht of vingerafdruk</p>
                        </div>
                    </div>

                    <div id="status-message" class="mb-4 p-3 rounded-lg bg-gray-100 dark:bg-gray-700">
                        <p class="text-sm text-gray-600 dark:text-gray-300">Laden...</p>
                    </div>
                </div>
            </div>

            {{-- Passkeys List --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-xl mb-6">
                <div class="p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-4">Geregistreerde Passkeys</h4>
                    <div id="passkeys-list" class="space-y-3">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Laden...</p>
                    </div>
                </div>
            </div>

            {{-- Register Button --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-xl">
                <div class="p-6">
                    <button
                        id="register-btn"
                        class="w-full flex items-center justify-center gap-3 px-6 py-4 bg-gradient-to-r from-purple-500 to-indigo-600 text-white rounded-xl font-semibold text-lg hover:from-purple-600 hover:to-indigo-700 transition-all shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        <span id="register-btn-text">Passkey Toevoegen</span>
                    </button>
                    <p class="text-center text-xs text-gray-500 dark:text-gray-400 mt-3">
                        Werkt met Face ID, Touch ID, Windows Hello of hardware keys
                    </p>
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        const API_BASE = 'https://havuncore.havun.nl/api/auth/webauthn';
        const USER_EMAIL = '{{ auth()->user()->email }}';
        const USER_ID = '{{ auth()->user()->id }}';

        let authToken = localStorage.getItem('havuncore_token');

        function base64UrlEncode(buffer) {
            const base64 = btoa(String.fromCharCode(...new Uint8Array(buffer)));
            return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        }

        function base64UrlDecode(base64url) {
            const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
            const padding = '='.repeat((4 - base64.length % 4) % 4);
            const binary = atob(base64 + padding);
            return Uint8Array.from(binary, c => c.charCodeAt(0));
        }

        function isWebAuthnSupported() {
            return window.PublicKeyCredential !== undefined;
        }

        function setStatus(message, type = 'info') {
            const el = document.getElementById('status-message');
            const colors = {
                info: 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300',
                success: 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300',
                error: 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300',
                warning: 'bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300'
            };
            el.className = 'mb-4 p-3 rounded-lg ' + colors[type];
            el.innerHTML = '<p class="text-sm">' + message + '</p>';
        }

        async function loadPasskeys() {
            if (!authToken) {
                setStatus('Klik op "Passkey Toevoegen" om te beginnen', 'info');
                document.getElementById('passkeys-list').innerHTML = '<p class="text-sm text-gray-500">Nog geen passkeys</p>';
                return;
            }

            try {
                const response = await fetch(API_BASE + '/credentials', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    if (response.status === 401) {
                        localStorage.removeItem('havuncore_token');
                        authToken = null;
                        setStatus('Klik op "Passkey Toevoegen" om te beginnen', 'info');
                    }
                    throw new Error('Failed to load');
                }

                const data = await response.json();
                const list = document.getElementById('passkeys-list');

                if (data.credentials && data.credentials.length > 0) {
                    setStatus('✅ ' + data.credentials.length + ' passkey(s) geregistreerd', 'success');
                    list.innerHTML = data.credentials.map(function(cred) {
                        return '<div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">' +
                            '<div class="flex items-center gap-3">' +
                                '<div class="w-10 h-10 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">' +
                                    '<svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>' +
                                '</div>' +
                                '<div>' +
                                    '<p class="font-medium text-gray-900 dark:text-white">' + (cred.name || 'Passkey') + '</p>' +
                                    '<p class="text-xs text-gray-500 dark:text-gray-400">Toegevoegd: ' + new Date(cred.created_at).toLocaleDateString('nl-NL') + '</p>' +
                                '</div>' +
                            '</div>' +
                            '<button onclick="deletePasskey(\'' + cred.id + '\')" class="text-red-500 hover:text-red-700 p-2">' +
                                '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>' +
                            '</button>' +
                        '</div>';
                    }).join('');
                } else {
                    setStatus('Nog geen passkeys geregistreerd', 'info');
                    list.innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400">Voeg een passkey toe om in te loggen met Face ID of vingerafdruk</p>';
                }
            } catch (error) {
                console.error('Error loading passkeys:', error);
                document.getElementById('passkeys-list').innerHTML = '<p class="text-sm text-gray-500">Geen passkeys gevonden</p>';
            }
        }

        async function registerPasskey() {
            const btn = document.getElementById('register-btn');
            const btnText = document.getElementById('register-btn-text');

            btn.disabled = true;
            btnText.textContent = 'Bezig...';

            try {
                if (!authToken) {
                    setStatus('🔐 Authenticeren...', 'info');

                    const authResponse = await fetch('https://havuncore.havun.nl/api/auth/token-for-email', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            email: USER_EMAIL,
                            app: window.location.hostname,
                            user_id: USER_ID
                        })
                    });

                    if (authResponse.ok) {
                        const authData = await authResponse.json();
                        authToken = authData.token;
                        localStorage.setItem('havuncore_token', authToken);
                    } else {
                        throw new Error('Authenticatie mislukt');
                    }
                }

                setStatus('📱 Passkey registratie starten...', 'info');

                const optionsResponse = await fetch(API_BASE + '/register-options', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Accept': 'application/json'
                    }
                });

                if (!optionsResponse.ok) throw new Error('Kon opties niet ophalen');

                const options = await optionsResponse.json();

                options.challenge = base64UrlDecode(options.challenge);
                options.user.id = base64UrlDecode(options.user.id);
                if (options.excludeCredentials) {
                    options.excludeCredentials = options.excludeCredentials.map(function(cred) {
                        return Object.assign({}, cred, { id: base64UrlDecode(cred.id) });
                    });
                }

                setStatus('👆 Bevestig met Face ID of vingerafdruk...', 'info');

                const credential = await navigator.credentials.create({ publicKey: options });

                const credentialData = {
                    id: credential.id,
                    rawId: base64UrlEncode(credential.rawId),
                    type: credential.type,
                    response: {
                        clientDataJSON: base64UrlEncode(credential.response.clientDataJSON),
                        attestationObject: base64UrlEncode(credential.response.attestationObject)
                    },
                    name: detectDeviceName()
                };

                const registerResponse = await fetch(API_BASE + '/register', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ credential: credentialData, name: credentialData.name })
                });

                if (!registerResponse.ok) throw new Error('Registratie mislukt');

                setStatus('✅ Passkey succesvol geregistreerd!', 'success');
                loadPasskeys();

            } catch (error) {
                console.error('Registration error:', error);
                if (error.name === 'NotAllowedError') {
                    setStatus('❌ Geannuleerd', 'error');
                } else {
                    setStatus('❌ Fout: ' + error.message, 'error');
                }
            } finally {
                btn.disabled = false;
                btnText.textContent = 'Passkey Toevoegen';
            }
        }

        async function deletePasskey(id) {
            if (!confirm('Passkey verwijderen?')) return;

            try {
                const response = await fetch(API_BASE + '/credentials/' + id, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    setStatus('✅ Passkey verwijderd', 'success');
                    loadPasskeys();
                } else {
                    throw new Error('Verwijderen mislukt');
                }
            } catch (error) {
                setStatus('❌ Fout: ' + error.message, 'error');
            }
        }

        function detectDeviceName() {
            const ua = navigator.userAgent;
            if (/iPhone/.test(ua)) return 'iPhone';
            if (/iPad/.test(ua)) return 'iPad';
            if (/Android/.test(ua)) return 'Android';
            if (/Windows/.test(ua)) return 'Windows PC';
            if (/Mac/.test(ua)) return 'Mac';
            return 'Apparaat';
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (!isWebAuthnSupported()) {
                setStatus('❌ Je browser ondersteunt geen passkeys', 'error');
                document.getElementById('register-btn').disabled = true;
                return;
            }

            document.getElementById('register-btn').addEventListener('click', registerPasskey);
            loadPasskeys();
        });
    </script>
    @endpush
</x-app-layout>
