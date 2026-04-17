# Verbeterplan Q2 2026 — Havun Software Development

> **Status (17-04-2026):** Cadans omgezet van kwartaal-deadlines naar **wekelijks
> incrementeel fixen**. Dit document blijft als **archief** van wat opgepakt en
> opgeleverd is — niet meer als deadline-roadmap. Geen Q3-audit-druk; punten die
> nog open staan worden opgepakt wanneer relevant.

**Gebaseerd op:** Externe beoordelingen Gemini AI + Claude Sonnet 4.6 (29 maart 2026)
**Versie:** 2.0
**Datum:** 29 maart 2026
**Eigenaar:** Henk van Unen
**Cadans:** wekelijks (was: kwartaal)

---

## Samenvatting externe beoordelingen

### Gemini AI

**Oordeel:** *"Uitzonderlijk robuust voor een eenmanszaak, overtreft de gemiddelde industriestandaard op het gebied van AI-governance."*

### Claude Sonnet 4.6

**Oordeel:** *7/10 — "Documentatie en denkstructuur ongewoon goed voor een soloproject. Grootste risico zit in de dunne operationele vanglaag."*

### Gecombineerde risico's (gerangschikt op ernst)

| # | Risico | Gemini | Claude | Ernst | Kernprobleem |
|---|--------|--------|--------|-------|--------------|
| 1 | Test coverage te laag | Medium | **Kritiek** | **Kritiek** | 20 tests voor 8 apps, coverage 30-60% is onvoldoende |
| 2 | Single Point of Failure | — | **Kritiek** | **Kritiek** | Bus factor = 1, geen backup-persoon, geen emergency runbook |
| 3 | AutoFix schrijft direct naar productie | Hoog | **Hoog** | **Hoog** | Geen branch-model, geen review vóór deploy |
| 4 | Staging niet verplicht in deploy | — | **Hoog** | **Hoog** | Lokaal → productie zonder staging-tussenstation |
| 5 | Geen uptime-monitoring/SLA | — | **Medium** | **Medium** | Geen monitoring, geen gedefinieerde downtime-doelen |
| 6 | Dependency-veroudering & security | Laag | **Medium** | **Medium** | Geen structureel plan voor updates en OWASP |
| 7 | Documentatie-inflatie | Medium | — | Medium | 80+ bestanden, AI kan relevante regels missen |
| 8 | Protocolmoeheid | Medium | — | Medium | AI kan bij lange sessies stappen overslaan |
| 9 | Integrity Check beperkt | Medium | — | Medium | Alleen tekst-matching, geen CSS/UI-element checks |
| 10 | KOR-grens bedrijfsrisico | — | **Laag** | **Laag** | Geen omzetmonitoring richting BTW-drempel |

---

## Verbeteracties

---

### VP-01: AutoFix Branch-Model + Dry-Run

**Risico:** Hoog → Kritiek (opgeschaald na Claude-beoordeling)
**Bron:** Gemini — *"functioneel destructieve code"* + Claude — *"universeel afgeraden in de industrie"*
**Deadline:** 30 april 2026

**Probleem:**
AutoFix schrijft AI-gegenereerde code rechtstreeks naar productie. Ondanks 8 safety-checks is dit een patroon dat in de professionele industrie vrijwel universeel wordt afgeraden. Syntax-check (`php -l`) vangt geen logische fouten.

**Oplossingsrichting (Claude):**
AutoFix schrijft naar een `hotfix/autofix-[timestamp]` branch + automatische PR, in plaats van direct naar main. Dat geeft de eigenaar één-klik review.

**Acties:**

| # | Actie | Type | Effort |
|---|-------|------|--------|
| 1.1 | AutoFix schrijft naar `hotfix/autofix-{timestamp}` branch i.p.v. direct main | Code | Medium |
| 1.2 | Automatische PR aanmaken via GitHub API na succesvolle fix | Code | Medium |
| 1.3 | Bij `RISK: medium/high` → dry-run (alleen notificatie, geen fix) | Code | Laag |
| 1.4 | Bij `RISK: low` → branch + PR (geen directe productie-push) | Code | Laag |
| 1.5 | Database-state snapshot vóór fix (relevante tabelrijen loggen) | Code | Medium |
| 1.6 | Review-URL (`/autofix/{token}`) verplicht voor alle fixes | Code | Laag |

