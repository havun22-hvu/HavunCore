---
title: Implementation plan — LogLevel + Priority enum extraction
type: runbook
scope: havuncore
status: COMPLETED
last_check: 2026-04-22
follows: severity-loglevel-priority-taxonomies.md
---

# Implementation plan — LogLevel + Priority enums

> **Approval-gate:** dit plan moet OK krijgen vóór Fase 3 (code).
> Werkt strikt incrementeel: 3 commits, elk los testbaar.

## Bestanden per stap

### Stap 1 — `App\Enums\LogLevel`

**Aanmaken:**
- `app/Enums/LogLevel.php` — backed enum met cases Critical/Error/Warning
- `tests/Unit/Enums/LogLevelTest.php` — per-case + factory + sortWeight tests

**Wijzigen:**
- `app/Models/ErrorLog.php` — `determineSeverity()` returnt
  `LogLevel::fromException($e)->value` ipv string-literal

**Tests:**
- Bestaande ErrorLog-tests blijven groen (string-output unchanged)
- Nieuwe LogLevelTest: ~10 assertions

### Stap 2 — `App\Enums\Priority`

**Aanmaken:**
- `app/Enums/Priority.php` — backed enum met cases Urgent/High/Normal/Low + `sortWeight()`
- `tests/Unit/Enums/PriorityTest.php` — per-case + sortWeight tests

**Wijzigen:**
- `app/Models/ClaudeTask.php` — `scopeOrderByPriority()` bouwt CASE-expression
  uit `Priority::cases()` ipv hardcoded raw SQL

**Tests:**
- Bestaande `tests/Unit/ClaudeTaskTest.php` blijft groen
- Nieuwe PriorityTest: ~10 assertions

### Stap 3 — Documentatie afronden

**Wijzigen:**
- `docs/kb/runbooks/severity-loglevel-priority-taxonomies.md` → `status: COMPLETED`
- `app/Enums/Severity.php` — PHPDoc verwijst naar de taxonomies-runbook
- Memory: `project_kv_systeem.md` aanvullen met LogLevel + Priority enums live

## Volgorde + afhankelijkheden

```
Stap 1 (LogLevel + ErrorLog)
        │
        ├──→ kan parallel met Stap 2 (geen overlap)
        │
Stap 2 (Priority + ClaudeTask)
        │
        ▼
Stap 3 (docs)
```

Stap 1 en 2 zijn **onafhankelijk** — andere file-sets, andere tests.
Sequential commits voor clean git-history.

## Risico's + mitigaties

| Risico | Mitigatie |
|--------|-----------|
| ErrorLog tests breken op string-comparison | Enum's `->value` is identiek aan oude string. Test-output unchanged. |
| ClaudeTask scopeOrderByPriority gedrag verandert | Nieuwe CASE-expression genereert dezelfde SQL als oude raw-SQL (zelfde sort-volgorde). |
| MSI-regression door nieuwe match-arms (zie ObservabilityService 100→59% in `de0a0ca`) | Tests + source in dezelfde commit. Per-case asserts killen MatchArmRemoval. |
| DB ENUM-column `claude_tasks.priority` accepteert geen nieuwe waardes | Geen nieuwe waardes — alleen refactor naar enum-class. Migration unchanged. |
| Wachtwoord/credentials raken | N/A — pure code-refactor. |

## Geschatte effort

- Stap 1: 30 min (enum + test + ErrorLog refactor)
- Stap 2: 30 min (enum + test + ClaudeTask scope refactor)
- Stap 3: 15 min (docs)
- Lokale tests + commit + push per stap: ~10 min totaal

**Totaal: ~1.5 uur, 3 commits.**

## Akkoord-vraag aan Henk

**Voor Fase 3 (code) start, akkoord nodig op:**
1. ✅ `LogLevel` als aparte enum (niet uitbreiden van `Severity`)?
2. ✅ `Priority` als aparte enum (niet uitbreiden van `Severity`)?
3. ✅ DB-kolom `error_logs.severity` NIET hernoemen (kosten >> baten)?
4. ✅ Drie sequential commits, geen big-bang?

**Als alle 4 akkoord = ga door. Als een twijfel = bespreek voor code.**
