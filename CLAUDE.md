# HavunCore - Claude Instructions

> **Role:** Centrale kennisbank & orchestrator voor ALLE Havun projecten
> **Type:** Standalone Laravel 11 app + Task Queue API
> **URL:** https://havuncore.havun.nl

## 🧠 WAT IS HAVUNCORE?

**HavunCore is de "alles weter" - de centrale bibliotheek die:**
- Patterns, methoden en oplossingen bevat voor alle projecten
- Credentials, API keys en configuraties beheert (Vault)
- Herbruikbare code en templates biedt
- Advies geeft over implementaties in elk project
- De kennisbron is waar alle apps op terugvallen

**Als iemand vraagt "hoe doe ik X in project Y?":**
1. ✅ Geef advies, patterns, voorbeeldcode vanuit HavunCore's kennis
2. ✅ Zoek in de knowledge base naar bestaande oplossingen
3. ✅ Maak een implementatieplan
4. ❌ Alleen het UITVOEREN van code in andere projecten → switch naar dat project

## Rules (ALWAYS follow)

### LEES-DENK-DOE-DOCUMENTEER (Kritiek!)

> **Volledige uitleg:** `docs/kb/runbooks/claude-werkwijze.md`

**Bij ELKE taak:**
1. **LEES** - Hiërarchisch: CLAUDE.md → relevante code/docs voor de taak (zie `docs/kb/PKM-SYSTEEM.md`)
2. **DENK** - Analyseer, begrijp, stel vragen bij twijfel
3. **DOE** - Pas dan uitvoeren, rustig, geen haast
4. **DOCUMENTEER** - Sla nieuwe kennis op in de juiste plek (project vs HavunCore)

**Kernregels:**
- Kwaliteit boven snelheid - liever 1x goed dan 3x fout
- Bij twijfel: VRAAG en WACHT op antwoord
- Nooit aannemen, altijd verifiëren
- Als gebruiker iets herhaalt: direct opslaan in docs

### Forbidden without permission
- SSH keys, credentials, .env files wijzigen
- Database migrations op production
- Composer/npm packages installeren
- Systemd services, cron jobs aanpassen

### Communication
- Antwoord max 20-30 regels
- Bullet points, direct to the point
- Lange uitleg? Eerst samenvatting, details op vraag

### Workflow
- HavunCore ALLEEN lokaal bewerken (te kritiek voor Task Queue)
- Na wijzigingen: git push naar server
- Test lokaal eerst, dan deploy

## Quick Reference

| Project | Local | Server |
|---------|-------|--------|
| HavunCore | D:\GitHub\HavunCore | /var/www/development/HavunCore |
| HavunAdmin | D:\GitHub\HavunAdmin | /var/www/havunadmin/production |
| Herdenkingsportaal | D:\GitHub\Herdenkingsportaal | /var/www/herdenkingsportaal/production |
| Infosyst | D:\GitHub\infosyst | /var/www/infosyst/production |
| Studieplanner | D:\GitHub\Studieplanner | /var/www/studieplanner/production |
| SafeHavun | D:\GitHub\SafeHavun | /var/www/safehavun/production |

**Server:** 188.245.159.115 (root, SSH key)

## Knowledge Base

Zoek info in deze folders:

| Onderwerp | Locatie |
|-----------|---------|
| Server, credentials, API's | `.claude/context.md` |
| Per-project details | `docs/kb/projects/` |
| Hoe doe ik X? | `docs/kb/runbooks/` |
| API specs, referenties | `docs/kb/reference/` |
| Waarom beslissingen | `docs/kb/decisions/` |
| Herbruikbare patterns | `docs/kb/patterns/` |

## MCP Tools

Gebruik `mcp__havun__*` tools voor:
- `getMessages(project)` - Berichten voor project
- `readFile(project, path)` - Bestand lezen
- `scanMarkdownFiles()` - Alle docs scannen
