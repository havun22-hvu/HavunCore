<nav x-data="{ open: false, colorOpen: false }" class="border-b-2 border-purple-700 dark:border-purple-600 shadow-sm relative md:sticky {{ !app()->environment('production') ? 'md:top-5 sm:md:top-7' : 'md:top-0' }} md:z-40" style="background-color: var(--color-header); transition: background-color 0.3s ease;">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16 md:h-20 relative">
            <!-- Logo - Centered on mobile, left on desktop -->
            <div class="absolute sm:relative left-1/2 sm:left-0 transform -translate-x-1/2 sm:translate-x-0 flex-shrink-0">
                <a href="{{ route('public.index') }}" class="flex items-center">
                    <x-logo />
                </a>
            </div>

            <!-- Centered Title (hidden on mobile) -->
            <div class="hidden sm:block absolute left-1/2 transform -translate-x-1/2">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white whitespace-nowrap">
                    Herdenkingsportaal
                </h1>
            </div>

            <!-- Right Side: User Welcome + Color Picker + Theme Toggle + Hamburger Menu -->
            <div class="flex items-center gap-3 relative ml-auto">
                <!-- User Welcome Text -->
                @auth
                @php
                    // Detect current memorial from route (route model binding gives us Memorial object)
                    $currentMemorial = null;
                    $routeMemorial = request()->route('memorial');
                    if ($routeMemorial && is_object($routeMemorial)) {
                        $currentMemorial = $routeMemorial;
                    } elseif (request()->route('uuid')) {
                        $currentMemorial = \App\Models\Memorial::where('uuid', request()->route('uuid'))->first();
                    }
                @endphp
                <div class="hidden sm:block text-sm text-gray-700 dark:text-gray-300">
                    Welkom <span class="font-semibold">{{ Auth::user()->getDisplayNameForMemorial($currentMemorial) }}</span>
                </div>
                @endauth

                <!-- Color Scheme Picker Button (alleen in light mode) - VOOR theme toggle -->
                <div class="relative block dark:hidden">
                    <button @click="colorOpen = !colorOpen"
                            type="button"
                            id="color-scheme-current"
                            class="w-8 h-8 rounded-full border-2 border-gray-300 hover:border-purple-500 transition-all shadow-sm hover:shadow-md"
                            style="background: linear-gradient(135deg, var(--color-bg-1), var(--color-bg-3));"
                            title="Wijzig kleurenschema">
                    </button>

                    <!-- Color Scheme Dropdown -->
                    <div x-show="colorOpen"
                         @click.away="colorOpen = false"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute top-full right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50 p-3"
                         style="display: none;">
                        <div class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2 px-1">Kies je kleurenschema:</div>
                        <div class="grid grid-cols-3 gap-2">
                            <!-- Warm Beige -->
                            <button onclick="setColorScheme('beige')"
                                    class="color-scheme-option group flex flex-col items-center gap-1 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <div class="w-10 h-10 rounded-full border-2 border-gray-300 dark:border-gray-600 group-hover:border-purple-500 transition" style="background: linear-gradient(135deg, #F5F1E8, #FFFCF5);"></div>
                                <span class="text-[10px] text-gray-600 dark:text-gray-400">Beige</span>
                            </button>

                            <!-- Zachte Salie (groen) -->
                            <button onclick="setColorScheme('sage')"
                                    class="color-scheme-option group flex flex-col items-center gap-1 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <div class="w-10 h-10 rounded-full border-2 border-gray-300 dark:border-gray-600 group-hover:border-purple-500 transition" style="background: linear-gradient(135deg, #E8F0E3, #F7FCF5);"></div>
                                <span class="text-[10px] text-gray-600 dark:text-gray-400">Salie</span>
                            </button>

                            <!-- Lavendel (paars) -->
                            <button onclick="setColorScheme('lavender')"
                                    class="color-scheme-option group flex flex-col items-center gap-1 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <div class="w-10 h-10 rounded-full border-2 border-gray-300 dark:border-gray-600 group-hover:border-purple-500 transition" style="background: linear-gradient(135deg, #F0E8F5, #FAF7FC);"></div>
                                <span class="text-[10px] text-gray-600 dark:text-gray-400">Lavendel</span>
                            </button>

                            <!-- Blauwe Mist -->
                            <button onclick="setColorScheme('mist')"
                                    class="color-scheme-option group flex flex-col items-center gap-1 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <div class="w-10 h-10 rounded-full border-2 border-gray-300 dark:border-gray-600 group-hover:border-purple-500 transition" style="background: linear-gradient(135deg, #E8EDF3, #F7F9FC);"></div>
                                <span class="text-[10px] text-gray-600 dark:text-gray-400">Mist</span>
                            </button>

                            <!-- Roze Parel -->
                            <button onclick="setColorScheme('pearl')"
                                    class="color-scheme-option group flex flex-col items-center gap-1 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <div class="w-10 h-10 rounded-full border-2 border-gray-300 dark:border-gray-600 group-hover:border-purple-500 transition" style="background: linear-gradient(135deg, #F8E8EF, #FFF7FA);"></div>
                                <span class="text-[10px] text-gray-600 dark:text-gray-400">Parel</span>
                            </button>

                            <!-- Gouden Zand -->
                            <button onclick="setColorScheme('sand')"
                                    class="color-scheme-option group flex flex-col items-center gap-1 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <div class="w-10 h-10 rounded-full border-2 border-gray-300 dark:border-gray-600 group-hover:border-purple-500 transition" style="background: linear-gradient(135deg, #F5EBD8, #FFFCF5);"></div>
                                <span class="text-[10px] text-gray-600 dark:text-gray-400">Zand</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Theme Toggle Button (blijft altijd op dezelfde plek) -->
                <button id="theme-toggle"
                        type="button"
                        class="p-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                        title="Toggle theme">
                    <!-- Light mode icon -->
                    <svg class="theme-icon theme-light w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    <!-- Dark mode icon -->
                    <svg class="theme-icon theme-dark w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                    </svg>
                    <!-- System mode icon -->
                    <svg class="theme-icon theme-system w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </button>

                <!-- Hamburger Menu Button (blijft altijd op dezelfde plek) -->
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Responsive Navigation Menu -->
                @auth
                <div x-show="open"
                     @click.away="open = false"
                     class="absolute top-full right-0 mt-2 w-64 bg-white dark:bg-gray-800 shadow-lg z-50 border border-purple-700 dark:border-purple-600 rounded-lg"
                     style="display: none;">
        <div class="pt-2 pb-3 space-y-1">
            <!-- Navigation Links -->
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                📊 {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('auth.qr-scanner')" class="md:hidden">
                📱 {{ __('Scan om in te loggen') }}
            </x-responsive-nav-link>
            @php
                $latestMemorial = auth()->user()->memorials()->latest()->first();
            @endphp
            @if($latestMemorial)
                <!-- Mobile: Direct naar laatste memorial -->
                <x-responsive-nav-link :href="route('memorial.show', $latestMemorial->uuid)" :active="request()->routeIs('memorial-overzicht.*')" class="md:hidden">
                    {{ __('Mijn Memorials') }}
                </x-responsive-nav-link>
                <!-- Desktop: Naar overzicht -->
                <x-responsive-nav-link :href="route('memorial-overzicht.index')" :active="request()->routeIs('memorial-overzicht.*')" class="hidden md:block">
                    {{ __('Mijn Memorials') }}
                </x-responsive-nav-link>
            @else
                <x-responsive-nav-link :href="route('memorial-overzicht.index')" :active="request()->routeIs('memorial-overzicht.*')">
                    {{ __('Mijn Memorials') }}
                </x-responsive-nav-link>
            @endif
            <x-responsive-nav-link :href="route('help.index')" :active="request()->routeIs('help.*')">
                {{ __('❓ Help') }}
            </x-responsive-nav-link>
            @if(auth()->check() && auth()->user()->isAdmin())
                <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.*')">
                    {{ __('⚙️ Admin Dashboard') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- User Menu in Dropdown -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-700">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->getDisplayNameForMemorial($currentMemorial ?? null) }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
                </div>
                @endauth

                <!-- Guest Navigation Menu -->
                @guest
                <div x-show="open"
                     @click.away="open = false"
                     class="absolute top-full right-0 mt-2 w-64 bg-white dark:bg-gray-800 shadow-lg z-50 border border-purple-700 dark:border-purple-600 rounded-lg"
                     style="display: none;">
        <div class="pt-2 pb-3 space-y-1">
            <!-- Public Links -->
            <x-responsive-nav-link :href="route('public.index')" :active="request()->routeIs('public.index')">
                {{ __('🏠 Hoofdpagina') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('public.search')" :active="request()->routeIs('public.search')">
                {{ __('🔍 Zoek Memorials') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('help.index')" :active="request()->routeIs('help.*')">
                {{ __('❓ Help') }}
            </x-responsive-nav-link>
        </div>

        <!-- Guest Authentication Links -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-700">
            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('login')">
                    {{ __('🔐 Inloggen') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('register')">
                    {{ __('📝 Registreren') }}
                </x-responsive-nav-link>
            </div>
        </div>
                </div>
                @endguest
            </div>
        </div>
    </div>
</nav>
