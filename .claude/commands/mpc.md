---
title: MPC — MD, Plan, Codering
type: claude
scope: havuncore
last_check: 2026-05-18
---

# MPC — MD, Plan, Codering

> Gefaseerde werkwijze: eerst documenteren, dan plannen, dan autonoom uitvoeren.

## Rolverdeling (ABSOLUUT)

| Rol | Wie | Wat |
|-----|-----|-----|
| **Macro-Architect** | Gemini | Blauwdrukken voor grote/complexe taken via `havun:gemini` |
| **Regisseur** | Henk | Geeft richting, keurt plan goed, zegt "ga maar" |
| **Tester** | Henk | Praktische tests — op zijn eigen moment |
| **Micro-Executor** | Claude | Code, docs, tests, commits, deploys — valideert Gemini-blauwdruk lokaal |

**Grote taak?** → Eerst Gemini-blauwdruk, dan pas MPC-fase 2+3.
**Gemini aan zet?** → Claude wacht. Niet zelf doorcoderen ("puppy-gedrag").
```bash
php artisan havun:gemini --project=<naam> "Ontwerp blauwdruk voor: [taak]" --out=gemini_blueprint.md
```

**Na "ga maar": Claude voert volledig autonoom uit. Geen vragen meer.**

---

## Fase 1: MD Docs — EXHAUSTIEF (ENIGE fase voor vragen)

**Werk ALLEEN aan de MD docs.** Geen code schrijven.

1. Lees alle bestaande docs over het onderwerp
2. Stel ALLE vragen die je ooit nodig kunt hebben — **nu**, niet later
3. Vragen die MOGEN: ontbrekende business-logica, onduidelijke requirements, vergeten edge cases
4. Vragen die NIET mogen later: "Zal ik X doen?", "Mag ik Y aanpassen?", technische keuzes
5. Update/maak docs tot ze 100% compleet zijn

**Klaar-criteria:** Een andere Claude kan de docs lezen en EXACT weten wat gebouwd moet worden — zonder één aanname of vraag.

## Fase 2: Plan — gedetailleerd agendaoverzicht

Na volledige docs → schrijf een implementatieplan:

1. Welke bestanden worden gewijzigd/aangemaakt?
2. Volgorde + afhankelijkheden
3. Wat parallel kan
4. Waar de risico's zitten
5. Per agendapunt: wat gebouwd wordt + welke geautomatiseerde tests er komen

**Sla het plan op** in `.claude/smallwork.md` of een apart planbestand.
**Presenteer het plan** aan Henk en eindig met: `Plan klaar — typ "ga maar" om te starten.`
NOOIT phrasing als vraag: geen "Ga maar?", geen "Zal ik beginnen?", geen "Akkoord?".

## Fase 3: Codering — volledig autonoom

**Na "ga maar"** → voer elk agendapunt uit in deze cyclus:

### Per-agendapunt cyclus (VERPLICHT na ELK punt)

```
1. Implementeer het agendapunt
2. Geautomatiseerde tests draaien + V&K check
   └── php artisan test --no-coverage  (Laravel)
   └── npm test                        (Node/RN)
3. /simplify uitvoeren op gewijzigde code
4. MD docs + planning + handover bijwerken
   └── Wat is af? Wat staat er nog?
5. Commit + push (atomair per punt)
6. → Volgende punt (geen wachten op Henk's praktische test)
```

**Bij planafwijking:** update het plan EERST, dan pas code. Meld de afwijking in één zin.

**Bij hoog risico** (productie-deploy, database-migratie, betalingssysteem):
→ Meld "klaar voor [X], wacht op jouw GO voor productie" — dit is de enige uitzondering.

## Regels

- **NOOIT** code schrijven in fase 1
- **NOOIT** coderen zonder goedgekeurd plan
- **NOOIT** afwijken van plan zonder update
- **NOOIT** wachten op technische beslissing van Henk — Claude beslist zelf
- **ALTIJD** per-agendapunt cyclus volgen: test → simplify → docs → commit
