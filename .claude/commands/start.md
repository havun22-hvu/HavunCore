# Start Session Command

> **VERPLICHT** bij elke nieuwe Claude sessie

## Stap 0: Sync lokale code + AutoFix detectie (VERPLICHT)

AutoFix kan code wijzigen op de server en automatisch pushen.
Pull altijd eerst de laatste wijzigingen voordat je begint:

```bash
cd [project directory] && git pull
```

Als er merge conflicts zijn: meld aan gebruiker, NIET zelf oplossen.

### AutoFix commits detecteren

Na de pull, check of er AutoFix commits zijn binnengekomen:

```bash
git log --oneline --since="3 days ago" --grep="autofix("
```

Als er AutoFix commits gevonden worden, toon aan de gebruiker:

```
🔧 AutoFix commits gedetecteerd sinds laatste sessie:

  - autofix(BlokController): Added null check for $poule->judokas (#42)
  - autofix(PouleService): Fixed undefined variable in scoring (#43)

Deze bestanden zijn automatisch gefixt op de server.
Zal ik de KB-secties voor deze bestanden markeren voor review?
```

**Bij "ja":** Lees de gewijzigde bestanden, check of de fixes consistent zijn met de KB docs, en meld inconsistenties.
**Bij "nee":** Ga verder met de sessie.

## Stap 0b: Dependency Security Audit (VERPLICHT)

Na de git pull, draai een security audit op dependencies:

```bash
# PHP projecten:
composer audit 2>/dev/null && echo "✓ Geen bekende PHP kwetsbaarheden" || echo "⚠️ PHP kwetsbaarheden gevonden — toon details aan gebruiker!"

# Node.js projecten (indien package.json aanwezig):
npm audit --omit=dev 2>/dev/null && echo "✓ NPM packages veilig" || echo "⚠️ NPM kwetsbaarheden gevonden!"

# Verouderde packages (maandelijks, of bij /start als >30 dagen sinds laatste check):
composer outdated --direct 2>/dev/null | head -20
```

Als er **kritieke kwetsbaarheden** zijn:
```
🔴 SECURITY: Kritieke kwetsbaarheden gevonden!

  - [package] [versie] → [CVE details]

⚠️ Dit moet EERST opgelost worden voordat we verder gaan.
Wil je de kwetsbaarheden nu oplossen?
```

Bij **lage/medium** kwetsbaarheden: melden, maar sessie mag doorgaan.

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

## Stap 3: Update Doc Intelligence index (VERPLICHT)

Werk de index bij zodat de kennisbank actueel is:

```bash
cd D:\GitHub\HavunCore && php artisan docs:index all --force
```

## Stap 4: Check Doc Intelligence issues

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

## Na Stap 1–4: Korte bevestiging

Geef een KORTE bevestiging:

```
✓ MD files gelezen:
  - CLAUDE.md (X regels)
  - context.md (X regels)
  - claude-werkwijze.md (werkwijze + docs-first + PKM)

📋 Dit project: [korte beschrijving]
⚠️ Verboden: [belangrijkste restricties]
📄 DOCS-FIRST: Ik schrijf alleen code zoals het in de docs staat.
📊 Doc issues: [X open issues / geen issues]

Klaar om te beginnen. Wat wil je doen?
```

## Stap 5: ONTHOUD deze principes

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
