# HavunCore Context

> Overzicht van het project. Details in aparte bestanden.

## Wat is HavunCore?

Centrale kennisbank & orchestrator voor ALLE Havun projecten:
- Patterns, methoden en oplossingen
- Credentials en configuraties (Vault)
- Herbruikbare code en templates

## Documentatie Structuur

| Bestand | Inhoud |
|---------|--------|
| `context.md` | Dit bestand - overzicht |
| `server.md` | Server paths, URLs, SSH keys |
| `credentials.md` | API keys, passwords, tokens |
| `handover.md` | Laatste sessie info + openstaande items |

## Local Development

| Project | App/Backend poort | Vite/Frontend poort | Stack |
|---------|------------------|---------------------|-------|
| havuncore-webapp frontend | - | 8000 | React + Vite (Vite IS de server) |
| HavunAdmin | 8001 | 5173 | Laravel + Vite |
| Herdenkingsportaal | 8002 | 5173 | Laravel + Vite |
| Studieplanner-api | 8003 | - | Laravel (geen Vite) |
| SafeHavun | 8004 | 5173 | Laravel + Vite |
| Infosyst | 8005 | 5173 | Laravel + Vite |
| IDSee | 8006 | 5173 | Node.js + React |
| JudoToernooi | 8007 | 5173 | Laravel + Vite |
| HavunVet | 8008 | 5173 | Laravel + Vite |
| havuncore-webapp backend | 8009 | - | Node.js Express |
| Studieplanner (Expo) | 8010 | - | React Native + Expo Metro |
| JudoScoreBoard (Expo) | 8011 | - | React Native + Expo Metro |
| HavunCore | - | - | Laravel (pure backend, geen lokale dev) |
| Ollama | 11434 | - | AI (Command-R) |

**Vite HMR:** Laravel+Vite projecten gebruiken standaard poort 5173 voor hot reload.
Je opent altijd de Laravel-poort in de browser — Vite HMR draait op de achtergrond.
Bij meerdere projecten tegelijk pakt Vite automatisch 5174, 5175 etc.

## USB / Op reis workflow

**Principe:** USB bevat **alleen credentials** (vault) + startscript. Geen projectcode op USB — die haal je via `git clone` / `git pull` op de reis-PC.

**Workflow:**
1. **Op reis-PC:** Cursor + Git installeren → USB in → `start.bat` draaien (vault unlock) → credentials vloeien naar SSH, git, projectmappen.
2. **Repos:** `git clone` (eerste keer) of `git pull` in gewenste map (bijv. D:\GitHub); code staat niet op de USB.
3. **Afsluiten:** Cleanup-script draaien (SSH keys e.d. van laptop verwijderen).

**Volledige stappen:** `docs/kb/runbooks/op-reis-workflow.md`

## Kwartaal-Audit

Elk kwartaal wordt de werkwijze extern beoordeeld. Documenten:

| Bestand | Inhoud |
|---------|--------|
| `docs/audit/werkwijze-beoordeling-derden.md` | Beoordelingsdocument voor derden |
| `docs/audit/verbeterplan-q2-2026.md` | Verbeterplan met 10 actiepunten (VP-01 t/m VP-10) |

Volgende review: Q3 2026 (juli 2026)

## Knowledge Base

Zoek info in `docs/kb/`:

| Onderwerp | Locatie |
|-----------|---------|
| Hoe doe ik X? | `runbooks/` |
| Herbruikbare patterns | `patterns/` |
| Beslissingen | `decisions/` |
| API specs | `reference/` |
| Per-project info | `projects/` |

## Essentiële docs (VP-03.2)

> **Doel:** kortste pad naar relevante context bij elke taak. Lees deze
> bestanden eerst voordat je een diepere `docs:search` start.

| Onderwerp | Bestand |
|-----------|---------|
| 5 Onschendbare Regels | `CLAUDE.md` (top) + `CONTRACTS.md` |
| AutoFix delivery-model | `docs/kb/runbooks/autofix-branch-model.md` |
| Emergency runbook | `docs/kb/runbooks/emergency-runbook.md` |
| Noodcontact-bevoegdheden | `docs/kb/runbooks/wat-mag-noodcontact.md` |
| Kwaliteitsnormen | `docs/kb/reference/havun-quality-standards.md` |
| Security headers + CSP | `docs/kb/runbooks/security-headers-check.md` |
| Mutation testing baseline | `docs/kb/reference/mutation-baseline-2026-04-17.md` |
| Test-repair anti-pattern | `docs/kb/runbooks/test-repair-anti-pattern.md` |
| Verbeterplan tracking | `docs/audit/verbeterplan-q2-2026.md` |
| Beschermingslagen | `docs/kb/runbooks/beschermingslagen.md` |
