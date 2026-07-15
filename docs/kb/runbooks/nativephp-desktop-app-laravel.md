---
title: NativePHP — Laravel als Windows desktop-app
type: runbook
scope: havuncore
last_check: 2026-07-15
---

# NativePHP — Laravel als Windows desktop-app

> **Herkomst:** Vusista, 14-15 juli 2026 (eerste NativePHP-project binnen Havun).
> Doel: een Laravel-app als installeerbare desktop-app (.exe) uitleveren aan
> gebruikers zonder PHP/terminal-kennis.

## 1. PHP-versie (struikelblok #1)

NativePHP 1.x vereist **PHP ^8.3**; Laragon-default is 8.2. Zet een tweede PHP
ernaast in plaats van de default te wijzigen (andere projecten blijven werken):

```bash
# download php-8.4.x-Win32-vs17-x64.zip (Thread Safe!) van windows.php.net
# checksum verifiëren tegen https://downloads.php.net/~windows/releases/sha256sum.txt
unzip php84.zip -d /c/laragon/bin/php/php-8.4.23-Win32-vs17-x64

# php.ini van php.ini-development + extensies aanzetten:
# curl, fileinfo, mbstring, openssl, pdo_sqlite, sqlite3, zip, gd, exif, intl
# en extension_dir naar het absolute ext-pad
```

Per project gebruiken via PATH-prefix:
```bash
export PATH="/c/laragon/bin/php/php-8.4.23-Win32-vs17-x64:$PATH"
```
Zet ook `"php": "^8.3"` in `composer.json` (anders installeert composer 1.x-incompatibele versies).

## 2. Installatie

```bash
composer require nativephp/electron livewire/livewire
php artisan native:install --force --no-interaction   # publiceert config + npm-deps
```

Als `native:install` faalt met "There are no commands defined in the native namespace":
`composer dump-autoload && php artisan config:clear` en opnieuw. Bij een
"Could not delete …php-8.3.zip"-fout (virusscanner/indexer): commando gewoon herhalen.

## 3. Configuratie

- `config/nativephp.php`: `app_id` (reverse domain), `author`, `description`, `website`.
- `app/Providers/NativeAppServiceProvider.php` → `boot()` opent het venster:
  ```php
  Window::open()->title('Appnaam')->width(1280)->height(800)
      ->minWidth(1024)->minHeight(640)->rememberState();
  ```
- **`APP_NAME` bepaalt de naam van de app én de installer** (`Laravel-1.0.0-setup.exe`
  als je het vergeet). Vereist .env-wijziging → in Havun-context: eerst overleggen.

## 4. Wat NativePHP zelf regelt

- **SQLite**: `NativeServiceProvider` herschrijft `database.default` naar een SQLite-DB in
  app-data (in dev: `database/nativephp.sqlite`, automatisch aangemaakt + gemigreerd),
  zet WAL + busy_timeout aan. Zet deze file in `.gitignore`.
- **Queue-workers**: `config/nativephp.php` → `queue_workers` start ze automatisch mee.
  Achtergrondwerk (scans, thumbnails) werkt dus zonder extra opzet.
- **Disks**: `NATIVEPHP_APP_DATA_PATH` e.d. worden als filesystem-disks geregistreerd.
  Lees het pad via config, niet via `env()` (dat breekt bij `config:cache`).
- **Runtime-detectie**: er is géén publieke API; `config('nativephp-internal.running')` is
  de key. Wikkel dit in één eigen helper-class zodat de rest van de app het niet kent.

## 5. Bouwen

```bash
php artisan native:build win x64     # ALTIJD os + arch meegeven
```

Zonder argumenten stelt het commando een **interactieve vraag** ("Please select Processor
Architecture") — in een non-interactieve shell hangt de build dan stil zonder output.
Resultaat: `dist/<AppNaam>-<versie>-setup.exe` (~110 MB; PHP + Electron zitten erin).

Let op: de dev-server (`native:serve`) en een build tegelijk botsen — stop de eerste.

## 6. Alpine-componenten: bundel Livewire's Alpine zelf

**Struikelblok #2, kostte een avond debuggen in Vusista.** Met de standaard
`inject_assets = true` start Livewire Alpine zodra zijn eigen script draait. Laadt
jouw `app.js` (via `@vite`, dus een module) net iets later, dan is `alpine:init`
al gevuurd en worden je `Alpine.data(...)`-componenten **nooit geregistreerd** —
een leeg scherm, afhankelijk van laadtiming. Symptoom: `mediaGrid is not defined`
in de console, en tests die los slagen maar in een suite willekeurig omvallen.

Deterministische opzet:

```php
// config/livewire.php
'inject_assets' => false,
```
```blade
{{-- layout --}}
<head>@livewireStyles @vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>{{ $slot }} @livewireScriptConfig</body>
```
```js
// resources/js/app.js
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import registerComponents from './components';

registerComponents(Alpine);   // vóór de start: gegarandeerd geregistreerd
Livewire.start();
```

Let ook op: zet **geen dynamische waarden in `x-data`** (bv. een URL die per
navigatie verandert). Livewire's morph patcht het attribuut, Alpine
herinitialiseert het component en je krijgt dubbele fetches en verdwijnende
state. Geef zulke waarden mee via een `data-*`-attribuut en lees ze in `init()`.

## 7. E2E met Playwright (zie ook `playwright-e2e-laravel.md`)

Twee dingen die specifiek zijn voor een NativePHP/SQLite-project:

- **Reset de test-database vanuit het serverproces**, niet van buitenaf. Een
  `migrate:fresh` via `execSync` trekt het SQLite-bestand onder de draaiende
  `artisan serve`-workers weg → willekeurig rode tests. Registreer in
  `routes/web.php` een route achter `app()->environment('e2e')` die
  `Artisan::call('migrate:fresh', [...])` doet, en roep die aan per test.
- **`PHP_CLI_SERVER_WORKERS`**: met 1 worker blokkeert een trage request (bv.
  thumbnail-generatie) je API-calls; met meerdere workers ziet de reset-route
  niet dezelfde state. Kies 1 worker + warm de cache in de seeder.

## 8. Aandachtspunten voor uitlevering

- **Ongetekende installer** → Windows SmartScreen waarschuwt ("onbekende uitgever").
  Code-signing-certificaat kost geld: businessbeslissing, geen technische.
- **Meegeleverde binaries** (exiftool, ffmpeg): zet ze in `resources/` en houd rekening
  met licenties bij distributie (ffmpeg-builds zijn GPL of LGPL — check de variant).
- **Zware PHP-extensies vermijden**: imagick/libheif meeleveren is bewerkelijk; ffmpeg als
  losse binary dekt HEIC-decode óók en scheelt een extensie in de installer.

## Gerelateerd

- Referentie-implementatie: `D:\GitHub\Vusista`
- Pattern: `docs/kb/patterns/exiftool-veilig-metadata-schrijven.md`
