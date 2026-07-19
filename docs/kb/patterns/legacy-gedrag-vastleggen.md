---
title: Gedrag van een oude app vastleggen vóór je hem vervangt
type: pattern
scope: havuncore
last_check: 2026-07-19
---

# Gedrag van een oude app vastleggen vóór je hem vervangt

> **Probleem:** je herbouwt een systeem zonder tests en zonder specificatie. De enige bron
> van waarheid is de draaiende oude app — en die verdwijnt.
> **Oplossing:** leg de uitvoer vast als referentiebestanden (karakterisatietests) vóór je
> begint.

## Wanneer toepassen

Bij elke herbouw van een systeem dat bestanden of berichten produceert die extern worden
verwerkt: bankopdrachten, facturen, koppelingen, exports. Merk je een afwijking pas als de
ontvanger klaagt, dan is dit noodzakelijk.

## Je hebt de oude runtime vaak niet nodig

Bij VeenLedenadministratie moest een SEPA-generator uit Laravel 5.5 (PHP 7.0) herbouwd
worden. De eerste reflex was een Docker-container met PHP 7 — maar dat bleek onnodig:

**De generator was een Blade-template.** Die gebruikte alleen `{{ }}`, `@php` en
`@foreach`, en dat werkt in Blade 12 identiek aan Blade 5.5. Laravel 12 rendert het oude
sjabloon dus rechtstreeks, met data uit een kopie van de productiedatabase.

```php
View::addLocation(base_path('_legacy/resources/views'));
Crypt::swap(new Encrypter($oudeSleutel, 'AES-256-CBC')); // voor versleutelde velden
$xml = View::make('users.batchfile.batchfile', [...])->render();
```

Kijk dus eerst wát er precies produceert. Een template, een simpele klasse of een
query-resultaat heeft de oude runtime meestal niet nodig.

## Leg ook de fouten vast

Een karakterisatietest beschrijft wat het systeem **doet**, niet wat het **hoort** te
doen. Zitten er fouten in — en die zitten er — leg die dan óók vast, met een notitie of je
ze meeneemt of repareert. Zo wordt elke afwijking in de nieuwe versie een bewuste keuze.

Voorbeeld uit Veen: de oude generator schrijft bedragen met exact twee decimalen, maar
alleen omdat Laravel 5.5 een `decimal`-kolom als string teruggeeft. Voegt de nieuwe app
een float-cast toe, dan wordt € 12,50 ineens `12.5` — en dat weigert de bank, pas ná
indiening. Die test vangt dat af.

## ⚠️ Referentiebestanden met echte gegevens

Dit is de valkuil waar dit pattern vooral voor bestaat.

Referentiebestanden uit productiedata bevatten persoonsgegevens. Maskeren van namen en
rekeningnummers is **niet genoeg** wanneer volgorde en bedragen echt blijven:

- de rijvolgorde is gelijk aan de databasevolgorde, dus positie N ís record N
- de bedragen vormen samen met de datum een sleutel terug naar het origineel
- lengte-behoudende maskering (`xxxxxxx xxxxx`) verraadt korte namen
- afgeleide velden lekken mee: een machtigingskenmerk uit een lid-id, een tekendatum uit
  `created_at`

Bij Veen waren drie rondes nodig om dat te ontdekken, en zelfs daarna bleek het bestand
gepseudonimiseerd in plaats van geanonimiseerd — twee broers waren herleidbaar via de
woordlengte in de omschrijving.

**Werk daarom met twee sets:**

| Set | Waar | Inhoud |
|---|---|---|
| Echt | lokaal, in `.gitignore` | productiedata, voor de daadwerkelijke vergelijking |
| Synthetisch | in git | verzonnen data door dezelfde generator, voor CI |

Genereer de tweede uit een verzonnen dataset — niet uit gemaskeerde productiedata. Dat is
de enige manier waarop je zeker weet dat er niets in zit.

## Herkomst

VeenLedenadministratie, juli 2026.
