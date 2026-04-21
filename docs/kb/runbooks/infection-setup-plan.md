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

## 2b. Pilot-meting — DeviceTrustService (pad 4, 21-04-2026)

### Run 1 (baseline)

```
vendor/bin/infection --filter=app/Services/DeviceTrustService.php \
  --threads=4 --no-progress --no-interaction
```

| Metric | Waarde |
|--------|--------|
| Totaal mutaties | 66 |
| **MSI baseline** | **83 %** |
| Covered Code MSI | 83 % |
| Escaped | 11 |
| Duur | 3 min 56 s |

### Run 2 (eind, commit `pending`)

10 gerichte tests toegevoegd + 1 source-fix (zie hieronder).

| Metric | Waarde |
|--------|--------|
| Totaal mutaties | 67 |
| **MSI** | **100 %** (+17 pp) |
| Escaped | 0 |

### Source-fix (pad 4)

`DeviceTrustService::verifyToken()` bevatte
`$device->expires_at->diffInDays(now()) < 7`. Voor een niet-verlopen
device is `expires_at` in de toekomst en Carbon geeft dan een negatief
verschil (`-7`, `-30`, etc.). Een negatief getal is altijd `< 7`, dus
de conditie was permanent true — elke verify extendde trust. Dit werd
zichtbaar toen de 7-dag-boundary mutation-test beide takken probeerde
te controleren. Gecorrigeerd naar
`$device->expires_at->lt(now()->addDays(7))` — directe datum-
vergelijking is robuust en leesbaar. Contract is nu echt "extend als
minder dan 7 dagen tot expiry".

### Killed escapes (tests toegevoegd)

| Regel | Mutator | Test |
|-------|---------|------|
| 26 | MethodCallRemoval `touchUsed` | `..._calls_touch_used_updating_ip_and_last_used_at` |
| 29 | LessThan (`< 7` / `<= 7`) | `..._extend_boundary_at_exactly_seven_days_does_not_extend` + `..._at_six_days_does_extend` |
| 49 | ArrayItem (`=>` / `>`) | `..._response_contains_expires_at_as_iso_string_key` |
| 66 | NullSafeMethodCall | `..._handles_null_last_used_at_without_crashing` |
| 91 + 97 | MethodCallRemoval + ArrayItemRemoval (revokeDevice log) | `..._revoke_device_writes_access_log_with_device_id_metadata` |
| 120 + 126 | idem (revokeAllDevices log) | `..._revoke_all_devices_writes_access_log_with_revoked_count_metadata` |
| 155 | MethodCallRemoval (logout log) | `..._logout_writes_access_log_with_logout_action` |
| 171 | Increment/Decrement `int $limit = 20` | `..._get_access_logs_default_limit_is_exactly_twenty` |

## 3. Vervolgstappen — per kritiek pad

| Pad | Target MSI | Huidige meting | Eerste / volgende stap |
|-----|-----------|----------------|-------------|
| 1 Vault | 90 % | **91 %** (21-04, commit `1e3a78c` — 4 runs via `infection-critical-paths.json5`) | target gehaald |
| 2 AIProxy | 90 % | **81 %** (21-04, commit `65b14f5` — 7 runs) | MySQL-integration fixture nodig voor SUM/COUNT CastInt-mutaties |
| 3 AutoFix | 85 % | **87 %** (22-04, commit `9bc30df` — 6 runs, +24 tests, type-fix in `AutofixProposal::isRateLimited`) | target gehaald |
| 4 QR Auth / Device Trust | 90 % | **100 %** (21-04 sessie, commit `0906ade` — 67/67 killed) | target ruim gehaald |
| 5 Observability | 85 % | **100 %** (21-04, commits `f23b17d` + `40541fd` — 220/220 killed, 0 escaped) | — (target ruim gehaald; fixture-tests voor `getDatabaseSize()`, invariant-asserts voor `getSystemHealth()`) |
| 7 Critical-paths audit | 85 % | **88,89 %** (21-04, commit `pending` — 176/198 killed) | target gehaald; `app/Console` toegevoegd aan `infection-critical-paths.json5` scope |

### Pad 7 run-log (21-04-2026)

| Run | Killed | Escaped | MSI | Actie |
|-----|--------|---------|-----|-------|
| 1 (baseline) | 168 | 30 | **84,85 %** | scope uitbreid naar `app/Console`; bestaande 26 tests dekken 85 % van mutaties |
| 2 (+15 tests) | 176 | 21 | **88,89 %** | DocParser edge-cases (preamble/unicode/anchor/trim/null-continue), ReferenceChecker leading-slash + list-keys + forward-slashes, Command renderText pinning (glyph ✓/✗, summary, error-line, ran-OK/FAILED), siblings-after-missing, zero-totals schema |

**Killed escapes (Run 1 → Run 2):**
- `CriticalPathsVerifyCommand:46` 3× DecrementInteger (totals schema) → `test_missing_doc_json_reports_full_zero_totals`
- `CriticalPathsVerifyCommand:48` Continue→break → `test_all_flag_continues_past_a_missing_doc`
- `CriticalPathsVerifyCommand:65` MethodCallRemoval (`renderText`) → `test_text_output_renders_project_header_and_summary`
- `CriticalPathsVerifyCommand:98` Continue→break → `test_missing_reference_does_not_abort_sibling_processing`
- `CriticalPathsVerifyCommand:146/154/161/167` MethodCallRemoval + Ternary → `test_text_output_*` set
- `DocParser:31` FalseValue → `test_preamble_before_first_pad_is_ignored`
- `DocParser:39` FalseValue → `test_new_pad_resets_tests_section_flag`
- `DocParser:44` Continue→break → `test_lines_before_first_pad_do_not_abort_parsing`
- `ReferenceChecker:57` UnwrapArrayValues → `test_check_all_returns_list_with_sequential_integer_keys`

**Resterende escapes (21) = grotendeels Infection false-positives:**
- `TestRunner.php:27` 6× duration-rounding (Plus-Minus/Increment/Decrement/Multiplication/RoundingFamily op sub-ms jitter — niet deterministisch kill-baar)
- `TestRunner.php:34` + `DocParser.php:77` CastString (het gecaste type is al identiek — mutation is no-op)
- `DocParser.php:47/52/56` PregMatchRemoveFlags `/u`-vlag (fixture-paden zijn ASCII, dus `/u` en `//` matchen beide)
- `DocParser.php:38` UnwrapTrim (regex capture strip al whitespace; trim is defensief)
- `ReferenceChecker.php:22/62` UnwrapLtrim/UnwrapRtrim (dubbele separator wordt door OS gededupt)
- `ReferenceChecker.php:64` UnwrapStrReplace (testbed is Linux — backslash komt niet voor)
- `CriticalPathsVerifyCommand.php:125` UnwrapStrReplace doc-path (idem: Linux testbed, geen backslashes)
- `CriticalPathsVerifyCommand.php:180/181` resolveProjectRoot LogicalAnd / ReturnRemoval (`config()` is null in testbed, dus de if-body wordt niet geraakt)
- `CriticalPathsVerifyCommand.php:148` renderText continue-after-error (`--all` + missing-doc pad moeilijk vast te pinnen)
- `CriticalPathsVerifyCommand.php:206` PregMatchRemoveDollar (glob-pattern eindigt altijd op `.md`, dus `$`-anchor is redundant)

Diminishing returns: elke nieuwe test kost ~1 assertion per escape en
levert ≤0,5 pp MSI. 88,89 % is ruim boven target 85 % — stop hier.

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
