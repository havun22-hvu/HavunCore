---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-05-22
---

# HavunCore — Handover

> Vul dit aan aan het einde van elke sessie. Houd het kort — wat een volgende Claude-sessie direct nodig heeft.

## Huidige status

**Branch:** master (schoon, alles gepusht)
**Laatste commit:** `276e7db` — docs(kb): update Gemini workflow docs to reflect new .claude/blueprint.md location

## Wat is er recent gedaan (21-22 mei)

### Alle projecten voorzien van session-start workflow
- `handover.md` aangemaakt in `.claude/` voor alle 11 projecten
- CLAUDE.md van elk project bijgewerkt met "✅ Verplicht bij sessiestart" sectie
- `/arch`-instructie toegevoegd per project (blueprint-locatie, `/mpc ga maar`)

### KB bijgewerkt en stale verwijzingen opgeruimd
- `ADR-008` en `gemini-claude-workflow.md` runbook: `gemini_blueprint.md` → `{project}/.claude/blueprint.md`
- Stale "we bouwen geen API-integratie" verwijderd (die integratie bestaat nu)
- Status-sectie runbook bijgewerkt met live API samples, blueprint locatie

## Openstaande kleine punten

- Dutch error string in Engels codebase: `'API_UNAVAILABLE (timeout of connectiefout)'` in `HavunPackCommand::fetchApiSamples()` — bewust gelaten, lage prioriteit
- `sync-start-command.md` runbook heeft incomplete projectlijst (mist Munus, Aeterna, Havunity)

## Lopende projecten (per project)

| Project | Status |
|---------|--------|
| SafeHavun | Stabiel — UI redesign geïmplementeerd (21 mei) |
| Herdenkingsportaal | Stabiel — deploy pending (avondsessie 20 mei) |
| JudoToernooi | Stabiel |
| HavunAdmin | Stabiel — Alpine CSP-migratie 21 views open |
| Munus | MVP Fase 1 — ontwerp klaar, code nog niet gestart |
| Aeterna | Feature-complete — Week 2-plan wacht op go/no-go |

## Architectuurprincipes (niet te herhalen elk gesprek)

- Gemini = architect (groot contextvenster, blauwdrukken)
- Claude = validator + executor (lokale schijf, implementatie)
- Blueprint flow: `/arch [opdracht]` → `{project}/.claude/blueprint.md` → `/start` detecteert → `/mpc ga maar`
- Zie `docs/kb/runbooks/gemini-claude-workflow.md` voor volledige beschrijving
