# Start Session Command

> **VERPLICHT** bij elke nieuwe Claude sessie

## Stap 1: Lees de project documentatie (VERPLICHT)

Lees deze bestanden in volgorde en bevestig aan de gebruiker:

```
1. CLAUDE.md                    ← Project regels en context
2. .claude/context.md           ← Project-specifieke details
3. .claude/rules.md             ← Security regels (indien aanwezig)
```

## Stap 2: Lees de HavunCore kennisbank (VERPLICHT)

```
4. D:\GitHub\HavunCore\docs\kb\runbooks\claude-werkwijze.md  ← LEES-DENK-DOE-DOCUMENTEER
5. D:\GitHub\HavunCore\docs\kb\runbooks\docs-first-workflow.md ← DOCS-FIRST (KRITIEK!)
6. D:\GitHub\HavunCore\docs\kb\PKM-SYSTEEM.md                ← Waar staat wat
```

## Stap 3: Bevestig aan gebruiker

Na het lezen, geef een KORTE bevestiging:

```
✓ MD files gelezen:
  - CLAUDE.md (X regels)
  - context.md (X regels)
  - claude-werkwijze.md
  - docs-first-workflow.md
  - PKM-SYSTEEM.md

📋 Dit project: [korte beschrijving]
⚠️ Verboden: [belangrijkste restricties]
📄 DOCS-FIRST: Ik schrijf alleen code zoals het in de docs staat.

Klaar om te beginnen. Wat wil je doen?
```

## Stap 4: ONTHOUD deze principes

### ⛔ DOCS-FIRST WORKFLOW (HOOFDREGEL!)

```
┌─────────────────────────────────────────────────────────┐
│  CODE MAG ALLEEN GESCHREVEN WORDEN ALS HET IN DE       │
│  MD FILES STAAT. NIET ZOALS IK DENK DAT HET MOET.      │
└─────────────────────────────────────────────────────────┘
```

**Bij ELKE vraag:**
1. Is dit groot (feature/styling/tekst) of klein (bug/typo)?
2. **GROOT** → Zoek docs → Meld wat er staat → Wacht op bevestiging → Update docs → Code
3. **KLEIN** → Log in `.claude/smallwork.md` → Fix → Klaar

### LEES-DENK-DOE-DOCUMENTEER
1. **LEES** - Eerst relevante docs/code lezen
2. **DENK** - Analyseer, vraag bij twijfel
3. **DOE** - Pas dan uitvoeren, geen haast
4. **DOCUMENTEER** - Sla nieuwe kennis op

### Kernregels
- **NOOIT** code schrijven voordat docs gecheckt zijn
- **NOOIT** aannemen hoe iets moet werken - het staat in de docs of ik vraag
- **ALTIJD** inconsistenties in docs melden VOORDAT ik code schrijf
- **ALTIJD** docs updaten VOORDAT code geschreven wordt

### Response template bij feature/wijziging vraag

```
📄 Over [onderwerp] vond ik:

[file1.md]:
  - [wat er staat]

[file2.md]:
  - [wat er staat]

⚠️ Inconsistenties: [ja/nee + details]
❓ Ontbreekt: [wat mist in docs]

Is dit correct en compleet?
```

## NIET DOEN

❌ Direct beginnen met code schrijven
❌ Zelf oplossingen verzinnen - het staat in docs of nergens
❌ Code schrijven terwijl docs inconsistent zijn
❌ Docs aanpassen NADAT code geschreven is
❌ "Ik denk dat..." zonder doc-verificatie
