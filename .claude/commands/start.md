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
4. D:\GitHub\HavunCore\docs\kb\runbooks\claude-werkwijze.md  ← Werkwijze, DOCS-FIRST, PKM (alles-in-1)
```

## Stap 3: Check Doc Intelligence issues (indien beschikbaar)

Als het Doc Intelligence systeem actief is, run in HavunCore:

```bash
cd D:\GitHub\HavunCore
php artisan docs:issues [huidig project]
```

> **Let op:** project is een positional argument, niet een --flag.
> Voorbeeld: `php artisan docs:issues havunclub`

Als er openstaande issues zijn, toon ze aan de gebruiker:

```
⚠️ Documentatie issues gevonden:

🔴 [HIGH] Inconsistent: Prijs verschilt tussen SPEC.md en PRICING.md
   → Welke is correct?

🟡 [MED] Duplicate: Mollie setup staat in 2 bestanden
   → Consolideer naar één locatie?

Wil je deze eerst oplossen of later?
```

## Stap 4: Rittenregistratie-plan (VERPLICHT)

Scan de centrale KB en maak of actualiseer het plan in smallwork.md:

1. **Scan** de centrale KB: `D:\GitHub\HavunCore\docs\kb\` (INDEX.md, OVERZICHT.md, relevante runbooks/patterns/reference).
2. **Maak of werk bij** in **dit project** het bestand `.claude/smallwork.md`: een plan om de **rittenregistratie-pagina** te bouwen volgens de Havun-standaarden (DOCS-FIRST, werkwijze, UI/UX uit `docs/kb/runbooks/claude-werkwijze.md`).
3. Als er al een rittenregistratie-plan in smallwork.md staat: controleer of het nog aansluit bij de KB; pas zo nodig aan.

> **Let op:** In HavunCore zelf gebruik je `D:\GitHub\HavunCore\.claude\smallwork.md`. In andere projecten gebruik je `{project}/.claude/smallwork.md`.

## Na Stap 1–4: Korte bevestiging

Geef een KORTE bevestiging:

```
✓ MD files gelezen:
  - CLAUDE.md (X regels)
  - context.md (X regels)
  - claude-werkwijze.md (werkwijze + docs-first + PKM)

✓ Rittenregistratie-plan: smallwork.md gescand/bijgewerkt

📋 Dit project: [korte beschrijving]
⚠️ Verboden: [belangrijkste restricties]
📄 DOCS-FIRST: Ik schrijf alleen code zoals het in de docs staat.
📊 Doc issues: [X open issues / geen issues]

Klaar om te beginnen. Wat wil je doen?
```

## Stap 6: ONTHOUD deze principes

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
