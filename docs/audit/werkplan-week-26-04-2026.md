---
title: Werkplan week 26 april — 2 mei 2026
type: planning
scope: havuncore
last_check: 2026-04-25
---

# Werkplan komende week (26 april — 2 mei 2026)

> Vier concrete actiepunten naar aanleiding van de zelf-reflectie 25 april:
> *"gaan de werkzaamheden wel zinvol of dweilen we telkens met de kraan open?"*
>
> Doel: van **wekelijks tweaken** naar **bewuste keuzes met grenzen**.

---

## Actiepunt 1 — Stop SSL/Mozilla score-jacht boven A

**Wat:** Vastleggen als beleid dat extern testresultaat **A** voldoende is. Alleen onder A = fixen verplicht. Boven A = alleen bij aantoonbaar risico.

**Deliverable:**
- `docs/kb/decisions/security-score-policy.md` met:
  - **Drempel:** A = ondergrens, alles daarboven is optioneel
  - **Welke tests:** SSL Labs, SecurityHeaders.com, Mozilla Observatory
  - **Wanneer wél boven A pushen:** alleen bij CVE-publicatie, security-incident, of compliance-eis (klant)
  - **Wanneer NIET:** "gewoon nog beter willen" — dat heet score-perfectionisme
- `productie-deploy-eisen.md` aanpassen: van *"A+ / 100 / 100 / 100 / 100"* naar *"≥ A acceptabel"*
- Verwijzing in `werkwijze-beoordeling-derden.md` sectie 4

**Acceptatiecriterium:** een externe auditor leest het beleid en weet wanneer er WEL en NIET geknepen wordt aan security-headers.

**Schatting:** 30 min schrijfwerk + 15 min review eisen-doc.

---

## Actiepunt 2 — Coverage drempel = 70% baseline

**Wat:** Coverage-target verlagen van 80% naar **70% baseline**. Boven 70% alleen via **mutation testing op 5-10 kritieke paden**, niet via blanket-coverage.

**Deliverable:**
- `docs/kb/reference/test-quality-policy.md` updaten:
  - Drempel **70%** lines (was 80%)
  - **Mutation MSI ≥95%** op kritieke paden (al ingevoerd, formaliseren)
  - **Anti-padding regel:** `assertTrue(true)`, `assertNotNull(new X)`, `class_exists(X)` zijn FORBIDDEN — verplicht detectie via `qv:scan`
  - Top-down lijst van **wat is een kritiek pad?** (auth, payment, data-integrity, security-headers, AutoFix)
- CI-drempels per project naar 70% verlagen
- `docs/kb/decisions/coverage-policy-70-percent.md` met rationale (verwijzing naar 591 padding-tests cleanup)

**Acceptatiecriterium:** geen nieuwe tests die alleen coverage halen zonder gedrag te checken — `qv:scan --only=test-erosion` valideert.

**Schatting:** 1 uur — beleid + CI-yaml updaten in 9 projecten via parallel-agent.

---

## Actiepunt 3 — Security-marathon = kwartaal, geen week

**Wat:** Cross-portfolio security-werk **bundelen per kwartaal**. Geen wekelijkse "nog een laagje". Tussen marathons alleen reactief (CVE/incident).

**Deliverable:**
- `docs/kb/decisions/security-cadans-kwartaalmarathon.md`:
  - Marathon 1× per kwartaal, vaste week (eerste week van Q1/Q2/Q3/Q4)
  - **Scope per marathon:** vooraf vastgelegd (max 5 onderwerpen)
  - **Tussendoor:** alleen CVE-patches, security-incidents, klant-eisen
  - **Geen score-jacht:** zie ook actiepunt 1
- `docs/kb/runbooks/security-marathon-kwartaal.md` — stappenplan voor elke marathon (1 dag voorbereiding + 2 dagen werk + 1 dag verificatie)
- Eerste geplande marathon: **eerste week juli 2026** (Q3-start)
- Verbeterplan-doc: open security-items niet meer per week schedulen, maar parkeren tot juli

