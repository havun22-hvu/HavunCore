# Beslissing: Scoreboard layout — horizontale scores in landscape/LCD

**Datum:** 2026-04-01
**Project:** JudoScoreBoard + JudoToernooi (LCD display)
**Status:** Vastgesteld

## Context

De JudoScoreBoard Android app heeft twee oriëntaties (portrait + landscape) en een gekoppeld LCD display (Blade webpagina). De landscape layout gebruikte "side-columns" met geneste flex, wat onvoorspelbaar schaalde in React Native.

## Probleem

React Native's flexbox is een subset van CSS flexbox — geen CSS Grid, geen `vh` units, geen betrouwbare `flex-shrink`. Geneste flex (2D: rijen + kolommen tegelijk) geeft onvoorspelbare resultaten, vooral in landscape waar de beschikbare hoogte beperkt is (~400px).

## Beslissing

**Scores (Y/W/I) horizontaal naast elkaar in landscape en LCD. Verticaal in portrait.**

### Portrait (app)
- Rij-structuur, flex-based
- Scores verticaal (Y/W/I onder elkaar) — meer hoogte beschikbaar
- Bewezen werkend, niet aanpassen

### Landscape (app)
- **Zelfde rij-structuur als portrait** — geen side-columns
- Scores horizontaal [Y 0] [W 0] [I 0] per kant
- Flex-based (1D), net als portrait
- Oriëntatie-verschil via `layout.horizontalScores` flag in useScoreboardLayout hook

### LCD display (Blade/CSS)
- Zelfde visuele layout als Android landscape
- Scores horizontaal per kant
- CSS flexbox/grid (heeft wél 2D mogelijkheden, maar voor consistentie dezelfde aanpak)

## Waarom niet geneste flex (side-columns)?

| Aanpak | Portrait | Landscape | Probleem |
|--------|----------|-----------|----------|
| Rijen (1D flex) | Werkt | Werkt | Geen |
| Side-columns (2D geneste flex) | N.v.t. | Onvoorspelbaar | Flex-in-flex schaalt slecht in RN |
| Berekende pixels | Werkt | Werkt | Complexer, foutgevoelig (som moet 100% zijn) |

## Gevolgen

- Eén JSX template voor beide oriëntaties
- Enige verschil: `flexDirection: 'row'` vs `'column'` op scores container
- LCD volgt dezelfde visuele structuur
- Makkelijker te onderhouden dan drie verschillende layout-strategieën
