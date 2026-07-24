---
title: AI-synthese-risico's — waar code van een model structureel kwetsbaar is
type: standard
scope: alle-projecten
last_check: 2026-07-24
---

# AI-synthese-risico's — BINDEND voor alle Havun-projecten

**Kernregel: een model produceert waarschijnlijke tekst, geen bewijs. Alles wat als "af" wordt
gemeld, leunt op een meting die je hebt zien slagen én zien falen — niet op de indruk dat het
klopt.**

Drie mechanismen maken AI-gegenereerde code op een voorspelbare manier kwetsbaar. Ze zijn niet
weg te nemen met een instructie; ze worden afgevangen met werkwijze.

| Mechanisme | Hoe het hier binnenkomt | Tegenmaatregel | Waar geborgd |
|---|---|---|---|
| **Behaagzucht** — het model geeft toe zodra iemand duwt, ook als het gelijk had | Niet alleen bij Henks tegenspraak, óók bij **autoriteit van documenten**: een handover of KB-doc zegt "X is stuk" en dat wordt aangenomen | Een technische claim wijkt voor een **meting**, niet voor een mening — van wie of wat dan ook. Reproduceer vóór je fixt | [`claims-verifieren.md`](claims-verifieren.md) · [`test-rood-gezien.md`](../patterns/test-rood-gezien.md) |
| **Mainstream-bias** — teruggrijpen op het gemiddelde van GitHub | Bij afwijkende architectuur: multi-tenant, ZK-proofs, strakke security, eigen patronen. Standaard-CRUD gaat goed; het gemiddelde is daar ook goed | KB-first (eigen patronen vóór trainingsgemiddelde) + **tweede mening bij afwijkende architectuur** via `/arch` (Gemini) + ADR bij de keuze | `CLAUDE.md` §Kennisbank · [`gemini-claude-workflow.md`](../runbooks/gemini-claude-workflow.md) |
| **Randgevallen** — de happy path klopt, de rand stort in | Race-conditions, ontbrekende foutafhandeling, rate limits, lege/dubbele invoer. De code compileert en de test is groen | Kwaliteitsnormen (circuit breaker, rate limiting, Form Requests) + tests die het **gedrag** vastleggen, niet de regels tellen | [`zinvolle-tests.md`](../patterns/zinvolle-tests.md) · [`coverage-test-cementeert-bug.md`](../patterns/coverage-test-cementeert-bug.md) |

## De drie regels die eruit volgen

1. **Rood gezien, of het telt niet.** Een test die je niet hebt zien falen tegen de oude code
   bewijst niets. Zie [`test-rood-gezien.md`](../patterns/test-rood-gezien.md) — dit is de
   belangrijkste van de drie.
2. **Reproduceer vóór je fixt.** Staat er in een doc, handover of issue dat iets kapot is: eerst
   zelf zien gebeuren. Lukt dat niet, dan is dát de bevinding — geen fix op een aanname.
3. **Afwijkende architectuur = tweede mening.** Niet bij een bugfix of standaard-CRUD, wél bij
   multi-tenancy, betalingen, auth/crypto, datamigratie en alles wat "wij doen het anders dan
   normaal" is. `/arch` voor de blauwdruk, ADR voor de keuze.

## Casus 24-07-2026 — alle drie in één sessie

De handover meldde: *"havuncore-webapp — update-banner activeert wachtende SW niet zichtbaar"*.

- **Behaagzucht richting een doc:** die claim werd aangenomen en er werd een fix voor gebouwd,
  vóór de bug ooit was gezien.
- **Randgeval, verkeerd gemeten:** de eerste meting gebruikte Playwright's `isVisible()`, dat
  níét wacht. Workbox stuurt zijn `waiting`-event 200 ms na `installed` — de banner verscheen
  dus wél, net na de check. Een plausibele conclusie uit een onvolledige meting.
- **Wat het ving:** de nieuwe test werd tegen de oude code gedraaid en slaagde daar óók. Daarmee
  viel de hele diagnose om: er was geen bug. De fix is verworpen, de test bleef.

Zonder die laatste stap was er een "opgeloste bug" gerapporteerd, een handover-punt afgevinkt en
code toegevoegd die niets repareerde — met een groene test als bewijs. Meting:
[`webapp-sw-update-fix.md`](../plans/webapp-sw-update-fix.md).

## Wat dit NIET is

- **Geen reden om alles te herhalen.** Bij een verse, ondertekende claim ga je door
  (`claims-verifieren.md` §Wat dit niet is). Het gaat om claims waar je op gaat handelen.
- **Geen extra vraagronde.** De vraagdiscipline blijft: technische keuzes maakt Claude zelf. Dit
  gaat over bewijs achteraf, niet over toestemming vooraf.
- **Geen belofte van het model.** Claude is geen betrouwbare rapporteur over zijn eigen neigingen.
  Wat telt zijn de structurele checks in dit document, niet de inschatting dat het wel meevalt.
