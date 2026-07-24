---
title: Een test die je niet rood hebt gezien, bewijst niets
type: pattern
scope: alle-projecten
tags: [testing, regressie, bewijs, ai-synthese]
last_check: 2026-07-24
---

# Een test die je niet rood hebt gezien, bewijst niets

**Regel: bij een bugfix zie je de test éérst falen tegen de oude code. Doe je dat niet, dan weet je
niet of de test de bug vangt of alleen de zon ziet schijnen — en meld je dat expliciet.**

Een groene test na een fix heeft twee mogelijke verklaringen: de fix werkt, óf de test raakt de bug
niet. Beide zien er identiek uit. De enige manier om ze uit elkaar te houden is de test draaien
zonder de fix.

## Werkwijze

| Stap | Wat je doet |
|---|---|
| 1 | Reproduceer de bug — handmatig of in een test. Lukt dat niet: **stop**, dat is de bevinding |
| 2 | Schrijf de test. Draai hem tegen de **oude** code → moet **rood** |
| 3 | Fix. Draai opnieuw → **groen** |
| 4 | Meld dat je stap 2 hebt gezien, in de commit of in je bericht |

Stap 2 hoeft niet netjes: `git stash` op de fix, of de fix even terugdraaien, is genoeg. Wat telt
is dat je de rode uitslag met eigen ogen hebt gehad.

**Let op bij builds en servers.** Een testrunner die een draaiende server hergebruikt of een
bestaande build serveert, test je oude code helemaal niet. Bouw expliciet opnieuw voordat je stap 2
beoordeelt — anders "faalt de test niet" om een reden die niets met je fix te maken heeft. Dit ging
op 24-07 precies zo mis: `reuseExistingServer` hield de nieuwe build in de lucht terwijl de test
tegen de "oude" code heette te draaien.

## Niet af te maken? Zeg dat

"Test toegevoegd, niet rood gezien tegen de oude code" is bruikbare informatie. "Bug gefixt, test
groen" terwijl stap 2 nooit is gebeurd, is een claim zonder dekking. Zelfde principe als
[`claims-verifieren.md`](../standards/claims-verifieren.md): niet-geverifieerd melden mag, een
onbewezen conclusie presenteren niet.

## Waarom dit een eigen regel is (24-07-2026)

Een openstaand punt in de HavunCore-handover meldde dat de update-banner van de webapp de
wachtende service worker niet activeerde. Er is een E2E-test gebouwd én een fix. De test was groen.

Toen diezelfde test tegen de **oude** code werd gedraaid, was hij óók groen: de flow werkte al. De
oorspronkelijke diagnose berustte op een meetfout (`isVisible()` wacht niet, workbox stuurt zijn
`waiting`-event 200 ms later). De fix is verworpen, de test bleef staan, het handover-punt is
gecorrigeerd naar "niet reproduceerbaar".

Zonder stap 2 was het resultaat geweest: een afgevinkt handover-punt, overbodige code in de
service-worker-hook, en een groene test als schijnbewijs.

## Wanneer wél overslaan

- **Nieuwe functionaliteit** zonder voorafgaande bug — daar is niets om rood te zien. De regel
  gaat over fixes en regressies.
- **Triviale, zichtbare fixes** (typo in een string, verkeerde constante) waar de test letterlijk
  de waarde asserteert.

## Zie ook

- [`ai-synthese-risicos.md`](../standards/ai-synthese-risicos.md) — waarom dit juist bij
  AI-gegenereerde code telt
- [`coverage-test-cementeert-bug.md`](coverage-test-cementeert-bug.md) — een groene test die de
  bug juist vastlegt
- [`test-repair-anti-pattern.md`](../runbooks/test-repair-anti-pattern.md) — nooit assertions
  aanpassen om groen te worden
