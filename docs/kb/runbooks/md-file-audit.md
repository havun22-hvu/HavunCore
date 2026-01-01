# MD File Audit - Runbook

> **Frequentie:** 2x per week + op verzoek
> **Uitvoerder:** HavunCore (centrale audit voor alle projecten)
> **Doel:** Consistente, goed georganiseerde documentatie

## Waarom centraal?

Elke Claude sessie moet toch opnieuw inlezen - maakt niet uit of HavunCore of het project zelf audits doet. Centraal is efficiënter: één plek, één standaard.

## Audit Checklist

### Per project controleren:

```
Project/
├── CLAUDE.md                 ✓ Bestaat? Max 60-80 regels?
├── .claude/
│   ├── context.md            ✓ Bestaat? Project-specifieke info?
│   └── rules.md              ✓ Optioneel, security regels
└── docs/                     ✓ Georganiseerd? Geen duplicaten?
```

### Inhoud checks:

| Check | Wat |
|-------|-----|
| **Structuur** | Voldoet aan PKM-SYSTEEM.md hiërarchie? |
| **Consistentie** | Zelfde format/headers als andere projecten? |
| **Duplicaten** | Staat info dubbel? (project én HavunCore) |
| **Actualiteit** | Verouderde info? Dode links? |
| **Logica** | Klopt de inhoud? Tegenstrijdigheden? |
| **Leesbaarheid** | Duidelijke koppen, bullets, tabellen? |

## Standaard CLAUDE.md Format

```markdown
# [Project] - Claude Instructions

> **Type:** [Framework/Tech]
> **URL:** [Production URL]

## Rules (ALWAYS follow)

### LEES-DENK-DOE-DOCUMENTEER (Kritiek!)

> **Volledige uitleg:** `HavunCore/docs/kb/runbooks/claude-werkwijze.md`

**Bij ELKE taak:**
1. **LEES** - Hiërarchisch: CLAUDE.md → relevante code/docs voor de taak
2. **DENK** - Analyseer, begrijp, stel vragen bij twijfel
3. **DOE** - Pas dan uitvoeren, rustig, geen haast
4. **DOCUMENTEER** - Sla nieuwe kennis op in de juiste plek

**Kernregels:**
- Kwaliteit boven snelheid - liever 1x goed dan 3x fout
- Bij twijfel: VRAAG en WACHT op antwoord
- Nooit aannemen, altijd verifiëren
- Als gebruiker iets herhaalt: direct opslaan in docs

### Forbidden without permission
- [Project-specifieke verboden acties]

### Communication
- Antwoord max 20-30 regels
- Bullet points, direct to the point

## Quick Reference

| Omgeving | Lokaal | Server |
|----------|--------|--------|
| Production | D:\GitHub\[Project] | /var/www/[path] |

**Server:** 188.245.159.115 (root, SSH key)

## Dit Project

[Korte beschrijving + belangrijkste features]

## Knowledge Base

Voor uitgebreide info:
- **Context:** `.claude/context.md`
- **HavunCore KB:** `D:\GitHub\HavunCore\docs\kb\`
```

## Standaard context.md Format

```markdown
# Context - [Project]

> Project-specifieke details en configuratie

## Features

[Uitleg hoe features werken]

## Database

[Tabellen, relaties]

## Authenticatie

[Hoe auth werkt in dit project]

## API's / Integraties

[Externe services die gebruikt worden]

## Veelvoorkomende taken

[Stappenplannen voor dit project]
```

## Audit Procedure

### 1. Scan alle projecten
```
HavunCore, HavunAdmin, Herdenkingsportaal, Judotoernooi,
Infosyst, Studieplanner, SafeHavun, Havun, VPDUpdate
```

### 2. Per project checken
- [ ] CLAUDE.md format correct?
- [ ] .claude/context.md bestaat en is actueel?
- [ ] Geen dubbele info (ook in HavunCore)?
- [ ] Verwijzingen kloppen?
- [ ] Logische structuur?

### 3. Rapport maken
- Lijst van gevonden issues
- Voorgestelde fixes
- Vragen voor gebruiker (bij twijfel)

### 4. Fixes doorvoeren
- **Kleine fixes:** Direct uitvoeren
- **Twijfel:** Overleggen met gebruiker
- **Technische vragen:** Zelf oplossen

### 5. Commit en push
- Per project committen
- Duidelijke commit message: "docs: MD file audit [datum]"

## Autoriteit

| Type | Actie |
|------|-------|
| Typos, formatting | Direct fixen |
| Verouderde info | Direct updaten |
| Structuur aanpassen | Direct fixen |
| Inhoudelijke twijfel | Overleggen |
| Technische keuzes | Zelf oplossen |
| Grote wijzigingen | Eerst bespreken |

## Audit Log

| Datum | Projecten | Issues | Status |
|-------|-----------|--------|--------|
| 2026-01-01 | Alle | Werkwijze toegevoegd | Done |
| ... | ... | ... | ... |

---

*Volgende audit: 2026-01-04 (zaterdag)*
