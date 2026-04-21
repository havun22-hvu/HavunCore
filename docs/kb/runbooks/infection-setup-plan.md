---
title: Infection mutation-testing — setup status & next-steps plan
type: runbook
scope: havuncore
status: ACTIVE
last_reviewed: 2026-04-21
follows: "test-quality-policy.md"
related:
  - "reference/critical-paths-havuncore.md"
  - "reference/mutation-baseline-2026-04-17.md"
---

# Infection setup plan

> **Status:** Infection is reeds geinstalleerd en geconfigureerd.
> Dit document beschrijft de huidige staat, een pilot-meting op
> een kritiek pad en de vervolgstappen om de MSI-targets uit
> `critical-paths-havuncore.md` te halen.

## 1. Huidige staat (verificatie 21-04-2026)

| Onderdeel | Status |
|-----------|--------|
| `infection/infection` in `composer.json` | aanwezig (`*`, resolved to 0.32.6) |
| `infection/extension-installer` | aanwezig + allow-listed in composer config |
| Config-bestand | `infection.json5` aanwezig |
| Scope | `app/Services` minus `Chaos` + `DocIntelligence` |
| Logs | `storage/logs/infection.{log,html,json,summary.log}` |
| Drempels | `minMsi=48`, `minCoveredMsi=48` (baseline +5pp marge lager) |
| Baseline | `reference/mutation-baseline-2026-04-17.md` — 53,78 % MSI |

**Conclusie:** er is geen installatie nodig. De taak is _verhogen_ van
de MSI richting de pad-targets (85–90 %).

## 2. Pilot-meting — AIProxyService (pad 2)

### Run 1 (baseline, 21-04-2026)

```
vendor/bin/infection --filter=app/Services/AIProxyService.php \
  --threads=4 --no-progress --no-interaction
```

| Metric | Waarde |
|--------|--------|
| Totaal mutaties | 124 |
| **MSI baseline** | **48 %** |
| Mutation Code Coverage | 100 % |
| Duur | 3 min 51 s |

### Run 2 (21-04-2026, na quick-wins, commit `95fa044`)

| Metric | Waarde |
|--------|--------|
| **MSI** | **58 %** (+10 pp) |
| Mutation Code Coverage | 100 % |

### Runs 3–7 (21-04-2026, commit `65b14f5`)

8 aanvullende tests + 2 source-fixes. Iteratief gemeten:

| Run | MSI | Δ | Focus |
|-----|-----|--:|-------|
| 3 | 62 % | +4 pp | HTTP headers + URL, maxTokens default, body payload keys |
| 4 | 69 % | +7 pp | Log::error payload, CircuitBreaker failure counter reset, rate-limit default = 60 (source: `?? 60` i.p.v. config()-default-arg) |
| 5 | 73 % | +4 pp | `?? 0` defaults in chat() return, execution-time `1000 ± 1` band |
| 6 | 75 % | +2 pp | logUsage subclass-access, match default-arm, strict === on stats sums, Log::warning catch branch |
| 7 | **81 %** | +6 pp | `?? 0` defaults in logUsage DB row (completed the zero-default mutation family) |

**Totaal sessie: 48 % → 81 % (+33 pp). Gap tot target 90 % = 9 pp.**

### Resterende ~19 escapes = Infection false-positive floor

Deze mutaties kunnen niet stabiel gekilled worden zonder de
test-harness te veranderen (niet gerechtvaardigd voor MSI-padding):

| Regel | Mutator | Waarom niet killable |
|-------|---------|----------------------|
| 58 | `Content-Type` ArrayItemRemoval | Laravel injecteert auto op POST+array |
| 63 | `timeout(60) ±1` | `Http::fake` honoreert geen timeouts |
| 98, 134, 174 | RoundingFamily op `round($t * 1000)` | floor/ceil/round-verschil < 1ms, CI-jitter-instabiel |
| 119 | `Cache::put(..., 60)` TTL ±1 | niet testbaar binnen unit-test zonder 60s wachten |
| 170–173 | `(int)` casts op SUM/COUNT | SQLite returns al ints; alleen MySQL-test vangt dit |
| 150 | MatchArmRemoval (resterend) | resterende arm na dataprovider-pass |

Voor 90 % target: **accepteer 81 %** of investeer in MySQL-integration
fixture (~3-4 u) voor pad-niveau MSI-claim richting 90 %.

### Escaped-categorieen (samenvatting uit de run-log)

- **Usage-logging branch (regel 128-138):** `round($executionTime * 1000)` —
  `Multiplication` en `RoundingFamily` muteren ongemoeid; `ArrayItem` en
  `ArrayItemRemoval` op `tenant`/`error` keys komen ongemerkt door.
- **`getUsageStats()` match-arms (regel 148):** `hour`/`day`/`week`/`month`
  kunnen alle vier verwijderd worden zonder dat een test breekt — geen
  assertions per periode.
- **Return-array `getUsageStats()` (regel 168-172):** `CastInt`,
  `IncrementInteger`, `DecrementInteger`, `RoundingFamily` op
  `total_*_tokens` + `avg_execution_time_ms` ontsnappen allemaal —
  assertions checken alleen dat de keys bestaan, niet de cast/default.
