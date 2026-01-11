# [PROJECT NAAM] - Claude Instructions

```
╔════════════════════════════════════════════════════════════════════╗
║  ⛔ DOCS-FIRST WORKFLOW - GEEN UITZONDERINGEN                      ║
╠════════════════════════════════════════════════════════════════════╣
║                                                                    ║
║  BIJ ELKE TAAK:                                                    ║
║  1. LEES relevante MD files                                        ║
║  2. UPDATE MD files EERST (belangrijker dan code!)                 ║
║  3. Geef korte samenvatting van taak uit MD files                  ║
║  4. Dan pas coderen                                                ║
║                                                                    ║
║  WAAROM: Morgen nieuwe sessie = alles vergeten.                    ║
║  MD files zijn het geheugen. Code zonder docs = nutteloos.         ║
║                                                                    ║
║  ════════════════════════════════════════════════════════════════  ║
║  ⚠️  NIETS VRAGEN - gewoon doen                                    ║
║  ⚠️  User zegt "STOP" → terug naar stap 1                          ║
║                                                                    ║
║  📖 Details: HavunCore/docs/kb/claude-workflow-enforcement.md      ║
╚════════════════════════════════════════════════════════════════════╝
```

> **Type:** [TECH STACK]
> **URL:** [PRODUCTIE URL]

## Rules (ALWAYS follow)

### DOCS-FIRST (kritiek!)

**Bij ELKE taak - geen uitzonderingen:**
1. **LEES** - Relevante MD files checken
2. **UPDATE** - MD files EERST bijwerken met wat je gaat doen
3. **SAMENVATTING** - Korte beschrijving van taak uit docs
4. **CODE** - Pas dan implementeren

**Waarom:** Nieuwe sessie = nieuw geheugen. Alles wat niet in MD staat is verloren.

### ABSOLUUT VERBODEN
- Edit/Write tool omzeilen via Bash, sed, Python, awk, echo, cat
- Als hook blokkeert: STOP en volg workflow, NIET omzeilen
- User iets vragen dat je zelf kunt doen
- SSH keys, credentials, .env files wijzigen
- Dependencies installeren (composer/npm)
- Database migrations op production

### Communication
- Antwoord max 20-30 regels
- Bullet points, direct to the point
- Geen vragen - gewoon doen

## Quick Reference

| Omgeving | Pad |
|----------|-----|
| Project | [PAD] |
| Backend | [PAD] |
| Server | [SERVER PAD] |

## Dit Project

- **Frontend:** [FRAMEWORK]
- **Backend:** [FRAMEWORK]
- **Auth:** [AUTH METHODE]
- **Database:** [DATABASE]

### Lokaal starten
```bash
# [COMMANDO'S]
```

## Knowledge Base

### Project Docs

| Doc | Inhoud |
|-----|--------|
| `.claude/context.md` | Overzicht + links |
| `.claude/docs/features.md` | Functionaliteit |
| `.claude/docs/api.md` | API endpoints |
| `.claude/docs/data-types.md` | Types + database |
| `.claude/docs/ui/screens.md` | Alle schermen |
| `.claude/docs/ui/components.md` | UI componenten |
| `.claude/docs/ui/styling.md` | Design tokens |

### Externe KB
- **HavunCore KB:** `D:\GitHub\HavunCore\docs\kb\`
