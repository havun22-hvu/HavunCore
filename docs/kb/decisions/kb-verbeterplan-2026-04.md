---
title: KB Verbeterplan — 5 Verbeterpunten Ollama/KB Systeem
type: decision
scope: havuncore
last_check: 2026-04-22
---

# KB Verbeterplan — 5 Verbeterpunten Ollama/KB Systeem

> **Datum:** 7 april 2026
> **Doel:** KB beter benutten, van passief zoekgereedschap naar actieve kennisassistent
> **Uitvoering:** Eerst HavunCore, dan uitbreiden naar alle projecten

---

## Huidige staat

- Ollama lokaal (nomic-embed-text, 768 dimensies)
- SQLite doc_intelligence database (1944 bestanden, 13 projecten)
- Handmatig zoeken via `docs:search`
- Auto-update 2x per dag (08:03 + 20:07)
- Weinig automatisch gebruik door Claude sessies

---

## Punt 1: Claude automatisch laten zoeken

**Probleem:** Claude zoekt alleen in de KB als de gebruiker het vraagt. Bij veel taken zou de KB automatisch geraadpleegd moeten worden.

**Oplossing:** Regel toevoegen aan CLAUDE.md van elk project:

```markdown
### KB Automatisch Raadplegen (VERPLICHT)
Bij ELKE vraag over features, betalingen, auth, deployment, of configuratie:
1. Draai `cd D:\GitHub\HavunCore && php artisan docs:search "[onderwerp]"` VOORDAT je code leest
2. Vermeld de bron: "Volgens [bestand] regel [X]..."
3. Als de KB geen resultaat geeft: meld dit aan de gebruiker
```

**Bestanden te wijzigen:**
- `CLAUDE.md` van alle 8 projecten
- `claude-werkwijze.md` — sectie "LEES" uitbreiden

**Geschatte tijd:** 30 minuten

---

## Punt 2: Betere zoekresultaten met type-filter

**Probleem:** Zoekresultaten mengen docs, models, controllers, configs. Als je zoekt naar "mollie betaling" krijg je ook migrations en configs die niet relevant zijn.

**Oplossing:** Type-filter promoten in de workflow:

```bash
# In plaats van:
docs:search "mollie betaling"

# Gebruik:
docs:search "mollie betaling" --type=service    # alleen services
docs:search "mollie betaling" --type=docs       # alleen MD docs
docs:search "mollie betaling" --type=controller # alleen controllers
```

**Bestanden te wijzigen:**
- `/start` command: voorbeelden met `--type` toevoegen
- `claude-werkwijze.md`: type-filter uitleggen
- `doc-intelligence-setup.md`: al bijgewerkt (bevestigen)

**Geschatte tijd:** 15 minuten

---

## Punt 3: KB als antwoord-bron met bronvermelding

**Probleem:** Claude zoekt in de KB maar citeert zelden de bron. De gebruiker weet niet OF het antwoord uit de docs komt of verzonnen is.

**Oplossing:** Verplichte bronvermelding na KB-zoekactie:

```markdown
### Bronvermelding (VERPLICHT na KB search)
Na elke `docs:search` MOET je de bron vermelden:

"Volgens [bestandsnaam] (regel X): [citaat]"

Als de KB geen resultaat geeft:
"KB bevat geen informatie over [onderwerp]. Zal ik dit documenteren?"
```

**Bestanden te wijzigen:**
- `claude-werkwijze.md` — bronvermelding toevoegen aan DOCS-FIRST sectie
- `/start` command — in de 5 onschendbare regels opnemen
- Alle project CLAUDE.md files — verwijzing naar bronvermelding

**Geschatte tijd:** 30 minuten

---

## Punt 4: Automatische KB-update bij elke commit

**Probleem:** KB wordt 2x per dag bijgewerkt (08:03 + 20:07). Tussen die tijden kan de KB verouderd zijn — een sessie zoekt en vindt oude info.

**Oplossing:** Git post-commit hook die KB bijwerkt na elke commit:

```bash
# .git/hooks/post-commit
#!/bin/bash
# Update KB na elke commit (alleen gewijzigde bestanden, geen --force)
cd D:\GitHub\HavunCore
php artisan docs:index $(basename $(pwd)) &
```

**Alternatief:** `docs:watch` commando draait al continu — activeren als Windows service of via `start.bat`.

**Implementatie:**
1. Post-commit hook script aanmaken
2. Hook installeren in alle 8 projecten
3. OF: `docs:watch --interval=60` als achtergrondproces

**Bestanden te wijzigen/aanmaken:**
- `scripts/post-commit-kb-update.sh` — hook script
- `scripts/install-hooks.sh` — installer voor alle projecten
- `docs/kb/runbooks/doc-intelligence-setup.md` — hook documenteren

**Geschatte tijd:** 1 uur

---

## Punt 5: KB zoekbalk in StatusView

**Probleem:** Om de KB te doorzoeken moet je een terminal openen en een commando typen. Niet handig als je snel iets wilt opzoeken.

**Oplossing:** Zoekbalk toevoegen aan de havuncore-webapp StatusView:

```
┌─────────────────────────────────────────┐
│  🔍 Zoek in KB: [________________] [🔎] │
│                                         │
│  Resultaten:                            │
│  1. mollie-payments.md (87% match)      │
│     "iDEAL | Wero checkout flow..."     │
│  2. judotoernooi.md (72% match)         │
│     "Mollie Connect + Platform mode..." │
│  3. betalingen.md (65% match)           │
│     "Inschrijfgeld via iDEAL..."        │
└─────────────────────────────────────────┘
```

**Implementatie:**
1. Frontend: zoekbalk component in StatusView (of nieuw tabblad)
2. Backend: endpoint `/api/kb/search` die `DocIndexer::search()` aanroept
3. Resultaten: titel, match percentage, preview snippet, link naar bestand

**Bestanden te wijzigen/aanmaken:**
- `webapp/frontend/src/components/KBSearchView.jsx` — nieuwe component
- `webapp/backend/src/routes/kb.js` — API endpoint
- `webapp/frontend/src/App.jsx` — tab/route toevoegen

**Geschatte tijd:** 2-3 uur

---

## Uitvoeringsplan

| Punt | Wat | Tijd | Volgorde |
|------|-----|------|---------|
| 1 | Auto-zoeken in CLAUDE.md | 30 min | EERST |
| 2 | Type-filter promoten | 15 min | Samen met 1 |
| 3 | Bronvermelding verplicht | 30 min | Samen met 1 |
| 4 | Post-commit KB update | 1 uur | Na 1-3 |
| 5 | KB zoekbalk in StatusView | 2-3 uur | Apart |
| **Totaal** | | **~5 uur** | |

**Punt 1-3:** Kunnen in één sessie (alleen MD docs wijzigen)
**Punt 4:** Aparte sessie (scripts + hooks)
**Punt 5:** Aparte sessie (React + Node.js code)

---

## Na implementatie

- Claude zoekt AUTOMATISCH in de KB bij elke vraag
- Zoekresultaten zijn gefilterd op type (relevanter)
- Antwoorden bevatten bronvermelding (verifieerbaar)
- KB is altijd actueel (post-commit update)
- Zoeken kan ook via de webapp (zonder terminal)

---

*Aangemaakt: 7 april 2026*
