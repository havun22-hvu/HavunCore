---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-05-29
---

# HavunCore — Handover

> Vul dit aan aan het einde van elke sessie. Houd het kort — wat een volgende Claude-sessie direct nodig heeft.

## Huidige status

**Branch:** master (schoon, alles gepusht)
**Laatste commit:** `197dc43` — dynamic workflows toegevoegd aan AI workflow doctrine

## Wat is er recent gedaan (29 mei)

### JudoScoreBoard — Play Console screenshots opgelost
- Screenshots werden steeds afgekeurd (⊘) — oorzaak: bibliotheek geopend vanuit Functieafbeelding-context (1024×500) i.p.v. Telefoon-context
- Fix: "Items toevoegen" klikken binnenin de rode Telefoon-box, niet de knop bovenaan
- Native 1080×1920 ADB screenshots zijn correct en worden nu geaccepteerd

### AI Werkwijze uitgebreid met Dynamic Workflows
- `CLAUDE.md`: drie pijlers toegevoegd — Gemini (architect/brainstorm), Claude normaal (klein), Claude dynamic workflow (groot)
- `docs/kb/runbooks/gemini-claude-workflow.md`: nieuwe sectie met decision table + uitleg
- Dynamic workflows roepen `havun:gemini` automatisch aan als eerste stap — geen handmatige `/arch` + `/mpc` meer nodig voor grote taken
- Starten: gewoon de opdracht typen (ultracode mode) — Claude beslist zelf

### Projectprioriteiten bijgewerkt
- Munus = geparkeerd (niet meer actief)
- Actieve focus: JudoScoreBoard, Aeterna, SafeHavun, JudoToernooi, Herdenkingsportaal, Studieplanner

## Openstaande punten

- **JudoScoreBoard**: pre-publish review via dynamic workflow (eerste echte dynamic workflow sessie)
- **Aeterna**: Week 2-plan wacht op go/no-go van Henk
- **HavunAdmin**: Alpine CSP-migratie 21 views open
- Dutch error string: `'API_UNAVAILABLE (timeout of connectiefout)'` in `HavunPackCommand::fetchApiSamples()` — lage prioriteit
- `sync-start-command.md` runbook heeft incomplete projectlijst

## Lopende projecten (per project)

| Project | Status |
|---------|--------|
| JudoScoreBoard | Play Console screenshots OK — pre-publish review via dynamic workflow |
| Aeterna | Feature-complete — Week 2-plan wacht op go/no-go |
| SafeHavun | Stabiel v1.1.3 |
| Herdenkingsportaal | Stabiel |
| JudoToernooi | Stabiel |
| HavunAdmin | Stabiel — Alpine CSP-migratie 21 views open |
| Munus | **GEPARKEERD** |
| Studieplanner | In ontwikkeling — geen bekende open items |

## Architectuurprincipes

- **Gemini** = architect + brainstorm (groot contextvenster, tweede mening) — via `/arch` of automatisch in dynamic workflow
- **Claude dynamic workflow** = grote taken (ultracode mode) — roept Gemini aan, implementeert parallel, test, commit
- **Claude normaal** = kleine fixes (< 5 bestanden, afgebakend)
- Memory flow: `/mem` → leest `C:/Users/henkv/.claude/projects/[SLUG]/memory/MEMORY.md`
- Bij config-issues na wijziging `havun-projects.php`: altijd `php artisan config:clear`
- Doc Intelligence MEDIUM duplicaten zijn vrijwel altijd false positives — bulk-negeren is correct