**Acceptatiecriteria:**
- [ ] AutoFix pusht NOOIT meer direct naar main/master
- [ ] Elke fix resulteert in een branch + PR
- [ ] `RISK: medium/high` krijgt alleen dry-run (notificatie)
- [ ] `RISK: low` krijgt branch + PR (eigenaar mergt met één klik)
- [ ] Database-state wordt gelogd vóór elke fix
- [ ] Alle fixes zijn zichtbaar via review-URL

**Configuratie (voorstel):**
```php
// config/autofix.php
'branch_model' => true,                    // Branch + PR i.p.v. direct push
'branch_prefix' => 'hotfix/autofix-',      // Branch naamgeving
'auto_pr' => true,                         // Automatische PR via GitHub API
'dry_run_on_risk' => ['medium', 'high'],   // Alleen notificatie
'snapshot_enabled' => true,                 // Database-state logging
'snapshot_max_rows' => 50,                  // Max rijen per tabel
```

---

### VP-02: Test Coverage Drastisch Verhogen + Feature Freeze

**Risico:** Kritiek (opgeschaald na Claude-beoordeling)
**Bron:** Gemini — *"Linter-Gate niet afgedwongen"* + Claude — *"20 tests voor 8 apps is bijna niets, alle andere protocollen zijn een illusie van veiligheid"*
**Deadline:** 31 mei 2026

**Probleem:**
20 tests in HavunCore voor een platform met 8 apps is alarmerend laag. Coverage-doelen van 30-60% zijn bescheiden. Zolang dit niet gehaald wordt, zijn alle andere protocollen een illusie van veiligheid.

**Verscherping t.o.v. v1.0:**
- Claude adviseert **80% op kritieke business-logica**
- Feature freeze totdat coverage op orde is

**Acties:**

| # | Actie | Type | Effort |
|---|-------|------|--------|
| 2.1 | GitHub Actions: tests MOETEN slagen bij elke push | CI/CD | Laag |
| 2.2 | Branch protection op main/master: merge alleen als checks slagen | GitHub | Laag |
| 2.3 | Coverage-doelen verscherpen (zie tabel) | Proces | Doorlopend |
| 2.4 | Coverage-rapport in GitHub Actions output | CI/CD | Laag |
| 2.5 | **Feature freeze** voor JudoToernooi tot 75% coverage bereikt | Proces | — |
| 2.6 | Kritieke business-logica identificeren per project (80% doel) | Docs | Medium |

**Coverage-doelen (verscherpt na Claude-review):**

| Project | Was (v1.0) | Was (Gemini) | Wordt (v2.0) | Actueel (16-04-2026) | Kritieke logica | Status |
|---------|------------|--------------|--------------|----------------------|-----------------|--------|
| SafeHavun | 30% | 40% | **80%** | 302 tests / 565 assertions — **94.22% coverage** | Models, Portfolio, MarketSignal | **GEHAALD** |
| Infosyst | 30% | 40% | **80%** | 834 tests / 1552 assertions — **91.51% coverage** | Models, WikiLink, Routes | **GEHAALD** |
| HavunVet | 30% | 40% | **80%** | 276 tests / 442 assertions — **90.87% coverage** | Models, Services, Routes | **GEHAALD** |
| JudoToernooi | 60% | 75% | **80%** | 3257 tests / 6789 assertions — **89.84% coverage** | Poule-indeling, gewichtscheck, scoring | **GEHAALD** |
| HavunAdmin | 30% | 40% | **80%** | 3180 tests / 5059 assertions — **89.75% coverage** | Invoices, BTW, Tenant isolatie | **GEHAALD** |
| HavunCore | 40% | 50% | **80%** | 795 tests / 2012 assertions — **87.4% coverage** | AI Proxy, Task Queue, Vault, Auth | **GEHAALD** |
| Herdenkingsportaal | 50% | 60% | **85%** (verscherpt: publieke betalingen) | 6.729 tests / 10.530 assertions — **85,94% coverage** | Betalingen (webhook 85,4%, invoice 88,6%, Mollie 81,7%), memorial CRUD, publieke views | **GEHAALD** |
| Studieplanner | 30% | 40% | **80%** | 223 tests (Jest) — **82,67% coverage** | Study flow, sync, notifications | **GEHAALD** |
| JudoScoreBoard | 30% | 40% | **80%** | (Jest) — **93,42% coverage** (1.140 statements) | Scoreboard, timing, mat assignment | **GEHAALD** |
| HavunClub | 30% | 40% | **80%** | 0 tests (alleen docs, geen app code) | — | N.v.t. |

