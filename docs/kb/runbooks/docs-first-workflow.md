# DOCS-FIRST Workflow

> **De #1 regel:** Code volgt docs. Nooit andersom.

## Waarom?

- Claude begint anders meteen te coderen op basis van aannames
- Docs raken inconsistent en verouderd
- Kennis gaat verloren tussen sessies
- Elke Claude vindt het wiel opnieuw uit

## De Regel

```
┌─────────────────────────────────────────────────────────┐
│  CODE MAG ALLEEN GESCHREVEN WORDEN ALS HET IN DE       │
│  MD FILES STAAT. NIET ZOALS CLAUDE DENKT DAT HET MOET. │
└─────────────────────────────────────────────────────────┘
```

## Workflow bij ELKE vraag

### Stap 1: CLASSIFICEER

Is dit een **grote wijziging** of **smallwork**?

**GROTE WIJZIGING (→ Docs-first):**
- Nieuwe features
- Bestaande features aanpassen
- Prijzen, teksten, labels wijzigen
- Styling/design (kleuren, spacing, fonts, layout)
- User flows
- Business rules
- Alles wat de gebruiker ZIET of ERVAART

**SMALLWORK (→ Log in smallwork.md):**
- Typos in code (variabele namen, comments)
- Bug fixes (iets werkt niet zoals spec zegt)
- Performance optimalisaties
- Technische refactoring
- Dependency updates

### Stap 2a: DOCS-FIRST (grote wijzigingen)

```
1. ZOEK alle MD files over dit onderwerp
   - SPEC.md, FEATURES.md, context.md, etc.
   - Gebruik grep/glob om ALLES te vinden

2. LEES volledig (niet scannen!)

3. MELD aan gebruiker:
   ┌────────────────────────────────────────┐
   │ 📄 Over [onderwerp] vond ik:           │
   │                                        │
   │ [file1.md]:                            │
   │   - [wat er staat]                     │
   │                                        │
   │ [file2.md]:                            │
   │   - [wat er staat]                     │
   │                                        │
   │ ⚠️ Inconsistenties: [details]          │
   │                                        │
   │ ❓ Ontbreekt: [wat mist]               │
   │                                        │
   │ Is dit correct en compleet?            │
   └────────────────────────────────────────┘

4. WACHT op bevestiging

5. UPDATE DOCS EERST als nodig:
   - Inconsistenties fixen
   - Ontbrekende info toevoegen
   - Gebruiker moet bevestigen: "Docs zijn correct"

6. DAN PAS CODE schrijven vanuit de docs
```

### Stap 2b: SMALLWORK (kleine fixes)

```
1. Log in .claude/smallwork.md:
   - Datum
   - Wat
   - Waarom
   - Welke bestanden

2. Voer fix uit

3. Bij /end: review of iets permanent moet
```

## Voorbeelden

### Voorbeeld 1: Feature vraag

**Gebruiker:** "Pas de checkout flow aan"

**FOUT:**
```
Claude begint direct code te schrijven...
```

**GOED:**
```
📄 Over checkout vond ik:

SPEC.md (regel 45-67):
  - Stap 1: Pakket kiezen
  - Stap 2: Gegevens invullen
  - Stap 3: Betalen via Mollie

CHECKOUT-FLOW.md (regel 12-34):
  - Na betaling: redirect naar /success
  - Email versturen met bevestiging

⚠️ Inconsistentie: SPEC.md noemt geen email, CHECKOUT-FLOW.md wel

❓ Ontbreekt: Wat gebeurt bij mislukte betaling?

Is dit correct en compleet? Wat wil je aanpassen?
```

### Voorbeeld 2: Styling vraag

**Gebruiker:** "Maak de buttons groter"

**FOUT:**
```
Claude past CSS aan naar eigen inzicht...
```

**GOED:**
```
📄 Over button styling vond ik:

STYLING.md:
  - Buttons: bg-primary, rounded-lg, px-4 py-2

Geen specifieke grootte gedefinieerd.

❓ Ontbreekt: Button groottes voor verschillende contexten

Wil je dit eerst toevoegen aan STYLING.md?
Bijvoorbeeld:
  - btn-sm: px-3 py-1
  - btn-md: px-4 py-2 (huidige)
  - btn-lg: px-6 py-3
```

### Voorbeeld 3: Bug fix (smallwork)

**Gebruiker:** "De datum wordt fout weergegeven"

```
Dit is een bug fix → smallwork

[Logt in smallwork.md, fixt de bug]

✓ Bug gefixt: Carbon format was Y-m-d ipv d-m-Y
```

## Niet Doen

❌ Code schrijven voordat docs gecheckt zijn
❌ Aannemen hoe iets moet werken
❌ Docs aanpassen NADAT code geschreven is
❌ "Ik denk dat..." zonder doc-verificatie
❌ Styling aanpassen zonder style guide te checken

## Wel Doen

✅ ALTIJD eerst zoeken in docs
✅ Inconsistenties direct melden
✅ Docs updaten VOORDAT code geschreven wordt
✅ Vragen bij twijfel
✅ Kleine fixes loggen in smallwork.md
