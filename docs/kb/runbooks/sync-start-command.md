# Runbook: Start-command sync naar alle projecten

> **Doel:** Alle projecten gebruiken dezelfde /start command inclusief rittenregistratie-plan stap.
> **Bron van waarheid:** `HavunCore/.claude/commands/start.md`

## Wanneer syncen?

- Na wijziging van de start-command in HavunCore
- Bij nieuw project (copy start.md uit HavunCore)
- Na audit als blijkt dat een project een verouderde start.md heeft

## Stappen

1. **Controleer bron:** Lees `D:\GitHub\HavunCore\.claude\commands\start.md`.
2. **Kopieer naar elk project:** Vervang `{project}/.claude/commands/start.md` door de inhoud van HavunCore.

### Projecten (zie docs/kb/projects-index.md)

| Project        | Pad naar start.md |
|----------------|-------------------|
| HavunCore      | (bron) `.claude/commands/start.md` |
| HavunAdmin     | `D:\GitHub\HavunAdmin\.claude\commands\start.md` |
| Herdenkingsportaal | `D:\GitHub\Herdenkingsportaal\.claude\commands\start.md` |
| JudoToernooi   | `D:\GitHub\JudoToernooi\.claude\commands\start.md` |
| SafeHavun      | `D:\GitHub\SafeHavun\.claude\commands\start.md` |
| HavunClub      | `D:\GitHub\HavunClub\.claude\commands\start.md` |
| Studieplanner  | `D:\GitHub\Studieplanner\.claude\commands\start.md` |
| Infosyst       | `D:\GitHub\infosyst\.claude\commands\start.md` |
| HavunVet       | `D:\GitHub\HavunVet\.claude\commands\start.md` |

3. **Optioneel:** Als een project geen `.claude/commands/` heeft, maak de map aan en plak start.md.

## Verplichte stap in start (sinds 2026-03-10)

Stap 4 moet in elke start-command zitten:

- **Rittenregistratie-plan (VERPLICHT):** Scan `D:\GitHub\HavunCore\docs\kb\`, maak of werk bij het plan in het project zijn `.claude/smallwork.md` voor de rittenregistratie-pagina volgens Havun-standaarden.

## Referentie

- Start-command inhoud: HavunCore `.claude/commands/start.md`
- Rittenregistratie-plan voorbeeld: HavunCore `.claude/smallwork.md` (sectie "Plan: Rittenregistratie-pagina")
