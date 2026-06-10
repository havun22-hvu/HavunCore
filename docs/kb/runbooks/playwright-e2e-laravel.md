---
title: Playwright E2E — Laravel + Blade projecten (blauwdruk)
type: runbook
scope: alle-projecten
last_check: 2026-06-11
---

# Playwright E2E — Laravel + Blade (blauwdruk)

> Herbruikbaar recept om end-to-end browsertests op te zetten in een
> **server-rendered Laravel-project** (Blade). Bedoeld voor o.a. JudoToernooi en
> Herdenkingsportaal. Volg dit in een **eigen project-sessie** (scope-regel) —
> niet vanuit HavunCore.
>
> Zustertechniek voor SPA's/PWA's: `playwright-e2e-webapp.md`.

## Kernverschil met de PWA-aanpak

De webapp-PWA kon je volledig **API-mocken** (React haalt alles via fetch). Een
Blade-app rendert HTML op de server, dus daar valt niets te mocken — je test
**tegen een echt draaiende Laravel-app met een test-database**. Gevolg:

| | PWA (React) | Laravel + Blade |
|---|---|---|
| Data | API-mock (route-interception) | echte **test-database** (migrate + seed) |
| Server | alleen Vite dev | `php artisan serve` (test-env) |
| Auth | localStorage-seed | sessie-cookie via `storageState` |
| CSRF | n.v.t. (gemockt) | werkt vanzelf (echte forms) |

## ⛔ Regel #1 — database-isolatie

E2E **muteert** data (registreren, aanmaken, verwijderen). Draai **NOOIT** tegen
de dev- of prod-database. Verplicht een aparte test-DB via een eigen env-file:

- SQLite-bestand (`database/e2e.sqlite`) — simpelst, snel weg te gooien.
- of een aparte MySQL-database `*_e2e` — als het project MySQL-specifieke dingen doet.

Nooit `.env` aanpassen; maak `.env.e2e` (zie onder). Dit is een SaaS-norm: het
recept moet veilig zijn voor élk project, niet net-aan goed voor één.

## Mapstructuur

```
<laravel-project>/
  e2e/
    playwright.config.js
    global-setup.js        # logt één keer in → bewaart sessie-cookie
    fixtures.js            # gedeelde helpers / test-data
    *.spec.js
  package.json             # los Node-eiland naast composer (alleen E2E-deps)
  .env.e2e                 # test-database + app-config (NIET .env)
```

Houd de E2E-`package.json` los van een eventuele asset-build-`package.json` om
dependency-botsingen te vermijden, tenzij het project er al één heeft.

## .env.e2e (voorbeeld — SQLite)

```env
APP_ENV=e2e
APP_KEY=base64:...        # php artisan key:generate --env=e2e
APP_URL=http://127.0.0.1:8001
DB_CONNECTION=sqlite
DB_DATABASE=/abs/pad/database/e2e.sqlite
SESSION_DRIVER=database   # of file; niet 'array' (cookies moeten overleven)
MAIL_MAILER=array
QUEUE_CONNECTION=sync
# externe integraties (betaal/SMS/etc.) → fake/sandbox keys, nooit live
```

## playwright.config.js

De `webServer` start Laravel mét de test-env. `globalSetup` regelt auth.

```js
import { defineConfig, devices } from '@playwright/test';

const PORT = 8001; // niet de dev-poort — zie poort-register
const baseURL = `http://127.0.0.1:${PORT}`;

