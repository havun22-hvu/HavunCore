# Claude Workflow Enforcement

> Strategie om Claude te dwingen docs-first te werken over alle projecten

## Het Probleem

Claude's instinct is om snel te helpen door direct code te schrijven. Dit werkt voor één sessie, maar over meerdere sessies:

- **Sessie 1:** Maakt feature X op manier A
- **Sessie 2:** Kent context niet, maakt feature Y op manier B
- **Sessie 3:** Inconsistente codebase, bugs, conflicten

**De oplossing:** Documentatie als "single source of truth" + geforceerde workflow.

---

## De 3-Fasen Workflow

Claude mag NIET naar de volgende fase zonder de vorige af te ronden.

### Fase 1: LEZEN (verplicht)

1. **Relevante docs noemen** - Welke docs zijn relevant voor deze taak?
2. **Specifiek citeren** - Copy-paste uit docs, niet parafraseren
3. **Checklist bevestigen** - "Checklist Fase 1 compleet"

### Fase 2: PLAN (verplicht)

1. **Aanpak beschrijven** - Exacte stappen + bestanden
2. **Approval vragen** - "Mag ik beginnen met coderen?"
3. **WACHTEN** - Niet doorgaan zonder "ja" van user

### Fase 3: CODE (pas na approval)

1. **Referenties in comments** - Verwijs naar docs
2. **Volgens specs** - Niet afwijken van wat in docs staat

---

## Implementatie per Project

### 1. CLAUDE.md - Stop Block

Plaats bovenaan elke project CLAUDE.md:

```
╔════════════════════════════════════════════════════════════════════╗
║  ⛔ VERPLICHTE WORKFLOW - GEEN UITZONDERINGEN                      ║
╠════════════════════════════════════════════════════════════════════╣
║                                                                    ║
║  FASE 1: LEZEN (verplicht VOOR code)                               ║
║  □ Noem ALLE relevante docs voor deze taak                         ║
║  □ CITEER specifieke regels (copy-paste, niet parafraseren)        ║
║  □ Zeg: "Checklist Fase 1 compleet"                                ║
║                                                                    ║
║  FASE 2: PLAN (verplicht VOOR code)                                ║
║  □ Beschrijf exacte aanpak + bestanden                             ║
║  □ Vraag: "Mag ik beginnen met coderen?"                           ║
║  □ WACHT op user "ja" - NIET doorgaan zonder approval              ║
║                                                                    ║
║  FASE 3: CODE (pas na "ja")                                        ║
║  □ Verwijs naar docs in code comments                              ║
║                                                                    ║
║  ════════════════════════════════════════════════════════════════  ║
║  ⚠️  CODE ZONDER FASE 1+2 = ONGELDIG                               ║
║  ⚠️  User zegt "STOP" → terug naar Fase 1                          ║
║  ⚠️  Geen uitzonderingen, ook niet voor "kleine" taken             ║
╚════════════════════════════════════════════════════════════════════╝
```

### 2. User Commando's

Voeg toe aan CLAUDE.md:

```markdown
## User Commando's

| Commando | Actie |
|----------|-------|
| `STOP` | Direct stoppen, terug naar Fase 1 |
| `DOCS?` | Welke docs heb je gelezen? Citeer. |
| `PLAN?` | Wat is je exacte aanpak? |
| `OK` / `JA` | Door naar volgende fase |
```

### 3. Claude Code Hooks

Maak `.claude/settings.json` in elk project:

```json
{
  "hooks": {
    "SessionStart": [
      {
        "hooks": [
          {
            "type": "prompt",
            "prompt": "VERPLICHT: Volg de 3-fasen workflow uit CLAUDE.md (LEZEN → PLAN → CODE). Start ALTIJD met Fase 1: lees relevante docs en citeer ze. Geen code zonder user approval."
          }
        ]
      }
    ],
    "PreToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [
          {
            "type": "prompt",
            "prompt": "STOP: Voordat je dit bestand aanpast - heb je Fase 1 (docs citeren) en Fase 2 (plan + user approval) doorlopen? Zo niet, stop en ga terug. Zo ja, ga door."
          }
        ]
      }
    ]
  }
}
```

### 4. Docs Structuur

Aanbevolen structuur voor elk project:

```
.claude/
├── context.md              # Overzicht + links naar details
├── settings.json           # Hook configuratie
└── docs/
    ├── features.md         # Functionele specs
    ├── api.md              # API endpoints
    ├── data-types.md       # Types + database
    ├── tech-stack.md       # Packages + config
    └── ui/
        ├── screens.md      # Alle schermen
        ├── components.md   # UI componenten
        ├── styling.md      # Design tokens
        └── navigation.md   # Navigatie flow
```

