---
title: IBAN opslaan en valideren
type: pattern
scope: havuncore
last_check: 2026-07-18
---

# IBAN opslaan en valideren

**Conclusie:** valideer met mod-97, sla genormaliseerd op, en houd de IBAN uit foutmeldingen.
Referentie-implementatie: `HavunAdmin/app/Rules/Iban.php` +
`app/Models/Concerns/HasBankDetails.php` (18-07-2026).

## De drie regels

| Regel | Waarom |
|-------|--------|
| **Valideer met mod-97** (ISO 7064), niet met een regex | Een regex accepteert `NL91ABNA0417164301` — één cijfer fout en de betaling gaat naar niemand. Mod-97 pakt exact dat |
| **Sla genormaliseerd op** (hoofdletters, geen spaties/streepjes) | Anders is `NL91 ABNA…` ≠ `NL91ABNA…` en faalt elke exacte match stil |
| **Nooit de IBAN in de foutmelding** | Het is een persoonsgegeven; foutmeldingen belanden in logs, Sentry en error-pagina's |

## Implementatie

Geen dependency nodig — de checksum is twintig regels:

```php
// Verplaats de eerste 4 tekens naar achteren, letters -> positiewaarde (A=10 … Z=35),
// deel in stukken door 97; de rest moet 1 zijn.
$rearranged = substr($iban, 4) . substr($iban, 0, 4);
$numeric = '';
foreach (str_split($rearranged) as $c) {
    $numeric .= ctype_alpha($c) ? (string) (ord($c) - 55) : $c;
}
$remainder = 0;
foreach (str_split($numeric, 7) as $chunk) {
    $remainder = (int) ((string) $remainder . $chunk) % 97;
}
return $remainder === 1;
```

Chunk de deling in stukken van 7 — een IBAN wordt anders een getal van 30+ cijfers en dat
overloopt een int. `bcmod` zou ook kunnen, maar vereist de bcmath-extensie.

**Controleer ook de landlengte.** Mod-97 alleen laat een te korte IBAN door als de checksum
toevallig klopt. Een tabel `landcode => totale lengte` voor de SEPA-zone is ~70 regels en vangt
dat af.

## Valkuilen

- **Kolom 34, validatie 42.** De kolom is `VARCHAR(34)` (de maximale IBAN-lengte), maar de
  invoer mag spaties bevatten — valideer dus op `max:42` op de rúwe input en normaliseer in een
  mutator vóór opslag. De rule zelf begrenst het genormaliseerde resultaat al op 34.
- **Leeg ≠ ongeldig.** Met `nullable` slaat Laravel een lege string over vóór de rule draait.
  Dat is gewenst: een leeg veld betekent "geen rekening", niet "foute invoer".
- **Normaliseer ook de zoekterm.** Zodra je opslag genormaliseerd is, moet elke query die erop
  matcht dezelfde behandeling krijgen. In HavunAdmin brak precies dit de koppeling van
  bankmutaties aan leveranciers: de bank levert de tegenrekening mét spaties, de opgeslagen
  waarde niet meer. Zie `TransactionCategorizationService::matchOrCreateSupplier()`.
- **Een geldige checksum ≠ een bestaande rekening.** Mod-97 zegt alleen dat het nummer
  wélgevormd is. Wil je weten of de rekening bestaat, dan heb je een bank-API nodig.

## Bestaande data eerst controleren

Zet je de rule aan op een kolom die al bestond en losser gevalideerd werd, controleer dan
**vóór** de deploy of er rijen zijn die de nieuwe regel niet halen — anders wordt een record
onbewerkbaar zodra iemand een ander veld wil opslaan. Vergeet de tenant-databases niet.

```sql
SELECT COUNT(*) FROM suppliers WHERE iban IS NOT NULL AND iban != '';
```

Normaliseer bestaande waarden in dezelfde migratie (idempotent):

```php
DB::table('suppliers')->whereNotNull('iban')->update([
    'iban' => DB::raw("UPPER(REPLACE(REPLACE(REPLACE(iban,' ',''),'-',''),'.',''))"),
]);
```

## Zie ook

- [[model-traits]] — deel mutator/accessor/regels via een trait zodat modellen niet uit elkaar lopen
- `HavunAdmin/docs/02-architecture/DATABASE-DESIGN.md` — kolomdefinities customers/suppliers
