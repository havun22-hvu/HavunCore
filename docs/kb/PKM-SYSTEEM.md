# Havun PKM Systeem

> Personal Knowledge Management voor Claude Code projecten

## Filosofie

**Projecten zijn zelfstandig, HavunCore is de orchestrator.**

```
┌─────────────────────────────────────────────────────────────┐
│                      HavunCore                               │
│                                                              │
│  Rol: Orchestrator + Gedeelde Resources                     │
│                                                              │
│  Bevat:                                                      │
│  • Credentials (alle wachtwoorden, API keys, SSH)           │
│  • Server configuratie (paths, services, nginx)             │
│  • Templates (hoe zet je een nieuwe site op)                │
│  • Contracts (gedeelde definities tussen projecten)         │
│  • Project index (overzicht alle projecten)                 │
│                                                              │
│  Kan:                                                        │
│  • In projecten kijken (MCP tools)                          │
│  • Opdrachten ontvangen (webapp)                            │
│  • Project docs lezen én bijwerken                          │
│  • Kennis delen tussen projecten                            │
└──────────────────────────┬──────────────────────────────────┘
                           │
         ┌─────────────────┼─────────────────┐
         ▼                 ▼                 ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ Herdenkings-    │ │ HavunAdmin      │ │ VPDUpdate       │
│ portaal         │ │                 │ │                 │
│                 │ │                 │ │                 │
│ 100% zelfstandig│ │ 100% zelfstandig│ │ 100% zelfstandig│
│ Eigen docs      │ │ Eigen docs      │ │ Eigen docs      │
│ Eigen context   │ │ Eigen context   │ │ Eigen context   │
│                 │ │                 │ │                 │
│ Weet iets niet? │ │ Weet iets niet? │ │ Weet iets niet? │
│ → Vraag Core    │ │ → Vraag Core    │ │ → Vraag Core    │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

## Wat staat waar?

### In elk PROJECT (zelfstandig):

```
Project/
├── CLAUDE.md                 ← Korte regels + context (max 60 regels)
├── .claude/
│   ├── context.md            ← ALLES over dit project
│   │   • Features en hoe ze werken
│   │   • Mollie/payment implementatie
│   │   • Auth systeem
│   │   • UI/UX richtlijnen
│   │   • Database structuur
│   │   • Deploy commando's voor DIT project
│   │
│   └── rules.md              ← Security regels voor dit project
│
└── docs/                     ← Eventuele extra project docs
```

### In HAVUNCORE (gedeeld):

```
HavunCore/
├── CLAUDE.md                 ← Korte regels
├── .claude/
│   ├── context.md            ← Server, credentials, API keys
│   ├── rules.md              ← Security regels
│   └── working-in-projects.md ← Hoe werk ik in een ander project
│
└── docs/kb/
    ├── PKM-SYSTEEM.md        ← Dit bestand (hoe werkt het systeem)
    ├── credentials.md        ← Alle wachtwoorden en keys
    ├── server.md             ← Server configuratie
    ├── templates/            ← Setup templates voor nieuwe sites
    ├── contracts/            ← Gedeelde definities (bv. memorial reference)
    └── projects-index.md     ← Overzicht alle projecten (1 regel per project)
```

## Kennisflow

### Scenario 1: Project weet iets niet

```
Herdenkingsportaal Claude:
  "Hoe SSH ik naar de server?"
       │
       ▼
HavunCore geeft antwoord + update Herdenkingsportaal docs
       │
       ▼
Volgende keer weet Herdenkingsportaal het zelf
```

### Scenario 2: HavunCore werkt in ander project

```
Gebruiker via webapp:
  "Fix bug in Herdenkingsportaal checkout"
       │
       ▼
HavunCore Claude:
  1. Leest Herdenkingsportaal/CLAUDE.md
  2. Leest Herdenkingsportaal/.claude/context.md
  3. Begrijpt de context
  4. Voert de taak uit
```

### Scenario 3: Gedeelde kennis nodig

```
Beide projecten gebruiken Memorial Reference:
       │
       ▼
HavunCore/docs/kb/contracts/memorial-reference.md
  "Memorial Reference = eerste 12 chars van UUID"
       │
       ├── Herdenkingsportaal/.claude/context.md
       │     "Wij gebruiken het in checkout + QR codes"
       │
       └── HavunAdmin/.claude/context.md
             "Wij matchen het met Mollie betalingen"
```

## Regels

### 1. Project-specifiek = in het project

**Goed:**
```
HavunAdmin/.claude/context.md:
  "BTW standaard 0% (KOR regeling)"
  "Flatpickr voor date pickers"
  "Facturatie frequenties: maandelijks, kwartaal, jaar"
```

**Fout:**
```
HavunCore/docs/kb/:
  "HavunAdmin BTW regels..."  ← Hoort in HavunAdmin!
```

### 2. Gedeeld = in HavunCore

**Goed:**
```
HavunCore/docs/kb/credentials.md:
  "Mollie API key: live_xxx"
  "Server SSH: root@188.245.159.115"
```

### 3. Contracts = gedeelde definities

Als meerdere projecten iets delen (format, protocol, API):

**Goed:**
```
HavunCore/docs/kb/contracts/memorial-reference.md:
  "Format: 12 hexadecimale karakters"
  "Bron: eerste deel van monument UUID"
```

## HavunCore als orchestrator

### MCP Tools beschikbaar:

```
mcp__havun__readFile(project, path)    → Bestand lezen uit project
mcp__havun__scanMarkdownFiles()        → Alle docs scannen
mcp__havun__getMessages(project)       → Berichten ophalen
mcp__havun__storeMessage(project, msg) → Bericht opslaan
```

### Voor werken in ander project:

1. **Lees eerst** de project docs (CLAUDE.md, .claude/)
2. **Begrijp de context** voor je iets doet
3. **Volg de project regels** (elke project kan eigen regels hebben)
4. **Update docs** als je nieuwe kennis opdoet

## Token-efficiëntie

### Waarom dit systeem?

**Voorheen:**
- CLAUDE.md van 800+ regels
- Elke sessie: 800 regels laden = veel tokens

**Nu:**
- CLAUDE.md van 60 regels
- Rest opzoeken wanneer nodig
- ~90% minder tokens per sessie

### Vuistregel:

```
CLAUDE.md = Wat ik ALTIJD moet weten (gedrag, regels)
.claude/  = Wat ik moet kunnen OPZOEKEN (details)
```

## Samenvatting

| Vraag | Antwoord |
|-------|----------|
| Waar staat project-specifieke info? | In het project zelf |
| Waar staan credentials? | HavunCore |
| Waar staan server paths? | HavunCore |
| Waar staat "hoe werkt feature X"? | In het project dat feature X heeft |
| Waar staat "wat is memorial reference"? | HavunCore (contract) |
| Waar staat "hoe gebruiken wij memorial ref"? | In elk project apart |

**Onthoud:** Projecten zijn zelfstandig. HavunCore helpt en deelt alleen wat echt gedeeld moet worden.