---

## Hook Types Beschikbaar

Claude Code ondersteunt deze hooks:

| Event | Moment | Kan blokkeren? |
|-------|--------|----------------|
| `SessionStart` | Bij start sessie | Ja (context injecten) |
| `PreToolUse` | Vóór tool call | Ja (exit 2 = blokkeer) |
| `PostToolUse` | Na tool execution | Ja (feedback) |
| `UserPromptSubmit` | Vóór verwerking | Ja |
| `Stop` | Wanneer Claude klaar is | Ja |

### Hook Matchers

Voor PreToolUse/PostToolUse:

```
Bash              # Exact match
Edit|Write        # Regex (OR)
mcp__*__read_*    # MCP tools
*                 # Alle tools
```

### Exit Codes

- `exit 0` = Toestaan
- `exit 2` = Blokkeren

### JSON Output (geavanceerd)

```json
{
  "additionalContext": "Extra instructie voor Claude",
  "hookSpecificOutput": {
    "permissionDecision": "deny",
    "permissionDecisionReason": "Workflow niet gevolgd"
  }
}
```

---

## Anti-Patterns

### FOUT: Direct coderen

```
USER: "Bouw timer scherm"
CLAUDE: "```typescript
export function TimerScreen() {
```"
```

### FOUT: Vage referentie

```
CLAUDE: "Volgens de docs moet ik..."
[geen specifieke citaten]
```

### GOED: Volledige workflow

```
CLAUDE: "## Fase 1: LEZEN

### Relevante docs:
- docs/ui/screens.md (TimerScreen sectie)
- docs/ui/components.md (TimerDisplay)

### Geciteerde specs:
Uit docs/ui/screens.md:140:
'Timer telt OP (niet af)'
'Grote display MM:SS'

Checklist Fase 1 compleet.

## Fase 2: PLAN
1. Maak src/screens/TimerScreen.tsx
2. Gebruik TimerDisplay component
3. Props via route: sessionId

Mag ik beginnen met coderen?"
```

---

## Voorbeeld Sessie

```
USER: Bouw het auth scherm

CLAUDE:
## Fase 1: LEZEN

### Relevante docs:
- docs/ui/screens.md (AuthScreen)
- docs/ui/components.md (Input, Button, PinInput)
- docs/api.md (POST /api/auth/login)

### Geciteerde specs:

docs/ui/screens.md:23-45:
"- Logo: tekst 'App naam' in primary color
- Naam input: placeholder, geen label
- Pincode input: 4 losse vakjes
- Submit button: Full width, primary"

docs/ui/components.md (PinInput):
"interface PinInputProps {
  value: string;
  onChangeText: (text: string) => void;
}"

Checklist Fase 1 compleet.

## Fase 2: PLAN

### Aanpak:
1. Maak src/screens/AuthScreen.tsx
2. Maak src/components/PinInput.tsx (bestaat nog niet)
3. Gebruik bestaande Input, Button components
4. State: name, pincode, isLogin mode

Mag ik beginnen met coderen?

USER: ja

CLAUDE:
## Fase 3: CODE

// src/screens/AuthScreen.tsx
// Specs: docs/ui/screens.md:23-45
...
```

---

## Waarom Dit Werkt

1. **Geen ruimte voor interpretatie** - Elke stap is expliciet
2. **Verplichte citaten** - Claude moet bewijzen dat docs gelezen zijn
3. **User als gatekeeper** - Approval nodig voor code
4. **Makkelijk corrigeren** - "STOP" stuurt terug naar Fase 1
5. **Consistentie** - Elke sessie volgt dezelfde workflow
6. **Hooks als backup** - Automatische reminders

---

## Checklist: Nieuw Project Setup

Bij elk nieuw project:

- [ ] CLAUDE.md met stop block + user commando's
- [ ] `.claude/context.md` als overzicht
- [ ] `.claude/docs/` met detail documentatie
- [ ] `.claude/settings.json` met hooks
- [ ] UI specs met wireframes/beschrijvingen
- [ ] Data types vastgelegd
- [ ] API endpoints gedocumenteerd

---

## Implementatieplan per Project

### Projecten Overzicht

| Project | Type | Huidige status | Actie nodig |
|---------|------|----------------|-------------|
| **HavunCore** | Laravel + Node | ✅ Compleet | Referentie project |
| **HavunAdmin** | Laravel | ✅ Compleet | - |
| **Herdenkingsportaal** | Laravel | ✅ Compleet | - |
| **JudoToernooi** | Laravel + Google Apps Script | ✅ Compleet | - |
| **VPDUpdate** | Node.js | ✅ Compleet | - |
| **Studieplanner** | Laravel | ✅ Compleet | - |
| **SafeHavun** | Laravel | ✅ Compleet | - |
| **Infosyst** | Laravel + Ollama | ✅ Compleet | - |

### Per Project Aan Te Passen

#### 1. `.claude/settings.json` (NIEUW of AANPASSEN)

```json
{
  "hooks": {
    "SessionStart": [
      {
        "hooks": [
          {
            "type": "prompt",
            "prompt": "VERPLICHT: Volg de 3-fasen workflow uit CLAUDE.md (LEZEN → PLAN → CODE). Start ALTIJD met Fase 1: lees relevante docs en citeer ze. Geen code zonder user approval."
          }
        ]
      }
    ],
    "PreToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [
          {
            "type": "prompt",
            "prompt": "WORKFLOW CHECK: Heb je Fase 1 (docs citeren) en Fase 2 (plan + user approval) doorlopen voordat je code schrijft? Zo niet, stop en ga terug naar Fase 1."
          }
        ]
      }
    ]
  }
}
```

#### 2. `.claude/settings.local.json` (STANDAARDISEREN)

```json
{
  "permissions": {
    "allow": [
      "Bash",
      "Read",
      "Edit",
      "Write"
    ],
    "deny": [
      "Bash(rm -rf /)",
      "Bash(format C:)",
      "Bash(git push --force origin main)",
      "Bash(git push --force origin master)"
    ],
    "defaultMode": "bypassPermissions"
  }
}
```

#### 3. `CLAUDE.md` - Stop Block Toevoegen

Bovenaan elk CLAUDE.md bestand, direct na de titel:

```
╔══════════════════════════════════════════════════════════════════╗
║  ⛔ STOP! LEES DIT VOORDAT JE IETS DOET                          ║
║                                                                   ║
║  GEEN CODE SCHRIJVEN VOORDAT JE ANTWOORD GEEFT OP:               ║
║                                                                   ║
║  1. "Wat staat er in de docs over dit onderwerp?"                ║
║  2. "Waar staat dat?" (geef bestandsnaam + regelnummer)          ║
║  3. "Is er iets inconsistent of ontbrekend?"                     ║
║                                                                   ║
║  PAS DAARNA mag je code voorstellen.                             ║
║  Gebruiker moet EERST akkoord geven.                             ║
║                                                                   ║
║  ⚠️  Bij twijfel: /kb of vraag aan gebruiker                     ║
╚══════════════════════════════════════════════════════════════════╝
```

---

## Uitvoering Checklist

### Fase 1: HavunCore als referentie bevestigen
- [x] claude-workflow-enforcement.md documenteren
- [x] templates/ map met CLAUDE-template.md
- [x] templates/claude-settings.json
- [x] templates/context-template.md
- [x] Dit implementatieplan toevoegen

### Fase 2: Hoofdprojecten updaten
- [x] **HavunAdmin**
  - [x] `.claude/settings.json` aanmaken met hooks
  - [ ] `CLAUDE.md` stop block controleren/updaten
  - [x] `.claude/settings.local.json` standaardiseren

- [x] **Herdenkingsportaal**
  - [x] `.claude/settings.json` aanmaken met hooks
  - [ ] `CLAUDE.md` stop block controleren/updaten
  - [x] `.claude/settings.local.json` standaardiseren

- [x] **JudoToernooi**
  - [x] `.claude/settings.json` updaten met hooks
  - [ ] `CLAUDE.md` stop block controleren/updaten
  - [x] `.claude/settings.local.json` standaardiseren

### Fase 3: Secundaire projecten updaten
- [x] **VPDUpdate** - settings.json + settings.local.json aangemaakt
- [x] **Studieplanner** - had al hooks, settings.local.json toegevoegd
- [x] **SafeHavun** - settings.json + settings.local.json aangemaakt
- [x] **Infosyst** - settings.json + settings.local.json aangemaakt

### Fase 4: Validatie
- [ ] Test nieuwe sessie in elk project
- [ ] Verifieer dat hooks actief zijn
- [ ] Documenteer eventuele aanpassingen

---

## Gerelateerde Docs

- `runbooks/claude-werkwijze.md` - Algemene LEES-DENK-DOE-DOCUMENTEER aanpak
- `templates/CLAUDE-template.md` - Template voor nieuwe projecten
- `templates/claude-settings.json` - Hook configuratie template
- `templates/context-template.md` - Docs structuur template
