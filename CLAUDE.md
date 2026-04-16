# HavunCore - Claude Instructions

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

## De 5 Onschendbare Regels

```
1. NOOIT code schrijven zonder KB + kwaliteitsnormen te raadplegen
2. NOOIT features/UI-elementen verwijderen zonder instructie
3. NOOIT credentials/keys/env aanraken
4. ALTIJD tests draaien voor én na wijzigingen (coverage >80%)
5. ALTIJD toestemming vragen bij grote wijzigingen
6. NOOIT een falende test "fixen" door de assertion aan te passen — eerst onderzoeken WAAROM hij faalt; assertion alleen wijzigen na expliciete gebruikersgoedkeuring + business-rule herverificatie (VP-17 — anti-pattern: AI repareert symptoom i.p.v. oorzaak)
```

## Rules (ALWAYS follow)

### LEES-DENK-DOE-DOCUMENTEER (Kritiek!)

> **Volledige uitleg:** `docs/kb/runbooks/claude-werkwijze.md`

**Bij ELKE taak:**
1. **LEES** - Hiërarchisch: CLAUDE.md → KB zoeken → relevante code
2. **DENK** - Analyseer, begrijp, stel vragen bij twijfel
3. **DOE** - Pas dan uitvoeren, rustig, geen haast
4. **DOCUMENTEER** - Sla nieuwe kennis op in de juiste plek (project vs HavunCore)

### KB Automatisch Raadplegen (VERPLICHT)

Bij ELKE vraag over features, betalingen, auth, deployment, of configuratie:
```bash
cd D:\GitHub\HavunCore && php artisan docs:search "[onderwerp]"
# Gebruik --type voor gerichte resultaten:
# --type=service   → alleen services
# --type=docs      → alleen MD docs
# --type=controller → alleen controllers
# --type=model     → alleen models
```

Na elke KB search: vermeld de bron → "Volgens [bestand]: [citaat]"
Als de KB geen resultaat geeft: meld dit → "KB bevat geen info over [X]. Documenteren?"

**Kernregels:**
- Kwaliteit boven snelheid - liever 1x goed dan 3x fout
- Bij twijfel: VRAAG en WACHT op antwoord
- Nooit aannemen, altijd verifiëren — ZOEK EERST in KB
- Als gebruiker iets herhaalt: direct opslaan in docs

### Security Headers & CSP (Kritiek!)

> **Volledige uitleg:** `docs/kb/runbooks/security-headers-check.md`

**Bij ELKE wijziging aan views/scripts:**
- `<script>` tags: ALTIJD `@nonce` attribuut
- `<style>` tags: ALTIJD `@nonce` attribuut
- NOOIT `style=""` inline attributen — gebruik Tailwind CSS classes
- Externe CDN scripts: ALTIJD `integrity` + `crossorigin="anonymous"` + `@nonce`
- Externe CDN URLs: ALTIJD exacte versie pinnen + `https://` prefix
- Alpine.js: gebruik `@alpinejs/csp` (NIET standaard alpinejs)

### Bescherming bestaande code (Kritiek!)

> **Volledige uitleg:** `docs/kb/runbooks/claude-werkwijze.md` (sectie 4)

- **Check altijd `DO NOT REMOVE` comments** voordat je views, templates of componenten wijzigt
- **Verwijder NOOIT** UI-elementen, features of logica zonder expliciete instructie van de gebruiker
- **Bij refactoring:** behoud ALLE bestaande functionaliteit — alleen herstructureren, niet verwijderen
- **Bij twijfel:** vraag de gebruiker of een element bewust is toegevoegd

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
| HavunCore | D:\GitHub\HavunCore | /var/www/havuncore/production |
| HavunAdmin | D:\GitHub\HavunAdmin | /var/www/havunadmin/production |
| Herdenkingsportaal | D:\GitHub\Herdenkingsportaal | /var/www/herdenkingsportaal/production |
| Infosyst | D:\GitHub\infosyst | /var/www/infosyst/production |
| Studieplanner | D:\GitHub\Studieplanner | /var/www/studieplanner/production |
| SafeHavun | D:\GitHub\SafeHavun | /var/www/safehavun/production |
| JudoScoreBoard | D:\GitHub\JudoScoreBoard | /var/www/judoscoreboard/ |

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

