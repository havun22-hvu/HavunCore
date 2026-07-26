---
title: Nul is niet niets — falsy-checks op waarden die 0 mogen zijn
type: pattern
scope: alle projecten
last_updated: 2026-07-27
---

# Nul is niet niets

**Regel: controleer op *aanwezigheid*, niet op *waarheid*, zodra `0` een geldige waarde is.**

`0`, `0.00` en `'0'`-als-getal zijn falsy in PHP én JavaScript. Een guard als `if ($x)` betekent
daardoor niet "is dit ingevuld?" maar "is dit ingevuld én niet nul?" — en die tweede voorwaarde
heb je zelden bedoeld.

```php
if ($invoice->vat_amount) { … }          // ❌ slaat 0.00 over
if ($invoice->vat_amount !== null) { … } // ✅
```

```js
if (this.form.amount_excl) { … }              // ❌ slaat 0 en '' allebei over
if (amount !== '' && amount !== null) { … }   // ✅ onderscheidt leeg van nul
const pct = parseFloat(input) || 21;          // ❌ maakt van een bewuste 0 stil 21
```

## Waarom dit zo vaak misgaat

Het faalt **alleen in het randgeval**, en juist daar waar de berekening het simpelst is. In
HavunAdmin (26-07-2026) sloeg `if ($invoice->amount && $invoice->vat_amount)` de berekening van het
bedrag exclusief BTW over bij precies de BTW-vrije facturen — waar dat bedrag gelijk is aan het
totaal. Alle facturen mét BTW gingen goed, dus de bug bleef maanden onzichtbaar. Dezelfde denkfout
zat in de frontend én in `parseFloat(pct) || 21`, dat een bewust gekozen 0% stil in 21% veranderde.

Domeinen waar `0` echt voorkomt: BTW-percentages en -bedragen, kortingen, saldi, voorraad,
coördinaten, sorteervolgorde, `business_percentage`, en elk "aantal".

## Waar op te letten

- **`??` is veilig, `||` en `?:` niet.** `$a ?? $b` valt alleen terug bij `null`; `$a ?: $b` en
  `a || b` vallen ook terug bij `0`, `''` en `false`.
- **`empty()` is hetzelfde probleem** — `empty(0)` is `true`. Gebruik `=== null` of `isset()`.
- **Laravel `$request->filled('x')`** is `false` bij een lege string, maar `true` bij `0` — dat is
  meestal wél wat je wilt. `$request->has('x')` kijkt alleen of de sleutel bestaat.
- **JSON-respons naar de frontend:** `0` overleeft, maar een `?:`-fallback onderweg niet. Test met
  de nulwaarde, niet alleen met een "normale" waarde.

## Testregel

Elke test voor een berekening met een percentage of bedrag krijgt een **nul-geval**. Groen op
21% zegt niets over 0% — dat is een ander codepad zodra er ergens een truthiness-check staat.

Zie ook: [[coverage-test-cementeert-bug]] — een test die het nulgeval nooit raakt, dekt de regel
wel af in de statistiek maar niet in werkelijkheid.
