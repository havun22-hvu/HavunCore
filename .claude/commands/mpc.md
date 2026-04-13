# MPC — MD, Plan, Codering

> Gefaseerde werkwijze: eerst documenteren, dan plannen, dan pas coderen.

## Fase 1: MD Docs

**Werk ALLEEN aan de MD docs.** Geen code schrijven.

1. Lees de bestaande docs over het onderwerp (`docs/`, `docs/kb/`, `.claude/`)
2. Zijn ze compleet? Duidelijk? Helder? Consistent met andere docs?
3. Ontbrekende informatie → vraag aan gebruiker
4. Tegenstrijdigheden → meld en corrigeer
5. Update/maak docs tot ze **100% compleet** zijn

**Klaar-criteria:** Een andere Claude sessie kan de docs lezen en EXACT weten wat er gebouwd moet worden, zonder aannames.

## Fase 2: Plan

**Pas als alle docs compleet zijn** → maak een implementatieplan.

1. Welke bestanden moeten gewijzigd/aangemaakt worden?
2. In welke volgorde?
3. Wat zijn de afhankelijkheden?
4. Wat kan parallel?
5. Waar zitten de risico's?

Schrijf het plan in een MD bestand. Leg voor aan de gebruiker voor akkoord.

## Fase 3: Codering

**Pas na akkoord op het plan** → voer het uit.

1. Volg het plan stap voor stap
2. Test na elke stap
3. Commit na elke werkende stap
4. Bij afwijking van plan: update het plan EERST, dan pas code

## Regels

- **NOOIT** code schrijven in fase 1
- **NOOIT** coderen zonder plan in fase 2
- **NOOIT** afwijken van plan zonder update
- Bij twijfel: terug naar fase 1 (docs)