> **Meting 16-04-2026 (PCOV + Jest):** alle 9 projecten hebben hun coverage-doel gehaald. Herdenkingsportaal: **85,75%** — boven het verscherpte 85%-doel voor projecten met publieke betalingen. Totaal testportfolio ~**16.000+ tests** met 28.000+ assertions. Laravel metingen via PCOV (15/16-04-2026), React Native via Jest (12-04-2026).

**Bugs gevonden door test coverage uitbreiding (06-04-2026):**
- HavunVet: `TreatmentForm` verwijst naar verwijderd `WorkLocation` model
- HavunVet: `Owner::treatments()` declareert `HasMany` maar retourneert `HasManyThrough`
- Infosyst: Source enum types in migrations matchen niet met model code
- HavunAdmin: `TransactionMatchingService::findAndLinkDuplicates()` crasht op `->fresh()` call op base Collection

**Acceptatiecriteria:**
- [ ] Push naar main/master faalt als tests niet slagen
- [ ] Coverage-percentage zichtbaar in GitHub Actions
- [ ] JudoToernooi: 75% totaal, 80% op business-logica
- [ ] Alle projecten hebben werkende GitHub Actions workflow
- [ ] Kritieke business-logica per project geïdentificeerd en gedocumenteerd

---

### VP-03: Context-Injectie Optimaliseren

**Risico:** Medium
**Bron:** Gemini — *"Documentatie-inflatie"*
**Deadline:** 30 juni 2026

**Probleem:**
80+ kennisbank-bestanden, AI kan relevante regels missen door overvloed aan context.

**Acties:**

| # | Actie | Type | Effort |
|---|-------|------|--------|
| 3.1 | `docs:search` optimaliseren met relevantie-scoring | Code | Medium |
| 3.2 | Per-project "essentiële docs" lijst in context.md | Docs | Laag |
| 3.3 | CLAUDE.md per project max 60 regels (handhaven) | Proces | Doorlopend |
| 3.4 | Evalueer vector-based search via Ollama embeddings | Research | Hoog |

**Acceptatiecriteria:**
- [ ] Elk project heeft "essentiële docs" sectie in context.md
- [ ] `docs:search` toont relevantie-score
- [ ] CLAUDE.md bestanden max 60 regels (audit)
- [ ] Evaluatierapport vector-search beschikbaar

---

### VP-04: Dependency & Security Audit Structureren

**Risico:** Medium (opgeschaald na Claude-beoordeling)
**Bron:** Gemini — *"Geen automatische security audit"* + Claude — *"Geen OWASP-toets op publieke applicaties"*
**Deadline:** 30 april 2026 (basis) / doorlopend

**Probleem:**
Geen structureel plan voor dependency updates, security headers, of OWASP-toetsing. Herdenkingsportaal heeft publiek verkeer en is daarmee het meest kwetsbaar.

**Acties:**

| # | Actie | Type | Effort | Frequentie |
|---|-------|------|--------|------------|
| 4.1 | `composer audit` + `npm audit` in `/start` command | Command | Zeer laag | Elke sessie |
| 4.2 | `composer audit` in GitHub Actions | CI/CD | Laag | Elke push |
| 4.3 | `composer outdated` + `npm outdated` maandelijks draaien | Proces | Laag | Maandelijks |
| 4.4 | OWASP ZAP scan op Herdenkingsportaal (publiek verkeer) | Security | Medium | Jaarlijks |
| 4.5 | Security headers check (CSP, HSTS, X-Frame-Options) | Security | Laag | Kwartaal |
| 4.6 | Kwartaal-review van dependencies plannen | Proces | Laag | Kwartaal |

