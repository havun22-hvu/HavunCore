# Werken in andere projecten

> Instructies voor HavunCore Claude bij werken in Herdenkingsportaal, HavunAdmin, VPDUpdate, etc.

## Voordat je begint

### Stap 1: Lees de project docs

```
{project}/CLAUDE.md              ← Regels en korte context
{project}/.claude/context.md     ← Alle project details
{project}/.claude/rules.md       ← Wat mag niet
```

### Stap 2: Gebruik MCP tools

```javascript
// Bestand lezen
mcp__havun__readFile("herdenkingsportaal", "CLAUDE.md")
mcp__havun__readFile("herdenkingsportaal", ".claude/context.md")

// Alle markdown files scannen
mcp__havun__scanMarkdownFiles()

// Specifiek bestand zoeken
mcp__havun__searchFiles("context.md")
```

## Standaard project structuur

Elk project volgt dezelfde structuur:

```
Project/
├── CLAUDE.md                 ← LEES DIT EERST
├── .claude/
│   ├── context.md            ← Project-specifieke details
│   ├── rules.md              ← Security regels
│   └── commands/             ← Slash commands (optioneel)
├── docs/                     ← Extra documentatie
└── [project bestanden]
```

## Project-specifieke aandachtspunten

### Herdenkingsportaal
- **CRITICAL:** Production is LIVE met echte klantdata
- **NOOIT** direct naar production deployen
- **ALTIJD** staging eerst, dan toestemming vragen
- Auth: eigen WebAuthn implementatie

### HavunAdmin
- Gebruiker is GEEN developer - vertaal technische termen
- Bij duplicaten/data: ALTIJD eerst controleren
- Auth: eigen WebAuthn implementatie

### VPDUpdate
- Node.js project (geen Laravel)
- Nog in development

## Workflow

### Bij een taak in ander project:

1. **Lees project docs** (CLAUDE.md + .claude/)
2. **Begrijp de context** voor je code aanraakt
3. **Volg project regels** (kunnen afwijken van HavunCore)
4. **Voer taak uit**
5. **Update project docs** als je nieuwe kennis opdoet

### Bij onbekende info:

Als het project iets niet weet dat ik wel weet:
1. Geef het antwoord
2. Update de project docs zodat het volgende keer zelf weet

```bash
# Voorbeeld: SSH command toevoegen aan project docs
# Als project vraagt "hoe SSH ik naar server?"
# → Antwoord geven
# → Toevoegen aan {project}/.claude/context.md
```

## Niet vergeten

- [ ] Project CLAUDE.md gelezen
- [ ] Project .claude/context.md gelezen
- [ ] Project rules begrepen
- [ ] Weet wat het project doet
- [ ] Weet welke tech stack (Laravel? Node?)
- [ ] Weet of het production data heeft
