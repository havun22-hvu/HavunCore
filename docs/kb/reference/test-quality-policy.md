---
title: Test-kwaliteit — bindend beleid voor alle Havun projecten
type: reference
scope: alle-projecten
status: BINDING
last_reviewed: 2026-04-20
supersedes: "havun-quality-standards.md §Coverage"
---

# Test-kwaliteit — bindend beleid

> **Eén regel:** wij leveren **zinvolle, robuuste tests op gevoelige plekken**.
> Geen cosmetische coverage-padding, geen assertion-flips, geen "test bestaat
> dus goed". Kwaliteit is wat een externe audit moet kunnen herleiden.

## 1. Waarom dit beleid

Coverage-percentages waren de primaire metric. Dat leidde tot **padding-tests**
(`*Coverage2Test.php`, `*Boost*Test.php`, `*UltimateCoverage*`, lege
`assertTrue(true)`-tests) die het getal opkrikten maar **niets testen**.
Die tests:

- geven valse zekerheid aan auditors en onszelf;
- blokkeren refactors (ze breken op implementatie-details);
- maken het echte kwaliteitssignaal onleesbaar tussen de ruis;
- zijn erger dan geen test — ze zeggen "ok" waar het niet ok is.

**Per 20-04-2026 is coverage-padding verboden.** Dit document is de binding
standaard; `havun-quality-standards.md` verwijst hiernaar.

## 2. De 3 lagen

| Laag | Domein | Eis | Test-type |
|------|--------|-----|-----------|
| **1. Kritiek** | auth, betalingen, migraties, security headers, data-integriteit, externe integraties met credentials | **100 %** — elke branch, elke edge-case, elk faalpad, expliciete assertie | Integration + Unit + **Mutation** |
| **2. Business-logic** | services, queries, workflows, commands die business-regels afdwingen | 70-85 % — happy path + belangrijke errors | Unit + Feature |
| **3. Glue** | thin controllers, getters, DTOs, views, framework-boilerplate | **zo laag als praktisch** (typisch 20-40 %) | Smoke via feature-routes |

**Projectgemiddelde komt dan typisch op 65-75 %.** Dat is geen zwakte — dat is
**juist gekalibreerd**: moeite gaat naar waar het ertoe doet.

## 3. Kritieke paden — expliciet per project

Elk project heeft een `docs/kb/reference/critical-paths-{project}.md` met:

- **Lijst van endpoints / services / flows** die 100 % moeten zijn.
- **Per pad: welke testen dit afdekken** (bestand + testmethod).
- **Per pad: mutation-score target** (minimaal 80, meestal 90+).
- **Laatste review-datum.**

Audit-bewijs: "Wij laten zien dat deze N paden ironclad zijn, niet dat ons
getal X % is." Voorbeeld: `docs/kb/reference/critical-paths-havuncore.md`.

## 4. Wat telt als zinvolle test

Een test is **zinvol** als:

1. **Hij heeft minstens één assertie die de waarneembare uitkomst controleert.**
   Niet `assertTrue(true)`, niet `$this->assertInstanceOf(Foo::class, $foo)`
   direct na `new Foo()`.
2. **Hij zou breken als het gedrag verandert.** Mutation-test: verander de
   implementatie, vangt een test het? Anders test je niets.
3. **Hij test gedrag, geen implementatie.** Geen `->shouldHaveCalled('thatPrivateMethod')`
   als de publieke uitkomst hetzelfde blijft. Refactoring moet tests groen
   houden zolang de contracten niet veranderen.
4. **De naam beschrijft het scenario**, niet het bestand/LOC-doel.
   - Goed: `test_webhook_verifies_mollie_signature_before_updating_payment`
   - Slecht: `test_payment_controller_line_47` of `test_boost_coverage_3`

## 5. Wat is coverage-padding (verboden)

Rode vlaggen — weigeren én bij bestaand werk wegsnijden:

- **Bestandsnaampatronen**: `*CoverageTest.php`, `*Coverage[0-9]Test.php`,
  `*Boost*Test.php`, `*Ultimate*Test.php`, `Final*Test.php`, `Push[0-9]+Test.php`,
  `Last[0-9]+Test.php`, `Over[0-9]+Test.php`. Deze namen bekennen dat het
  getal-gericht is, niet gedrag-gericht.
