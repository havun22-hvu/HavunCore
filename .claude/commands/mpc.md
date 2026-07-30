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

**Grote taak?** → Eerst `/arch` uitvoeren voor Gemini-blauwdruk, dan pas MPC-fase 2+3.
**Gemini aan zet?** → Claude wacht. Niet zelf doorcoderen ("puppy-gedrag").
```
/arch --project=<naam> Ontwerp blauwdruk voor: [taak]
```

**Na "ga maar": Claude voert volledig autonoom uit. Geen vragen meer.**

---

## Fase 0: Intake — alleen bij een nieuw project of een herbouwbesluit

**Sla over bij werk binnen een bestaand project.** Gaat het om een nieuw project, of om de
vraag of het fundament nog deugt, dan komt de stackkeuze **vóór** fase 1:

1. **Waar draait het?** · 2. **Hoeveel gebruikers tegelijk?** · 3. **Waar staat de data, en
hoeveel?** · 4. **Wat is de zwaarste operatie, en hoe vaak?** · 5. **Waar merkt de gebruiker
vertraging?**

Antwoorden + **de conclusie die eruit volgt** in `docs/intake.md`. De antwoorden verzamelen is
niet genoeg — bij Vusista stonden ze al in een besluit-doc, en het werd toch een webapp met een
HTTP-server onder een lokale fotomanager. Zonder ingevulde intake weigert `project:scaffold`.

**"Havun-standaard" is geen argument.** Norm: `docs/kb/standards/stack-keuze.md`.

## Fase 1: MD Docs — EXHAUSTIEF (ENIGE fase voor vragen)

**Werk ALLEEN aan de MD docs.** Geen code schrijven.

1. Lees alle bestaande docs over het onderwerp
2. Stel ALLE vragen die je ooit nodig kunt hebben — **nu**, niet later
3. Vragen die MOGEN: ontbrekende business-logica, onduidelijke requirements, vergeten edge cases
4. Vragen die NIET mogen later: "Zal ik X doen?", "Mag ik Y aanpassen?", technische keuzes
5. Update/maak docs tot ze 100% compleet zijn
6. **Architectuurbesluit erbij?** Noem de **aanname** waarop hij rust en het **omkeerpunt** —
   de meting die hem zou omkeren. Geen datum. `standards/docs-first.md`

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
   └── **Bugfix? Eerst de test rood zien tegen de oude code** — anders bewijst groen niets.
       Niet gedaan → expliciet melden. `patterns/test-rood-gezien.md`
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
- **NOOIT** een stack erven. Nieuw project → fase 0 eerst. `standards/stack-keuze.md`
- **ALTIJD** per-agendapunt cyclus volgen: test → simplify → docs → commit
- **Tweede omweg om het fundament heen?** → architectuurreview, geen commit.
  Registreer 'm in `docs/omwegen.md`. `patterns/omwegen-tellen.md`
