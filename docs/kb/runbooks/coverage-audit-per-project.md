---
title: Coverage-audit per project — de complete opdracht
type: runbook
scope: alle-projecten
last_check: 2026-07-24
---

# Coverage-audit per project

Standaardopdracht om een project zijn eigen dekking te laten nakijken. Niet om een percentage te
halen — om te zien **of het cijfer ergens op slaat**. Norm:
[`decisions/coverage-drempel-vervalt-2026-07-24.md`](../decisions/coverage-drempel-vervalt-2026-07-24.md).

## De opdracht (kopieer en vul het project in)

> Voer een coverage-kwaliteitsaudit uit voor **{project}** (`{pad}`, {stack}).
>
> **Norm sinds 24-07-2026:** geen drempel meer. Leidend is `test-quality-policy.md` — kritiek
> (auth, betalingen, migraties, security headers, data-integriteit) 100%; business 70-85%; glue
> 20-40%; projectgemiddelde 65-75% is prima. Lees ook `zinvolle-tests.md` en
> `coverage-test-cementeert-bug.md`.
>
> **Meten en documenteren — wijzig GEEN tests en GEEN applicatiecode. Nooit tests tegen staging
> of productie.**
>
> 1. Meet met het juiste commando. **Meet tot het einde:** een run is pas klaar als hij dat zelf
>    zegt (`Tests:`-regel + Total). Stilte is niet klaar. Niet afgemaakt → rapporteer expliciet
>    "niet afgemeten", nooit een half getal.
> 2. Zoek padding-verklikkers (zie tabel hieronder).
> 3. Beoordeel de **verdeling** — het belangrijkste punt. Staan modellen op 100% terwijl
>    {kritieke domein van dit project} lager staat? Noem bestanden mét percentage.
> 4. Lever op: `docs/testschuld.md` in het project (max 100 regels, met meetdatum), en corrigeer
>    coverage-cijfers/drempels in `CLAUDE.md` naar het gemeten cijfer mét datum. CLAUDE.md onder
>    120 regels — details horen in testschuld.md.
> 5. Commit + push.
> 6. Rapporteer max 15 regels: alleen bevindingen, geen procesverslag.

## Padding-verklikkers

| Verklikker | Waarom het telt |
|---|---|
| Percentage of "push/boost/max/ultimate/last" in bestandsnaam of docblock | Padding by design — `Push90Test`, `Last825Test`, `MaxServiceCoverageTest` |
| Foutstatus (`assertStatus(500)`) als **verwacht** resultaat | Cementeert een bug; de suite blijft groen terwijl het endpoint dood is |
| `assertTrue(true)` / `expect(true).toBe(true)` / geen assertions | Raakt regels, test niets |
| Mock-only tests | Je test de mock, niet het gedrag |
| Relationship-type asserts, getter/setter-tests | Staan letterlijk in het model erboven |
| Assertions per test < ~2 | Zwak signaal, maar in combinatie met de rest veelzeggend |

## Wat de uitkomst waard is

Het percentage zelf zegt weinig. **De verdeling is het signaal:** modellen op 100% naast een
betaalcontroller op 67% betekent dat er op gemak is gedekt, niet op risico. Dat patroon is
aangetroffen in Studieplanner-api (24-07) en eerder in HavunAdmin en JudoToernooi.

Padding schrappen laat het percentage zakken. Dat is winst, geen verlies — commit apart met
`refactor(tests): verwijder padding-tests`.

## Uitvoering 24-07-2026

Negen projecten parallel laten auditen (HavunAdmin, Herdenkingsportaal, JudoToernooi, SafeHavun,
Vusista, JudoScoreBoard, Studieplanner, VeenLedenadministratie, HavunCore). Studieplanner-api was
al handmatig gedaan. Resultaten per project in `docs/testschuld.md`; samenvatting in de
HavunCore-handover.

Overgeslagen wegens te weinig tests: Aeterna (1 testbestand), LastMatch (3), Havun en
HavunMarketing (0). havuncore-webapp heeft er 7, maar Vitest is daar geblokkeerd door lokale
HTTPS-interceptie — zie [lokale HTTPS-interceptie](../reference/lokale-https-interceptie.md).
