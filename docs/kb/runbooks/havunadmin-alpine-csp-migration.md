---
title: HavunAdmin — Alpine CSP migratie plan
type: runbook
scope: havunadmin
status: PLANNED
last_check: 2026-04-22
---

# HavunAdmin — Alpine CSP migratie

> **Doel:** alle inline `x-data="{...}"` patterns in HavunAdmin's Blade
> templates vervangen door CSP-safe Alpine component-references via
> `@alpinejs/csp` plugin, zodat de CSP-header `script-src` geen
> `'unsafe-eval'` meer hoeft te bevatten.
>
> **Status:** PLANNED. Uitvoering vereist UI-test cycle per file en is
> daarom een eigen sessie. Zie ook `feedback_mozilla_observatory.md`
> (alle projecten moeten Mozilla Observatory CSP+SRI tests halen).

## Waarom

`@alpinejs/csp` (de CSP build van Alpine) parst geen JS-expressies in
attributen. Inline `x-data="{ open: false }"` werkt **niet meer** — alleen
references naar via `Alpine.data('name', () => ({...}))` geregistreerde
componenten. Voordeel: geen `'unsafe-eval'` nodig in CSP `script-src`.

## Inventarisering (per 22-04-2026)

```bash
grep -rn 'x-data="{' resources/views/ | wc -l
# 29 inline patterns
```

### Categorie A — Trivial booleans (~18 files)

Patterns: `{ open: false }`, `{ show: true }`, `{ copied: false }`,
`{ search: '' }`, `{ editing: false }`, `{ canSave: false }`, etc.

**Migratie-aanpak:**
1. Definieer een generieke factory in `resources/js/alpine-data.js`:
   ```js
   Alpine.data('toggle', (initial = false) => ({ open: initial }))
   Alpine.data('toggleShow', () => ({ show: true }))
   Alpine.data('searchable', () => ({ search: '' }))
   ```
2. Vervang in Blade:
   ```blade
   {{-- voor --}}
   <div x-data="{ open: false }">
   {{-- na --}}
   <div x-data="toggle">
   ```

**Affected files (zoek pattern `x-data="{ open: false }"`):**
- `resources/views/components/dropdown.blade.php` (regel 16)
- `resources/views/components/modal.blade.php` (regel 18, multi-line)
- `resources/views/customers/partials/form.blade.php` (regel 34)
- `resources/views/layouts/app.blade.php` (regels 351, 379)
- `resources/views/observability/autofix.blade.php` (regel 43)
- `resources/views/suppliers/partials/form.blade.php` (regel 2)

**Voor `{ search: '' }`:**
- `resources/views/customers/index.blade.php` (regel 23)
- `resources/views/quotes/index.blade.php` (regel 29)
- `resources/views/suppliers/index.blade.php` (regel 23)
- `resources/views/reconciliation/index.blade.php` (regel 285, andere veldnaam `ledgerSearch`)

**Voor `{ show: true }` met x-init timeout:**
- `resources/views/layouts/app.blade.php` (regels 181, 432)
- `resources/views/profile/partials/update-{password,preferences,profile-information}-form.blade.php`

### Categorie B — Met Blade-interpolation (~7 files)

Patterns: `{ open: {{ ... }} }`, `{ theme: '{{ auth()->user()->theme }}' }`,
`{ btwYear: '{{ $currentYear }}' }`, etc.

**Migratie-aanpak:** initiële waarde via `data-*` attributen doorgeven aan
de Alpine factory. Voorbeeld:
```js
Alpine.data('themeToggle', (el) => ({
  theme: el.dataset.theme || 'system',
  // methods...
}))
```
```blade
{{-- voor --}}
<div x-data="{ theme: '{{ auth()->user()->theme ?? 'system' }}' }">
{{-- na --}}
<div data-theme="{{ auth()->user()->theme ?? 'system' }}" x-data="themeToggle($el)">
```

**Affected files:**
- `resources/views/invoice-templates/{create,edit}.blade.php` (selectedDay)
- `resources/views/layouts/app.blade.php` (regels 248, 273, 405)
- `resources/views/layouts/navigation.blade.php` (regels 110, 251)
- `resources/views/reports/index.blade.php` (regels 149, 199)

### Categorie C — Conditional inline (2 files)

Pattern: `@if(session('new_token')) x-data="{ showNewToken: true }" @endif`

**Migratie-aanpak:** verplaats conditionaliteit naar de factory of split
in 2 elementen.

- `resources/views/api-tokens/index.blade.php` (regels 8, 46)

## Uitvoeringsstappen

1. **Install plugin:**
   ```bash
   cd D:/GitHub/HavunAdmin
   npm install --save-dev @alpinejs/csp
   ```

2. **Vervang Alpine import in `resources/js/app.js`:**
   ```js
   // voor
   import Alpine from 'alpinejs'
   // na
   import Alpine from '@alpinejs/csp'
   ```

3. **Definieer factories** in nieuwe `resources/js/alpine-data.js`,
   import vóór `Alpine.start()`:
   ```js
   import './alpine-data.js'
   ```

4. **Per Blade-file** (gebruik de inventarisering hierboven):
   - Vervang inline x-data met factory-naam
   - Run `npm run dev`
   - Test in browser: dropdowns, search-velden, modals, theme-toggle, etc.
   - Commit per logische groep (bijv. "components", "layouts",
     "feature/customers")

5. **CSP-header aanpassen** (na succesvolle migratie):
   ```php
   // config/security-headers.php of vergelijkbaar
   // verwijder 'unsafe-eval' uit script-src
   ```

6. **Mozilla Observatory test:**
   ```bash
   cd D:/GitHub/HavunCore
   php artisan qv:scan --only=observatory --project=havunadmin
   ```

7. **Update memory + KB:**
   - `project_mozilla_observatory_status.md` — markeer HavunAdmin als clean
   - Deze runbook → `status: COMPLETED` + datum

## Risico's

- **UI breekt zonder duidelijke error**: Alpine CSP geeft soms geen JS-error
  bij verkeerd geregistreerde component-naam, alleen "doet niets". **Mitigatie:**
  test elke gemigreerde file in browser vóór commit.
- **Inline-conditionals (Categorie C)**: meer maatwerk dan A/B. **Mitigatie:**
  doe als laatste, met aparte review.
- **DO NOT REMOVE comments**: check `feedback_no_destructive_actions.md` —
  niet zomaar features/UI-elementen verwijderen tijdens refactor.

## Geschatte effort

- Categorie A: ~3 uur (18 files × ~10 min)
- Categorie B: ~2 uur (7 files × ~15 min, data-attribuut design per pattern)
- Categorie C: ~30 min (2 files)
- Build + CSP-header + Observatory verify: ~30 min

**Totaal: ~6 uur, eigen sessie.** Niet inplannen samen met andere refactors —
één foutje breekt de hele admin-UI.

## Zie ook

- `feedback_mozilla_observatory.md` — alle projecten moeten Observatory halen
- `project_mozilla_observatory_status.md` — huidige cross-portfolio status
- `runbooks/security-headers-check.md` — CSP-detail
- `runbooks/kwaliteit-veiligheid-systeem.md` — V&K systeem (waarom dit telt)