**Acceptatiecriterium:** als ik over 3 weken denk "laat ik nog wat aan SSL doen" → ik weet dat het tot juli wacht (tenzij CVE).

**Schatting:** 30 min beleid + 30 min verbeterplan-prioriteit aanpassen.

---

## Actiepunt 4 — Rollback-discipline: ADR met "waarom NIET"

**Wat:** Bij elke rollback verplicht een ADR (Architecture Decision Record) in `docs/kb/decisions/` met **expliciete "WAAROM NIET"** zodat de fout niet opnieuw geprobeerd wordt.

**Deliverable:**
- `docs/kb/patterns/adr-rollback-template.md` — template:
  ```
  - Wat probeerden we
  - Waarom leek het een goed idee
  - Wat ging er mis (concreet)
  - Hoe lang heeft het ons gekost
  - Wat is de ondergrens om dit OPNIEUW te overwegen
    (bijv. "alleen als HA architectuur volledig herschreven wordt")
  ```
- **Eerste invulling**: `docs/kb/decisions/alpine-csp-rollback-havunadmin.md` (was open punt)
  - Concreet: 30+ inline `x-data` constructies, kosten-baten Mozilla -10 vs UI-rewrite
- **Backfill** — historische rollbacks die we ons herinneren documenteren:
  - Cursor → terug naar VS Code (09-03-2026)
  - Chromecast verwijderd uit JudoToernooi (24-04-2026)
  - Andere die we tegenkomen
- Verwijzing in werkwijze-beoordeling-derden.md sectie 12 (Risico's & Mitigaties): "rollback = leermoment, geen schande"

**Acceptatiecriterium:** als een AI-sessie over 2 maanden voorstelt "laten we Alpine CSP-build proberen voor HavunAdmin" → er is een doc dat zegt: lees eerst dit, dan pas beslissen.

**Schatting:** 2 uur — template + 3 historische ADRs + linkstructuur.

---

## Totale tijdsinvestering komende week

| Actie | Schatting |
|-------|----------:|
| 1. SSL/Mozilla score-policy | 0,75u |
| 2. Coverage 70% beleid + CI | 1u |
| 3. Security-marathon cadans | 1u |
| 4. Rollback-ADR discipline | 2u |
| **Totaal** | **~5 uur** |

**Niet-doelen voor deze week:**
- Geen nieuwe security-tweaks (zie actiepunt 3)
- Geen nieuwe coverage-tests boven 70% (zie actiepunt 2)
- Geen Mozilla Observatory pogingen om van 90 naar 95 te komen

---

## Verificatie eind week (2 mei 2026)

- [ ] 4 ADRs / decision-docs gepubliceerd in `docs/kb/decisions/`
- [ ] `productie-deploy-eisen.md` aangepast voor A i.p.v. A+ / 100
- [ ] `test-quality-policy.md` aangepast naar 70%
- [ ] CI-coverage drempels in 9 projecten op 70%
- [ ] Eerste invulling rollback-ADR voor HavunAdmin Alpine CSP
- [ ] Verbeterplan Q2-2026 herzien: security-items naar Q3-marathon

---

## Opvolging

Na uitvoering van deze 4 punten:
- **Audit-addendum** bijwerken met realisatie (juli 2026, vóór Q3-marathon)
- **Werkwijze v3.1** kandidaat: alleen als deze 4 punten + Q3-marathon zijn voltooid
- Tussendoor: focus op **features** (Munus Fase 1, Studieplanner, JudoToernooi roadmap) — niet op meta-werk

---

*Vastgelegd 25 april 2026, op basis van zelf-reflectie "schieten we wel op?". De doorlopende drang naar "nog beter" is identiek aan de drang naar "nog meer tests" — beide leiden tot diminishing returns. Deze week = grenzen stellen.*
