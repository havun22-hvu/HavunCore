---
title: Coverage-padding sanitization — hoe opruimen zonder risico
type: runbook
scope: alle-projecten
status: active
last_reviewed: 2026-04-20
follows: "test-quality-policy.md"
---

# Coverage-padding opruimen — werkwijze

> Eén-regel: elke deletion moet bewijs achterlaten (nieuwe dekking elders
> of legitieme duplicate). Massaal weggooien is **riskanter dan** het
> probleem laten staan.

## Status per project (20-04-2026)

| Project | Padding-candidate files | Toegepast |
|---------|-------------------------|-----------|
| Herdenkingsportaal | **~150** files met `*Coverage*`, `*Boost*`, `Final*`, `Push[0-9]+`, `Last[0-9]+`, `Over[0-9]+` naming | pilot gestart |
| HavunCore | 0 | n.v.t. (geen padding gebouwd) |
| JudoToernooi | paar (`Push90Last`, `Push90Final`, `Over80`) | toekomst |
| Andere | nog te inventariseren | toekomst |

Inventarisatie:
```bash
ls tests/Feature tests/Unit | \
  grep -iE 'coverage|boost|ultimate|^final|push[0-9]|last[0-9]|over[0-9]' | wc -l
```

## Stappenplan per file

Voor **elke** kandidaat-file:

1. **Inhoud scannen** — hoeveel tests, welke assertions. `grep -c "public function test" <file>`.
2. **Coverage-vergelijking** — welke regels in target-class dekt deze file die een andere file ook al dekt? `grep -l "TargetClass" tests/ -r` levert alle tests op die die class aanraken.
3. **Classificatie:**
   - **A. Duplicate** — exact scenario bestaat elders → delete met verwijzing.
   - **B. Padding** — 0-1 assertie, tautologie, alleen `new` + assertInstance → delete.
   - **C. Stale** — asserteert niet-meer-bestaand gedrag → delete (commit legt uit).
   - **D. Zinvol** — bevat assertieve dekking die nergens anders zit → **behouden**, evt. hernoemen.
4. **Verwijderen + commit** met:
   - Filename(s).
   - Welke regels dekking verloren gaan (meestal 0).
   - Verwijzing naar surviving tests.
5. **Suite draaien** na elk paar verwijderingen — niet-groen = rollback.
6. **qv:scan test-erosion** checken — deletions worden gemonitord, maar dit proces hoort bij de "legitieme verwijdering" per VP-17.

## Pilot — bewijs dat padding echt padding is

Stap 1: kies 3 duidelijke duplicaten uit één groep (bv. `AdminControllerCoverage2..5Test.php`).
Stap 2: meet coverage-baseline (Unit + Feature).
Stap 3: verwijder één file.
Stap 4: meet opnieuw. Als delta < 0,5 pp en mutation-score op AdminController gelijk blijft → bevestigd padding.
Stap 5: herhaal voor de rest van de groep.

Dit pilot bewijst het mechanisme. Daarna kan per groep worden doorgepakt.

## Veiligheids-guards

- **Nooit ruw delete** op basis van naampatroon alleen.
- **Nooit** meer dan 10 files per commit; anders wordt debuggen bij suite-breuk lastig.
- **Mutation-run op kritieke paden** blijft de echte guard — zolang die niet zakt is de deletion veilig geweest.
- Verwijderde files mogen **nergens** worden genoemd (geen imports, geen referenties in docs buiten historische commits).

## Bewezen valkuil: naam is geen bewijs

Tijdens de eerste pilot-ronde (20-04-2026) bleek dat **HP
`tests/Unit/MiscCoverageTest.php`** ondanks de padding-naam
**14 zinvolle tests** bevat (mail-envelopes, listener-gedrag,
model-observers — allemaal met werkelijke assertions). Verwijdering
op basis van de naam zou echte dekking hebben weggenomen.

**Les:** `*Coverage*Test` betekent niet automatisch padding. De
padding-check is **inhoudelijk** (assertion-armoede, tautologie,
duplicatie), niet lexicaal. De naamregex levert alleen **kandidaten**
voor handmatige review.

Wat wel evident padding bleek (separate sessie eerder): de twee
`VerifyBunqPayments`-tests in `FinalCoverageBoost2Test` +
`Over80Test` — stale assertion, dubbele coverage, geen echte
scenario-waarde. Die zijn verwijderd.

## Waarom dit rustig aan

Een verkeerde deletion betekent: echte dekking weg zonder het te merken, tot
er een regressie doorheen slipt. Beter is langzaam, gecontroleerd opruimen
dan "we zijn nu van de 150 files af" voelen.

Proces verwacht over **3-5 sessies** voor HP afgerond (de grootste padding-
berg). Andere projecten: 1 sessie each.

## Zie ook

- `test-quality-policy.md` — waarom en wat zinvolle tests zijn (BINDING).
- `runbooks/test-repair-anti-pattern.md` — VP-17, niet relevant voor duidelijke padding maar wél bij onduidelijke failures.
