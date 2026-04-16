# Alpine.js CSP Migratie — Conversie Patronen

> **Doel:** `'unsafe-eval'` uit CSP verwijderen door `@alpinejs/csp` build te gebruiken.
> **Van toepassing op:** alle Havun projecten met Alpine.js.
> **Status per project:** HP klaar, JT in progress (VP-18), overige nog te evalueren.

## Kernprincipe

De `@alpinejs/csp` build accepteert in `x-data`, `:attr`, `x-show`, `x-text`, `@click` e.d. **alleen**:
- Property access: `foo`, `foo.bar`, `foo[key]`
- Method calls met literal of property args: `doThing()`, `doThing(foo, 'x')`
- Eenvoudige assignments: `foo = value`

**Niet toegestaan:**
- Ternaries (`a ? x : y`)
- Operators (`===`, `!==`, `&&`, `||`, `+`, `!`)
- Object/array literals (`{ key: val }`, `[1, 2]`)
- Method chaining op globals (`Object.values(...).flat()`)
- Arrow functions / callbacks
- Multi-statement sequences met `;`

## Conversie Patronen

### Patroon A — Ternary in attribuut

```html
<!-- VOOR -->
<button :class="active === id ? 'text-blue-600' : 'text-gray-500'">

<!-- NA -->
<button :class="tabClass(id)">
```

```js
tabClass(id) {
    return this.active === id ? 'text-blue-600' : 'text-gray-500';
}
```

### Patroon B — Vergelijking in x-show

```html
<!-- VOOR -->
<div x-show="active === 'mat'">

<!-- NA -->
<div x-show="isActive('mat')">
```

```js
isActive(rol) { return this.active === rol; }
```

### Patroon C — String-concatenatie voor vergelijking

```html
<!-- VOOR -->
<span x-show="copiedId === 'url_' + item.id">

<!-- NA -->
<span x-show="isCopied('url', item)">
```

```js
isCopied(prefix, item) { return this.copiedId === `${prefix}_${item.id}`; }
```

### Patroon D — Multi-statement handler

```html
<!-- VOOR -->
<button @click="showQr = showQr === 'x' ? null : 'x'; url = item.url">

<!-- NA -->
<button @click="toggleQr(item)">
```

```js
toggleQr(item) {
    this.showQr = this.showQr === `qr_${item.id}` ? null : `qr_${item.id}`;
    this.url = item.url;
}
```

### Patroon E — Method chaining

```html
<!-- VOOR -->
<div x-show="Object.values(groups).flat().length > 0">

<!-- NA -->
<div x-show="hasAny">
```

```js
get hasAny() { return Object.values(this.groups).flat().length > 0; }
```

### Patroon F — Inline callback met setTimeout

```html
<!-- VOOR -->
<button @click="copied = true; setTimeout(() => copied = false, 2000)">

<!-- NA -->
<button @click="flashCopied()">
```

```js
flashCopied() {
    this.copied = true;
    setTimeout(() => { this.copied = false; }, 2000);
}
```

### Patroon G — `navigator.clipboard` inline

```html
<!-- VOOR -->
<button @click="navigator.clipboard.writeText(url); copied = id">

<!-- NA -->
<button @click="copyUrl(item)">
```

```js
copyUrl(item) {
    navigator.clipboard.writeText(item.url);
    this.copied = item.id;
    setTimeout(() => { this.copied = null; }, 2000);
}
```

### Patroon H — Object literal in :class

```html
<!-- VOOR -->
<div :class="{ 'print-exclude': !include, 'opacity-50': !include }">

<!-- NA (directe classList manipulatie in $watch) -->
<div x-data="pouleSelect">
```

```js
Alpine.data('pouleSelect', () => ({
    include: true,
    init() {
        this.$watch('include', (v) => {
            this.$el.classList.toggle('print-exclude', !v);
            this.$el.classList.toggle('opacity-50', !v);
        });
    },
}));
```

### Patroon I — Blade-geïnterpoleerde URLs

```html
<!-- VOOR: URL zit hardcoded in handler -->
<button @click="navigator.clipboard.writeText('{{ url('/tv') }}/' + item.code)">

<!-- NA: URL via data-attribuut, method leest ze -->
<div x-data="device" data-tv-base="{{ url('/tv') }}">
    <button @click="copyTvUrl(item)">
</div>
```

```js
Alpine.data('device', () => ({
    tvBase: '',
    init() { this.tvBase = this.$el.dataset.tvBase || ''; },
    copyTvUrl(item) {
        navigator.clipboard.writeText(`${this.tvBase}/${item.code}`);
    },
}));
```

## Workflow per view

1. **Inventariseer** alle inline directives: `grep -E ':class=|x-show=|@click=|x-text=|@input=|@change=' view.blade.php`
2. **Registreer** component met `Alpine.data('name', () => ({...}))` — bovenin of in centraal `resources/js/alpine-components.js`
3. **Converteer** elke directive volgens patroon A–I
4. **Verplaats** Blade-URLs naar `data-*` attributen; lees in `init()`
5. **Build** `npm run build` → check compile
6. **Test handmatig** de view in browser (golden path + edge cases)
7. **Commit** met duidelijke scope

## Anti-patroon: "script @nonce" in view

Een inline `<script @nonce>` met daarin een `function name()` definitie is **niet CSP-strict** — het vereist `'unsafe-inline'` OF nonce-allow. Nonce is OK zolang de CSP-nonce per request random is, maar het blijft een losse JS-blob buiten de build. Liever naar `resources/js/alpine-components.js` tenzij er dynamische Blade-interpolatie nodig is die niet via `data-*` kan.

## Wanneer behoud je `'unsafe-eval'`?

Als een view afhankelijk is van een library die `new Function()` of `eval()` intern gebruikt (bijv. sommige charting libs, Fabric.js edit mode in HP). Dan:
- Laat CSP `'unsafe-eval'` toe **alleen op die specifieke route** via conditional middleware.
- Documenteer het in `CONTRACTS.md` van dat project.
- HP doet dit voor `editDesign` route — voorbeeld.

## Verwijzingen

- `@alpinejs/csp` docs: https://alpinejs.dev/advanced/csp
- Havun voorbeeldmigraties:
  - HP: `herdenkingsportaal/resources/js/alpine-components.js`
  - JT VP-18: `judotoernooi/resources/js/alpine-components.js`
- HavunCore verbeterplan: `docs/audit/verbeterplan-q2-2026.md` (VP-11, VP-18)
