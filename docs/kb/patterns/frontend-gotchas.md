---
title: Frontend gotchas — herhalende valkuilen
type: pattern
scope: alle-projecten
last_check: 2026-07-16
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

## `@alpinejs/csp`: `foo = x` mag, `foo.bar = x` niet — dus `x-model="a.b"` is stuk

**Symptoom:** `Uncaught Error: Property assignments are prohibited in the CSP build` zodra je in een
veld typt of een `<select>` kiest. **Werkt lokaal, breekt op staging/prod** — de strikte CSP staat
meestal alleen buiten `local` aan.

**Oorzaak.** De evaluator van de csp-build (`@alpinejs/csp/dist/module.esm.js`,
`case "AssignmentExpression"`) doet:
```js
if (node.left.type === "Identifier") scope[node.left.name] = value;              // ✅
else if (node.left.type === "MemberExpression") throw new Error("Property assignments are prohibited…");  // ❌
```
Drie gevallen, drie gedragingen — dit verklaart álle CSP-assignment-bugs:

| Expressie | Gedrag |
|---|---|
| `open = false` op de **eigen** component | ✅ werkt |
| `open = false` op een **ancestor** | ⚠️ **stil fout** — schrijft naar de *eigen* scope, ancestor verandert nooit, geen error |
| `form.naam = x` — elk pad met een punt | ❌ harde error |

`x-model` compileert intern naar de string `<expressie> = __placeholder`, dus **elke**
`x-model="a.b"` valt in het derde geval.

**Fix.** Geef `x-model` een getter/setter-paar: het checkt eerst `isGetterSetter()` en parset de
assignment-string dan nooit (die is lazy).
```js
formModel(veld) { return { get: () => this.form[veld], set: (w) => { this.form[veld] = w; } }; }
```
```blade
<input x-model="formModel('naam')">   {{-- i.p.v. x-model="form.naam" --}}
```
De datastructuur blijft heel, dus `JSON.stringify(this.form)` bij submit werkt onveranderd.

**Guard** (statisch, geen browser nodig — e2e CSP-specs vangen dit **niet**: die checken alleen
page-load terwijl deze bug interactie vereist):
```bash
grep -rn 'x-model="[a-zA-Z_$][A-Za-z0-9_$]*\.' resources/views/    # moet leeg zijn
```
JudoToernooi heeft dit als PHPUnit-test: `tests/Unit/Views/AlpineCspBindingTest.php`.

**Status per project** (gemeten 16-07-2026 — `package.json` zegt niets, kijk wat `app.js` **importeert**):
- **JudoToernooi** — importeert de csp-build. 22 bindings over 4 views; alle toevoeg/bewerk-formulieren
  waren stuk. Gefixt + guard (`f46e77ed`).
- **Herdenkingsportaal** — importeert de csp-build (`app.js:3`). **5 bindings in
  `guestbook/register.blade.php` (`form.name/email/postcode/city/message`) → waarschijnlijk stuk;
  niet geverifieerd, niet gefixt.** Eigen sessie nodig.
- **HavunAdmin** — importeert **standaard** Alpine (`app.js`: "Standard Alpine (NOT @alpinejs/csp)").
  43 nested bindings, maar **geen bug**; `@alpinejs/csp` in `package.json` is ongebruikt.
- **Infosyst** — 0 bindings.

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
