---
title: Frontend gotchas — herhalende valkuilen
type: pattern
scope: alle-projecten
last_check: 2026-04-18
---

# Frontend gotchas

> Herhalende frontend-valkuilen die Claude in meerdere projecten tegenkomt.
> Lees voor je Blade/Alpine/Tailwind wijzigt.

## Tailwind JIT — arbitrary values met runtime-variabelen breken stilletjes

**Symptoom:** progress bar toont 100% in productie terwijl variabele 43% teruggeeft.

**Oorzaak:** Tailwind compileert arbitrary values zoals `w-[43.4%]` **at build time**.
Een Blade-expressie zoals `class="w-[{{ $progress }}%]"` produceert op runtime een
`class="w-[43.4%]"`, maar die class is nooit in de build opgenomen — dus het
uiteindelijke CSS-bestand heeft er geen `width: 43.4%` regel voor. Browser valt terug
op `w-full` (100%) of geen width, zonder foutmelding.

**Fix:** gebruik `style="width: {{ $progress }}%"` in plaats van Tailwind arbitrary class.
- CSP vereist: `style-src-attr 'unsafe-inline'` moet toegestaan zijn (niet hetzelfde als
  `style-src 'unsafe-inline'` — de -attr variant is specifiek voor inline `style=""` attributes).

**Waar dit is fout gegaan:**
- 2026-04-18: HavunAdmin `dashboard.blade.php` BTW-grens progress bar (commit `f0f6038`)
- 2026-04-18: HavunAdmin `observability/index.blade.php` disk + memory bars
- 2026-04-17: HavunAdmin `urenregistratie` modal progress bar (commit `cc97d57`)

**Alternatief voor wie geen inline style mag:** Tailwind safelist in `tailwind.config.js`:
```js
safelist: [{ pattern: /^w-\[\d+%\]$/ }]
```
Maar dat bloat de CSS met alle mogelijke percentages. Inline style is de pragmatische keuze.

---

## Alpine.js `x-data="{ ... }"` inline objecten breken met CSP `unsafe-eval` weg

**Symptoom:** Alpine-component doet niks; console: `Alpine Expression Error: unsafe-eval not allowed`.

**Oorzaak:** Alpine evalueert `x-data="{ foo: 'bar' }"` met `new Function()`, wat onder
CSP `unsafe-eval` valt. Mozilla Observatory vereist géén `unsafe-eval`.

**Fix:** migratie naar `@alpinejs/csp` + `Alpine.data()` componenten in een `.js`-bestand.
Zie runbook: `docs/kb/runbooks/alpine-csp-migratie.md` (9 conversie-patronen).

**Voltooid voor:**
- JudoToernooi (commit `5197c995`, 17-04-2026) — `'unsafe-eval'` uit SecurityHeaders
- Herdenkingsportaal (feature branch `feat/vp11-alpine-csp-migration`, pending smoke test)

---

## Blade `@` directives botsen met JSON-LD / email-templates

**Symptoom:** `@context`, `@type`, `@id` in JSON-LD schema render als leeg / error.

**Oorzaak:** Blade interpreteert `@woord` als directive. `@context` wordt gezocht als
Blade directive, gevonden = niet bestaat → fout of skip.

**Fix:** gebruik `@@context` (dubbele `@`) om literal `@context` te renderen.

**Waar dit raakt:**
- JSON-LD structured-data in SEO-tags
- Email-content met `@mentions`
- Markdown die later naar Blade teruglinkt

---

## `<x-app-layout>` vs `@extends` inconsistentie per project

**Symptoom:** view-refactor werkt niet, layout-slots blijven leeg.

**Oorzaak:** verschillende projecten gebruiken verschillende layout-systemen:
- Herdenkingsportaal: `<x-app-layout>` (component-based)
- JudoToernooi: `@extends('layouts.app')` (blade-inheritance)

**Fix:** kijk eerst welke stijl het project hanteert (`grep -r "x-app-layout\|@extends" resources/views | head -5`).
Vermeng ze niet.

---

## Dark-mode klassen missen na view-refactor

**Symptoom:** view ziet er goed uit in light mode, gebroken in dark mode.

**Oorzaak:** bij het copy-pasten of herschrijven van een component vergeet men
`dark:bg-*`, `dark:text-*`, `dark:border-*` varianten toe te voegen.

**Fix:** lees altijd 2-3 naburige componenten als referentie vóór een nieuwe component
te schrijven. Alle Havun-projecten hebben dark-mode actief.

---

## Template — nieuwe gotcha toevoegen

```markdown
## [Korte titel — `symptoom → oorzaak → fix`]

**Symptoom:** [wat de gebruiker/ontwikkelaar ziet gaat mis]

**Oorzaak:** [root cause, niet symptoom]

**Fix:** [concrete code-actie of config-wijziging]

**Waar dit is fout gegaan:** [projecten + commits als traceer-punten]
```
