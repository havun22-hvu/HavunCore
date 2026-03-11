# Runbook: Start-command sync naar alle projecten

> **Doel:** Alle projecten gebruiken dezelfde /start command.
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

## Referentie

- Start-command inhoud: HavunCore `.claude/commands/start.md`
