---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-05-24
---

# HavunCore — Handover

> Vul dit aan aan het einde van elke sessie. Houd het kort — wat een volgende Claude-sessie direct nodig heeft.

## Huidige status

**Branch:** master (schoon, alles gepusht)
**Laatste commit:** `70c294b` — alle 20 projecten volledig gesynchroniseerd met nieuw AI-werksysteem

## Wat is er recent gedaan (23-24 mei)

### Geheugen- en werksysteem uitgerold naar alle 20 projecten
- `/mem` command aangemaakt en in `/start` verankerd als stap 0 (memory lezen voor alles)
- `/arch` command uitgerold naar alle 18 overige projecten
- Blueprint-detectie toegevoegd aan elk project's `start.md`
- `autoMode.allow` voor `php artisan havun:gemini *` toegevoegd aan elk project (settings.local.json of settings.json)
- `f.md` aangemaakt waar ontbrak (havuncore-webapp, Aeterna)
- `handover.md` aangemaakt waar ontbrak (HavunVet, Studieplanner-api, Demo, IDSee)
- `/arch` sectie toegevoegd aan CLAUDE.md van 6 projecten

### Config cache bug opgelost
- `judoscoreboard` stond al in `config/havun-projects.php` maar Laravel cache was verouderd
- Fix: `php artisan config:clear` — `/arch judoscoreboard` werkt nu

### Speciale situaties per project
- **Herdenkingsportaal + Munus**: `settings.local.json` gitignored → `autoMode` in `settings.json`
- **VPDUpdate + Aeterna**: force-add nodig voor `.claude/` (was gitignored)
- **Munus + Havunity**: geen GitHub remote — commits lokaal aanwezig, push wacht op remote

## Openstaande kleine punten

- Dutch error string: `'API_UNAVAILABLE (timeout of connectiefout)'` in `HavunPackCommand::fetchApiSamples()` — bewust gelaten, lage prioriteit
- `sync-start-command.md` runbook heeft incomplete projectlijst (mist Munus, Aeterna, Havunity)
- **JudoScoreBoard**: `/arch` pre-publish review nog uitvoeren in aparte sessie

## Lopende projecten (per project)

| Project | Status |
|---------|--------|
| JudoScoreBoard | Pre-publish review pending via `/arch` |
| SafeHavun | Stabiel |
| Herdenkingsportaal | Stabiel |
| JudoToernooi | Stabiel |
| HavunAdmin | Stabiel — Alpine CSP-migratie 21 views open |
| Munus | MVP Fase 1 — ontwerp klaar, code nog niet gestart |
| Aeterna | Feature-complete — Week 2-plan wacht op go/no-go |

## Architectuurprincipes

- Gemini = architect (groot contextvenster, blauwdrukken)
- Claude = validator + executor (lokale schijf, implementatie)
- Blueprint flow: `/arch [opdracht]` → `gemini_blueprint.md` (HavunCore root) → `/mpc ga maar` (blueprint persisteert tussen sessies)
- Memory flow: `/mem` → leest `C:/Users/henkv/.claude/projects/[SLUG]/memory/MEMORY.md`
- Bij config-issues na wijziging `havun-projects.php`: altijd `php artisan config:clear`
