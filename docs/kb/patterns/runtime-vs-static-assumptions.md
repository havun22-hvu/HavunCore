---
title: Runtime-waarde vs. static-time aanname — anti-pattern familie
type: pattern
scope: alle-projecten
last_check: 2026-04-18
---

# Runtime vs. static-time — de onderliggende fout-familie

> **Kernfout:** je gaat ervan uit dat een waarde of element statisch bekend is, maar iets anders (browser, compiler, runtime-engine) evalueert het óók — en weigert of faalt als de runtime-waarde buiten jouw statische aanname valt.

## De gemene deler

| Speler | Wat die NIET weet op build-time | Gevolg bij runtime-afwijking |
|--------|--------------------------------|------------------------------|
| **Tailwind JIT** | welke `{{ $var }}` in je Blade wordt | CSS-class niet in output → fallback `w-full` |
| **CSP-header** | van welke origins je assets laadt | asset geblokkeerd, geen fout in PHP |
| **Alpine** | welke elementen verborgen zijn | `x-text` evalueert alsnog → null-deref |
| **Service Worker** | wat `caches.match()` teruggeeft | `undefined` → `respondWith()` crash |
| **Browser (CSP script-src-attr)** | hoe `onclick=` gegenereerd wordt | inline handler geblokkeerd, silent fail |
| **Per-request nonce** | JS dat later `<style>` bijbouwt | runtime element valt buiten nonce-lijst |

In elk geval: **er is iets dat óók oordeelt op een moment dat jij dacht dat je alleen statische controle had**. Zonder defensieve fallback → crash of stille blokkade.

## De 6 bekende gevallen (per 2026-04-18)

### 1. Tailwind arbitrary values met runtime data
```blade
{{-- fout --}}
<div class="w-[{{ $progress }}%]">

{{-- goed --}}
<div style="width: {{ $progress }}%">
```
**Waarom:** Tailwind scant Blade als tekst tijdens `npm run build`. Alleen exacte strings als `w-[43%]` komen in CSS. `w-[{{ $progress }}%]` is letterlijk dat — geen numeriek percentage.
**CSP-eis:** `style-src-attr 'unsafe-inline'` (niet hetzelfde als `style-src 'unsafe-inline'` — deze variant is specifiek voor inline `style=""` attributes).

### 2. Cross-origin CSS niet in CSP
```php
// fout
"style-src 'self' 'nonce-...'",

// goed (alleen als CDN écht nodig is — liever self-hosten)
"style-src 'self' 'nonce-...' https://cdn.jsdelivr.net",
```
**Waarom:** CSP is statische whitelist. Als een lib (Flatpickr, Chart.js) CSS van een CDN laadt, en die origin staat er niet in, blokkeert browser. Geen PHP-fout, alleen UI-breuk.
**Aanbeveling:** self-host via Vite bundling. CDN alleen als laatste optie met SRI.

### 3. Dynamisch aangemaakte `<style>` zonder nonce
```js
// fout: SW/JS bouwt runtime style-element zonder nonce
const s = document.createElement('style');
s.textContent = '.dyn { color: red; }';
document.head.appendChild(s);

// goed: pak per-request nonce uit een meta-tag
const nonce = document.querySelector('meta[name="csp-nonce"]')?.content;
const s = document.createElement('style');
if (nonce) s.nonce = nonce;
```
**Layout-eis:** `<meta name="csp-nonce" content="{{ csp_nonce() }}">` in `<head>`, anders kan JS de nonce niet vinden.

### 4. Inline `onclick=` / `onsubmit=` / `onchange=` met strict CSP
```blade
{{-- fout --}}
<button onclick="doThing()">

{{-- goed: data-attribute + delegated listener --}}
<button data-confirm="Weet je het zeker?">
```
JS-helper:
```js
document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-confirm]');
    if (el && !confirm(el.dataset.confirm)) e.preventDefault();
});
```
**Waarom:** `onclick=` is runtime-JS-injectie via attribuut. Strict CSP (`script-src 'self' 'nonce-...'`) staat dit niet toe — ook niet met `'unsafe-hashes'`. Delegated listeners bundelen events op document-niveau.

### 5. Service Worker `caches.match()` zonder gegarandeerde Response
```js
// fout — undefined als cache mist
self.addEventListener('fetch', (e) => {
    e.respondWith(caches.match(e.request));
});

// goed — fallback naar network + default
self.addEventListener('fetch', (e) => {
    e.respondWith(
        caches.match(e.request).then(r => r || fetch(e.request))
    );
});
```
**Waarom:** `respondWith()` vereist altijd een `Response`-object of Promise daarvan. `undefined` → "Failed to convert value to Response" runtime-fout, SW faalt stil.

