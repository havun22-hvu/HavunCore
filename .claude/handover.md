---
title: HavunCore Handover
type: claude
scope: havuncore
last_updated: 2026-05-31
---

# HavunCore — Handover

> Vul dit aan aan het einde van elke sessie. Houd het kort — wat een volgende Claude-sessie direct nodig heeft.

## Huidige status

**Branch:** master (schoon, alles gepusht)
**Laatste commit:** IDSee Midnight docs + HavunCore autoMode settings fix

## Wat is er recent gedaan (31 mei)

### IDSee — Midnight Network kennisbank aangelegd
- `docs/midnight/OVERVIEW.md` — platform architectuur, SDK, roadmap status (Kolu actief!)
- `docs/midnight/ZK-PATTERNS.md` — commitment/nullifier/Merkle patronen + 3 IDSee circuits uitgewerkt
- `docs/midnight/COMPACT-LANGUAGE.md` — Compact DSL syntax, types, Midnight.js integratie (TypeScript-achtig, NIET Rust)
- `docs/midnight/INTEGRATION-PLAN.md` — fasering fase 0-4, nieuwe services, DB schema
- `docs/midnight/HOSKINSON-CONTEXT.md` — video samenvatting incl. Hawaiian roadmap (Kolu=actief, Mahalu=Q2, Ua=Q3 2026)
- `docs/contracts/VERIFICATION.md` — pseudo-code gecorrigeerd van Rust naar Compact
- Memory opgeslagen: `project_midnight_network.md` — Midnight voor IDSee én Aeterna

### Midnight gebruik: IDSee + Aeterna
- **IDSee**: anonieme ZK-verificatie fokkers/dierenartsen/chippers
- **Aeterna**: zelfde patroon (use case nog te concretiseren)
- Academy: https://academy.midnight.network (gratis, 3 certificaten — doorlopen vóór implementatie)

### Globale settings fix — autoMode MD-bestanden
- `~/.claude/settings.json`: `autoMode.allow` uitgebreid met patronen voor handover.md, context.md, HANDOVER.md, CLAUDE.md
- Reden: extension vroeg steeds om bevestiging bij MD-edits buiten `.claude/*.md`

## Openstaande punten

- **JudoScoreBoard**: pre-publish review via dynamic workflow (eerste echte dynamic workflow sessie)
- **Aeterna**: Week 2-plan wacht op go/no-go van Henk + Midnight use case concretiseren
- **HavunAdmin**: Alpine CSP-migratie 21 views open
- **IDSee Midnight**: Fase 0 = Academy doorlopen vóór implementatie begint
- ~~Dutch error string in `HavunPackCommand::fetchApiSamples()`~~ ✓ opgelost 6 jun (nu Engels: `timeout or connection error`)
- ~~`sync-start-command.md` runbook heeft incomplete projectlijst~~ ✓ opgelost 6 jun (tabel gesynct met projects-index + Havun/Studieplanner-api/IDSee/JudoScoreBoard/VPDUpdate toegevoegd)

## Lopende projecten (per project)

| Project | Status |
|---------|--------|
| JudoScoreBoard | Play Console screenshots OK — pre-publish review via dynamic workflow |
| Aeterna | Feature-complete — Week 2-plan wacht op go/no-go + Midnight use case |
| SafeHavun | Stabiel v1.1.3 |
| Herdenkingsportaal | Stabiel |
| JudoToernooi | Stabiel |
| HavunAdmin | Stabiel — Alpine CSP-migratie 21 views open |
| IDSee | Midnight KB aangelegd — klaar voor Fase 0 (Academy) |
| Munus | **GEPARKEERD** |
| Studieplanner | In ontwikkeling — geen bekende open items |

## Architectuurprincipes

- **Gemini** = architect + brainstorm (groot contextvenster, tweede mening) — via `/arch` of automatisch in dynamic workflow
- **Claude dynamic workflow** = grote taken (ultracode mode) — roept Gemini aan, implementeert parallel, test, commit
- **Claude normaal** = kleine fixes (< 5 bestanden, afgebakend)
- Memory flow: `/mem` → leest `C:/Users/henkv/.claude/projects/[SLUG]/memory/MEMORY.md`
- Bij config-issues na wijziging `havun-projects.php`: altijd `php artisan config:clear`
- Doc Intelligence MEDIUM duplicaten zijn vrijwel altijd false positives — bulk-negeren is correct
- **Midnight**: Compact = TypeScript-achtige DSL (niet Rust). Backend genereert proofs server-side — gebruikers zien nooit blockchain.
- **autoMode.allow**: handover.md en context.md staan nu globaal in de allow-lijst (`~/.claude/settings.json`)
