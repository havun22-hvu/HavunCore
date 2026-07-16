---
title: Een claim verifieer je hélemaal, of je laat 'm staan
type: standard
scope: alle-projecten
last_check: 2026-07-16
---

# Claims verifiëren — BINDEND voor alle Havun-projecten

**Regel: een half geverifieerde claim vervangen door je eigen half geverifieerde claim maakt het
erger, niet beter.** Docs die liegen zijn het probleem; een correcte regel "corrigeren" naar een
foute is datzelfde probleem, alleen met meer zelfvertrouwen.

## De drie manieren waarop het misgaat

| Fout | Wat er gebeurt | Wat je doet |
|------|----------------|-------------|
| **Te vroeg concluderen** | Je leest een tussenstand als eindresultaat | Wacht op het afrondingssignaal (`Tests:`-regel, exit code), niet op "het lijkt stil" |
| **Verkeerde eenheid lezen** | Je telt iets anders dan de claim bedoelt | Check wát er geteld wordt vóór je het getal vergelijkt |
| **De kopie geloven** | Je verifieert doc A tegen doc B, beide kopieën | Ga naar de bron: `git log`, de code, de server |

## Waarom dit een eigen regel is (16-07-2026)

Op één avond ging het drie keer mis, twee keer bij mij:

1. **HavunAdmin's "31 testfailures".** Ik draaide de suite, zag na ~25 minuten 11 failures over
   1770 tests, en concludeerde dat de claim niet klopte. De suite was **niet klaar** — hij draait
   42 minuten. Eindstand: **31 failures**, exact de verdeling die er al stond (`Last825Test` 16,
   `MaxServiceCoverageTest` 10, `Push90Test` 3, `CommandCoverageTest` 1, `FastCoverageTest` 1).
   Bovendien las ik Pest's **testnamen** (`project matching`, `stripe service`) als **klassenamen**
   — dat zijn tests bínnen precies de klassen die ik fout noemde. Een correcte handover werd zo
   een uur lang onjuist.

2. **Vusista's "vier open gezichten-vragen".** Overgenomen uit een inventarisatie die de oude
   handover las. `fase2-gezichten.md` zei in zijn eigen frontmatter *"alle vragen beantwoord
   (15 juli)"*, en gezichten wérkte al op 806 foto's. Eén uur oud en al fout.

3. **`closing_date` in Herdenkingsportaal.** De handover noemde het legacy-code om op te ruimen.
   Het is een levende feature: kolom, migratie, cast, twee views, ~40 tests. Alleen omdat de agent
   dóórprikte in plaats van de instructie uit te voeren, is er geen functionaliteit gesloopt.

Alle drie hadden dezelfde vorm: **een plausibele conclusie uit een onvolledige meting.**

## Wat je concreet doet

1. **Meet tot het einde.** Een run is klaar als hij dat zelf zegt. Stilte ≠ klaar. Duurt het te
   lang → zeg dát ("suite draait >8 min, niet afgewacht"), niet een half getal.
2. **Kan je het niet afmaken? Laat de claim staan en meld de twijfel.** "Niet geverifieerd" is
   bruikbare informatie; een verzonnen correctie niet.
3. **Verifieer in het bronproject, niet in een kopie.** Cross-project items in een handover zijn
   kopieën — check ze bij de bron vóór je erop afgaat.
4. **Klopt een claim? Zeg dat er expliciet bij**, met de datum. "384 tests groen (geverifieerd
   16-07, 5,7 min)" is meer waard dan het getal alleen — de volgende hoeft het niet over te doen.
5. **Prik door je eigen opdracht heen.** Krijg je "ruim X op" en blijkt X levend? Melden en
   stoppen, niet uitvoeren.

## Wat dit NIET is

Geen excuus om alles opnieuw te meten. Bij een verse, ondertekende claim ("geverifieerd 15-07,
`dae025c` staat op prod") ga je door. Het gaat om **claims die je aanpast**: raak je 'm aan, dan
neem je 'm over — en dan moet je 'm hard hebben.