**Security headers checklist (nieuw):**
```
[ ] Content-Security-Policy
[ ] Strict-Transport-Security (HSTS)
[ ] X-Content-Type-Options: nosniff
[ ] X-Frame-Options: DENY / SAMEORIGIN
[ ] Referrer-Policy
[ ] Permissions-Policy
```

**Acceptatiecriteria:**
- [ ] `/start` toont security audit resultaat
- [ ] GitHub Actions blokkeert bij kritieke kwetsbaarheden
- [ ] Security headers geconfigureerd op alle publieke apps
- [ ] Eerste OWASP ZAP scan uitgevoerd op Herdenkingsportaal
- [ ] Maandelijkse outdated-check ingepland

---

### VP-05: Integrity Check Uitbreiden

**Risico:** Medium
**Bron:** Gemini — *"Alleen tekst-matching, geen CSS/UI-element checks"*
**Deadline:** 31 mei 2026

**Probleem:**
`.integrity.json` controleert alleen tekst-strings. CSS-selectors en kritieke UI-elementen worden niet bewaakt.

**Acties:**

| # | Actie | Type | Effort |
|---|-------|------|--------|
| 5.1 | `must_contain_selector` toevoegen aan integrity-check schema | Code | Medium |
| 5.2 | CSS-class controle voor kritieke knoppen | Code | Laag |
| 5.3 | Route-existence check toevoegen | Code | Medium |

**Acceptatiecriteria:**
- [x] `.integrity.json` ondersteunt `must_contain_selector` (v2.0 schema, 15-04-2026)
- [x] Minimaal 1 project heeft CSS/selector checks actief (HavunCore, 15-04-2026)
- [x] Integrity-check script bijgewerkt (artisan command met selector+route+json support, 15-04-2026)

---

### VP-06: Protocolmoeheid Mitigeren

**Risico:** Medium
**Bron:** Gemini — *"AI kan bij lange sessies de hiërarchie negeren"*
**Deadline:** 30 april 2026

**Acties:**

| # | Actie | Type | Effort |
|---|-------|------|--------|
| 6.1 | Sessielimiet-advies (max 2-3 uur) documenteren | Docs | Zeer laag |
| 6.2 | `/update` mid-sessie check versterken | Command | Laag |
| 6.3 | Max 5 "onschendbare" regels per project | Docs | Laag |

**De 5 onschendbare regels:**
```
1. NOOIT code schrijven zonder docs te lezen
2. NOOIT features/UI-elementen verwijderen zonder instructie
3. NOOIT credentials/keys/env aanraken
4. ALTIJD tests draaien voor én na wijzigingen
5. ALTIJD toestemming vragen bij grote wijzigingen
```

**Acceptatiecriteria:**
- [x] Sessielimiet gedocumenteerd (claude-werkwijze.md + /update command)
- [x] `/update` herinnert aan 5 onschendbare regels (Stap 0 in update.md)
- [x] Elk project heeft max 5 kernregels bovenaan CLAUDE.md (15-04-2026, HavunCore toegevoegd)

---

### VP-07: Emergency Runbook & Backup-Persoon (NIEUW)

**Risico:** Kritiek
**Bron:** Claude — *"Bus factor = 1. Als jij uitvalt staan klanten van live producties vast. Documentatie is geen mitigatie."*
**Deadline:** 30 april 2026

**Probleem:**
Er is geen enkele persoon die bij calamiteiten (ziekte, ongeluk) de live applicaties kan benaderen of herstarten. "Uitgebreide documentatie" is geen echte mitigatie als niemand anders de documentatie kan gebruiken.

**Acties:**

| # | Actie | Type | Effort |
|---|-------|------|--------|
| 7.1 | Emergency runbook schrijven (zonder voorkennis te volgen) | Docs | Medium |
| 7.2 | Minimaal 1 vertrouwde technische contactpersoon aanwijzen | Organisatie | — |
| 7.3 | Toegang regelen: nood-SSH key of noodtoegang via Hetzner console | Infra | Laag |
| 7.4 | Emergency runbook testen met de contactpersoon | Validatie | Medium |

**Emergency runbook moet bevatten:**

```
1. Hoe log je in op de server (IP, credentials, SSH)
2. Hoe herstart je een applicatie (per project: exact commando)
3. Hoe herstel je een backup (stap-voor-stap)
4. Hoe zet je de site in maintenance mode
5. Wie zijn de klanten en hoe bereik je ze
6. Welke services zijn kritiek (betaalverkeer, publieke sites)
7. Contactgegevens hosting provider (Hetzner)
```

