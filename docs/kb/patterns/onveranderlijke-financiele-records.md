---
title: Records die niet meer mogen wijzigen — waar leg je die vast
type: pattern
scope: havuncore
last_check: 2026-07-19
---

# Records die niet meer mogen wijzigen — waar leg je die vast

> **Probleem:** een betaling is verstuurd, een factuur verzonden, een bestand ingediend.
> Vanaf dat moment is de rij bewijsmateriaal en mag hij niet meer wijzigen of verdwijnen.
> **Oplossing:** een trigger, niet een modelhook en niet alleen een unique index.

## Drie lagen, en wat elke laag níét kan

Bij VeenLedenadministratie (SEPA-incasso) is dit in drie rondes opgebouwd, waarbij elke
laag door een controleronde werd gebroken:

| Laag | Beschermt tegen | Faalt bij |
|---|---|---|
| Modelhook (`saving`, `deleting`) | gewone `create`/`update`/`delete` | `saveQuietly()`, `Model::query()->update()`, `insert()`, `upsert()`, raw SQL |
| Gegenereerde kolom + unique index | dubbele rijen, ook buiten Eloquent om | de rij *verdwijnt* (`forceDelete`) of *verhuist* (sleutelveld wijzigen) |
| Trigger | alles wat de database bereikt | niets — behalve het droppen van de trigger zelf |

De middelste rij is de verrassing. Een unique index bewaakt een **toestand**: welke
combinaties mogen naast elkaar bestaan. Hij kan per definitie niets vasthouden wat er
niet meer is. Een trigger bewaakt een **overgang** en is daarmee de enige laag die
verdwijnen en verhuizen dekt.

Concreet aangetoond: een betaling met status `submitted` hield haar periode bezet via een
gegenereerde kolom. `forceDelete()` haalde de rij fysiek weg — sleutel weg, periode vrij,
tweede incasso mogelijk. En een raw update van `period` liet de rij uit het slot
vertrekken in plaats van het vrij te geven. Beide via de gewone API, geen trucs.

## De trigger

```sql
CREATE TRIGGER payments_geen_delete_na_indiening
BEFORE DELETE ON payments
FOR EACH ROW
BEGIN
    IF OLD.status IN ('submitted', 'paid') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Deze incasso is aangeboden aan de bank en kan niet worden verwijderd.';
    END IF;
END
```

Voor de UPDATE-variant: gebruik `<=>` (NULL-veilig gelijk) in plaats van `=`, anders
glipt een wijziging van of naar NULL erdoorheen.

```sql
IF NOT (NEW.period <=> OLD.period) THEN ...
```

Schrijf de `MESSAGE_TEXT` als een zin voor een mens. Dit is wat de beheerder te zien
krijgt, en een begrijpelijke melding scheelt een supportvraag.

## Read-then-write is geen controle

Naast de triggers zat er een klassieke race:

```php
if ($this->isSubmitted()) {          // lezen
    throw new RuntimeException(...);
}
$this->update([...]);                // schrijven
```

Twee gelijktijdige verzoeken komen daar allebei doorheen. Bij een incassobestand betekent
dat twee bestanden naar de bank, waarvan er één geen spoor achterlaat in de
administratie. De controle hoort in de schrijfactie zelf:

```php
$geraakt = static::whereKey($this->getKey())
    ->whereNull('submitted_at')      // <- de controle, nu atomair
    ->update([...]);

if ($geraakt === 0) {
    throw new RuntimeException('Al ingediend.');
}
```

## Wat je níét in code moet willen afdwingen

`seal()` binnen een `DB::transaction()` is gevaarlijk: een rollback wist het bewijs
terwijl het bestand al verstuurd is. De verleiding is om dat te blokkeren met
`DB::transactionLevel() > 0`.

Doe dat niet. `RefreshDatabase` draait élke test in een transactie, dus die controle moet
je in tests uitzetten — en een controle die je in tests uitzet, test je nooit. Maak er een
procesregel van, gedocumenteerd op de aanroepplek: eerst committen, dan pas versturen.

## Normaliseren hoort ook in de database

`' 2026-Q3'` met een voorloopspatie kwam door `blank()` heen en botste niet met
`'2026-Q3'` — MySQL's collatie negeert alleen spaties aan het **eind**. Twee rijen, twee
afschrijvingen. Een `BEFORE INSERT`-trigger met `TRIM()` lost dat op ongeacht wie er
schrijft.

## Kosten

Triggers zijn onzichtbaar in de code en daardoor makkelijk te vergeten. Twee dingen
helpen:

- zet ze in een migratie met uitleg erboven, niet in een los SQL-bestand
- test ze, en test ze langs de wegen die de hooks omzeilen (`forceDelete`, raw update,
  mass update). Een test die alleen `$model->update()` gebruikt bewijst niets over de
  trigger

Ze bestaan alleen op een echte database — zie
[testen-op-de-echte-database.md](testen-op-de-echte-database.md).

## Wanneer dit te zwaar is

Bij gegevens die geen geld of juridisch bewijs zijn. Een profielfoto of een
notitieveld hoeft dit niet. De vuistregel: kan een wijziging achteraf leiden tot een
geschil waarin jouw administratie het bewijs is, dan hoort de rem in de database.

## Herkomst

VeenLedenadministratie, juli 2026. De drie lagen zijn er stuk voor stuk in gekomen nadat
een controleronde de vorige had gebroken — met een aantoonbare dubbele afschrijving als
bewijs, niet als theorie.
