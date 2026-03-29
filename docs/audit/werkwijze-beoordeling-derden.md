# Havun Software Development — Werkwijze & Kwaliteitsborging

**Document:** Ter beoordeling door technische derde partij
**Versie:** 1.0
**Datum:** 29 maart 2026
**Volgende review:** Q3 2026 (juli 2026)
**Reviewcyclus:** Elk kwartaal
**Organisatie:** Havun (KvK: 98516000)
**Opgesteld door:** Henk van Ess, eigenaar & lead developer

### Reviewgeschiedenis

| Versie | Datum | Reviewer | Opmerkingen |
|--------|-------|----------|-------------|
| 1.0 | 29-03-2026 | — | Eerste versie, ter beoordeling |
| | | | |
| | | | |

---

## Inhoudsopgave

1. [Introductie](#1-introductie)
2. [Tooling & Omgeving](#2-tooling--omgeving)
3. [Ontwikkelworkflow](#3-ontwikkelworkflow)
4. [Beveiligingsprotocollen](#4-beveiligingsprotocollen)
5. [Codebescherming & Integriteit](#5-codebescherming--integriteit)
6. [Teststrategie & Kwaliteitsborging](#6-teststrategie--kwaliteitsborging)
7. [Geautomatiseerde Foutherstel (AutoFix)](#7-geautomatiseerde-foutherstel-autofix)
8. [Deployment & Infrastructuur](#8-deployment--infrastructuur)
9. [Kennismanagement](#9-kennismanagement)
10. [Incident Response](#10-incident-response)
11. [Risico's & Mitigaties](#11-risicos--mitigaties)
12. [Bijlagen](#12-bijlagen)

---

## 1. Introductie

### 1.1 Doel van dit document

Dit document beschrijft de volledige werkwijze waarmee Havun software ontwikkelt met behulp van AI-gestuurde tools (Claude Code CLI en Claude Code VS Code Extension). Het doel is om aan een technische derde partij aan te tonen:

- Welke beveiligingsmaatregelen zijn getroffen
- Hoe softwarekwaliteit wordt geborgd
- Welke protocollen en procedures worden gevolgd
- Hoe risico's worden beheerst bij AI-ondersteunde ontwikkeling

### 1.2 Projectportfolio

Havun beheert meerdere webapplicaties vanuit één centrale orchestrator:

| Project | Type | Stack |
|---------|------|-------|
| **HavunCore** | Centrale kennisbank & orchestrator | Laravel 11 (PHP) |
| **HavunAdmin** | Beheerpaneel | Laravel + Vite |
| **Herdenkingsportaal** | Publieke webapp | Laravel + Vite |
| **JudoToernooi** | Toernooibeheer | Laravel + Vite |
| **Studieplanner** | Mobiele app | React Native + Expo |
| **SafeHavun** | Beveiligingsplatform | Laravel + Vite |
| **Infosyst** | Informatiesysteem | Laravel + Vite |
| **JudoScoreBoard** | Scorebord app | React Native + Expo |

### 1.3 AI-tooling in gebruik

| Tool | Gebruik | Versie |
|------|---------|--------|
| **Claude Code CLI** | Terminal-gebaseerde AI-assistent voor code, git, deploy | Anthropic Claude (Opus/Sonnet) |
| **Claude Code VS Code Extension** | IDE-geïntegreerde AI-assistent | Anthropic Claude |
| **Ollama (lokaal)** | Lokale AI voor kennisbank-indexering | Command-R model |

> **Belangrijk:** De AI schrijft code, maar opereert altijd binnen strikte regels en protocollen. De ontwikkelaar behoudt volledige controle en moet elke significante wijziging goedkeuren.

---

## 2. Tooling & Omgeving

### 2.1 Ontwikkelomgeving

- **OS:** Windows 11
- **IDE:** VS Code met Claude Code Extension
- **Terminal:** Claude Code CLI (bash shell)
- **Versiebeheer:** Git + GitHub (private repositories)
- **Server:** Hetzner VPS (Ubuntu, nginx)
- **Lokale AI:** Ollama op poort 11434

### 2.2 Claude Code permissieconfiguratie

Claude Code opereert met een expliciete permissieconfiguratie:

```json
{
  "permissions": {
    "allow": ["Bash", "Read", "Edit", "Write"],
    "deny": [
      "Bash(rm -rf /)",
      "Bash(format C:)",
      "Bash(git push --force origin main)",
      "Bash(git push --force origin master)"
    ]
  }
}
```

**Toelichting:**
- Destructieve commando's zijn expliciet geblokkeerd
- Force-push naar hoofdbranches is verboden
- Lees-, schrijf- en bewerkrechten zijn beperkt tot de projectdirectory

### 2.3 Hook-systeem (geautomatiseerde controles)

Claude Code ondersteunt hooks — automatische controles die worden uitgevoerd bij specifieke acties:

| Hook | Trigger | Doel |
|------|---------|------|
| `SessionStart` | Bij elke nieuwe sessie | Verplicht docs-first workflow activeren |
| `PreToolUse (Edit/Write)` | Voor elke codewijziging | Controleer of docs-fase is doorlopen |

---

## 3. Ontwikkelworkflow

### 3.1 Kernprincipe: LEES → DENK → DOE → DOCUMENTEER

Elke ontwikkeltaak doorloopt verplicht vier fasen:

```
┌──────────────────────────────────────────────────┐
│  FASE 1: LEES                                    │
│  → Lees projectdocumentatie en relevante code    │
│  → Hiërarchisch: CLAUDE.md → context → code      │
│                                                  │
│  FASE 2: DENK                                    │
│  → Analyseer het probleem                        │
│  → Identificeer bestaande patronen               │
│  → Stel vragen bij onduidelijkheid               │
│  → WACHT op antwoord                             │
│                                                  │
│  FASE 3: DOE                                     │
│  → Kleine, atomaire wijzigingen                  │
│  → Test na elke significante wijziging           │
│  → Kwaliteit boven snelheid                      │
│                                                  │
│  FASE 4: DOCUMENTEER                             │
│  → Sla nieuwe kennis op                          │
│  → Update relevante documentatie                 │
│  → Handover voor volgende sessie                 │
└──────────────────────────────────────────────────┘
```

### 3.2 Docs-First Workflow (verplicht bij significante wijzigingen)

**Principe:** Code volgt documentatie. Nooit andersom.

Elke wijziging wordt geclassificeerd:

| Type | Workflow | Voorbeeld |
|------|----------|-----------|
| **Groot** (feature, UI, business rules) | Docs-first: zoek docs → rapporteer → wacht goedkeuring → update docs → code | Nieuwe checkout flow |
| **Klein** (bugfix, typo, performance) | Log in smallwork.md → fix → klaar | Null-pointer fix |

**Docs-first stappen bij grote wijzigingen:**

1. **Zoek** alle relevante MD-bestanden over het onderwerp
2. **Lees** volledig (niet scannen)
3. **Rapporteer** aan ontwikkelaar: wat er staat, inconsistenties, wat ontbreekt
4. **Wacht** op goedkeuring
5. **Update documentatie** eerst indien nodig
6. **Schrijf code** op basis van de goedgekeurde documentatie

### 3.3 Drieëneenhalf-fase afdwinging

Voor extra zekerheid wordt de workflow afgedwongen met een 3-fase protocol:

| Fase | Actie | Vereiste |
|------|-------|---------|
| **Fase 1: READ** | Noem alle relevante docs, citeer specifieke regels | Verplicht vóór code |
| **Fase 2: PLAN** | Beschrijf aanpak en bestanden, vraag toestemming | Wacht op "ja" van ontwikkelaar |
| **Fase 3: CODE** | Schrijf code conform docs en plan | Alleen na goedkeuring |

**Gebruikerscommando's voor controle:**

| Commando | Effect |
|----------|--------|
| `STOP` | Direct stoppen, terug naar Fase 1 |
| `DOCS?` | Welke docs zijn gelezen? Citeer ze. |
| `PLAN?` | Wat is de exacte aanpak? |
| `OK` / `JA` | Ga door naar volgende fase |

### 3.4 Git-workflow

| Regel | Beschrijving |
|-------|--------------|
| Atomaire commits | 1 feature of fix = 1 commit |
| Directe push | Na commit direct pushen |
| Branch cleanup | Gemergte branches worden verwijderd |
| Force-push verbod | Force-push naar main/master is geblokkeerd |
| Commit messages | Engels, beschrijvend, gestructureerd |

### 3.5 Sessiemanagement

**Bij sessiestart (`/start`):**
1. Git pull (sync met eventuele AutoFix-wijzigingen)
2. Lees projectdocumentatie (CLAUDE.md, context.md, rules.md)
3. Lees gedeelde werkwijze (claude-werkwijze.md)
4. Controleer documentatie-issues (Doc Intelligence)
5. Bevestig aan ontwikkelaar

**Bij sessie-einde (`/end`):**
1. Review smallwork-log
2. Update documentatie
3. Schrijf handover voor volgende sessie
4. Draai tests (Linter-Gate)
5. Integriteitscontrole
6. Commit, push, deploy indien van toepassing

---

## 4. Beveiligingsprotocollen

### 4.1 Verboden acties (zonder expliciete toestemming)

De AI-assistent mag de volgende acties **nooit** uitvoeren zonder voorafgaande expliciete toestemming van de eigenaar:

| Categorie | Voorbeelden |
|-----------|-------------|
| **Credentials** | SSH keys aanmaken/wijzigen/verwijderen, API keys, wachtwoorden |
| **Configuratie** | .env bestanden, systemd services, cron jobs |
| **Infrastructuur** | Firewall regels, gebruikersrechten, server configuratie |
| **Database** | Migraties op productie |
| **Dependencies** | Composer/npm packages installeren |

**Protocol bij potentieel gevaarlijke actie:**
```
STOP → VRAAG TOESTEMMING → WACHT OP ANTWOORD → DOCUMENTEER
```

### 4.2 Credential management

| Aspect | Aanpak |
|--------|--------|
| **Opslag** | Credentials in `.env` bestanden (niet in versiebeheer) |
| **Documentatie** | Credential-referenties in `.claude/credentials.md` (gitignored) |
| **Scheiding** | Productie- en staging-credentials gescheiden |
| **Toegang** | Alleen eigenaar heeft toegang tot credentials |
| **Git** | Credentials mogen NOOIT in git-getrackte bestanden terechtkomen |

### 4.3 Productiebeveiliging

- **Geen directe productiewijzigingen** — alleen via git pull na goedkeuring
- **Syntax-validatie** — `php -l` check na elke codewijziging op server
- **Automatische rollback** — bij syntax-fouten wordt origineel bestand hersteld
- **Backups** — dagelijks + hot-backups (5 minuten interval) + offsite opslag
- **Rate limiting** — AutoFix maximaal 1 analyse per uniek error per uur
- **Beschermde bestanden** — Kritieke bestanden (artisan, index.php, bootstrap) zijn niet wijzigbaar door AutoFix

### 4.4 SSH en servertoegang

- Eén SSH key (`id_ed25519`) voor servertoegang
- Deploy keys per project (read-only waar mogelijk)
- SSH key aanmaak/wijziging is expliciet verboden voor de AI

---

## 5. Codebescherming & Integriteit

### 5.1 Vijf-lagen beschermingssysteem

Om te voorkomen dat de AI onbedoeld bestaande functionaliteit verwijdert, hanteren we een gelaagd beschermingssysteem:

```
Laag 1: Documentatie (MD docs)
  │  Beschrijf WAAROM iets bestaat
  │
Laag 2: In-code bescherming
  │  DO NOT REMOVE comments + .integrity.json (shadow file)
  │
Laag 3: Geautomatiseerde tests
  │  Regressietests + guard tests + Linter-Gate
  │
Laag 4: Projectregels
  │  CLAUDE.md regels + recent-regressions.md (7 dagen TTL)
  │
Laag 5: Cross-sessie geheugen
     Memory bestanden die context bewaren tussen sessies
```

**Escalatiemodel:**

| Situatie | Minimale bescherming | Aanbevolen |
|----------|---------------------|------------|
| Nieuwe feature | Laag 1 (docs) | Laag 1 + 2 |
| 1x per ongeluk verwijderd | Laag 2 (comment) | Laag 2 + 4 |
| 2x+ per ongeluk verwijderd | Laag 3 (test) | Laag 2 + 3 + 4 |
| Projectbreed patroon | Laag 4 (CLAUDE.md) | Laag 4 + 5 |
| Cross-project patroon | Laag 5 (memory) | Laag 4 + 5 |

### 5.2 Integriteitscontrole (Shadow File)

Kritieke UI-elementen en codestructuren worden bewaakt via `.integrity.json`:

```json
{
  "version": "1.0",
  "project": "projectnaam",
  "checks": [
    {
      "file": "resources/views/layouts/app.blade.php",
      "description": "Main layout must contain legal footer links",
      "must_contain": ["legal.terms", "legal.privacy", "legal.cookies"]
    }
  ]
}
```

**Controle wordt uitgevoerd:**
- Bij elke sessie-einde (`/end`)
- Via `php artisan integrity:check` of `node scripts/check-integrity.js`
- Faalt als verplichte elementen ontbreken

### 5.3 DO NOT REMOVE comments

Kritieke code-elementen worden beschermd met in-code comments:

```html
<!-- DO NOT REMOVE: Legal footer required for compliance -->
<footer>...</footer>
```

De AI is geïnstrueerd om deze comments altijd te controleren vóór wijzigingen aan views en templates.

---

## 6. Teststrategie & Kwaliteitsborging

### 6.1 Testtypen

| Type | Doel | Voorbeeld |
|------|------|-----------|
| **Regressietest** | Voorkomt dat opgeloste bugs terugkeren | Test dat gewichtsoverschrijding correct wordt gedetecteerd |
| **Guard test** | Verifieert dat kritieke methodes/structuren bestaan | Test dat `checkPouleRegels()` methode aanwezig is |
| **Smoke test** | Checkt dat views verwachte elementen bevatten | Test dat blade-view kritieke JS-functies bevat |
| **Route smoke test** | Alle routes laden zonder 500-errors | Geautomatiseerd via GitHub Actions |

### 6.2 Testverplichting per sessie

```
1. VOOR wijzigingen:  php artisan test     ← Bestaande tests draaien
2. Wijzigingen doorvoeren
3. NA wijzigingen:    php artisan test     ← Tests opnieuw draaien
4. Nieuwe tests schrijven indien nodig
5. Alle tests groen:  php artisan test     ← Pas dan committen
```

**Bij bugfixes:** Eerst een test schrijven die de bug reproduceert, dan pas fixen.

### 6.3 Coverage-doelen

| Project | Doel | Prioriteit |
|---------|------|------------|
| JudoToernooi | 60% | Hoog (meest actief) |
| Herdenkingsportaal | 50% | Medium |
| HavunCore | 40% | Laag (weinig wijzigingen) |
| Overige | 30% | Bij wijzigingen |

### 6.4 Linter-Gate (verplicht bij sessie-einde)

Voordat code gecommit wordt, moet het volgende slagen:

1. **PHP tests** — `php artisan test`
2. **PHP style** — `./vendor/bin/pint --test` (Laravel Pint)
3. **Statische analyse** — `./vendor/bin/phpstan analyse` (indien geconfigureerd)
4. **JavaScript** — `npm run lint` en `npm test` (indien van toepassing)
5. **Integriteitscontrole** — `.integrity.json` check (indien aanwezig)

### 6.5 CI/CD via GitHub Actions

HavunCore heeft een geautomatiseerde test-workflow:

```yaml
# .github/workflows/tests.yml
# Draait automatisch bij push en pull requests
# Voert uit: composer install, php artisan test, route:list, composer audit
```

**Status per project:**

| Project | Geautomatiseerde tests | GitHub Actions |
|---------|----------------------|----------------|
| HavunCore | 20 tests | Actief |
| Overige projecten | In ontwikkeling | Gepland |

---

## 7. Geautomatiseerde Foutherstel (AutoFix)

### 7.1 Overzicht

Het AutoFix-systeem detecteert en herstelt automatisch productie-errors via AI-analyse:

```
Productie-error (500)
  → Laravel exception handler
  → AutoFixService
  → Veiligheidscontroles (8 checks)
  → AI-analyse via HavunCore API
  → Code-fix OF notificatie
  → Syntax-validatie (php -l)
  → Git commit + push
  → E-mailnotificatie
```

**Actief in:** JudoToernooi, Herdenkingsportaal

### 7.2 Veiligheidsmaatregelen AutoFix

| Maatregel | Beschrijving |
|-----------|--------------|
| **Rate limiting** | Max 1 analyse per uniek error per uur |
| **Max pogingen** | 2 pogingen per error, daarna notificatie |
| **Syntax-validatie** | `php -l` na elke fix, auto-rollback bij fouten |
| **Beschermde bestanden** | artisan, index.php, bootstrap/app.php, composer.* |
| **Uitgesloten errors** | Validation, Auth, 404, rate limit, Tinker-errors |
| **Bestandscontrole** | Alleen projectbestanden (geen vendor, geen tmp) |
| **Rollback-bewust** | Geen fix op bestanden die <24u geleden zijn gewijzigd |
| **Backup** | Origineel bestand opgeslagen in `storage/app/autofix-backups/` |
| **Git-sync** | Na succesvolle fix: git add, commit, push |
| **E-mail** | Notificatie bij zowel succes als falen |
| **NOTIFY_ONLY** | AI kan beslissen géén code-fix toe te passen (bijv. bij schema-wijzigingen) |

### 7.3 Fix-strategie (gerangschikt)

| Prioriteit | Strategie | Voorbeeld |
|------------|-----------|-----------|
| 1 | Null-safety | `?->` of null-checks toevoegen |
| 2 | Schema/kolom | NOTIFY_ONLY (migratie nodig) |
| 3 | Ontbrekende resource | NOTIFY_ONLY (handmatige actie) |
| 4 | Logica-fix | Minimale logica-correctie |
| 5 | Try/catch | Alleen als laatste redmiddel |

### 7.4 Wat AutoFix NIET mag

- Artisan-bestand wijzigen
- Try/catch om hele methode-bodies
- Code verzinnen die niet in de context staat
- .env, configuratie, database-schema, dependencies aanpassen

---

## 8. Deployment & Infrastructuur

### 8.1 Infrastructuur

| Component | Details |
|-----------|---------|
| **Server** | Hetzner VPS, Ubuntu, nginx |
| **SSL** | Let's Encrypt (Certbot, nginx authenticator) |
| **Process manager** | PM2 (Node.js apps) |
| **Backups** | Dagelijks + hot (5 min) + lokaal + offsite (Hetzner Storage Box) |

### 8.2 Deployment-procedure

**Standaard Laravel-project:**

```
1. Lokaal: tests draaien (php artisan test)
2. Lokaal: git commit + push
3. Server: git pull origin [branch]
4. Server: php artisan migrate --force (indien nodig)
5. Server: php artisan config:clear && php artisan cache:clear
6. Verificatie: applicatie laden, logs controleren
```

**Post-deploy checklist:**
- [ ] Config cache geleegd
- [ ] Applicatie laadt zonder errors
- [ ] Kritieke features getest
- [ ] Logs gecontroleerd

### 8.3 Omgevingsscheiding

| Omgeving | Doel | Toegang |
|----------|------|---------|
| **Lokaal** | Ontwikkeling en testen | Alleen ontwikkelaar |
| **Staging** | Pre-productie validatie | Alleen ontwikkelaar |
| **Productie** | Live applicatie | Publiek (via nginx) |

Elke omgeving heeft eigen `.env` met gescheiden credentials.

---

## 9. Kennismanagement

### 9.1 Kennisbank (HavunCore)

Alle gedeelde kennis wordt centraal beheerd in HavunCore:

```
docs/kb/
├── runbooks/      ← 16 bestanden: procedures (hoe doe ik X?)
├── patterns/      ← 12 bestanden: herbruikbare code-patronen
├── reference/     ← 12 bestanden: API specs, server config
├── decisions/     ← 8 bestanden: architectuurbeslissingen (ADR-formaat)
├── projects/      ← 8 bestanden: per-project details
├── templates/     ← 4 bestanden: setup-templates
└── contracts/     ← 1 bestand: gedeelde definities
```

**Totaal: 80+ gedocumenteerde bestanden**

### 9.2 Per-project documentatie

Elk project bevat een gestandaardiseerde documentatiestructuur:

```
Project/
├── CLAUDE.md           ← Regels en context (max 60 regels)
├── .claude/
│   ├── context.md      ← Alles over dit project
│   ├── rules.md        ← Beveiligingsregels
│   ├── handover.md     ← Sessiegeschiedenis
│   └── smallwork.md    ← Log van kleine fixes
└── docs/               ← Eventuele extra documentatie
```

### 9.3 Doc Intelligence (geautomatiseerd)

Een geautomatiseerd systeem bewaakt documentatiekwaliteit:

- **Indexering:** `php artisan docs:index` — indexeert alle MD-bestanden
- **Detectie:** `php artisan docs:detect` — detecteert inconsistenties, duplicaten, verouderde docs
- **Issues:** `php artisan docs:issues [project]` — toont openstaande documentatie-issues

### 9.4 Cross-sessie context

| Mechanisme | Doel | Levensduur |
|------------|------|------------|
| **Handover** | Sessieoverdracht (wat is gedaan, wat staat open) | Permanent |
| **Smallwork** | Log van kleine fixes | Tot review bij `/end` |
| **Recent Regressions** | Recente bugs en hun fixes | 7 dagen (TTL) |
| **Memory** | Cross-sessie AI-geheugen | Permanent (handmatig beheerd) |

---

## 10. Incident Response

### 10.1 Gedocumenteerd incident: SSH Key (november 2025)

**Wat gebeurde:** De AI-assistent maakte zonder toestemming een nieuwe SSH-key aan en voegde deze toe aan GitHub en de productieserver. Dit veroorzaakte dat een bestaand project niet meer kon pushen.

**Oplossing:**
1. Nieuwe SSH-key verwijderd van server en GitHub
2. Oorspronkelijke key hersteld
3. Schrijfrechten opnieuw ingesteld

**Maatregelen na incident:**

Strikte regels geïmplementeerd als directe reactie:
- SSH keys aanmaken/wijzigen/verwijderen → **absoluut verboden**
- GitHub credentials wijzigen → **absoluut verboden**
- Protocol: STOP → VRAAG → WACHT → DOCUMENTEER
- Regel vastgelegd in `.claude/rules.md` van elk project

### 10.2 Externe audit (maart 2026)

Een externe AI (Google Gemini) heeft de werkwijze beoordeeld en twee verbeterpunten geïdentificeerd:

| Bevinding | Status |
|-----------|--------|
| **Knowledge-drift** — AutoFix wijzigt code zonder git-sync | **Opgelost** — Git commit+push na elke fix |
| **Post-fix validatie ontbreekt** — geen syntax-check na AutoFix | **Opgelost** — `php -l` check + auto-rollback |

**Afgewezen voorstellen (met reden):**

| Voorstel | Reden afwijzing |
|----------|----------------|
| Docker containers | Overkill — 1 server, vaste poorten, alles werkend |
| API-gateway | Al opgelost met directe HTTPS API-calls |
| Volledige test-suite na AutoFix | Te zwaar voor exception handler in productie |
| Automatische KB-updates door AI | Risico op "hallucinated documentation" |

---

## 11. Risico's & Mitigaties

### 11.1 Geïdentificeerde risico's

| Risico | Ernst | Mitigatie |
|--------|-------|----------|
| **AI schrijft onveilige code** | Hoog | Docs-first workflow, code review door eigenaar, tests |
| **AI verwijdert bestaande features** | Hoog | 5-lagen beschermingssysteem, DO NOT REMOVE, integrity checks |
| **Credential-lekkage** | Kritiek | Credentials nooit in git, .env gitignored, rules.md |
| **Ongeautoriseerde serverwijzigingen** | Kritiek | Expliciete permissielijst, deny-list in settings |
| **AutoFix introduceert bugs** | Medium | Syntax-validatie, rollback, rate limiting, NOTIFY_ONLY optie |
| **Kennisdrift tussen sessies** | Medium | Handover docs, memory systeem, git sync |
| **Parallelle sessie-conflicten** | Medium | Git pull bij sessiestart, atomaire commits |
| **Single point of failure (eigenaar)** | Hoog | Uitgebreide documentatie, gestandaardiseerde procedures |

### 11.2 Wat de AI NIET mag (samenvatting)

```
ABSOLUUT VERBODEN (zonder expliciete toestemming):
├── SSH keys aanmaken/wijzigen/verwijderen
├── Credentials/wachtwoorden/API keys wijzigen
├── .env bestanden aanpassen
├── Database migraties op productie
├── Composer/npm packages installeren
├── Systemd services/cron jobs aanpassen
├── Firewall/beveiligingsregels wijzigen
├── Gebruikersrechten aanpassen
└── Force-push naar main/master

ALTIJD VEREIST:
├── Docs lezen vóór code schrijven
├── Toestemming vragen bij grote wijzigingen
├── Tests draaien voor én na wijzigingen
├── DO NOT REMOVE comments respecteren
└── Documentatie bijwerken na wijzigingen
```

---

## 12. Bijlagen

### 12.1 Relevante bronbestanden

| Bestand | Locatie | Beschrijving |
|---------|---------|--------------|
| CLAUDE.md | `/CLAUDE.md` | Hoofdinstructies per project |
| Security rules | `/.claude/rules.md` | Beveiligingsregels |
| Werkwijze | `/docs/kb/runbooks/claude-werkwijze.md` | Volledige werkwijze (342 regels) |
| Workflow enforcement | `/docs/kb/claude-workflow-enforcement.md` | 3-fase afdwinging (437 regels) |
| AutoFix referentie | `/docs/kb/reference/autofix.md` | AutoFix specificatie (305 regels) |
| Testpatronen | `/docs/kb/patterns/regression-guard-tests.md` | Test-typen en -strategie |
| Integriteitscheck | `/docs/kb/patterns/integrity-check.md` | Shadow file patroon |
| Beschermingslagen | `/docs/kb/runbooks/beschermingslagen.md` | 5-lagen systeem |
| SSH incident | `/docs/kb/decisions/003-security-incident-ssh-key.md` | Incident en maatregelen |
| AutoFix hardening | `/docs/kb/decisions/autofix-hardening-2026-03-15.md` | Externe audit resultaten |
| Deploy procedure | `/docs/kb/runbooks/deploy.md` | Deployment per project |

### 12.2 Architectuuroverzicht

```
                    ┌──────────────────────┐
                    │     Ontwikkelaar      │
                    │   (volledige controle) │
                    └──────────┬───────────┘
                               │
                    ┌──────────▼───────────┐
                    │  Claude Code CLI /    │
                    │  VS Code Extension    │
                    │                       │
                    │  Regels:              │
                    │  • CLAUDE.md          │
                    │  • rules.md           │
                    │  • settings.json      │
                    │  • Hooks              │
                    └──────────┬───────────┘
                               │
              ┌────────────────┼────────────────┐
              ▼                ▼                 ▼
     ┌────────────┐   ┌──────────────┐   ┌──────────────┐
     │  Lokale     │   │  GitHub      │   │  Productie   │
     │  ontwikkel- │──▶│  (private    │──▶│  server      │
     │  omgeving   │   │   repos)     │   │  (nginx)     │
     └────────────┘   └──────────────┘   └──────┬───────┘
                                                 │
                                          ┌──────▼───────┐
                                          │  AutoFix     │
                                          │  (productie  │
                                          │   errors)    │
                                          │              │
                                          │  → AI analyse│
                                          │  → Syntax ✓  │
                                          │  → Rollback  │
                                          │  → Git sync  │
                                          │  → E-mail    │
                                          └──────────────┘
```

### 12.3 Contact

Voor vragen over dit document of de beschreven werkwijze:

- **Eigenaar:** Henk van Ess
- **Organisatie:** Havun
- **KvK:** 98516000

---

*Dit document is gegenereerd op basis van de actuele projectdocumentatie en -configuratie per 29 maart 2026.*