**Acceptatiecriteria:**
- [x] Emergency runbook geschreven (docs/kb/runbooks/emergency-runbook.md)
- [x] Emergency runbook getest (droogtest met Thiemo, 16-04-2026)
- [x] Minimaal 1 technische contactpersoon aangewezen (Thiemo)
- [x] Contactpersoon heeft (verzegelde) toegangsgegevens (16-04-2026)
- [x] Contactpersoon heeft het runbook succesvol doorlopen (droogtest 16-04-2026)

---

### VP-08: Staging Verplicht in Deploy-Procedure (NIEUW)

**Risico:** Hoog
**Bron:** Claude — *"Deployment gaat rechtstreeks van lokaal naar productie via git pull"*
**Deadline:** 31 mei 2026

**Probleem:**
De deploy-procedure beschrijft geen verplichte staging-test vóór productie-deploy. Er wordt direct van lokaal naar productie gegaan via `git pull`.

**Acties:**

| # | Actie | Type | Effort |
|---|-------|------|--------|
| 8.1 | Deploy-procedure aanpassen: staging verplicht vóór productie | Docs | Laag |
| 8.2 | Staging-omgeving inrichten voor projecten die dat nog missen | Infra | Medium |
| 8.3 | Minimale wachttijd op staging definiëren (bijv. 24u, of 1u + handmatige goedkeuring) | Proces | Laag |
| 8.4 | `/end` command aanpassen: staging-deploy als stap vóór productie | Command | Laag |

**Nieuwe deploy-procedure:**

```
1. Lokaal: tests draaien (php artisan test)
2. Lokaal: git commit + push
3. STAGING: git pull + migrate + cache clear
4. STAGING: handmatige verificatie (minimaal 1 uur, bij grote wijzigingen 24u)
5. PRODUCTIE: git pull + migrate + cache clear
6. PRODUCTIE: verificatie + logs controleren
```

**Uitzonderingen (geen staging vereist):**
- Alleen documentatie-wijzigingen
- Hotfixes voor productie-kritieke bugs (met extra review)

**Acceptatiecriteria:**
- [ ] Deploy-procedure in docs bijgewerkt met staging-vereiste
- [ ] Alle projecten met publiek verkeer hebben een staging-omgeving
- [ ] `/end` command bevat staging-stap
- [ ] Minimale wachttijd gedefinieerd

---

### VP-09: Uptime-Monitoring & SLA (NIEUW)

**Risico:** Medium
**Bron:** Claude — *"Geen uptime-doelen, monitoring-tooling, of downtime-procedure beschreven"*
**Deadline:** 30 april 2026

**Probleem:**
Voor betalende klanten (Herdenkingsportaal, JudoToernooi) is er geen monitoring, geen uptime-doel, en geen gedefinieerde procedure bij downtime.

**Acties:**

| # | Actie | Type | Effort |
|---|-------|------|--------|
| 9.1 | UptimeRobot (gratis tier) instellen voor alle publieke apps | Infra | Zeer laag |
| 9.2 | Uptime-doelen definiëren per app | Docs | Laag |
| 9.3 | Downtime-procedure documenteren (wie, wat, wanneer) | Docs | Laag |
| 9.4 | Alerting instellen (e-mail + evt. SMS bij kritieke apps) | Infra | Laag |

**Uptime-doelen (voorstel):**

| App | Uptime-doel | Kritiek? | Toelichting |
|-----|-------------|----------|-------------|
| Herdenkingsportaal | 99.5% (~43u downtime/jaar) | Ja | Publiek verkeer, betalingen |
| JudoToernooi | 99% (~87u downtime/jaar) | Ja | Actief tijdens toernooien |
| HavunCore | 99% | Ja | Andere apps zijn afhankelijk |
| HavunAdmin | 95% | Nee | Intern beheer |
| Overige | 95% | Nee | Beperkt gebruik |

**Acceptatiecriteria:**
- [ ] UptimeRobot actief voor alle publieke apps
- [ ] Uptime-doelen gedocumenteerd
- [ ] E-mail alerting werkt (getest)
- [ ] Downtime-procedure beschreven

