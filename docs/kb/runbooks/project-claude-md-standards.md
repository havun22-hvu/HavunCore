---
title: CLAUDE.md Standaarden per Project
type: runbook
scope: havuncore
last_check: 2026-04-27
---

# CLAUDE.md Standaarden per Project

> **Doel:** elke project-CLAUDE.md verwijst naar dezelfde Havun-normen.
> Eén canonieke template — alle projecten linken (niet dupliceren).

## Achtergrond

Op 27-04-2026 bleek dat 0/13 projecten `qv:scan`, productie-deploy-eisen of test-quality-policy noemde. Centrale standaarden bestonden, maar werden in de praktijk overgeslagen omdat project-CLAUDE.md er niet naar verwees.

Deze doc legt vast welk blok élke project-CLAUDE.md moet bevatten.

## Verplicht blok per project-CLAUDE.md

**Single source of truth:** `HavunCore/stubs/claude-md-standards-block.md`. Plak de inhoud van dat bestand aan het einde van elke project-CLAUDE.md (vóór de project-specifieke runbooks-tabel).

`project:scaffold` gebruikt deze stub automatisch via `loadStandardsBlock()` — nieuwe projecten krijgen het blok zonder code-wijziging in de command.

**Bij update** van het standaarden-blok:
1. Wijzig alleen `stubs/claude-md-standards-block.md`
2. Run het audit-script (zie §Auditcheck) — toont welke project-CLAUDE.md's drift hebben
3. Sync drift-projecten één voor één (klein commit per project)

## Rollout-procedure

1. **Pilot:** template eerst in 1 actief project plaatsen, controleren dat alle KB-paden kloppen.
2. **Cross-project rollout:** voor elk actief project 1 commit `docs(claude): add Havun standards block` met het identieke blok.
3. **IDSee:** krijgt een nieuwe `CLAUDE.md` (had er geen) met basis-template + dit blok.
4. **Geparkeerd (HavunClub, Havunity):** lager prioriteit — toevoegen bij re-activatie.
5. **Scaffold-update:** `project:scaffold` template moet dit blok auto-toevoegen voor nieuwe projecten.

## Wat als een KB-doc niet bestaat?

Bij introductie van deze rollout (27-04-2026) bestonden alle docs behalve `runbooks/beschermingslagen.md`. Die is gerefactored naar `runbooks/claude-werkwijze.md` §4. Houd dit blok in lijn met de werkelijke KB-paden — als een doc verhuist, update dan dit canonieke blok én rol opnieuw uit.

## Auditcheck

### A. Heeft elk actief project het blok?

```bash
for d in HavunAdmin Herdenkingsportaal JudoToernooi Infosyst SafeHavun \
         HavunVet Studieplanner JudoScoreBoard Munus havuncore-webapp IDSee; do
  if grep -q "Havun Standaarden" "/d/GitHub/$d/CLAUDE.md" 2>/dev/null; then
    echo "✓ $d"
  else
    echo "✗ $d — ONTBREEKT"
  fi
done
```

Resultaat moet zijn: 11x ✓ voor actieve projecten.

### B. Drift-detectie tussen stub en project-CLAUDE.md's

```bash
# Vergelijk regels in stub met regels in elk project-CLAUDE.md.
# Mismatch = drift — project-CLAUDE.md is achterhaald t.o.v. canon.
STUB="/d/GitHub/HavunCore/stubs/claude-md-standards-block.md"

for d in HavunAdmin Herdenkingsportaal JudoToernooi Infosyst SafeHavun \
         HavunVet Studieplanner JudoScoreBoard Munus havuncore-webapp IDSee; do
  cmd="/d/GitHub/$d/CLAUDE.md"
  [ -f "$cmd" ] || continue

  # Extract block uit project-CLAUDE.md (alles vanaf "## Havun Standaarden")
  block=$(awk '/^## Havun Standaarden/,/^## /{if(/^## / && !/^## Havun Standaarden/) exit; print}' "$cmd")

  # Compare with stub (skip ## header line — sub-tooling kan kop verschillend hebben)
  if diff -q <(echo "$block" | tail -n +2) <(tail -n +2 "$STUB") >/dev/null 2>&1; then
    echo "✓ $d (sync)"
  else
    echo "✗ $d (drift)"
  fi
done
```

Bij drift: kopieer `stubs/claude-md-standards-block.md` content over de bestaande `## Havun Standaarden`-sectie van het project en commit `docs(claude): sync standards block with canonical stub`.
