---
title: MD-docs moeten leesbaar blijven voor Claude (doc-grootte)
type: standard
scope: alle-projecten
last_check: 2026-07-15
---

# MD-doc grootte — BINDEND voor alle Havun-projecten

**Regel:** een doc dat te lang is, wordt niet gelezen — niet door Claude, niet door jou.
De staart is dan zinloos. Schrijf voor iemand die alleen het begin leest.

## Waarom (niet alleen netheid)

1. **Context-budget.** Claude leest meerdere docs per taak. Eén doc van 400 regels verdringt
   vijf andere. Lange docs maken de sessie dommer, niet slimmer.
2. **Afnemende trefkans.** Hoe langer het doc, hoe kleiner de kans dat het relevante stuk
   überhaupt in beeld komt.
3. **Een lang doc liegt eerder.** Niemand leest de staart na, dus daar blijft achterhaalde tekst
   staan — zie het JudoToernooi-voorbeeld onderaan.

> **Vervallen reden (16-07-2026): "de KB indexeert alleen het begin".** Dat gold tot 15-07 —
> `docs:search` embedde per document alleen de eerste ~2000-8000 tekens, dus de staart was
> onvindbaar. **Chunking heeft dat opgelost**: lange docs worden in stukken geëmbed en per stuk
> doorzocht (`HavunCore/docs/kb/plans/kb-chunking-plan.md`). Vindbaarheid is dus **geen** argument
> meer voor korte docs — de andere drie redenen zijn dat wel. Deze regel blijft staan, met een
> eerlijker fundament.
>
> Let ook op de eenheid: de grenzen hieronder tellen **regels**, de indexer telde **tekens**.
> Die twee zijn nooit gelijk geweest; sinds chunking maakt het voor de zoekfunctie niet meer uit.

## Harde grenzen

| Soort doc | Richtlijn | Hard maximum |
|-----------|-----------|--------------|
| KB-pattern / standard / reference | 60-120 regels | **200** |
| Runbook | 80-150 regels | **250** |
| `CLAUDE.md` (per project) | 40-80 regels | **120** |
| Handover | per sessie 15-30 regels | zie hieronder |
| Plan / blueprint | 100-200 regels | **300** |

**Over het maximum? Splitsen, niet persen.** Een index-doc + deeldocs leest beter dan één muur.

## Hoe: hiërarchie, niet compressie

Kort maken ≠ alles eruit gooien. Zet de conclusie bovenaan en de details eronder:

1. **Bovenaan:** wat is dit, wat moet ik weten, wat is de status — in 3-5 regels.
2. **Daarna:** een tabel met bevindingen/stappen. Tabellen zijn dichter dan proza.
3. **Daarna pas:** onderbouwing, achtergrond, alternatieven.
4. **Details die zelden nodig zijn:** eigen doc + link. Niet onderaan plakken.

Vuistregel: **kan iemand na de eerste 20 regels handelen?** Zo nee, herschrijf de kop.

## Handovers — er is er precies één, en die werk je BIJ

De grootste veroorzaker van muren. **Regel (Henk, 15 jul 2026): één `.claude/handover.md`,
bijwerken — nooit een sessieblok toevoegen.** Het is een **levende status** ("hoe staat dit project
ervoor"), geen logboek ("wat deed ik wanneer"). Git bewaart de historie al.

Bij elke `/end`:

1. **Afgeronde taken weghalen** — niet doorstrepen, niet naar "Afgerond" schuiven. Weg.
   Waarde voor later? → KB (`patterns/`, `runbooks/`, `decisions/`).
2. **Nieuwe open punten toevoegen** aan de bestaande lijst.
3. **Bestaande punten bijwerken** als de status veranderde.
4. **Achterhaalde tekst schrappen** — verifieer bij twijfel (`git log`, `composer.json`, server)
   in plaats van te laten staan.

**Max ~120 regels.** Groeit hij daarboven → er staat afgeronde geschiedenis in. Weghalen, niet
splitsen. Vaste vorm: status → open punten → recent afgerond (kort) → vaste projectcontext.

> **Waarom dit hard is.** JudoToernooi's handover groeide naar **842 regels** met 20+ sessieblokken:
> "(Afgerond) Laravel 12 — GEDEPLOYED" stond pal boven "⚠️ Laravel 12 — NOG NIET gedeployed", en
> taken die al weken klaar waren (forms-coverage, `.env.bak`-residu) stonden nog open. Zo'n doc kost
> niet alleen context — hij **liegt**, en dat is erger dan geen handover.

## Wat je NIET doet

- Tekst comprimeren tot telegramstijl of pijlketens (`A → B → faalt`). Onleesbaar ≠ kort.
- Details schrappen die de volgende sessie een fout besparen. Die verhuizen naar een eigen doc.
- Eén doc laten groeien "omdat het bij elkaar hoort". Splits op zodra het over het maximum gaat.
