---
title: "Pattern: beperkingen van de PHP built-in server (php -S / artisan serve)"
type: pattern
scope: havuncore
last_check: 2026-07-15
---

# PHP built-in server: single-threaded op Windows

Geldt voor **elk project dat `php artisan serve` gebruikt** — dev-server,
E2E-suites (Playwright/Cypress), en NativePHP-apps (die starten zelf `php -S`).

## Het probleem

`php artisan serve` draait PHP's ingebouwde webserver. Die kan meerdere workers
gebruiken via `PHP_CLI_SERVER_WORKERS` — **maar dat werkt alleen waar PHP `fork`
heeft**. Op Windows is er geen `pcntl_fork`:

```bash
php -r 'echo PHP_OS_FAMILY, " | fork: ", function_exists("pcntl_fork") ? "ja" : "nee";'
# Windows | fork: nee
```

`PHP_CLI_SERVER_WORKERS` is daar dus een **no-op**. De server is altijd
single-threaded, terwijl een browser 4-6 verbindingen tegelijk opent (HTML, CSS, JS,
afbeeldingen). Gevolgen:

- **Sporadisch `net::ERR_CONNECTION_RESET`** op een willekeurige request.
- **Hangs onder load** → `page.goto`/`page.reload`-timeouts.

## Waaraan je het herkent

Flaky E2E-tests die **individueel wél slagen**. Typisch beeld: een test faalt op
"element niet gevonden" of "0 items", terwijl de pagina er prima uitziet.

Meet het voor je gaat gissen — log de netwerkfouten in je fixture:

```js
page.on('requestfailed', (r) => console.log(`[requestfailed] ${r.url()} :: ${r.failure()?.errorText}`));
page.on('pageerror', (e) => console.log(`[pageerror] ${e.message}`));
```

Faalt de **JS-bundel**, dan start je framework (Alpine/Vue) nooit en is de pagina
functioneel dood — wat zich voordoet als een applicatiebug, maar het niet is.

## Wat níet werkt

| Aanpak | Waarom niet |
|--------|-------------|
| `PHP_CLI_SERVER_WORKERS` verhogen | No-op op Windows (geen fork) |
| `retries` in Playwright | Verbergt het; in productie houdt de gebruiker een lege pagina |
| Keep-alive uitzetten op de client | Niet de oorzaak; getest en verworpen |

## Wat wel

1. **Vang een mislukte asset-load op** (nodig als de app zelf op `php -S` draait,
   zoals NativePHP). Injecteer de mislukte `<script>`/`<link>` opnieuw met een
   cache-buster; enkele pogingen, dan een melding.
   **Geen `location.reload()`** — dat breekt een lopende navigatie af.
2. **Voor echte serverloads**: een server met echte concurrency (php-fpm, Octane,
   FrankenPHP). Niet altijd een optie: NativePHP start zelf `php -S`.
3. **Accepteer de rest**: onder testdruk hangt de server soms. Draai een gefaalde
   test individueel voor je een regressie vermoedt.

## Gezien bij

- **Vusista** (NativePHP): ~2-8% van de E2E-tests rood door gereset asset-loads;
  opgelost met een vangnet in de layout. Zie `Vusista/docs/besluiten/005-assets-vangnet-php-s.md`.

## Bonus-valkuil: exit codes

`... | tail -n` geeft de exit code van `tail`, niet van de testrunner — een rode
suite lijkt dan groen. Lees de `N failed`-regel, niet `$?`.
