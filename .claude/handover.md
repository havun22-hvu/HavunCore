---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-05-28
---

# HavunCore — Handover

> Vul dit aan aan het einde van elke sessie. Houd het kort — wat een volgende Claude-sessie direct nodig heeft.

## Huidige status

**Branch:** master (schoon, alles gepusht)
**Laatste commit:** `c2f6f66` — security update Symfony packages

## Wat is er recent gedaan (28 mei)

### Security fixes (kritiek — CVE's gepusht)
- `symfony/http-foundation` 7.4.1 → 7.4.13 (CVE-2026-48736: SSRF bypass fix)
- `symfony/routing` 7.4.12 → 7.4.13 (CVE-2026-48784: URL normalization fix)
- `symfony/polyfill-intl-idn` 1.33.0 → 1.38.1 (CVE-2026-46644: Punycode fix)

### Config
- `judoscoreboard` entry definitief gecommit in `config/havun-projects.php`

### Doc Intelligence cleanup
- 4 HIGH issues genegeerd (archive + andere-project docs)
- Alle MEDIUM duplicaten bulk-genegeerd (false positives — structuurmatch, niet inhoudsdup)
- Mermaid `[[node]]` broken-link false positives genegeerd (issues 15836, 15837)
- 0 open issues na cleanup

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
- Doc Intelligence MEDIUM duplicaten zijn vrijwel altijd false positives — bulk-negeren is correct
