---
title: Bewijspositie bij onderhoud aan overgenomen software
type: pattern
scope: havuncore
last_check: 2026-07-30
---

# Bewijspositie bij onderhoud aan overgenomen software

> **Probleem:** je doet onderhoud op een systeem dat iemand anders gebouwd heeft en dat al
> gebreken had. Zodra jij er als laatste aan zat, landt elke storing bij jou — met onbetaald
> onderzoekswerk en een welles-nietes als gevolg.
> **Oplossing:** leg vóór het eerste verwijt vast wat je deed, wat je níét raakte, en wie wat
> wanneer deed. Dat kost een uur en beslecht elke discussie daarna.

## Wanneer toepassen

Bij elk onderhoudscontract op software die je niet zelf hebt opgeleverd — zeker als er geld
in omgaat (incasso, facturatie, betalingen) en als de eindgebruikers niet jouw klant zijn maar
die van je opdrachtgever.

**Aanleiding in de praktijk** (VeenLedenadministratie, 29-07-2026): een school maakte vier
identieke incassobestanden aan, twee met een incassodatum op zaterdag. De opdrachtgever
concludeerde dat het door onze ingrepen kwam — wij hadden een week eerder een account
gedeblokkeerd en een IDOR op de bankgegevens gedicht. Zonder logboek was dat niet te weerleggen.

## Drie lagen

### 1. Ingrepenlogboek — wat je deed én wat je niet raakte

Eén bestand, chronologisch, per ingreep: datum, reikwijdte (welke tabellen, hoeveel rijen, welke
klant), backuppad, verificatie achteraf. En het cruciale veld dat men vergeet: **wat viel er
expliciet buiten**. "Uitsluitend de rij `users` id 74, velden `name`/`email`/`password`/`status`;
niet `organizations`, niet `members`, niet `member_mandates`" is het verschil tussen een bewering
en een bewijs.

Vaste regel bij elke ingreep: backup vooraf (volledige dump + gericht herstelbestand, hash
gecontroleerd) → reikwijdte tellen → in één transactie uitvoeren met `ROW_COUNT()` → verificatie
achteraf (tellingen, wees-records, totalen die niet mochten wijzigen) → vastleggen.

### 2. Logarchief met controlegetallen — wie deed wat

Webserverlogs zijn het enige dat handelingen van gebruikers vastlegt, maar ze roteren (vaak na
een maand) en staan op een server die jij beheert — dus zwak bewijs. Daarom dagelijks archiveren
met een ketting van hashes:

1. log van gisteren kopiëren naar een eigen store (chmod 700);
2. SHA-256 per bestand in een dagmanifest;
3. hash van dat manifest met tijdstempel toevoegen aan één doorlopend ketenbestand.

Wie een gearchiveerd log wijzigt, breekt de vergelijking met het manifest; wie het manifest
meeverandert, breekt de keten. Een controlescript dat beide checkt en exitcode 0 geeft, is wat
je meestuurt bij een betwisting. Referentie-implementatie:
`VeenLedenadministratie/scripts/oude-app-bewijs/`.

Wees eerlijk over de grens: dit bewijst **manipulatie achteraf**, niet dat de originele regels
kloppen. Wil de klant meer (tijdstempel bij een derde, logserver buiten je beheer), dan is dat
een aparte opdracht.

### 3. Afspraken op papier — de zakelijke grens

Inspanningsverplichting (geen garantie op een systeem dat je niet bouwde), bestaande gebreken
blijven bestaande gebreken, storing melden is gratis maar onderzoeken is werk-na-opdracht, en
een verwerkersovereenkomst als je met persoonsgegevens van andermans klanten werkt (art. 28 AVG
— bij overgenomen SaaS ontbreekt die vaak). **Je schrijft het concept, een jurist toetst het.**
Beloof nooit "juridisch waterdicht"; je levert de bewijsketen, niet het juridische oordeel.

## Een verwijt weerleggen — de vaste volgorde

1. **Code:** is het betrokken bestand door jou aangeraakt? `stat -c '%y %n'` op de hele keten.
   Mtimes van jaren terug sluiten je uit — sterker dan elke uitleg. Diff je eigen `.bak` tegen
   het huidige bestand en toon dat de wijziging een andere methode raakt.
2. **Data:** `MAX(updated_at)` op de betrokken tabellen. Ligt die vóór je eerste sessie, dan is
   die data niet van jou.
3. **Handelingen:** access-log op IP, tijd en route. Jouw eigen werk loopt via SSH/CLI en staat
   er níét in — verschijnt er toch een POST vanaf je eigen IP, dan was het een browsertest.
4. **Veldniveau:** grep het verdachte veld door de hele keten. "Het e-mailadres komt in
   `BatchFileController`, de generator, de XML-template en de middleware nergens voor" sluit een
   hele klasse verwijten in één keer uit.

> **Valkuil die je twee keer verkeerd laat concluderen:** Laravel schrijft timestamps in **UTC**,
> het access-log staat in lokale tijd (`+0200` in de zomer). Twee uur verschil. Een `updated_at`
> van `07:38:08` is de handeling van `09:38:08` in het log — in dat geval een gebruiker die zelf
> zijn wachtwoord wijzigde, tien minuten vóór de storing. Vergelijk nooit zonder die correctie.

## Wat je ermee opschiet

Bij de casus hierboven duurde het weerleggen twintig minuten in plaats van een discussie: alle
vier de handelingen kwamen van het IP van de school, de gebruiker had die ochtend zelf zijn
wachtwoord gewijzigd, de bankgegevens waren een jaar niet aangeraakt en de hele incassoketen had
nog mtimes uit 2018-2019. Bijkomend effect: je vindt de échte pre-existente gebreken, die je
daarna als betaald werk kunt aanbieden.