export default defineConfig({
  testDir: '.',
  globalSetup: './global-setup.js',
  fullyParallel: false,   // server-rendered + gedeelde test-DB → serieel is veilig
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: process.env.CI ? [['html', { open: 'never' }], ['list']] : 'list',
  timeout: 30_000,
  use: {
    baseURL,
    storageState: 'e2e/.auth/state.json', // gevuld door global-setup
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: {
    // Verse test-DB bij elke run, dan serven met de e2e-env.
    command:
      'php artisan migrate:fresh --seed --env=e2e --force && ' +
      'php artisan serve --env=e2e --port=8001',
    url: baseURL,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});
```

> `migrate:fresh --seed` geeft elke run een schone, deterministische staat. Maak
> een `E2ESeeder` met bekende accounts/data (bv. een testgebruiker waarvan
> global-setup de inlog kent).

## global-setup.js — één keer inloggen

Login via de **echte** loginpagina (test meteen dat die werkt), bewaar de
sessie-cookie, en hergebruik die in alle specs via `storageState`.

```js
import { chromium } from '@playwright/test';

export default async function globalSetup() {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('http://127.0.0.1:8001/login');
  await page.getByLabel('E-mail').fill('e2e@havun.nl');     // uit E2ESeeder
  await page.getByLabel('Wachtwoord').fill('e2e-password');
  await page.getByRole('button', { name: /Inloggen/ }).click();
  await page.waitForURL('**/dashboard');                    // bevestig succes
  await page.context().storageState({ path: 'e2e/.auth/state.json' });
  await browser.close();
}
```

CSRF werkt vanzelf: Playwright vult het echte formulier in, inclusief het
verborgen `_token`-veld dat Blade rendert.

## Wat te testen (laag-1 eerst)

Volg `reference/test-quality-policy.md`: dek de **kritieke flows** af, geen
smoke-padding. Typisch voor deze projecten:

- **Auth** — login (goed/fout wachtwoord), logout, geblokkeerd na X pogingen.
- **Kernflow van het project** — bv. JudoToernooi: toernooi aanmaken → deelnemer
  inschrijven → poule/score; Herdenkingsportaal: herdenking aanmaken → publieke
  pagina toont die.
- **Autorisatie** — gebruiker A ziet niet de data van gebruiker B (policies).

Assert de **waarneembare uitkomst** (record zichtbaar op pagina, redirect,
flash-melding), niet "pagina laadde".

## CI — GitHub Actions (skelet)

```yaml
name: E2E
on:
  push: { branches: [main, master] }
  pull_request: { branches: [main, master] }
jobs:
  e2e:
    runs-on: ubuntu-latest
    timeout-minutes: 20
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.2', extensions: 'sqlite3, pdo_sqlite' }
      - run: composer install --no-interaction --prefer-dist --no-progress
      - run: cp .env.e2e.example .env.e2e && php artisan key:generate --env=e2e --force
      - uses: actions/setup-node@v4
        with: { node-version: '20', cache: npm, cache-dependency-path: e2e/package-lock.json }
      - run: npm ci
        working-directory: e2e
      - run: npx playwright install --with-deps chromium
        working-directory: e2e
      # Als het project front-end assets bouwt (Vite/Mix), bouw ze vóór serve:
      # - run: npm ci && npm run build
      - run: npx playwright test
        working-directory: e2e
      - uses: actions/upload-artifact@v4
        if: ${{ !cancelled() }}
        with: { name: playwright-report, path: e2e/playwright-report/, retention-days: 7 }
```

## Valkuilen

1. **Database-isolatie** (§Regel #1) — het allerbelangrijkste. Verkeerde DB =
   dataverlies. Altijd `.env.e2e` + aparte test-DB.
2. **Asset-build** — als Blade gecompileerde Vite/Mix-assets verwacht, draai
   `npm run build` vóór `artisan serve`, anders mist de pagina JS/CSS.
3. **Poortkeuze** — niet de dev-poort; check `reference/poort-register.md` en kies
   een vrije E2E-poort zodat een draaiende dev-server niet botst.
4. **Serieel** (`workers:1`) — een gedeelde test-DB verdraagt geen parallelle
   mutaties. Wil je parallel, geef elke worker een eigen DB (complex; meestal niet nodig).
5. **Tijd/locale** — Laravel-app op UTC vs NL-weergave; assert op stabiele
   tekst, niet op exacte tijdstempels.

## Stappen om te starten (in de project-sessie)

1. `composer`-app draait lokaal? → maak `e2e/` + `.env.e2e` + `E2ESeeder`.
2. `npm init` in `e2e/`, `@playwright/test` toevoegen (devDep — vraag Henk: nieuwe dep).
3. Config + global-setup + eerste auth-test → lokaal groen krijgen.
4. Rest van de kritieke flows toevoegen.
5. CI-workflow toevoegen, push, controleer groen in Actions.
6. Documenteer in `{project}/.claude/context.md` + dit recept als referentie.
