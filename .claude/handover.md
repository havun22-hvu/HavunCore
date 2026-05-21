---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-05-21
---

# HavunCore — Handover

> Vul dit aan aan het einde van elke sessie. Houd het kort — wat een volgende Claude-sessie direct nodig heeft.

## Huidige status

**Branch:** master (schoon, alles gepusht)
**Laatste commit:** `d3794cc` — feat(arch-pipeline): live API samples, blueprint to .claude/, start blueprint check

## Wat is er recent gedaan (deze sessie)

### AIOps pipeline — volledig uitgerold
De Gemini–Claude pipeline is nu end-to-end gebouwd:

1. **`havun:pack --include-source`** — pakt broncode (PHP/JS/TS) + live API responses mee
2. **`havun:gemini`** — stuurt pack context naar Gemini REST API, slaat blueprint op in `{project}/.claude/blueprint.md` (niet meer in HavunCore root)
3. **Blueprint timestamp header** — blockquote bovenaan: `> **Blueprint** — PROJECT | Gegenereerd: ... | Model: ...`
4. **`/start` skill** — detecteert nu automatisch blueprint.md en vraagt of het geïmplementeerd moet worden
5. **`/arch` skill** — instructies bijgewerkt met nieuwe paths en handoff-tekst

### Infrastructuur
- `NormalizesPath` trait geïntroduceerd (gedeeld door Pack + Gemini commands)
- `config/havun-projects.php` omgebouwd van flat strings naar arrays met `path`, `local_url`, `endpoints`
- `fetchApiSamples()` in HavunPackCommand — live HTTP calls met 3s timeout, graceful fallback naar `API_UNAVAILABLE`

### SafeHavun docs structureel verbeterd
- `D:\GitHub\SafeHavun\.claude\handover.md` aangemaakt (UI Redesign state)
- `D:\GitHub\SafeHavun\CLAUDE.md` uitgebreid met session-start stappen
- `D:\GitHub\SafeHavun\docs\kb\reference\architecture.md` en `pwa-structure.md` aangemaakt

## Openstaande kleine punten

- Dutch error string in Engels codebase: `'API_UNAVAILABLE (timeout of connectiefout)'` in `HavunPackCommand::fetchApiSamples()` — bewust gelaten, lage prioriteit
- API timeout in fetchApiSamples is 3s — voor localhost-URLs zou 1-2s ook volstaan

## Lopende projecten (per project)

| Project | Status | Blueprint aanwezig |
|---------|--------|--------------------|
| SafeHavun | UI Redesign — spec klaar in `gemini_blueprint.md` | Nee (staat nog op HavunCore root, te verplaatsen) |
| Herdenkingsportaal | Stabiel | Nee |
| JudoToernooi | Stabiel | Nee |
| HavunAdmin | Stabiel | Nee |

## Hoe verder te gaan

1. SafeHavun UI Redesign uitvoeren → open in SafeHavun sessie, `/start` + `/mpc ga maar`
2. SafeHavun blueprint verplaatsen van `D:\GitHub\HavunCore\gemini_blueprint.md` → `D:\GitHub\SafeHavun\.claude\blueprint.md` als het er nog staat

## Architectuurprincipes (niet te herhalen elk gesprek)

- Gemini = architect (groot contextvenster, blauwdrukken)
- Claude = validator + executor (lokale schijf, implementatie)
- Blueprint flow: `/arch [opdracht]` → `.claude/blueprint.md` → `/start` detecteert → `/mpc ga maar`
- Zie `docs/kb/runbooks/gemini-claude-workflow.md` voor volledige beschrijving