---

### VP-10: KOR-Omzetmonitoring (NIEUW)

**Risico:** Laag
**Bron:** Claude — *"Bij groei over de KOR-drempel val je plotseling in het reguliere BTW-stelsel"*
**Deadline:** 30 juni 2026

**Probleem:**
Bij overschrijding van de KOR-grens (€20.000/jaar omzet) vervalt de BTW-vrijstelling. Geen monitoring hierop.

**Acties:**

| # | Actie | Type | Effort |
|---|-------|------|--------|
| 10.1 | Kwartaallijkse omzetcheck inplannen | Proces | Zeer laag |
| 10.2 | BTW-overgangsplan klaarzetten (administratie, facturatie-aanpassing) | Docs | Laag |
| 10.3 | Drempelwaarschuwing bij 80% van KOR-grens (€16.000) | Proces | Zeer laag |

**Acceptatiecriteria:**
- [ ] Kwartaal-omzetcheck ingepland (Q2 = eerste check)
- [ ] BTW-overgangsplan beschikbaar
- [ ] Drempelwaarschuwing gedefinieerd

---

## Planning overzicht

```
April 2026 — Focus: Kritieke risico's + quick wins
├── VP-01: AutoFix Branch-Model + Dry-Run             [KRITIEK]
├── VP-07: Emergency Runbook & Backup-Persoon          [KRITIEK]
├── VP-09: Uptime-Monitoring & SLA                     [MEDIUM - snel klaar]
├── VP-04: Dependency & Security Audit                 [MEDIUM - snel klaar]
└── VP-06: Protocolmoeheid Mitigeren                   [MEDIUM - snel klaar]

Mei 2026 — Focus: Kwaliteit & deploy
├── VP-02: Test Coverage + Feature Freeze + CI         [KRITIEK]
├── VP-08: Staging Verplicht in Deploy                 [HOOG]
└── VP-05: Integrity Check Uitbreiden                  [MEDIUM]

Juni 2026 — Focus: Optimalisatie & bedrijf
├── VP-03: Context-Injectie Optimaliseren              [MEDIUM]
└── VP-10: KOR-Omzetmonitoring                         [LAAG]

Juli 2026
└── Q3 Audit: voortgang meten + nieuw rapport
```

---

## Tracking

