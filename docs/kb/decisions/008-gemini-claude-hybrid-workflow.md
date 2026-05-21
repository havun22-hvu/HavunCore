---
title: "ADR-008: Gemini+Claude hybride AI werkwijze"
type: decision
scope: alle projecten
date: 2026-05-20
status: actief
---

# ADR-008: Gemini+Claude hybride AI werkwijze

## Context

Claude's contextvenster (~200k tokens) was te klein voor grote onderhoud- en architectuurtaken over meerdere bestanden. Langdurige sessies leidden tot context-verwatering en scope-drift. Copy-paste vanuit de browser naar AI Studio was te omslachtig.

## Beslissing

We voeren een hybride AI werkwijze in met vaste rolverdeling:

**Gemini (AI Studio)** = Macro-Architect
- Groot contextvenster (2M+ tokens)
- Architectuurblauwdrukken en onderhoud
- Aangestuurd via `@google/gemini-cli` of `php artisan havun:gemini`

**Claude (Code Extension)** = Micro-Executor
- Valideert blauwdrukken lokaal op correctheid
- Voert code-wijzigingen uit op schijf
- Draait tests en past /simplify toe

## Infrastructuur

- `havun:pack` — pakt projectcontext in (auto-detect via cwd)
- `havun:gemini` — Claude kan Gemini direct aanroepen via REST API
- `GEMINI_API_KEY` in `.env` en Windows User Environment
- `NODE_TLS_REJECT_UNAUTHORIZED=0` + `GEMINI_CLI_TRUST_WORKSPACE=true` permanent ingesteld

## Standaard pipe (door Claude via /arch)

```bash
/arch --project=<naam> "opdracht"
```
Blueprint landt automatisch in `{project}/.claude/blueprint.md`. Ophalen bij `/start`.

## Handmatige pipe (door gebruiker)

```powershell
php artisan havun:pack --project=<naam> | gemini "opdracht" | Out-File -Encoding utf8 "D:\GitHub\<naam>\.claude\blueprint.md"
```

## Autonome pipe (door Claude)

```bash
php artisan havun:gemini --project=<naam> "opdracht"
# Slaat automatisch op in {project}/.claude/blueprint.md
```

## Gevolgen

- Claude start geen grote architectuurtaken meer zonder Gemini-blauwdruk
- CLAUDE.md's van alle projecten verwijzen naar dit runbook
- Maximaal één reflectieronde (Gemini → Claude kritiek → Gemini corrigeert)
- Zie `runbooks/gemini-claude-workflow.md` voor de volledige procedure
