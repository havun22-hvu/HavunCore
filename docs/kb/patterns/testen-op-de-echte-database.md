---
title: Tests draaien op dezelfde database als productie
type: pattern
scope: havuncore
last_check: 2026-07-19
---

# Tests draaien op dezelfde database als productie

> **Probleem:** SQLite-in-memory is de Laravel-standaard voor tests, maar negeert
> beveiligingen die MySQL wél afdwingt. Groene tests bewijzen dan minder dan ze lijken.
> **Oplossing:** laat de suite op dezelfde database draaien als productie.

## Wat SQLite stilzwijgend laat passeren

| Wat | SQLite | MySQL |
|---|---|---|
| Kolomlengte (`varchar(140)`) | genegeerd, slaat alles op | `1406 Data too long` |
| `CHECK`-constraints | bestaan niet op een bestaande tabel | afgedwongen |
| `ENUM`-achtige beperkingen | geen | afgedwongen |
| Strict mode op ontbrekende kolommen | soepel | streng |

Het gaat dus precies om de laag die je *bewust* in de database hebt gelegd omdat een
controle in code vergeten kan worden.

## Waarom dat erger is dan een gemiste test

Bij VeenLedenadministratie (SEPA-incasso) stonden er twee fouten tegelijk in:

1. `payments.description` was `varchar(140)` — de SEPA-limiet. Maar dat is de limiet van
   het *afgeleide* bankveld, niet van wat een beheerder intypt. Op MySQL gaf een langere
   toelichting "Data too long"; de testsuite zag er niets van.

2. Een `CHECK` verbood onbekende waarden in `payments.type`. Op SQLite bestond die niet,
   dus een importscript met `type = 'contributie'` kwam door de hele suite heen. En dat
   type maakte de gegenereerde `uniqueness_key` NULL — waarmee de complete beveiliging
   tegen dubbel incasseren verviel.

De tweede is het patroon om te onthouden: **een ongeldige waarde valt vaak juist buiten
de constraint die hem had moeten vangen.** Dan is de schade niet "één foute rij" maar
"de garantie geldt niet meer".

## Doen

```xml
<!-- phpunit.xml -->
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="project_test"/>
```

Met een aparte database naast de ontwikkeldatabase, zodat `RefreshDatabase` niets
belangrijks wegvaagt. Aanmaken hoort in het runbook:

```bash
mysql -e "CREATE DATABASE IF NOT EXISTS project_test"
```

Kosten in de praktijk: bij ~130 tests ging het van 1,0 naar 3,2 seconden. Dat is geen
argument tegen.

## Wanneer SQLite wél volstaat

Als het project geen databaseconstraints gebruikt — geen CHECK, geen gegenereerde
kolommen, geen krappe kolomlengtes — verbergt SQLite ook niets. Maar dan is de vraag
eerder waarom die beveiligingen ontbreken.

## Bijkomend

Draait de suite op de echte database, dan kun je driver-specifieke constructies gewoon
testen: gegenereerde kolommen, partiële-unique-vervangers, `CONCAT`-expressies. Zie
[unique-met-soft-deletes.md](unique-met-soft-deletes.md).

Test wel expliciet wat je overslaat. Een test die alleen op MySQL kan slagen hoort
`markTestSkipped()` te gebruiken met de reden erin — anders leest een groene suite als
volledige dekking.

## Herkomst

VeenLedenadministratie, juli 2026.