| ID | Actie | Status | Prioriteit | Deadline | Bron |
|----|-------|--------|------------|----------|------|
| VP-01 | AutoFix Branch-Model + Dry-Run | DONE — `config/autofix.php` (branch_model, dry_run_on_risk, snapshot_enabled), `AutoFixService::resolveDeliveryMode()` zet proposal-status op `dry_run` (medium/high) of `branch_pending` (low) met `delivery_mode` + `branch_name` in context. 5 tests in `tests/Feature/AutoFixDeliveryModeTest.php` groen. Executor-contract gedocumenteerd in `docs/kb/runbooks/autofix-branch-model.md` (17-04-2026) | Kritiek | 30-04-2026 | Gemini + Claude |
| VP-02 | Test Coverage + Feature Freeze | DONE — alle 9 projecten op of boven hun doel. HP 85,94% (verscherpt 85% voor publieke betalingen); binnen HP payment-webhook 85,4%, invoice 88,6%, Mollie productie 81,7% | Kritiek | 31-05-2026 | Gemini + Claude |
| VP-03 | Context-Injectie | DONE — (3.1) relevance-scoring `Relevance: X%` al actief in `docs:search`. (3.2) Essentiële-docs sectie in `.claude/context.md` (10 kernbestanden). (3.3) `php artisan docs:audit-claude-md` command live; ALLE 9 projecten gesnoeid naar <60 regels (HavunCore 147→44, HavunAdmin 128→36, Herdenkingsportaal 212→53, JudoToernooi 352→58, SafeHavun 117→45, Studieplanner 133→43, infosyst 143→37, HavunVet 82→41, JudoScoreBoard 262→59) — verbose how-to en quick-reference verhuisd naar `.claude/context.md` of relevante runbooks. (3.4) Vector-search evaluatierapport `docs/kb/decisions/vector-search-evaluatie.md` — besluit NIET in Q2, Q4-heroverweeg trigger gedocumenteerd (17-04-2026) | Medium | 30-06-2026 | Gemini |
| VP-04 | Dependency & Security Audit | DONE (CI + /start) | Medium | 30-04-2026 | Gemini + Claude |
| VP-05 | Integrity Uitbreiden | DONE (v2.0: selector+route checks, artisan command, HavunCore actief) | Medium | 31-05-2026 | Gemini |
| VP-06 | Protocolmoeheid | DONE (5 regels in ALLE 9 projecten, /update, sessielimiet) | Medium | 30-04-2026 | Gemini |
| VP-07 | Emergency Runbook | DONE (runbook+contact+credentials+droogtest afgerond 16-04-2026) | Kritiek | 30-04-2026 | Claude |
| VP-08 | Staging Verplicht | DONE | Hoog | 31-05-2026 | Claude |
| VP-09 | Uptime-Monitoring | DONE | Medium | 30-04-2026 | Claude |
| VP-10 | KOR-Omzetmonitoring | DONE | Laag | 30-06-2026 | Claude |
| VP-11 | Alpine.js CSP migratie (Herdenkingsportaal) | READY-FOR-STAGING (regression-vrij) — feature branch `feat/vp11-alpine-csp-migration` (HP repo) is functioneel compleet. Alle inline `x-data` (17) + alle function-call `x-data="fn()"` (9) + alle inline `@click="X = Y"` assignments → Alpine.data() componenten in `resources/js/alpine-components.js`. Inline `<script>`-blokken met Alpine functies geëxtraheerd uit show.blade.php, display.blade.php, guestbook/photo-upload.blade.php, guestbook/register.blade.php. CDN Alpine include weggehaald uit 5 views. SecurityHeaders middleware: `'unsafe-eval'` weg uit CSP voor alle reguliere routes (alleen Fabric.js editDesign-route houdt het). **Volledige PHPUnit-suite gedraaid: 6.726/6.739 groen, 0 errors, 0 nieuwe regressies** (de 3 failures zijn pre-existing verify_bunq_payments command tests, ook in CI rood). Wacht op browser-rooktest op staging vóór merge | Medium | 30-06-2026 | Claude |
| VP-12 | Documentatie-synchronisatie check bij `/end` | DONE — `.claude/commands/end.md` heeft nieuwe stap 6 "Doc-Sync Check" met procedure, beslismatrix, anti-pattern + klaar-criteria | Hoog | 31-05-2026 | Gemini (v3.0) |
| VP-13 | Periodieke noodprotocol-droogtest | DONE — schema + roster doc, Laravel command + Mailable + Blade view, scheduler, 5 tests; eerste reminder gaat 12-07-2026 automatisch uit | Medium | 31-07-2026 | Gemini (v3.0) |
| VP-14 | CONTRACTS.md per app | DONE — alle 9 projecten hebben CONTRACTS.md (HavunCore, HP, JT, HavunAdmin, SafeHavun, Infosyst, HavunVet, Studieplanner, JSB) met elk 10 onveranderlijke regels. Centrale template in `docs/kb/patterns/contracts-md-template.md`. CLAUDE.md per project verwijst naar CONTRACTS.md | Hoog | 30-06-2026 | Claude (v3.0 contra-review) |
| VP-15 | Formele deploy-bevoegdheden tijdens afwezigheid | DONE — `docs/kb/runbooks/wat-mag-noodcontact.md` met 3 scenario's (A/B/C), commando-matrix (mag wel/niet), SSH-cheatsheet, cross-link in `noodcontactpersoon-protocol.md` | Hoog | 31-05-2026 | Claude (v3.0 contra-review) |
| VP-16 | Kwartaalse mutation testing | DONE — Infection PHP 0.32.6 geïnstalleerd. `infection.json5` scope=`app/Services` (excl. Chaos+DocIntelligence). GitHub Action `.github/workflows/mutation-test.yml` (cron 1e jan/apr/jul/okt 03:00 UTC + handmatig). **Eerste baseline gedraaid 17-04-2026: 679 mutaties / 363 killed / 312 escaped → MSI 53,78%, Covered MSI 53%.** Drempel pragmatisch op minMsi=48 gezet (5pp onder baseline) — verhogen per kwartaal richting 75%. Hot-spots gedocumenteerd in `docs/kb/reference/mutation-baseline-2026-04-17.md` (ObservabilityService disk-bytes, QrAuthService device-update, AutoFixService config-defaults) | Medium | 30-09-2026 | Claude (v3.0 contra-review) |
| VP-17 | AI test-repair anti-pattern review-regel | DONE — CLAUDE.md regel 6 actief + runbook `docs/kb/runbooks/test-repair-anti-pattern.md` met 3 worked examples (code-bug, verouderde test, verouderde doc) | Hoog | 30-04-2026 | Claude (v3.0 contra-review) |
| VP-18 | Alpine.js CSP migratie (JudoToernooi) | DONE-PENDING-SMOKE — 22 batches + 28 follow-up fixes in `feat/vp18-alpine-csp-migration`. Runbook `docs/kb/runbooks/alpine-csp-migratie.md` (9 conversie-patronen, herbruikbaar voor toekomstige Havun projecten). Alle 17 aktieve views omgezet, shared Alpine.data() componenten in plaats. Offline/index bewust overgeslagen (eigen CDN-alpine). **`'unsafe-eval'` is verwijderd uit SecurityHeaders middleware** (commit `5197c995`, 17-04-2026) + regressietest `SecurityHeadersTest::csp_does_not_contain_unsafe_eval_in_non_local_env`. Vite build groen. Resterend: end-to-end smoke test in browser (publiek-view + mat interface brackets) — vereist live browser-sessie, kan niet automatisch | Medium | 31-07-2026 | Claude (Mozilla Observatory) |

