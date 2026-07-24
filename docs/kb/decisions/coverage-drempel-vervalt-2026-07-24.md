---
title: "De 80%-coveragedrempel vervalt — zinvolle tests, geen percentage"
type: decision
date: 2026-07-24
status: accepted
supersedes: decisions/enterprise-quality-standards.md (het coverage-deel)
scope: alle-projecten
---

# Besluit: de coveragedrempel vervalt

**Henk, 24-07-2026:** *"we hebben afgesproken dat we niet per se een coverage >80% willen, want
dat was aanvankelijk de opzet, maar bleek een wassen neus — een coverage met veel onzinnige
testen. We willen zo hoog mogelijk % zinnige testen implementeren, in alle projecten."*

> **Dit is geen nieuw inzicht — het is handhaving.** [`test-quality-policy.md`](../reference/test-quality-policy.md)
> zegt sinds **20-04-2026** al hetzelfde, en is BINDEND: padding verboden, een gelaagd model
> (kritiek 100% / business 70-85% / glue 20-40%) met een projectgemiddelde van 65-75%. Wat
> ontbrak was de naleving: er stond een CI-drempel van ≥80% naast, de CLAUDE.md's droegen ">80%",
> en er kwam ná april nog padding bij (`Push90Test`, 24-07 gemeten). Dat besluit van vandaag haalt
> de laatste drempels weg, zodat het beleid niet meer wordt tegengesproken door zijn eigen gate.

## Wat vervalt

- De verplichte **80% line coverage** voor alle projecten.
- De verscherpte **85%** voor projecten met publieke betalingen.
- Elke project-specifieke ondergrens (`--min=82.5` en varianten).

## Wat ervoor in de plaats komt

**Zo hoog mogelijke dekking met uitsluitend zinvolle tests.** Een test telt alleen mee als hij een
contract, invariant, bug-regressie of domeinregel vastlegt — de vier categorieën uit
[`zinvolle-tests.md`](../patterns/zinvolle-tests.md). Het percentage is de **uitkomst**, nooit het
doel.

Waar het cijfer botst met de inhoud, wint de inhoud: padding schrappen mag de coverage laten
zakken. Dat is dan winst, geen verlies.

## Waarom

De drempel produceerde precies wat hij moest voorkomen. Gemeten bij Studieplanner-api (24-07):

- `Push90Test.php` — 36 tests, 811 regels, met in het eigen docblock *"Tests to push coverage from
  88.6% to 90%+"*. Geschreven voor het getal.
- `ModelRelationsTest.php` — 377 regels die `belongsTo`/`hasMany` verifiëren; die relatie staat in
  het model erboven.
- `assertStatus(500)` in twee API-tests: vastgelegd dat een onbereikbare externe API een **500**
  uit onze eigen API laat komen. Dat hoort 502/503 te zijn — een kapot foutpad als norm.

Ondertussen stond `PremiumController` (XRP-betalingen) op 67,7%, `AuthController` op 80,7% en
`UserDevice` op 0%, terwijl elf modellen 100% haalden. **Gedekt werd wat makkelijk was, niet wat
kapot mag gaan.** Een ondergrens beloont precies die verdeling: elke goedkope regel telt even
zwaar als een dure.

Hetzelfde patroon zat eerder in HavunAdmin (`Push90Test`, `Last825Test`, `MaxServiceCoverageTest`)
en JudoToernooi (drie `assertStatus(500)`-tests op live-productiebugs).

## Hoe je het dan wél stuurt

1. **Risico bepaalt de diepte.** Betalingen, auth, multi-tenant-scoping, datamigratie en
   security-headers horen volledig gedekt — met assertions op het effect, niet op de statuscode.
2. **Scheefheid is het signaal.** Modellen op 100% naast een betaalcontroller op 67% betekent dat
   er op gemak is gedekt. Kijk naar de verdeling, niet naar het gemiddelde.
3. **Meet met datum.** Een coverage-getal zonder meetdatum in een `CLAUDE.md` is een claim, geen
   feit — zie [`claims-verifieren.md`](../standards/claims-verifieren.md).
4. **Padding schrappen mag altijd**, en heeft geen goedkeuring nodig. Commit apart met
   `refactor(tests): verwijder padding-tests`.

## Gevolg voor bestaande docs

`runbooks/test-coverage-normen.md` is herschreven. De ondergrenzen zijn uit de CLAUDE.md's van de
actieve projecten gehaald. De per-project bevindingen staan in `docs/testschuld.md` in het
betreffende project.
