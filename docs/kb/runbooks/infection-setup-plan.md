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

Gedraaid 21-04-2026 met:

```
vendor/bin/infection \
  --filter=app/Services/AIProxyService.php \
  --threads=4 --no-progress --no-interaction
```

| Metric | Waarde |
|--------|--------|
| Totaal mutaties | 124 |
| Killed (test framework) | 59 |
| Escaped | 63 |
| Errored / Timed Out / Skipped | 0 |
| Ignored | 2 |
| **MSI pilot** | **~48,4 %** |
| **Target (pad 2)** | **90 %** |
| **Gap** | **~42 pp** |
| Duur | 3 min 51 s (threads=4) |

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

### Quick-win test-lijst (pad 2 naar 90 %)

1. `tests/Unit/Services/AIProxyServiceTest.php`: nieuwe datapoint-set
   voor `execution_time_ms` — assert dat waarde een integer is **en**
   in ms-schaal (`>= 1000` voor seconde-lange call).
2. Losse test per match-arm: `hour/day/week/month` each produceren een
   andere `since`-waarde (stub `now()`).
3. Return-array hard-assertions: exact `0` voor lege stats, exact
   verwachte integer voor gevulde stats; `is_int()` + `===` in plaats
   van `>=`.
4. Subclass-extensie-test die `getDefaultSystemPrompt()` overschrijft
   — vangt `protected→private` mutatie.

## 3. Vervolgstappen — per kritiek pad

| Pad | Target MSI | Huidige meting | Eerste stap |
|-----|-----------|----------------|-------------|
| 1 Vault | 90 % | _nog niet los gemeten_ | filter-run op `app/Services/Vault` |
| 2 AIProxy | 90 % | 48 % (pilot 21-04) | quick-wins hierboven |
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
