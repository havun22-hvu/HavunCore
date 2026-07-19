---
title: Constraints die normaal werk blokkeren
type: pattern
scope: havuncore
last_check: 2026-07-19
---

# Constraints die normaal werk blokkeren

> **Probleem:** je zet een regel in de database om een fout onmogelijk te maken, en drie
> stappen verderop ligt gewoon werk stil.
> **Oplossing:** toets elke constraint tegen de handelingen die eromheen zitten, niet
> alleen tegen de fout die hij moet voorkomen.

## Waarom dit zo vaak misgaat

Een constraint wordt bedacht bij één scenario: "twee keer incasseren voor dezelfde periode
mag niet". In dat scenario klopt hij. Maar hij geldt ook in scenario's waar niemand aan
dacht toen hij werd geschreven.

Bij VeenLedenadministratie (SEPA-incasso) blokkeerde elke laag die werd toegevoegd
uiteindelijk iets dat gewoon moest kunnen:

| Constraint | Bedoeld tegen | Blokkeerde óók |
|---|---|---|
| unique op (lid, periode) | dubbel incasseren | opnieuw incasseren ná een storno |
| unique op naam per school | twee keer hetzelfde lid | herinschrijving, en elke naamgenoot |
| unique op mandaatkenmerk | twee machtigingen met één kenmerk | een lid dat van bank wisselt |
| tenant-consistentie op betalingen | rijen planten bij een andere klant | de historie van een lid dat verhuist |
| "elke incasso hoort bij een periode" | onherleidbare afschrijvingen | de complete datamigratie |

Die laatste is de duurste les: de bestaande gegevens **kúnnen** de nieuwe regel niet halen.
De oude app had geen periodeveld en geen rem, dus één lid had 26 contributies in één
kwartaal. Ruim 3.800 rijen zouden bij de import zijn geweigerd.

## De vuistregel

**De invariant geldt voor wat het systeem aanmaakt, niet met terugwerkende kracht voor wat
er al was.**

Markeer overgenomen rijen (`legacy_id`) en stel ze vrij:

```sql
CASE WHEN legacy_id IS NULL AND ... THEN <sleutel> ELSE NULL END
```

Dat is geen zwakte in de regel maar een erkenning van wat hij is: een afspraak over
toekomstig gedrag. De kolom maakt bovendien de import herhaalbaar.

## Wat er misgaat als je dit niet doet

De beheerder vindt een omweg. Bij Veen was de enige manier om na een storno opnieuw te
incasseren: de storno-rij verwijderen. Daarmee verdwijnt precies het bewijs van de
terugboeking uit de administratie — de constraint maakt de gegevens dan slechter in plaats
van beter.

Een constraint die normaal werk blokkeert, wordt omzeild. En de omweg is bijna altijd
schadelijker dan de fout die je wilde voorkomen.

## Hoe je ze vindt

Losse tests vinden dit niet — die toetsen precies het scenario waarvoor de constraint is
geschreven. Wat wel werkt: **tests die een hele keten doorlopen.**

```php
// Niet: "een tweede incasso wordt geweigerd"
// Maar: incasseren → batch → verzenden → storno → opnieuw incasseren
```

Loop bij elke nieuwe constraint deze vragen langs:

- wat als dit record wordt **ingetrokken** en er een nieuw voor in de plaats komt?
- wat als de klant/het lid **verhuist** naar een andere eigenaar?
- wat als de handeling wordt **teruggedraaid** (storno, creditering, annulering)?
- kan de **bestaande data** deze regel halen?
- blokkeert dit een **naamgenoot** of een tweede geval dat toevallig lijkt op het eerste?

## Herkomst

VeenLedenadministratie, juli 2026. Vijf van deze gevallen zijn gevonden door
controlerondes die scenario's doorliepen, niet door de tests die bij de constraints zelf
waren geschreven — die waren allemaal groen.

Zie ook [onveranderlijke-financiele-records.md](onveranderlijke-financiele-records.md) en
[unique-met-soft-deletes.md](unique-met-soft-deletes.md).
