---
title: Runtime-vs-Static Pitfalls (Laravel + Alpine + Tailwind + CSP)
type: pattern
scope: havuncore
last_check: 2026-04-22
---

# Runtime-vs-Static Pitfalls (Laravel + Alpine + Tailwind + CSP)

> Deze fouten lijken verschillend, maar hebben dezelfde wortel: iets
> evalueert **op buildtime** of **per request**, terwijl we aanneemden dat
> het de runtime-waarde zou kennen. Strict CSP en Alpine reactiviteit maken
> dit extra hard zichtbaar.

## De zes patronen die we in havunadmin-sessies herhaaldelijk tegenkwamen

### 1. Tailwind arbitrary values met runtime-data — BREEKT STIL

```blade
{{-- FOUT: JIT kent {{ $progress }} niet op buildtime --}}
<div class="w-[{{ $progress }}%]"></div>

{{-- GOED --}}
<div style="width: {{ $progress }}%"></div>
```

Tailwind JIT scant **statische source-strings**, niet gerenderde HTML.
Een onbekende arbitrary value wordt niet gegenereerd en de div valt terug
op full-width (100%). Vaak zichtbaar als "progress bar altijd vol".

**Regel:** numerieke runtime-waarde → inline `style="…"`. Nooit
`w-[{{ $x }}%]` / `bg-[#{{ $color }}]` / `h-[{{ $y }}px]`.

### 2. CSP `style-src` en dynamische `<style>`-elementen via JS

```js
// FOUT: nonce ontbreekt
const s = document.createElement('style');
s.textContent = '...';
document.head.appendChild(s);

// GOED
const s = document.createElement('style');
const nonce = document.querySelector('meta[name="csp-nonce"]')?.content;
if (nonce) s.nonce = nonce;
s.textContent = '...';
document.head.appendChild(s);
```

Zet **altijd** `<meta name="csp-nonce" content="{{ app('csp-nonce') }}">`
in de authenticated layout head. Vanilla JS-bibliotheken die stijl-
animaties injecteren hebben deze nonce nodig onder strict CSP.

### 3. Inline `onclick=` / `onchange=` / `onsubmit=` attributen

Onder strict CSP (geen `'unsafe-inline'` in `script-src`) blokkeert de
browser **elke** inline handler. Gebruik data-attributes + gedelegeerde
listeners:

```blade
{{-- FOUT --}}
<select onchange="this.form.submit()">...</select>
<button onclick="return confirm('Zeker?')">...</button>

{{-- GOED (zie resources/js/csp-handlers.js) --}}
<select data-autosubmit>...</select>
<button data-confirm="Zeker?">...</button>
```

Patronen die in havunadmin ondersteund worden door `csp-handlers.js`:

| Attribuut | Effect |
|---|---|
| `data-autosubmit` | submit parent form on change |
| `data-confirm="msg"` | confirm() op click/submit |
| `data-show="#id"` / `data-hide="#id"` / `data-toggle-hidden="#id"` | modal show/hide/toggle |
| `data-action="fn"` + `data-args='["a","b"]'` | call `window.fn(...args, el)` on click |
| `data-change-action="fn"` | zelfde op change |
| `data-print` | window.print() |
| `data-navigate-prefix="/path/"` | window.location.href = prefix + value on change |
| `data-submit-closest-form` | submit nearest ancestor form |
| `data-submit-target="#id"` | submit target form (combineer met data-confirm) |
| `data-toggle-next-hidden` | toggle `.hidden` op nextElementSibling |
| `data-lowercase` | force input.value naar lowercase on input |

### 4. Alpine `x-text` op object-keten, ook op hidden elementen

Alpine evalueert **alle** reactive expressies, ook in `x-show`-hidden
takken. Als je methode null teruggeeft, crashen de `x-text`-expressies
die op dat object accessen.

```blade
{{-- FOUT: crasht bij yearlyDepreciationInfo() === null --}}
<div x-show="yearlyDepreciationInfo()">
    <span x-text="yearlyDepreciationInfo().years"></span>
</div>

{{-- GOED --}}
<div x-show="yearlyDepreciationInfo()">
    <span x-text="yearlyDepreciationInfo()?.years ?? ''"></span>
</div>
```

**Regel:** **altijd** `?.` voor ketens in Alpine-expressies, of fallback
via `(fn() || {}).prop`.

### 5. Service Worker `respondWith(undefined)`

```js
// FOUT: caches.match() mist → undefined → TypeError bij miss
event.respondWith(
    fetch(req).catch(() => caches.match(req))
);

// GOED
event.respondWith(
    fetch(req)
        .catch(() => caches.match(req))
        .then(r => r || Response.error())
);
```

`respondWith` eist een Response. Elke `.then(r => r || Response.error())`
als laatste stap in de keten voorkomt stille crashes op cache-miss.

### 6. Tailwind runtime-kleuren / dynamische classes

```blade
{{-- FOUT: bg-red-500 / bg-green-500 moeten letterlijk in source staan --}}
<span class="bg-{{ $color }}-500"></span>

{{-- GOED (1): if-lijstje met statische classes --}}
<span class="{{ $isError ? 'bg-red-500' : 'bg-green-500' }}"></span>

{{-- GOED (2): inline style --}}
<span style="background-color: {{ $project->color }}"></span>
```

## Defensieve checklist bij elke blade/js wijziging

- [ ] Geen `w-[{{ }}]` / `h-[{{ }}]` / `bg-[{{ }}]` — gebruik inline style
- [ ] Geen `bg-{{ $kleur }}-500` — gebruik if/ternary of inline style
- [ ] Geen `onclick=` / `onchange=` / `onsubmit=` / `oninput=` attributen
- [ ] Geen `style=""` zonder **runtime numerieke data** (attr wordt
      gedekt door `style-src-attr 'unsafe-inline'` maar alleen voor
      attribuut, niet voor `<style>` blok)
- [ ] Dynamische `<style>` in JS: `style.nonce = meta[name=csp-nonce].content`
- [ ] Alpine `x-text`/`x-html` op object-keten: altijd `?.`
- [ ] Service worker `respondWith`: altijd eindigen op Response fallback

## Gerelateerd

- `docs/kb/runbooks/alpine-csp-migratie.md` — migratie naar strict CSP
- `docs/kb/runbooks/ggshield-setup.md` — pre-commit secret scan
- havunadmin `resources/js/csp-handlers.js` — implementatie van de
  data-attributes hierboven
