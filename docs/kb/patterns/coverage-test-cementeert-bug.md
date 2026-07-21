---
title: Een coverage-test die een 500 vastlegt cementeert de bug
type: pattern
scope: havuncore
tags: [testing, coverage, anti-pattern, phpunit]
last_check: 2026-07-21
---

# Een coverage-test die een 500 vastlegt cementeert de bug

**Regel:** een test die `assertStatus(500)` (of een andere fout) verwacht om een regel "gedekt" te
krijgen, is geen dekking — hij maakt de bug permanent en houdt de suite groen terwijl het endpoint
dood is. Zoek bij een verdacht groene suite op `assertStatus(500)` in de coverage-tests.

## Hoe je het herkent

De verklikker is een comment die de bug beschrijft in plaats van hem te fixen:

```php
// Note: verplaatsJudoka has a known bug ($nieuweIsDynamisch undefined on line 259)
// but hitting the endpoint still covers lines 171-258
$response = $this->postJson($url, [...]);
$response->assertStatus(500);
```

Iemand jaagde op een coverage-percentage, zag een kapotte endpoint, en legde de crash vast als
verwacht gedrag. Coverage-tooling telt de geraakte regels vóór de exception als gedekt, dus het
cijfer stijgt en niemand kijkt om.

## Waarom het gevaarlijk is

- **De bug wordt onzichtbaar.** De suite is groen; de fix van de bug maakt hem juist rood.
- **Het is besmettelijk.** In JudoToernooi stonden er drie in één testklasse
  (`WedstrijddagControllerCoverageTest`): een undefined variabele (elke judoka-verplaatsing 500),
  een `null` op een NOT NULL-kolom (elke herstel-actie 500), en een `null` in een `string`-getypte
  parameter (elke laatkomer met gewicht 500). Alle drie live op productie, alle drie "gedekt".

## Wat te doen

1. Fix de onderliggende bug.
2. Draai de assert om naar het correcte resultaat (200/422/…) én voeg een assert toe die het
   *effect* controleert — is de judoka echt verplaatst, is de waarde echt weggeschreven. Een kale
   `assertStatus(200)` is bijna net zo zwak als de 500 die je verving.
3. Grep de rest van de coverage-tests op `assertStatus(500)`; waar een bug-comment bij staat is het
   bijna altijd hetzelfde patroon.

## Bredere les

Coverage-percentage is een middel, geen doel. Een regel "raken" met een request die crasht dekt
niets af — het bewijst alleen dat de regel bestaat. Dekking betekent: het verwachte gedrag is
vastgelegd en zou breken als iemand het stukmaakt.