### 5b. PHP `memory_limit` vs. runtime-groei van test-suite

```bash
# fout — default 512M is te laag voor HP/JT test-suite
php artisan test

# goed — expliciet ophogen
php -d memory_limit=2G artisan test
```
**Waarom:** `memory_limit` is een statische cap in `php.ini` (of `-d` flag). PHPUnit
bouwt tijdens een lange test-run geheugen op (fixtures, seeders, herhaalde app-boots).
Overschreden → `Allowed memory size of 536870912 bytes exhausted`, hele proces hangt.
**Waar dit is fout gegaan:** HP test-suite crashte 2026-04-18 na ~5563 tests groen.
**Alternatief:** tests opsplitsen in `--testsuite` chunks (Unit + Feature apart).
**CI-CD:** op GitHub Actions standaard hoger, maar ook daar expliciet instellen.

### 6. Alpine `x-text` op object-chain zonder null-safety
```blade
{{-- fout: crash als yearlyDepreciationInfo() tijdelijk null geeft --}}
<span x-text="yearlyDepreciationInfo().years"></span>

{{-- goed --}}
<span x-text="yearlyDepreciationInfo()?.years ?? '-'"></span>
```
**Waarom:** Alpine evalueert **alle** `x-text` / `x-show` expressies op elke render, ook van verborgen elementen (`x-show="false"`). Een `null.prop`-access wordt niet gevangen door `x-show` eromheen — dan crasht de hele component.

## Reviewchecklist bij nieuwe/gewijzigde frontend-code

- [ ] Elke `w-[…]` / `h-[…]` / `text-[…]` Tailwind arbitrary bevat géén `{{ }}` of JS-variabele
- [ ] Elke dynamische stijl (nummer, percentage) staat in `style="…"`, niet in `class="…"`
- [ ] CSP in `SecurityHeaders.php` bevat alle origins waar CSS/JS/fonts vandaan komen
- [ ] `<meta name="csp-nonce" content="{{ csp_nonce() }}">` staat in de layout-head
- [ ] Runtime `<style>` / `<script>` via JS pakt die nonce (`meta.content`) over
- [ ] Geen inline `on*=` attributes — altijd `data-*` + event-listener
- [ ] Elke `caches.match()` heeft `.then(r => r || fetch(...))` fallback
- [ ] Elke `x-text` / `x-html` met object-chain gebruikt `?.` of `|| {}`

## Testhint: zichtbaar maken wat CSP blokkeert

In Chrome DevTools → Console → filter op "Refused to" of "Content Security Policy".
Elke geblokkeerde resource levert een console-regel op — óók op productie waar géén foutmelding aan user wordt getoond.

Voor automatisering: `mcp__claude-in-chrome__read_console_messages` met pattern `"Content Security Policy"` (als Chrome-integratie actief).

## Waar dit patroon tot concrete bugs leidde (Havun)

| Datum | Project | Symptoom | Oorzaak-categorie | Fix-commit |
|-------|---------|----------|-------------------|-----------|
| 2026-04-17 | HavunAdmin | urenregistratie balk altijd 100% | #1 Tailwind JIT | `cc97d57` |
| 2026-04-18 | HavunAdmin | BTW-grens + disk/memory bars 100% | #1 Tailwind JIT | `f0f6038` |
| 2026-04-18 | HavunAdmin | 38 views met inline `on*=` | #4 strict CSP | parallelle sessie, pending |
| (tbd) | Herdenkingsportaal | Flatpickr datepicker zonder styling | #2 CSP CDN | — |
| (tbd) | — | SW "Failed to convert to Response" | #5 SW fallback | — |
| (tbd) | — | Alpine crash yearlyDepreciationInfo | #6 null-safety | — |

## Verband met bestaande KB-docs

- `patterns/frontend-gotchas.md` — symptoom-gericht (Tailwind JIT, Alpine CSP, Blade @)
- `reference/security-findings.md` — chronologisch log van externe-scan hits
- `runbooks/security-headers-check.md` — hoe CSP headers testen
- `runbooks/alpine-csp-migratie.md` — de 9 conversie-patronen (Alpine CSP-strict)

**Dit document is de *onderliggende* denkstructuur**: begrijp de categorie (runtime evalueert óók), dan herken je een nieuw voorval sneller dan via patroon-matching op 6 losse fouten.
