# Laravel i18n Pattern — Meertaligheid

> **Status:** Bewezen in productie (JudoToernooi)
> **Categorie:** Herbruikbaar pattern voor ALLE Laravel projecten
> **Regel:** Elk nieuw project start met i18n, ook als het alleen Nederlands is

---

## Waarom altijd i18n, zelfs bij 1 taal?

- **Kosten nu:** 5 minuten setup
- **Kosten later (zonder i18n):** dagen refactoring door hardcoded strings in 100+ templates
- **SaaS-mindset:** klanten kunnen internationaal zijn, je wilt niet alles herschrijven

---

## Setup (bij elk nieuw Laravel project)

### 1. Config (`config/app.php`)

```php
'locale' => env('APP_LOCALE', 'nl'),
'fallback_locale' => env('APP_FALLBACK_LOCALE', 'nl'),
'available_locales' => ['nl'],  // Voeg talen toe wanneer nodig
```

### 2. Vertaalbestand aanmaken

```
lang/
└── nl.json    ← Start hiermee (leeg object: {})
```

Bij toevoegen van een tweede taal:
```
lang/
├── nl.json    ← Keys = Nederlands (default)
└── en.json    ← Values = Engelse vertalingen
```

**Structuur:** Flat JSON, key = default taal tekst:
```json
{
    "Opslaan": "Save",
    "Gegevens opgeslagen.": "Data saved.",
    ":count items gevonden": ":count items found"
}
```

### 3. In Blade templates: ALTIJD `__()` gebruiken

```blade
{{-- GOED --}}
<h1>{{ __('Dashboard') }}</h1>
<label>{{ __('E-mailadres') }}</label>
<button>{{ __('Opslaan') }}</button>
{{ __(':count resultaten', ['count' => $total]) }}

{{-- FOUT — hardcoded strings --}}
<h1>Dashboard</h1>
<label>E-mailadres</label>
```

**Regel:** Elke string die de gebruiker ziet → `__()`. Geen uitzonderingen.

### 4. Middleware (pas nodig bij 2+ talen)

```php
// app/Http/Middleware/SetLocale.php
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = session('locale', config('app.locale'));

        if (in_array($locale, config('app.available_locales', ['nl']))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
```

### 5. Taalwissel route (pas nodig bij 2+ talen)

```php
// routes/web.php
Route::post('/locale/{locale}', function (Request $request, string $locale) {
    if (in_array($locale, config('app.available_locales', ['nl']))) {
        $request->session()->put('locale', $locale);
    }
    return redirect()->back();
})->name('locale.switch');
```

### 6. Taalwissel UI component (pas nodig bij 2+ talen)

```blade
{{-- resources/views/components/locale-switcher.blade.php --}}
@if(count(config('app.available_locales', ['nl'])) > 1)
<div x-data="{ open: false }" class="relative">
    <button @click="open = !open" class="text-gray-500 hover:text-gray-700">
        {{ strtoupper(app()->getLocale()) }}
    </button>
    <div x-show="open" @click.away="open = false"
         class="absolute right-0 bg-white rounded-lg shadow-lg py-1 z-50">
        @foreach(config('app.available_locales') as $locale)
            <form action="{{ route('locale.switch', $locale) }}" method="POST">
                @csrf
                <button type="submit" class="block w-full px-4 py-2 text-left hover:bg-gray-100
                    {{ app()->getLocale() === $locale ? 'font-bold' : '' }}">
                    {{ $locale === 'nl' ? 'Nederlands' : 'English' }}
                </button>
            </form>
        @endforeach
    </div>
</div>
@endif
```

---

## Database locale (optioneel, bij multi-tenant)

Als gebruikers/tenants hun taalvoorkeur moeten bewaren:

```php
// Migration
Schema::table('users', function (Blueprint $table) {
    $table->string('locale', 5)->nullable();
});
```

Middleware cascade (meest specifiek eerst):
```
Session → User locale → Tenant locale → config default
```

---

## Checklist nieuw project

- [ ] `config/app.php`: `locale`, `fallback_locale`, `available_locales`
- [ ] `lang/nl.json` aangemaakt (kan leeg `{}` zijn)
- [ ] Alle user-facing strings gebruiken `__()`
- [ ] `.env`: `APP_LOCALE=nl`

## Checklist tweede taal toevoegen

- [ ] `lang/en.json` aanmaken met vertalingen
- [ ] `available_locales` updaten in config
- [ ] `SetLocale` middleware toevoegen
- [ ] Taalwissel route toevoegen
- [ ] Locale switcher component in layout
- [ ] Database `locale` kolom (indien persistent nodig)

---

## Referentie

- **Laravel docs:** https://laravel.com/docs/11.x/localization
- **Bewezen in:** JudoToernooi (2480 keys, 227 templates, 2 talen)
- **Geen dependencies nodig** — puur Laravel framework

---

*Aangemaakt: 15 maart 2026*