---

## Afgewezen voorstellen (met motivatie)

| Voorstel | Bron | Reden afwijzing |
|----------|------|----------------|
| **Docker Environment Isolation** | Gemini | Overkill voor huidige schaal (1 server, 1 ontwikkelaar). Heroverweeg bij teamuitbreiding. |
| **Vector-based Context Injector (volledig)** | Gemini | Te ambitieus voor Q2. Evaluatie (VP-03.4) ingepland. |
| **Volledige test-suite na AutoFix** | Gemini + Claude | Branch-model (VP-01) maakt dit overbodig — review vóór merge vervangt post-fix test-suite. |
| **Feature freeze álle projecten** | Claude (impliciet) | Disproportioneel. Feature freeze alleen voor JudoToernooi (meest actief + laagste coverage). |

---

## Vergelijking beoordelingen

| Onderwerp | Gemini | Claude Sonnet | Verschil |
|-----------|--------|---------------|----------|
| **Algemeen oordeel** | Zeer positief | 7/10, positief-kritisch | Claude strenger |
| **Test coverage** | Medium risico | Kritiek risico | Claude significant strenger |
| **AutoFix** | Dry-run toevoegen | Branch-model verplicht | Claude gaat verder |
| **Single Point of Failure** | Niet benoemd | Kritiek | Alleen Claude benoemt dit |
| **Staging** | Niet benoemd | Hoog risico | Alleen Claude benoemt dit |
| **Uptime/SLA** | Niet benoemd | Medium risico | Alleen Claude benoemt dit |
| **KOR-risico** | Niet benoemd | Laag risico | Alleen Claude benoemt dit |
| **Documentatie** | Inflatie-risico | Positief maar "papier is geduldig" | Vergelijkbaar |
| **Dependencies** | Laag risico | Medium + OWASP | Claude breder |
| **Protocolmoeheid** | Benoemd | Niet separaat | Alleen Gemini benoemt dit |

---

## Verantwoording

Dit verbeterplan wordt elk kwartaal geëvalueerd samen met het beoordelingsdocument (`werkwijze-beoordeling-derden.md`).

Bij de volgende audit (Q3 2026) wordt gemeten:
- Welke verbeterpunten zijn afgerond
- Actuele test-coverage per project
- Aantal incidenten sinds vorige audit
- AutoFix: hoeveel fixes via branch-model, hoeveel handmatig gemerged
- Uptime-cijfers per app
- Of prioriteiten moeten worden bijgesteld

---

*v1.0 — Opgesteld op basis van Gemini AI beoordeling, 29 maart 2026*
*v2.0 — Aangevuld met Claude Sonnet 4.6 beoordeling, 29 maart 2026*
