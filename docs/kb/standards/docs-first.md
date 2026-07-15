---
title: Docs-first — code zonder MD is geen code
type: standard
scope: alle-projecten
last_check: 2026-07-15
---

# Docs-first — BINDEND voor alle Havun-projecten

**Regel (Henk, 15 jul 2026): "code zonder MD docs/plan is geen code."**
Ga je implementeren, dan werk je **eerst** de MD bij — docs en/of plan. Daarna pas code.
Andersom mag nooit.

## Waarom

De MD is de bron, de code is de uitvoering. Code die vooruitloopt op de docs levert:
- **docs die liegen** — de bekende ellende: handovers die "NOG NIET gedeployed" zeggen over iets
  dat al weken draait (zie `md-doc-grootte.md`);
- **werk zonder toets** — zonder opgeschreven bedoeling is er niets om de code tegen af te meten,
  dus "af" is een gevoel;
- **kennis die verdampt** — wat niet in een MD staat, weet de volgende sessie niet, en de KB kan
  het niet vinden.

## Wat "eerst de MD" betekent — naar omvang

| Omvang | Wat je vooraf bijwerkt |
|---|---|
| **Groot** (nieuwe feature, architectuur, >5 bestanden, meerdere sessies) | Volledige `/mpc`: fase 1 docs → fase 2 plan in een MD → **wachten op "ga maar"** → fase 3 code |
| **Middel** (afgebakende feature/fix, ~2-5 bestanden) | Plan-MD (`.claude/plan-<onderwerp>.md` of `smallwork.md`) + de feature-doc die het raakt. Dan bouwen |
| **Klein** (bugfix, 1-2 bestanden) | De doc die het gedrag beschrijft bijwerken (feature-doc/README) + het punt in de handover. Dan fixen |
| **Triviaal** (typo, formatting, comment) | Geen MD nodig. Dit is de enige uitzondering |

De omvang bepaalt **hoeveel** MD, niet **óf**. Bij twijfel: schrijf het op.

## Concreet

1. **Vóór de eerste regel code:** bestaat er een MD die beschrijft wat je gaat doen en waarom?
   Zo nee → eerst schrijven of bijwerken.
2. **Wijkt de implementatie af van het plan?** → **eerst de MD bijwerken**, dan verder coderen.
   Nooit stilzwijgend afwijken; meld de afwijking in één zin.
3. **Na afloop:** docs kloppend maken met wat er werkelijk staat, en de handover bijwerken
   (afgerond eruit, nieuw erin — zie `md-doc-grootte.md`).
4. **Herbruikbare kennis** (een patroon, een valkuil, een beslissing) hoort in de KB
   (`patterns/`, `runbooks/`, `decisions/`), niet in een sessieverslag.

## Wat dit NIET is

- Geen excuus voor een muur tekst vooraf. De doc-grootte-regel blijft gelden: een plan van 300
  regels voor een fix van 3 regels is geen docs-first, dat is uitstel.
- Geen reden om te vragen of je mag documenteren. MD's bijwerken en committen is **altijd**
  toegestaan zonder overleg — zie de vraagdiscipline in `.claude/rules.md`.
