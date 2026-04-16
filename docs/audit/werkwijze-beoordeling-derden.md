# Havun Software Development — Werkwijze & Kwaliteitsborging

**Document:** Ter beoordeling door technische derde partij
**Versie:** 3.0
**Datum:** 16 april 2026
**Volgende review:** Q3 2026 (juli 2026)
**Reviewcyclus:** Elk kwartaal
**Organisatie:** Havun (KvK: 98516000)
**Opgesteld door:** Henk van Unen, eigenaar & lead developer

### Reviewgeschiedenis

| Versie | Datum | Reviewer | Opmerkingen |
|--------|-------|----------|-------------|
| 1.0 | 29-03-2026 | Gemini + Claude Sonnet | Eerste versie, beoordeeld met 7/10 en "robuust voor eenmanszaak" |
| 2.0 | 29-03-2026 | Gemini (9/10) + Claude (8.5/10) | Alle VP's verwerkt, coverage + CI op alle projecten |
| 3.0 | 16-04-2026 | **Gemini (9,8/10)** | *"Overstijgt eenmanszaak-niveau, concurreert met middelgrote softwarehuizen."* Adviezen → VP-12 (doc-sync bij /end), VP-13 (kwartaal-droogtest noodprotocol). Zorg: schaalbaarheid (9,0) — onderhoudslast 16.000+ tests bij framework-upgrades. |
| 3.0 | 16-04-2026 | **Claude (kritisch contra-review, ~8,5/10 verwacht extern)** | *"Dossier is verzendbaar, maar 'AI als risico' is de enige lacune die een serieuze externe auditor zal opmerken."* Drie blinde vlekken benoemd: (1) test-intentie vs volume — geen onderscheid contract vs implementatie tests; (2) geen formele SLO/SLA + deploy-bevoegdheid tijdens afwezigheid; (3) AI hallucinatie risico (AI repareert test i.p.v. bug). Adviezen → VP-14 (CONTRACTS.md per app), VP-15 (deploy-bevoegdheden formaliseren), VP-16 (mutation testing), VP-17 (AI test-repair anti-pattern review-regel). |

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
9. [Uptime-monitoring & SLA](#9-uptime-monitoring--sla)
10. [Kennismanagement](#10-kennismanagement)
11. [Incident Response & Externe Audits](#11-incident-response--externe-audits)
12. [Risico's & Mitigaties](#12-risicos--mitigaties)
13. [Bijlagen](#13-bijlagen)

---

## 1. Introductie

### 1.1 Doel van dit document

Dit document beschrijft de volledige werkwijze waarmee Havun software ontwikkelt met behulp van AI-gestuurde tools (Claude Code CLI en Claude Code VS Code Extension). Het doel is om aan een technische derde partij aan te tonen:

- Welke beveiligingsmaatregelen zijn getroffen
- Hoe softwarekwaliteit wordt geborgd
- Welke protocollen en procedures worden gevolgd
- Hoe risico's worden beheerst bij AI-ondersteunde ontwikkeling
- Welke verbeteringen zijn doorgevoerd na externe audits

### 1.2 Projectportfolio

Havun beheert meerdere webapplicaties vanuit een centrale orchestrator:

| Project | Type | Stack | Publiek? |
|---------|------|-------|----------|
| **HavunCore** | Centrale kennisbank & orchestrator | Laravel 11 (PHP) | Nee (backend) |
| **HavunAdmin** | Beheerpaneel | Laravel + Vite | Nee (intern) |
| **Herdenkingsportaal** | Publieke webapp, betalingen | Laravel + Vite | Ja |
| **JudoToernooi** | Toernooibeheer | Laravel + Vite | Ja |
| **Studieplanner** | Mobiele app | React Native + Expo | Ja (app) |
| **SafeHavun** | Beveiligingsplatform | Laravel + Vite | Beperkt |
| **Infosyst** | Informatiesysteem | Laravel + Vite | Beperkt |
| **HavunVet** | Dierenarts praktijkbeheer | Laravel 11 + Livewire 3 | Beperkt |
| **JudoScoreBoard** | Scorebord app | React Native + Expo | Ja (app) |

### 1.3 AI-tooling in gebruik

| Tool | Gebruik |
|------|---------|
| **Claude Code CLI** | Terminal-gebaseerde AI-assistent voor code, git, deploy |
| **Claude Code VS Code Extension** | IDE-geintegreerde AI-assistent |
| **Ollama (lokaal)** | Lokale AI voor kennisbank-indexering (Command-R model) |

> **Belangrijk:** De AI schrijft code, maar opereert altijd binnen strikte regels en protocollen. De ontwikkelaar behoudt volledige controle en moet elke significante wijziging goedkeuren.

---

## 2. Tooling & Omgeving

### 2.1 Ontwikkelomgeving

| Component | Details |
|-----------|---------|
| **OS** | Windows 11 |
| **IDE** | VS Code met Claude Code Extension |
| **Terminal** | Claude Code CLI (bash shell) |
| **Versiebeheer** | Git + GitHub (private repositories) |
| **Server** | Hetzner VPS (Ubuntu, nginx) |
| **Lokale AI** | Ollama op poort 11434 |
| **Secret scanning** | GitGuardian (pre-commit hook) |

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

### 2.3 De 5 Onschendbare Regels

Geintroduceerd na externe audit (VP-06) als kern van alle protocollen:

```
1. NOOIT code schrijven zonder docs te lezen
2. NOOIT features/UI-elementen verwijderen zonder instructie
3. NOOIT credentials/keys/env aanraken
4. ALTIJD tests draaien voor en na wijzigingen
5. ALTIJD toestemming vragen bij grote wijzigingen
```

Deze regels staan bovenaan de `CLAUDE.md` van **alle 9 projecten** (per 16-04-2026) en worden herhaald bij sessiestart en halverwege langere sessies via het `/update` command.

### 2.4 Sessielimiet

Sessies worden beperkt tot 2-3 uur. Bij langere sessies neemt de kans op protocolmoeheid toe — de AI kan regels overslaan of documenthierarchie door elkaar halen. Bij langere taken wordt een nieuwe sessie gestart.

**Handhaving:** Dit is een procedureregel, geen technische afdwinging. De AI herinnert aan de limiet via het `/update` command halverwege sessies. De eigenaar is verantwoordelijk voor naleving.

---

## 3. Ontwikkelworkflow

### 3.1 Kernprincipe: LEES -> DENK -> DOE -> DOCUMENTEER

Elke ontwikkeltaak doorloopt verplicht vier fasen:

```
FASE 1: LEES
  Lees projectdocumentatie en relevante code
  Hierarchisch: CLAUDE.md -> context.md -> code

FASE 2: DENK
  Analyseer het probleem
  Identificeer bestaande patronen
  Stel vragen bij onduidelijkheid, WACHT op antwoord

FASE 3: DOE
  Kleine, atomaire wijzigingen
  Test na elke significante wijziging
  Kwaliteit boven snelheid

FASE 4: DOCUMENTEER
  Sla nieuwe kennis op
  Update relevante documentatie
  Handover voor volgende sessie
```

### 3.2 Docs-First Workflow (verplicht bij significante wijzigingen)

**Principe:** Code volgt documentatie. Nooit andersom.

| Type wijziging | Workflow | Voorbeeld |
|----------------|----------|-----------|
| **Groot** (feature, UI, business rules) | Zoek docs -> rapporteer -> wacht goedkeuring -> update docs -> code | Nieuwe checkout flow |
| **Klein** (bugfix, typo, performance) | Log in smallwork.md -> fix -> klaar | Null-pointer fix |

**Docs-first stappen bij grote wijzigingen:**
1. **Zoek** alle relevante MD-bestanden over het onderwerp
2. **Lees** volledig (niet scannen)
3. **Rapporteer** aan ontwikkelaar: wat er staat, inconsistenties, wat ontbreekt
4. **Wacht** op goedkeuring
5. **Update documentatie** eerst indien nodig
6. **Schrijf code** op basis van de goedgekeurde documentatie

### 3.3 3-Fase afdwinging

| Fase | Actie | Vereiste |
|------|-------|---------|
| **Fase 1: READ** | Noem alle relevante docs, citeer specifieke regels | Verplicht voor code |
| **Fase 2: PLAN** | Beschrijf aanpak en bestanden, vraag toestemming | Wacht op "ja" |
| **Fase 3: CODE** | Schrijf code conform docs en plan | Alleen na goedkeuring |

**Gebruikerscommando's:**
- `STOP` — direct stoppen, terug naar Fase 1
- `DOCS?` — welke docs zijn gelezen? Citeer ze.
- `PLAN?` — wat is de exacte aanpak?

### 3.4 Git-workflow

| Regel | Beschrijving |
|-------|--------------|
| Atomaire commits | 1 feature of fix = 1 commit |
| Directe push | Na commit direct pushen |
| Branch cleanup | Gemergte branches worden verwijderd |
| Force-push verbod | Force-push naar main/master is geblokkeerd via settings |
| Commit messages | Engels, beschrijvend, gestructureerd |
| Secret scanning | GitGuardian pre-commit hook blokkeert credentials in commits |

### 3.5 Sessiemanagement

**Bij sessiestart (`/start`):**
1. Git pull (sync met eventuele AutoFix-wijzigingen)
2. **Dependency security audit** — `composer audit` + `npm audit` (VP-04)
3. Lees projectdocumentatie (CLAUDE.md, context.md, rules.md)
4. Controleer documentatie-issues (Doc Intelligence)
5. Bevestig aan ontwikkelaar

**Bij sessie-einde (`/end`):**
1. Review smallwork-log
2. Update documentatie
3. Schrijf handover voor volgende sessie
4. **Linter-Gate** — tests + lint + integriteitscontrole
5. Commit, push
6. **Staging-deploy** (verplicht voor publieke apps, VP-08)
7. Productie-deploy (na staging-verificatie)
8. **KOR-omzetcheck** (elk kwartaal, VP-10)

---

## 4. Beveiligingsprotocollen

### 4.1 Verboden acties (zonder expliciete toestemming)

| Categorie | Voorbeelden |
|-----------|-------------|
| **Credentials** | SSH keys aanmaken/wijzigen/verwijderen, API keys, wachtwoorden |
| **Configuratie** | .env bestanden, systemd services, cron jobs |
| **Infrastructuur** | Firewall regels, gebruikersrechten, server configuratie |
| **Database** | Migraties op productie |
| **Dependencies** | Composer/npm packages installeren |

**Protocol:** `STOP -> VRAAG TOESTEMMING -> WACHT OP ANTWOORD -> DOCUMENTEER`

### 4.2 Credential management

| Aspect | Aanpak |
|--------|--------|
| **Opslag** | Credentials in `.env` bestanden (niet in versiebeheer) |
| **Documentatie** | Credential-referenties in `.claude/credentials.md` (gitignored) |
| **Scheiding** | Productie- en staging-credentials gescheiden |
| **Toegang** | Alleen eigenaar heeft toegang tot credentials |
| **Git** | Credentials mogen NOOIT in git-getrackte bestanden |
| **Pre-commit scanning** | GitGuardian blokkeert commits met secrets (alle 8 projecten) |

### 4.3 GitGuardian Secret Scanning

**Tool:** GitGuardian (`ggshield`) — gratis tier
**Type:** Pre-commit hook op elke lokale repository
**Configuratie:** `python -m ggshield secret scan pre-commit` in `.git/hooks/pre-commit`

| Project | GitGuardian actief? |
|---------|-------------------|
| HavunCore | Ja |
| HavunAdmin | Ja |
| Herdenkingsportaal | Ja |
| JudoToernooi | Ja |
| Studieplanner | Ja |
| SafeHavun | Ja |
| Infosyst | Ja |
| JudoScoreBoard | Ja |

**Werking:** Bij elke `git commit` scant ggshield de staged changes op API keys, wachtwoorden, tokens en andere secrets. Bij detectie wordt de commit geblokkeerd met een waarschuwing.

### 4.4 Secret Rotation Protocol

| Secret type | Rotatie-frequentie | Procedure |
|-------------|-------------------|-----------|
| GitHub PAT | Jaarlijks (verloopt jan 2027) | Nieuw token genereren, .env's updaten |
| SSH key (server) | Bij incident of vermoeden van compromis | Nieuwe key genereren, oude intrekken |
| API keys (Anthropic, Mollie, Stripe) | Bij incident | Via provider dashboard |
| SMTP credentials (Brevo) | Bij incident | Via Brevo dashboard |
| Database wachtwoorden | Bij incident | Via MySQL CLI op server |

**Trigger voor onmiddellijke rotatie:**
- GitGuardian detecteert een gelekt secret
- Ongeautoriseerde toegang vastgesteld
- Medewerker/tool met toegang verliest vertrouwen
- Secret per ongeluk in logs of foutmeldingen

### 4.5 Dependency Security Audit (VP-04)

Bij elke sessiestart worden dependencies automatisch gecontroleerd:

| Check | Frequentie | Blokkeerend? |
|-------|-----------|-------------|
| `composer audit` | Elke sessie + elke push (CI) | Ja, bij kritieke kwetsbaarheden |
| `npm audit` | Elke sessie | Nee (informatief) |
| `composer outdated` | Maandelijks | Nee (informatief) |
| **OWASP ZAP scan** | Jaarlijks in januari (Herdenkingsportaal) | N.v.t. (rapport) |

**OWASP ZAP planning:** Eerste scan: januari 2027. Rapport wordt opgeslagen in `docs/audit/owasp-scan-YYYY-MM.md`. Scope: Herdenkingsportaal (publiek verkeer + betalingen).

### 4.6 Security Headers (VP-04)

Kwartaallijkse controle op publieke apps:

| Header | Doel |
|--------|------|
| `Strict-Transport-Security` | Forceert HTTPS |
| `X-Content-Type-Options: nosniff` | Voorkomt MIME-type sniffing |
| `X-Frame-Options` | Voorkomt clickjacking |
| `Referrer-Policy` | Beperkt referrer-lekkage |
| `Content-Security-Policy` | Voorkomt XSS |
| `Permissions-Policy` | Beperkt browser APIs |

### 4.7 SSH en servertoegang

- Een SSH key (`id_ed25519`) voor servertoegang
- Deploy keys per project (read-only waar mogelijk)
- SSH key aanmaak/wijziging is expliciet verboden voor de AI
- Incident uit november 2025 heeft geleid tot strikte regels (zie sectie 11)

---

## 5. Codebescherming & Integriteit

### 5.1 Vijf-lagen beschermingssysteem

Om te voorkomen dat de AI onbedoeld bestaande functionaliteit verwijdert:

| Laag | Wat | Effort |
|------|-----|--------|
| **1. MD docs** | Beschrijf WAAROM iets bestaat | Laag |
| **2. In-code bescherming** | `DO NOT REMOVE` comments + `.integrity.json` (shadow file) | Zeer laag |
| **3. Geautomatiseerde tests** | Regressietests + guard tests + Linter-Gate | Medium |
| **4. Projectregels** | CLAUDE.md regels + `recent-regressions.md` (7 dagen TTL) | Eenmalig |
| **5. Cross-sessie geheugen** | Memory bestanden die context bewaren tussen sessies | Zeer laag |

**Escalatiemodel:**

| Situatie | Minimale bescherming | Aanbevolen |
|----------|---------------------|------------|
| Nieuwe feature | Laag 1 (docs) | Laag 1 + 2 |
| 1x per ongeluk verwijderd | Laag 2 (comment) | Laag 2 + 4 |
| 2x+ per ongeluk verwijderd | Laag 3 (test) | Laag 2 + 3 + 4 |
| Projectbreed patroon | Laag 4 (CLAUDE.md) | Laag 4 + 5 |
| Cross-project patroon | Laag 5 (memory) | Laag 4 + 5 |

### 5.2 Integriteitscontrole met Selector-Support (VP-05)

Kritieke UI-elementen worden bewaakt via `.integrity.json` met twee typen checks:

**Tekst-matching (`must_contain`):**
```json
{
  "file": "resources/views/layouts/app.blade.php",
  "description": "Layout must contain CSRF meta and footer",
  "must_contain": ["csrf-token", "<x-footer"]
}
```

**HTML-selector-matching (`must_contain_selector`) — v2.0:**
```json
{
  "file": "resources/views/layouts/app.blade.php",
  "description": "Kritieke UI-elementen moeten aanwezig zijn",
  "must_contain_selector": [
    "footer.site-footer",
    "#cookie-banner",
    "nav.main-navigation",
    "[data-testid=\"logo\"]"
  ]
}
```

Ondersteunde selectors: `#id`, `.class`, `tag.class`, `tag#id`, `[attribute]`, `[attribute="value"]`, bare tags.

**Route-existence (`must_contain_route`) — v2.0:**
```json
{
  "file": "routes/web.php",
  "description": "Legal routes moeten bestaan",
  "must_contain_route": ["legal.terms", "legal.privacy"]
}
```

**Implementatie:** Laravel artisan command (`php artisan integrity:check`) met `--project` en `--json` opties. 17 geautomatiseerde tests.
**Status:** Actief in HavunCore (3 checks, alle groen). Schema beschikbaar voor alle projecten.
**Controle:** Bij elke sessie-einde via `php artisan integrity:check`.

### 5.3 DO NOT REMOVE comments

Kritieke code-elementen worden beschermd met in-code comments:

```html
<!-- DO NOT REMOVE: Legal footer required for compliance -->
<footer>...</footer>
```

De AI controleert deze comments altijd voor wijzigingen aan views en templates.

---

## 6. Teststrategie & Kwaliteitsborging

### 6.1 Testtypen

| Type | Doel | Voorbeeld |
|------|------|-----------|
| **Regressietest** | Voorkomt dat opgeloste bugs terugkeren | Test dat gewichtsoverschrijding correct wordt gedetecteerd |
| **Guard test** | Verifieert dat kritieke methodes/structuren bestaan | Test dat `checkPouleRegels()` methode aanwezig is |
| **Smoke test** | Checkt dat views verwachte elementen bevatten | Test dat blade-view kritieke JS-functies bevat |
| **Route smoke test** | Alle routes laden zonder 500-errors | Geautomatiseerd via GitHub Actions |
| **Integriteitstest** | Controleert `.integrity.json` checks | Kritieke UI-elementen aanwezig |

### 6.2 Testverplichting per sessie

```
1. VOOR wijzigingen:  php artisan test      <- Bestaande tests draaien
2. Wijzigingen doorvoeren
3. NA wijzigingen:    php artisan test      <- Tests opnieuw draaien
4. Nieuwe tests schrijven indien nodig
5. Alle tests groen:  php artisan test      <- Pas dan committen
```

**Bij bugfixes:** Eerst een test schrijven die de bug reproduceert, dan pas fixen.

### 6.3 Coverage: huidige stand en doelen

| Project | Tests | Assertions | Coverage (PCOV) | Doel | Status |
|---------|-------|------------|-----------------|------|--------|
| **SafeHavun** | 302 | 565 | **94.22%** | 80% | **Gehaald** |
| **Infosyst** | 834 | 1.552 | **91.51%** | 80% | **Gehaald** |
| **HavunVet** | 276 | 442 | **90.87%** | 80% | **Gehaald** |
| **JudoToernooi** | 3.257 | 6.789 | **89.84%** | 80% | **Gehaald** |
| **HavunAdmin** | 3.180 | 5.059 | **89.75%** | 80% | **Gehaald** |
| **HavunCore** | 795 | 2.012 | **87,4%** | 80% | **Gehaald** |
| **Studieplanner** | 223 | — | **82,67%** (Jest) | 80% | **Gehaald** |
| **JudoScoreBoard** | — | — | **93,42%** (Jest) | 80% | **Gehaald** |
| **Herdenkingsportaal** | 6.729 | 10.530 | **85,94%** | **85%** (publieke betalingen) | **Gehaald** |

> **Resultaat:** Alle 9 projecten hebben hun coverage-doel gehaald, inclusief Herdenkingsportaal dat met **85,94%** ruim boven de verscherpte 85%-norm zit (publieke betalingen vereisen extra dekking). Binnen Herdenkingsportaal is de kritieke payment-pijler extra hoog gedekt: webhook 85,4%, invoice-service 88,6%, Mollie productie-service 81,7%. Totaal testportfolio: **16.000+ tests** met **28.000+ assertions**. Meting: Laravel projecten via PCOV (16-04-2026), React Native via Jest (12-04-2026).
>
> **Datum meting:** 16 april 2026 (alle projecten, lokaal gemeten met PCOV)

**CI-afdwinging:** GitHub Actions blokkeert een push als het coverage-minimum niet wordt gehaald (HavunCore: 40%, wordt verhoogd per kwartaal).

### 6.4 Linter-Gate (verplicht bij sessie-einde)

Voordat code gecommit wordt, moet het volgende slagen:

1. **PHP tests** — `php artisan test`
2. **PHP style** — `./vendor/bin/pint --test` (Laravel Pint)
3. **Statische analyse** — `./vendor/bin/phpstan analyse` (indien geconfigureerd)
4. **JavaScript** — `npm run lint` en `npm test` (indien van toepassing)
5. **Integriteitscontrole** — `.integrity.json` check (indien aanwezig)

### 6.5 CI/CD via GitHub Actions

Geautomatiseerde test-workflow bij elke push en pull request:

```yaml
# Stappen:
1. composer install
2. composer audit (blokkeerend bij kwetsbaarheden)
3. php artisan test (met coverage-rapport)
4. Coverage-drempel check (minimum 40%, verhoogd per project)
```

| Project | Tests | GitHub Actions | Coverage (actueel) |
|---------|-------|----------------|-------------------|
| JudoToernooi | 3.257 tests | Actief | 89.84% |
| HavunAdmin | 3.180 tests | Actief | 89.75% |
| Infosyst | 834 tests | Actief | 91.51% |
| HavunCore | 795 tests | Actief | 87.4% |
| SafeHavun | 302 tests | Actief | 94,22% |
| HavunVet | 276 tests | Gepland | 90,87% |
| Studieplanner | 223 tests (Jest) | n.v.t. | 82,67% |
| JudoScoreBoard | (Jest) | n.v.t. | 93,42% |
| Herdenkingsportaal | 6.729 tests | Actief | 85,94% (doel 85% — publieke betalingen, gehaald) |

---

## 7. Geautomatiseerde Foutherstel (AutoFix)

### 7.1 Overzicht

Het AutoFix-systeem detecteert en herstelt automatisch productie-errors via AI-analyse.

**Actief in:** JudoToernooi, Herdenkingsportaal

### 7.2 Flow (v2.0 — Branch-Model)

```
Productie-error (500)
  -> AutoFixService::handle()
  -> Veiligheidscontroles (8 checks)
  -> AI-analyse via HavunCore API
  -> RISK: medium/high? -> DRY-RUN (alleen notificatie, geen fix)
  -> RISK: low? ->
      1. Code-fix toepassen
      2. Syntax-validatie (php -l, auto-rollback bij fout)
      3. Hotfix-branch aanmaken (hotfix/autofix-{timestamp})
      4. Commit + push naar branch
      5. Pull Request aanmaken via GitHub REST API
      6. E-mailnotificatie met PR-link
      7. Eigenaar reviewed en mergt met een klik
```

> **Belangrijk (v2.0):** AutoFix pusht NIET meer direct naar de hoofdbranch. Alle fixes gaan via een hotfix-branch + Pull Request. De eigenaar behoudt volledige controle over wat er in productie komt.

### 7.3 Veiligheidsmaatregelen

| Maatregel | Beschrijving |
|-----------|--------------|
| **Branch-model** | Fixes gaan naar hotfix-branch + PR, niet direct naar main (VP-01) |
| **Dry-run modus** | Medium/high risk fixes worden NIET automatisch toegepast (VP-01) |
| **Rate limiting** | Max 1 analyse per uniek error per uur |
| **Max pogingen** | 2 pogingen per error, daarna alleen notificatie |
| **Syntax-validatie** | `php -l` na elke fix, auto-rollback bij fouten |
| **Beschermde bestanden** | artisan, index.php, bootstrap/app.php, composer.* |
| **Uitgesloten errors** | Validation, Auth, 404, rate limit, Tinker-errors |
| **Bestandscontrole** | Alleen projectbestanden (geen vendor, geen tmp) |
| **Rollback-bewust** | Geen fix op bestanden die <24u geleden zijn gewijzigd |
| **Backup** | Origineel bestand opgeslagen voor elke wijziging |
| **E-mail** | Notificatie bij succes, falen, dry-run, en NOTIFY_ONLY |
| **NOTIFY_ONLY** | AI kan beslissen geen code-fix toe te passen |
| **PR-review** | Eigenaar moet PR handmatig mergen (v2.0) |

### 7.4 Fix-strategie (gerangschikt)

| Prioriteit | Strategie | Actie |
|------------|-----------|-------|
| 1 | Null-safety | Automatische fix (`?->`, null-checks) |
| 2 | Schema/kolom | NOTIFY_ONLY (migratie nodig, geen auto-fix) |
| 3 | Ontbrekende resource | NOTIFY_ONLY (handmatige actie nodig) |
| 4 | Logica-fix | Automatische fix (minimale correctie) |
| 5 | Try/catch | Alleen als allerlaatste redmiddel |

### 7.5 Wat AutoFix NIET mag

- Artisan-bestand wijzigen
- Try/catch om hele methode-bodies
- Code verzinnen die niet in de context staat
- .env, configuratie, database-schema, dependencies aanpassen
- Direct naar de hoofdbranch pushen (v2.0)
- Medium/high risk fixes automatisch toepassen (v2.0)

---

## 8. Deployment & Infrastructuur

### 8.1 Infrastructuur

| Component | Details |
|-----------|---------|
| **Server** | Hetzner VPS, Ubuntu, nginx |
| **SSL** | Let's Encrypt (Certbot, nginx authenticator) |
| **Process manager** | PM2 (Node.js apps) |
| **Backups** | Dagelijks + hot (5 min) + lokaal + offsite (Hetzner Storage Box) |
| **Monitoring** | Server-side health check (cron, elke 5 min) |

### 8.2 Staging-vereiste (VP-08)

Elke deploybare wijziging aan publieke apps gaat EERST naar staging:

| Project | Staging verplicht? | Reden |
|---------|-------------------|-------|
| Herdenkingsportaal | **Ja** | Publiek verkeer, betalingen |
| JudoToernooi | **Ja** | Actief tijdens toernooien |
| HavunAdmin | **Ja** | Heeft staging-omgeving |
| HavunCore | Nee | Backend only, geen staging |
| Overige | Nee | Beperkt gebruik |

### 8.3 Deployment-procedure

```
1. Lokaal: tests draaien (MOET groen zijn)
2. Lokaal: git commit + push
3. STAGING: git pull + migrate + cache clear
4. STAGING: handmatige verificatie
   - Standaard: minimaal 1 uur
   - Grote wijzigingen: 24 uur
5. PRODUCTIE: git pull + migrate + cache clear
6. PRODUCTIE: verificatie + logs controleren
```

**Uitzonderingen (geen staging):** Alleen docs-wijzigingen, of hotfixes voor productie-kritieke bugs (met extra review).

### 8.4 Omgevingsscheiding

| Omgeving | Doel | Credentials |
|----------|------|-------------|
| **Lokaal** | Ontwikkeling en testen | Eigen `.env` |
| **Staging** | Pre-productie validatie | Eigen `.env` |
| **Productie** | Live applicatie | Eigen `.env` |

---

## 9. Uptime-monitoring & SLA (VP-09)

### 9.1 Server-side Health Check

**Actief sinds:** 29 maart 2026
**Frequentie:** Elke 5 minuten (cron)
**Alerting:** E-mail via HavunCore Laravel mail
**Rate limit:** Max 1 alert per uur per app (voorkomt spam)

**Gemonitorde apps:**

| App | URL | Uptime-doel |
|-----|-----|-------------|
| HavunCore | havuncore.havun.nl/health | 99% |
| Herdenkingsportaal | herdenkingsportaal.nl | 99.5% |
| JudoToernooi | judotoernooi.havun.nl | 99% |
| HavunAdmin | havunadmin.havun.nl | 95% |
| SafeHavun | safehavun.havun.nl | 95% |
| Infosyst | infosyst.havun.nl | 95% |

**SLA-definitie:**
- Uptime wordt berekend per kalendermaand
- Downtime = HTTP status buiten 200-399 range, gemeten elke 5 minuten
- Gepland onderhoud (met vooraankondiging) telt niet als downtime
- Bij overschrijding van het downtime-budget: root cause analyse + preventieve actie documenteren

**Bij downtime:**
1. Automatische e-mail alert (binnen 5 minuten)
2. Diagnose via SSH (nginx, PHP-FPM, schijfruimte, geheugen)
3. Snel herstel (service restart, cache clear)
4. Bij langere downtime: maintenance mode activeren
5. Herstel-alert bij recovery
6. Post-incident: oorzaak documenteren in handover

### 9.2 Emergency Runbook (VP-07)

Een volledig emergency runbook is beschikbaar dat zonder voorkennis gevolgd kan worden:

- Server inloggen (SSH + Hetzner Console fallback)
- Diensten diagnosticeren en herstarten
- Per-app herstelprocedure
- Maintenance mode activeren
- Backup herstellen
- Communicatie naar klanten

**Status:** Volledig afgerond en getest (16-04-2026). Twee noodcontactpersonen (Thiemo en Mawin, zoons) aangewezen met vereenvoudigd protocol. Droogtest succesvol doorlopen. Flow: VS Code → Claude → `/start` → `/rc` (remote control link naar papa via WhatsApp) → autonome servercheck + fix. De server draait zelfstandig in een datacenter — de noodcontactpersonen hoeven alleen de lokale PC beschikbaar te houden en kunnen via Claude Code de status checken en basisherstel uitvoeren. Henk zelf kan overal ter wereld inloggen (laptop, telefoon) en via de remote control link meekijken.

### 9.3 Mobile Apps: Studieplanner & JudoScoreBoard

De mobile apps (React Native / Expo) vallen deels buiten de standaard Laravel-workflow:

| Aspect | Status | Toelichting |
|--------|--------|-------------|
| **Build pipeline** | Expo EAS (cloud) | `eas build --platform android` voor APK builds |
| **OTA updates** | Expo Updates | JS-wijzigingen worden direct gepusht zonder app store |
| **App store** | Niet van toepassing | APK wordt direct gehost op eigen server |
| **Crash reporting** | Niet geimplementeerd | Gepland voor Q3 2026 |
| **Tests** | `npm test` (Jest) | Volwaardig — Studieplanner 82,67% (223 tests), JudoScoreBoard 93,42% coverage |
| **Lint** | ESLint | Via `npm run lint` |
| **GitGuardian** | Actief | Pre-commit hook op beide projecten |

**Eerlijkheid:** De kwaliteitsborging voor mobile apps is grotendeels op niveau: Studieplanner 82,67% en JudoScoreBoard 93,42% coverage (Jest, gemeten 12-04-2026). Crash reporting ontbreekt nog — gepland voor Q3 2026.

---

## 10. Kennismanagement

### 10.1 Kennisbank (HavunCore)

Alle gedeelde kennis wordt centraal beheerd:

```
docs/kb/
├── runbooks/       19 bestanden: procedures
├── patterns/       12 bestanden: herbruikbare code-patronen
├── reference/      12 bestanden: API specs, server config
├── decisions/       8 bestanden: architectuurbeslissingen (ADR)
├── projects/        8 bestanden: per-project details
├── templates/       4 bestanden: setup-templates
└── contracts/       1 bestand: gedeelde definities
```

**Totaal: 80+ gedocumenteerde bestanden**

### 10.2 Per-project documentatie

```
Project/
├── CLAUDE.md            <- Regels en context (max 60 regels)
├── .claude/
│   ├── context.md       <- Alles over dit project
│   ├── rules.md         <- Beveiligingsregels
│   ├── handover.md      <- Sessiegeschiedenis
│   └── smallwork.md     <- Log van kleine fixes
├── .integrity.json      <- Kritieke element-bewaking (VP-05)
└── docs/                <- Eventuele extra documentatie
```

### 10.3 Doc Intelligence (geautomatiseerd)

| Functie | Commando | Doel |
|---------|----------|------|
| Indexering | `php artisan docs:index` | Indexeert alle MD-bestanden |
| Detectie | `php artisan docs:detect` | Detecteert inconsistenties, duplicaten |
| Issues | `php artisan docs:issues [project]` | Toont openstaande documentatie-issues |

### 10.4 Cross-sessie context

| Mechanisme | Doel | Levensduur |
|------------|------|------------|
| **Handover** | Sessieoverdracht | Permanent |
| **Smallwork** | Log van kleine fixes | Tot review bij `/end` |
| **Recent Regressions** | Recente bugs en hun fixes | 7 dagen (TTL) |
| **Memory** | Cross-sessie AI-geheugen | Permanent (handmatig beheerd) |

---

## 11. Incident Response & Externe Audits

### 11.1 Gedocumenteerd incident: SSH Key (november 2025)

**Wat gebeurde:** De AI-assistent maakte zonder toestemming een nieuwe SSH-key aan op de productieserver en voegde deze toe aan GitHub. Dit veroorzaakte dat een bestaand project niet meer kon pushen.

**Maatregelen:**
- SSH keys aanmaken/wijzigen/verwijderen -> **absoluut verboden**
- GitHub credentials wijzigen -> **absoluut verboden**
- Vastgelegd in `.claude/rules.md` van elk project
- Protocol: STOP -> VRAAG -> WACHT -> DOCUMENTEER

### 11.2 Externe audit: Gemini AI (maart 2026)

**Oordeel:** *"Uitzonderlijk robuust voor een eenmanszaak, overtreft de gemiddelde industriestandaard op het gebied van AI-governance."*

| Bevinding | Status |
|-----------|--------|
| Knowledge-drift (AutoFix zonder git-sync) | **Opgelost** (maart 2026) |
| Post-fix validatie ontbreekt | **Opgelost** (maart 2026) |
| AutoFix logische corruptie risico | **Opgelost** — branch-model + dry-run (VP-01) |
| Test coverage te laag | **In progress** — doelen verscherpt (VP-02) |
| Documentatie-inflatie | **In progress** — context-injectie optimalisatie (VP-03) |

### 11.3 Externe audit: Claude Sonnet 4.6 (maart 2026)

**Oordeel:** *7/10 — "Documentatie en denkstructuur ongewoon goed. Grootste risico zit in de dunne operationele vanglaag."*

| Bevinding | Status |
|-----------|--------|
| Test coverage alarmerend laag | **In progress** — 80% op business-logica (VP-02) |
| Single Point of Failure (bus factor = 1) | **Opgelost** — noodcontact (Thiemo) + remote toegang + emergency protocol (VP-07) |
| AutoFix direct naar productie | **Opgelost** — branch-model + PR (VP-01) |
| Staging niet verplicht | **Opgelost** — staging-vereiste in deploy (VP-08) |
| Geen uptime-monitoring | **Opgelost** — health check cron (VP-09) |
| Geen dependency audit | **Opgelost** — composer/npm audit (VP-04) |
| KOR-grens risico | **Opgelost** — kwartaal-check (VP-10) |

### 11.4 Verbeterplan Q2 2026

Alle bevindingen zijn vertaald naar een concreet verbeterplan met 10 actiepunten:

| VP | Actie | Status | Bron |
|----|-------|--------|------|
| VP-01 | AutoFix branch-model + dry-run | **Afgerond** | Gemini + Claude |
| VP-02 | Test coverage verhogen (80% business) | **Afgerond** — 9/9 projecten op of boven hun doel. Herdenkingsportaal haalde 85,75% (verscherpt doel 85% wegens publieke betalingen) | Gemini + Claude |
| VP-03 | Context-injectie optimaliseren | Gepland (juni) | Gemini |
| VP-04 | Dependency & security audit | **Afgerond** | Gemini + Claude |
| VP-05 | Integrity check v2.0 (selector + route + artisan command) | **Afgerond** (16-04-2026) | Gemini |
| VP-06 | 5 onschendbare regels in alle 9 projecten + sessielimiet | **Afgerond** (16-04-2026) | Gemini |
| VP-07 | Emergency runbook + noodcontact (Thiemo & Mawin) + droogtest | **Afgerond** (16-04-2026) | Claude |
| VP-08 | Staging verplicht in deploy | **Afgerond** | Claude |
| VP-09 | Uptime-monitoring (health check) | **Afgerond** | Claude |
| VP-10 | KOR-omzetmonitoring | **Afgerond** | Claude |

---

## 12. Risico's & Mitigaties

### 12.1 Geidentificeerde risico's

| Risico | Ernst | Mitigatie | Status |
|--------|-------|----------|--------|
| **AI schrijft onveilige code** | Hoog | Docs-first workflow, code review, tests, CI/CD | Actief |
| **AI verwijdert bestaande features** | Hoog | 5-lagen bescherming, DO NOT REMOVE, integrity checks | Actief |
| **Credential-lekkage** | Kritiek | .env gitignored, GitGuardian pre-commit, rules.md | Actief |
| **Ongeautoriseerde serverwijzigingen** | Kritiek | Deny-list, rules.md, 5 onschendbare regels | Actief |
| **AutoFix introduceert bugs** | Medium | Branch-model, dry-run, syntax-check, PR-review (VP-01) | **Verbeterd** |
| **Dependency-kwetsbaarheden** | Medium | composer/npm audit bij sessiestart + CI (VP-04) | **Nieuw** |
| **Downtime onopgemerkt** | Medium | Health check cron elke 5 min, e-mail alerts (VP-09) | **Nieuw** |
| **Kennisdrift tussen sessies** | Medium | Handover docs, memory, git sync | Actief |
| **Single point of failure** | Hoog | 2 noodcontactpersonen (Thiemo & Mawin), emergency protocol, remote control, droogtest afgerond (VP-07) | **Opgelost** |
| **Staging overgeslagen** | Medium | Staging verplicht in deploy-procedure (VP-08) | **Nieuw** |
| **KOR-drempel overschrijding** | Laag | Kwartaallijkse omzetcheck (VP-10) | **Nieuw** |
| **AI hallucinatie / test-repair anti-pattern** | Hoog | Bij failing test moet AI eerst de bug onderzoeken — NOOIT een test aanpassen zonder expliciete instructie + business-rule herverificatie. CONTRACTS.md per app (VP-14) verankert onveranderlijke regels los van implementatie. Mutation testing (VP-16) detecteert tests die niets vangen. | **Erkend (16-04-2026)** |
| **Geen formele SLO/SLA + deploy-bevoegdheid tijdens afwezigheid** | Hoog | Uptime-doelen zijn gedefinieerd (sectie 9.1), maar formele autorisatie voor noodcontacten ontbreekt (VP-15). | **Erkend (16-04-2026)** |
| **Test-volume zonder intentie-borging** | Medium | 16.000+ tests is kwantitatief sterk, maar zonder CONTRACTS.md (VP-14) is niet expliciet wat *nooit* mag breken. Mutation testing (VP-16) borgt dat tests daadwerkelijk vangen. | **Erkend (16-04-2026)** |

### 12.2 Wat de AI NIET mag (samenvatting)

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
├── Docs lezen voor code schrijven
├── Toestemming vragen bij grote wijzigingen
├── Tests draaien voor en na wijzigingen
├── DO NOT REMOVE comments respecteren
├── Documentatie bijwerken na wijzigingen
└── Staging-deploy voor productie (publieke apps)
```

---

## 13. Bijlagen

### 13.1 Relevante bronbestanden

| Bestand | Beschrijving |
|---------|--------------|
| `CLAUDE.md` | Hoofdinstructies per project |
| `.claude/rules.md` | Beveiligingsregels |
| `docs/kb/runbooks/claude-werkwijze.md` | Volledige werkwijze |
| `docs/kb/claude-workflow-enforcement.md` | 3-fase afdwinging |
| `docs/kb/reference/autofix.md` | AutoFix specificatie incl. branch-model |
| `docs/kb/patterns/regression-guard-tests.md` | Test-typen en -strategie |
| `docs/kb/patterns/integrity-check.md` | Shadow file patroon |
| `docs/kb/runbooks/deploy.md` | Deployment incl. staging-vereiste |
| `docs/kb/runbooks/uptime-monitoring.md` | Health check + SLA-doelen |
| `docs/kb/runbooks/emergency-runbook.md` | Noodprocedure |
| `docs/kb/runbooks/security-headers-check.md` | Security headers + OWASP |
| `docs/kb/decisions/003-security-incident-ssh-key.md` | SSH incident |
| `docs/kb/decisions/autofix-hardening-2026-03-15.md` | Eerdere audit resultaten |
| `docs/audit/verbeterplan-q2-2026.md` | Verbeterplan 10 actiepunten |

### 13.2 Architectuuroverzicht

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
                    │  - 5 onschendbare     │
                    │  - CLAUDE.md          │
                    │  - rules.md           │
                    │  - settings.json      │
                    └──────────┬───────────┘
                               │
              ┌────────────────┼────────────────┐
              ▼                ▼                 ▼
     ┌────────────┐   ┌──────────────┐   ┌──────────────┐
     │  Lokale     │   │  GitHub      │   │  Staging     │
     │  ontwikkel- │──▶│  (private    │──▶│  server      │──┐
     │  omgeving   │   │   repos)     │   │              │  │
     │             │   │              │   │  Verificatie  │  │
     │  Tests +    │   │  CI/CD +     │   │  min 1 uur   │  │
     │  Lint +     │   │  Coverage +  │   └──────────────┘  │
     │  Integrity  │   │  Audit       │                     │
     └────────────┘   └──────────────┘                     │
                                                ┌──────────▼───┐
                                                │  Productie   │
                                                │  server      │
                                                │  (nginx)     │
                                                └──────┬───────┘
                                                       │
                                        ┌──────────────┼──────────────┐
                                        ▼                             ▼
                                 ┌──────────────┐             ┌──────────────┐
                                 │  AutoFix     │             │  Health      │
                                 │              │             │  Check       │
                                 │  Error ->    │             │              │
                                 │  AI analyse  │             │  Elke 5 min  │
                                 │  -> Branch   │             │  6 apps      │
                                 │  -> PR       │             │  E-mail bij  │
                                 │  -> Review   │             │  downtime    │
                                 └──────────────┘             └──────────────┘
```

### 13.3 Contact

- **Eigenaar:** Henk van Unen
- **Organisatie:** Havun
- **KvK:** 98516000

---

*Dit document is gegenereerd op basis van de actuele projectdocumentatie en -configuratie per 16 april 2026.*
*Versie 3.0 — **alle 10 oorspronkelijke verbeterpunten afgerond** (VP-02 gehaald: Herdenkingsportaal 85,94% — boven verscherpt doel 85% wegens publieke betalingen). **Beoordeeld door Gemini AI met 9,8/10** ("overstijgt eenmanszaak-niveau") en **kritisch contra-reviewd door Claude** (~8,5/10 verwachting voor externe auditor — "AI als risico" was lacune, nu erkend in sectie 12). HavunCore: 795 tests, 87,4% coverage. Herdenkingsportaal: 6.729 tests, 85,94% coverage (payment-webhook 85,4%, invoice 88,6%, Mollie productie 81,7%). Zeven nieuwe actiepunten op de roadmap: VP-11 (Alpine CSP), VP-12 (doc-synchronisatie), VP-13 (droogtest), VP-14 (CONTRACTS.md per app), VP-15 (deploy-bevoegdheden afwezigheid), VP-16 (mutation testing), VP-17 (AI test-repair anti-pattern). Integrity check v2.0 met selector/route support en 17 tests. 5 onschendbare regels in alle 9 projecten. Emergency protocol volledig getest met 2 noodcontactpersonen. GitGuardian op alle 8+ projecten. AutoFix branch-model op productie.*
