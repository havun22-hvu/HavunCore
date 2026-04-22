---
title: Severity / LogLevel / Priority — drie aparte taxonomies
type: runbook
scope: havuncore
status: COMPLETED
last_check: 2026-04-22
---

# Drie aparte taxonomies in HavunCore

> **Doel:** explicit maken dat `severity`, `log-level` en `priority`
> drie verschillende domeinen zijn met eigen waardenstelsels. Voorkomt
> dat refactors per ongeluk de verkeerde enum gebruiken.

## Domein-mapping

| Taxonomy | Enum | Cases | Bron-domein | DB-kolom(men) |
|----------|------|-------|-------------|---------------|
| **Severity** | `App\Enums\Severity` (LIVE) | critical, high, medium, low, info | V&K scan findings, doc-issues, quality assessment | `doc_issues.severity`, qv-scan JSON |
| **LogLevel** | `App\Enums\LogLevel` (PLANNED) | critical, error, warning | runtime exception/error logging (subset PSR-3) | `error_logs.severity` (kolomnaam misleidend, contenttype = log-level) |
| **Priority** | `App\Enums\Priority` (PLANNED) | urgent, high, normal, low | task scheduling, queue ordering | `claude_tasks.priority` (DB ENUM column) |

## Waarom drie aparte enums

**Naming-overlap is OK.** Zowel `Severity::High` als `Priority::High` hebben
de string-waarde `'high'`, maar leven in verschillende namespaces en
contexten. Het type-systeem voorkomt verwarring (een `Priority` accepteren
waar een `Severity` verwacht wordt = type-error).

**Waardenstelsels verschillen:**
- Severity `critical/high/medium/low/info` (5) volgt CVSS / OWASP Risk
  Rating conventie
- LogLevel `critical/error/warning` (3) volgt PSR-3 subset (we loggen
  geen `info`/`debug`/`notice` als ErrorLog row)
- Priority `urgent/high/normal/low` (4) volgt task-queue conventie
  (urgent > high > normal > low)

**Sort-orders verschillen:**
- Severity: critical = 0 (laagste sortWeight = meest severe)
- LogLevel: critical > error > warning (gevaar)
- Priority: urgent > high > normal > low (uitvoeringsvolgorde)

## Huidige staat (vóór refactor)

### Severity ✅ LIVE
- `App\Enums\Severity` met cases + `icon()` + `sortWeight()` + `safe()`
- Gerold in: `Severity` zelf, `QualitySafetyScanner`, `ScanReportRenderer`,
  `DocIssuesCommand`, `QualitySafetyScanCommand`, `SecurityFindingsLogAppender`
- 6 V&K-files gebruiken de enum (commit `ac25d20`)
- `ObservabilityService` gebruikt nog string-literals (rollback `de0a0ca`
  na MSI-regression — kan opnieuw als tests bijgewerkt worden)

### LogLevel ✅ LIVE (commit `59a6c83`, 22-04-2026)
- `App\Enums\LogLevel` met cases Critical/Error/Warning + `fromException()`
  factory + `sortWeight()`
- `tests/Unit/Enums/LogLevelTest.php` — per-case + boundary asserts
  (incl. >= 500 boundary pin om off-by-one mutations te killen)
- `ErrorLog::determineSeverity()` delegeert nu naar
  `LogLevel::fromException($e)->value` — string-output identiek aan
  vorige hardcoded versie, geen DB-migratie
- Backward-compat: DB column `error_logs.severity` blijft string;
  kolomnaam is misleidend (content = log-level) maar hernoemen kost >> baten

### Priority ✅ LIVE (commit `78fa93d`, 22-04-2026)
- `App\Enums\Priority` met cases Urgent/High/Normal/Low + `sortWeight()`
- `tests/Unit/Enums/PriorityTest.php` — per-case + ordering + pin-test
  voor de exacte sortWeight-integers (1/2/3/4) die de oude raw-SQL
  CASE matcht
- `ClaudeTask::scopeByPriority()` bouwt CASE-expression dynamisch uit
  `Priority::cases()` → `WHEN 'urgent' THEN 1 ...`. Adding a new case
  auto-extends sort, no SQL edit needed
- DB ENUM column `claude_tasks.priority` unchanged (waardes identiek)
- Bestaande `ClaudeTaskTest` blijft groen (44/44)

## Refactor-plan

Drie aparte commits, elk los testbaar:

### Commit 1 — `App\Enums\LogLevel` introduceren
1. Maak `app/Enums/LogLevel.php` met 3 cases + `fromException()` factory
2. Maak `tests/Unit/Enums/LogLevelTest.php` met per-case asserts
3. Update `ErrorLog::determineSeverity()` om `LogLevel::fromException($e)->value`
   te returnen (string-equivalent, geen contract-change)
4. Importeer + gebruik enum

### Commit 2 — `App\Enums\Priority` introduceren
1. Maak `app/Enums/Priority.php` met 4 cases + `sortWeight()`
2. Maak `tests/Unit/Enums/PriorityTest.php` met per-case asserts
3. Refactor `ClaudeTask::scopeOrderByPriority()` van raw SQL naar enum-
   gebaseerde sort:
   ```php
   $weights = collect(Priority::cases())
       ->mapWithKeys(fn ($p) => [$p->value => $p->sortWeight()])
       ->all();
   $caseExpr = collect($weights)
       ->map(fn ($w, $v) => "WHEN '{$v}' THEN {$w}")
       ->implode(' ');
   return $query->orderByRaw("CASE priority {$caseExpr} ELSE 999 END");
   ```
4. Bestaande `ClaudeTaskTest` blijft groen want string-waarden onveranderd

### Commit 3 — Documenteer de 3 taxonomies
- Deze runbook updaten naar `status: COMPLETED`
- `App\Enums\Severity` PHPDoc-blok bijwerken om naar deze runbook te wijzen

## Niet-doen

- **DB-kolom `error_logs.severity` hernoemen naar `log_level`**: vereist
  migration + data-backfill + downstream-callers (Observability,
  ErrorLog-renderers in HavunAdmin, etc.). Kosten >> baten. Naming-quirk
  is gedocumenteerd in deze runbook — voldoende.
- **`Severity` cases uitbreiden om LogLevel/Priority te omvatten**: zou
  type-veiligheid breken (een rendering-helper voor severity zou dan
  per ongeluk een Priority-waarde kunnen krijgen).
- **Backward-compat shim** (`Severity::fromLogLevel()` of vice versa):
  introduceert exact de verwarring die deze runbook wil voorkomen.

## Mutation-test impact

ErrorLog en ClaudeTask zitten in `infection-critical-paths.json5` scope
(app/Models). Per-case enum tests killen MatchArmRemoval mutations op
de nieuwe `match` expressies. Geen ignore-config-uitbreiding nodig —
de bestaande tests zouden voldoende moeten zijn na de enum-extractie.

Risico: ObservabilityService Severity-rollback (commit `de0a0ca`) leerde
dat enum-rollouts MSI kunnen droppen als tests niet bijgewerkt worden.
**Mitigatie:** schrijf de enum-tests samen met de source-changes, niet
in een aparte commit.

## Zie ook

- `App\Enums\Severity` — bestaande enum als referentie-implementatie
- `tests/Unit/Enums/SeverityTest.php` — test-pattern (per-case asserts)
- `runbooks/kwaliteit-veiligheid-systeem.md` — V&K landing page