- **Assertion-armoede**: test van 30 regels met 1 `assertTrue(true)`.
- **Tautologie-tests**: `assertEquals($obj->foo, $obj->foo)` of
  `assertInstanceOf(X::class, new X())`.
- **Dubbele tests**: meerdere test-files die hetzelfde scenario raken zonder
  extra branches te dekken (zie HP `VerifyBunqPayments` — was 2× dupliceert
  in Final/Over80 + al gedekt in CoverageDeepCommandsTest).
- **Stale assertion-flip** (VP-17): test faalt → iemand wijzigt alleen de
  `assertEquals(1, ...)` naar `assertEquals(0, ...)`. Altijd **eerst
  oorzaakonderzoek**.

## 6. Wanneer mag je een test verwijderen

Drie gevallen waarin **delete** de juiste actie is:

1. **Duplicaat** — exact scenario bestaat elders; verwijzing in commit-bericht.
2. **Stale** — test asserteert gedrag dat niet meer bestaat (bv. een
   command-argument dat is verwijderd). Vervang door test van het huidige
   gedrag of verwijder als elders gedekt.
3. **Tautologie/padding** — test zonder echte assertie (zie §5).

**Altijd met commit-bericht dat uitlegt:**

- Welke dekking verloren gaat (vaak: geen, want elders gedekt).
- Waarom de test als padding gold.
- Verwijzing naar de surviving tests.

**Niet verwijderen bij**: test faalt en je wil niet uitzoeken waarom (VP-17).

## 7. Metrieken die we echt meten

1. **Mutation-score op kritieke paden** — primair. Tooling: Infection (PHP),
   Stryker (TS). Baseline: `docs/kb/reference/mutation-baseline-2026-04-17.md`.
2. **Assertion-density** — assertions per test. Target ≥ 2. Tests met 0-1
   worden geflagd in code-review.
3. **Line-coverage** — secundair. Floor: 60 % Unit, 80 % Unit+Feature voor
   Laravel-projecten (blijft CI-gate om gaten te zien, niet om ons eraan
   te meten).
4. **Branch-coverage** — waar line- en branch-% uit elkaar lopen (>10 pp)
   zitten vaak untested edge-cases.
5. **Test-erosion** — deletions in `tests/` van laatste 30 dagen, geflagd
   door `qv:scan --only=test-erosion`. Elke deletion moet uit te leggen zijn
   volgens §6.

## 8. Operationeel — bij elke PR / commit

- **Raak je een kritiek pad aan?** Werk dan ook `critical-paths-{project}.md`
  bij (test-referenties actueel houden).
- **Schrijf je een nieuwe test?** Lees §4 en stel jezelf de mutation-vraag:
  "Als ik één regel in de target-code flip, faalt mijn test dan?"
- **Verwijder je een test?** Volg §6. Commit-bericht verplicht.
- **Is er een falende test?** VP-17: eerst oorzaak, dan fix. Never flip the
  assertion.

## 9. Wat audit-ready betekent (voor Gemini/externe review)

Een externe reviewer moet in 5 minuten kunnen zien:

1. Welke paden zijn kritiek? → `critical-paths-*.md`.
2. Hoe goed zijn die gedekt? → mutation-baseline + referentie per pad.
3. Hoe voorkomen we regressie? → `qv:scan` test-erosion + VP-17 beleid.
4. Hoe voorkomen we padding? → dit document + naam-patronen in §5.

**Niet:** "Hier is een 87 %-number." Dat zegt niks en kan verhuld padding
bevatten. Wat wij verkopen is **aantoonbare kwaliteit op de paden die ertoe
doen** — en aantoonbare discipline op de rest.

## Zie ook

- `havun-quality-standards.md` — omvattende enterprise-normen (deze file
  is autoritatief voor de sectie "Testing").
- `mutation-baseline-2026-04-17.md` — startpunt mutation testing.
- `runbooks/test-repair-anti-pattern.md` — VP-17 in detail.
- `critical-paths-havuncore.md` — eerste concrete kritieke-paden-lijst
  (exemplar voor andere projecten).
