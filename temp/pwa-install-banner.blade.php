{{-- PWA Install Banner - Only shown on desktop --}}
<div id="pwa-install-banner" class="hidden md:block bg-gradient-to-r from-purple-600 to-indigo-600 text-white p-4 rounded-xl mb-6 shadow-lg">
    <div class="flex items-start gap-4">
        <div class="flex-shrink-0">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
            </div>
        </div>
        <div class="flex-1">
            <h3 class="font-bold text-lg mb-1">📱 Installeer de app op je telefoon</h3>
            <p class="text-white/90 text-sm mb-3">
                Scan straks de QR code met de app en je bent met 1 tap ingelogd. Geen wachtwoord nodig!
            </p>
            <div class="flex flex-wrap gap-2">
                <a href="https://staging.herdenkingsportaal.nl" target="_blank"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-white text-purple-700 rounded-lg text-sm font-medium hover:bg-gray-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                    Open op telefoon
                </a>
                <button onclick="document.getElementById('pwa-install-banner').style.display='none'"
                        class="px-3 py-2 text-white/70 hover:text-white text-sm transition">
                    Later
                </button>
            </div>
        </div>
    </div>
    <div class="mt-4 pt-4 border-t border-white/20">
        <p class="text-white/80 text-xs">
            <strong>Tip:</strong> Open deze link op je telefoon → tap "Toevoegen aan startscherm" → klaar!
        </p>
    </div>
</div>