- **Sichtbaarheid (regel 191):** `getDefaultSystemPrompt()` mag van
  `protected` naar `private` zonder dat een test het merkt.

### Quick-win test-lijst (afgerond in Run 2)

1. ✅ `test_chat_logs_execution_time_in_milliseconds_not_seconds` —
   usleep(50ms) bewijst `round($t * 1000)` mutaties.
2. ✅ `test_chat_logs_usage_record_contains_each_documented_key` —
   per-key `===` op logUsage payload.
3. ✅ `test_usage_stats_period_arms_resolve_correct_since_window` —
   8-row data-provider dekt alle hour/day/week/month grenzen.
4. ✅ `test_usage_stats_empty_window_returns_strict_zero_integers` —
   `=== 0` + `assertIsInt` dekt CastInt / Increment / Decrement.
5. ✅ `test_default_system_prompt_is_reachable_by_a_subclass` —
   anonymous subclass vangt `protected→private` mutatie.

**Source-fix (als nevenresultaat):** `AIProxyService::getUsageStats`
cast nu `avg_execution_time_ms` expliciet naar `(int)`. `round()`
returnde float; documentation en callers verwachten integer ms.

### Nog openstaand voor 90 % (HTTP request-config)

- Assert op `maxTokens` default via `Http::assertSent` body-check.
- Assert op `timeout(60)` via request-introspection.
- Assert op `Content-Type` + `anthropic-version` headers via
  `Http::assertSent(fn ($req) => $req->hasHeader(...))`.

## 3. Vervolgstappen — per kritiek pad

| Pad | Target MSI | Huidige meting | Eerste / volgende stap |
|-----|-----------|----------------|-------------|
| 1 Vault | 90 % | _nog niet los gemeten_ | filter-run op `app/Services/Vault` |
| 2 AIProxy | 90 % | **58 %** (21-04 na quick-wins) | HTTP-config mutaties via `Http::assertSent` |
| 3 AutoFix | 85 % | deel van baseline (53 %) | escaped-list uit baseline uitwerken |
| 4 QR Auth / Device Trust | 90 % | baseline noemt hotspots rond regel 309-419 | per-key assertions in device-update payload |
| 5 Observability | 85 % | baseline noemt `getSystemMetrics()` regels 178-179 | disk-bytes unit-test met fixture |
| 7 Critical-paths audit | 85 % | buiten scope `app/Services` — aparte filter nodig | `infection.json5` scope uitbreiden met `app/Console` + `app/Services/CriticalPaths` |

## 4. CI-integratie (bestaand + uitbreiding)

- Workflow aanwezig: `gh workflow run mutation-test.yml` (referentie in
  baseline-doc).
- **Toevoeging:** per-pad filter-runs als aparte job, met `--min-msi`
  gelijk aan de target in `critical-paths-havuncore.md`. Falen =
  PR-block, zonder hele-scope-run te eisen (snelheid).

Voorbeeld job-matrix (pseudo):

```yaml
matrix:
  path:
    - { name: aiproxy,     filter: app/Services/AIProxyService.php, min_msi: 90 }
    - { name: vault,       filter: app/Services/Vault,              min_msi: 90 }
    - { name: devicetrust, filter: app/Services/DeviceTrustService.php, min_msi: 90 }
    - { name: autofix,     filter: app/Services/AutoFixService.php, min_msi: 85 }
```

## 5. Timeline-schatting

| Week | Actie | Inspanning |
|------|-------|------------|
| 1 | AIProxy quick-wins (4 test-groepen) → MSI ~75 % | 2 u |
| 2 | Vault + DeviceTrust filter-run + quick-wins → MSI ~80 % | 3 u |
| 3 | Observability disk-bytes + getSystemMetrics tests | 2 u |
| 4 | AutoFix unit-level test (gap uit critical-paths doc, pad 3) | 2 u |
| 5 | CI matrix-job per kritiek pad live | 1 u |
| 6 | `minMsi` globaal 48 → 60 verhogen; baseline-doc vervangen | 0,5 u |

**Totaal eerste kwartaal:** ~10,5 u focus-werk om alle kritieke paden op
of dicht tegen target te krijgen.

## 6. Niet-doen (scope-bewaking)

- **Geen** mutation-runs forceren op `Chaos` / `DocIntelligence` —
  bewust uitgesloten in config.
- **Geen** coverage-padding om MSI kunstmatig op te krikken
  (VP-17 / `feedback_durable_tests_only`).
- **Geen** assertions aanpassen om mutaties te killen zonder
  echte gedrags-check (VP-17, `feedback_no_test_repair`).

## 7. Referenties

- `docs/kb/reference/test-quality-policy.md` §7 — MSI als primaire metric.
- `docs/kb/reference/critical-paths-havuncore.md` — pad-targets.
- `docs/kb/reference/mutation-baseline-2026-04-17.md` — vorige meting.
- `infection.json5` — actuele config.
