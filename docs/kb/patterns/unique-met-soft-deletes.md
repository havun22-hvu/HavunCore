---
title: Unique constraints in tabellen met soft deletes
type: pattern
scope: havuncore
last_check: 2026-07-19
---

# Unique constraints in tabellen met soft deletes

> **Probleem:** een `unique` op een tabel met `deleted_at` faalt altijd één van twee
> kanten op — te streng of te los. Beide gaan pas maanden later stuk.
> **Oplossing:** een gegenereerde kolom die alleen gevuld is voor rijen die werkelijk
> moeten botsen.

## De twee faalrichtingen

**Te streng.** `deleted_at` zit niet in de sleutel, dus een verwijderde rij blijft zijn
plek bezetten. De vervanger kan nooit worden aangemaakt.

```
mandaat intrekken → nieuw mandaat aanmaken → "Duplicate entry"
```

Dit is de vervelendste: het werkt maandenlang, tot het eerste lid van bank wisselt.

**Te los.** Een kolom in de sleutel is nullable, en SQL beschouwt NULL-waarden als
onderling verschillend. De constraint doet dan niets.

```sql
UNIQUE (member_id, period, type)   -- period is nullable
```

Drie identieke incasso's zonder periode gaan er zonder protest in.

## De oplossing

MySQL kent geen partiële unique index (`WHERE deleted_at IS NULL`). Wel een gegenereerde
kolom, en die mag je wél indexeren:

```php
$table->string('active_key', 60)->nullable()
    ->virtualAs("CASE WHEN deleted_at IS NULL THEN {$sleutel} ELSE NULL END");
$table->unique('active_key');
```

Rijen die niet mee moeten doen krijgen NULL — en NULL botst niet met NULL. Precies het
gedrag dat je bij de "te losse" variant per ongeluk kreeg, nu bewust ingezet.

## Twee valkuilen bij de uitvoering

**`virtualAs`, niet `storedAs`.** MySQL verbiedt een *stored* gegenereerde kolom waarvan
de basiskolom onder een foreign key met `ON DELETE CASCADE` of `ON UPDATE CASCADE` valt.
De fout is misleidend:

```
SQLSTATE[HY000]: General error: 1215 Cannot add foreign key constraint
```

Die wijst naar de foreign key, niet naar de gegenereerde kolom. Met `virtualAs` speelt het
niet, en MySQL 8 en SQLite indexeren virtuele kolommen allebei prima.

**Samenvoegen verschilt per database.** MySQL gebruikt `CONCAT()`, SQLite de `||`-operator
— en in MySQL betekent `||` juist OR. Eén expressie voor beide bestaat niet:

```php
private function samenvoeging(string ...$kolommen): string
{
    return Schema::getConnection()->getDriverName() === 'sqlite'
        ? implode(" || '-' || ", $kolommen)
        : 'CONCAT(' . implode(", '-', ", $kolommen) . ')';
}
```

Draaien de tests op SQLite en productie op MySQL, dan is dit niet optioneel.

## Zoek er meteen álle plekken bij

Bij VeenLedenadministratie gold dit patroon op **vijf** tabellen tegelijk: mandaten,
batches, betalingen, abonnementen en factuuradressen. Wie één `unique` naast een
`softDeletes()` vindt, vindt de rest ook.

Let daarbij op commentaar dat een regel *beschrijft* zonder hem af te dwingen:

```php
// Eén lopend abonnement per lid tegelijk.
$table->index(['member_id', 'starts_on', 'ends_on']);   // <- een index, geen unique
```

Dat stond er op twee van de vijf plekken. Het commentaar leest als een garantie en wordt
bij review ook zo gelezen.

## Leg beide richtingen vast in een test

Eén test per constraint is niet genoeg — die dekt maar één faalrichting:

| Test | Vangt |
|---|---|
| tweede lopende rij wordt geweigerd | de "te losse" kant |
| na intrekken kan er wél een nieuwe bij | de "te strenge" kant |

## Herkomst

VeenLedenadministratie, juli 2026. Zie
[../projects/veen-ledenadministratie.md](../projects/veen-ledenadministratie.md).
