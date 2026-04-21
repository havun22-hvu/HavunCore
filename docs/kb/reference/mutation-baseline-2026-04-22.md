# Mutation Testing Baseline — 22-04-2026

> **Bron:** VP-16 — tweede baseline-meting na 21/22-04-sessie.
> **Scope:** kritieke paden individueel via filter-runs (niet meer alleen
> `app/Services` brede-baseline; die komt op cron 01-07-2026).
> **Tooling:** Infection PHP 0.32.6, `infection-critical-paths.json5` voor
> pad 1 (Vault) + pad 7 (CriticalPaths), `infection.json5` voor de rest.

## Per-pad resultaten

| Pad | File(s) | Baseline (17-04) | Eind (22-04) | Target | Status |
|-----|---------|-----------------:|-------------:|-------:|:-------|
| 1 Vault | `VaultController` + 4 `Vault*` models | n.v.t. (was buiten scope) | **91 %** | 90 % | ✅ |
| 2 AIProxy | `AIProxyService.php` | 48 % | **81 %** | 90 % | ⚠️ floor |
| 3 AutoFix | `AutoFixService.php` | 28 % | **87 %** | 85 % | ✅ |
| 4 Device Trust | `DeviceTrustService.php` | 83 % | **100 %** | 90 % | ✅ |
| 5 Observability | `ObservabilityService.php` | 69 % | **100 %** | 85 % | ✅ |
| 6 Session cookies | `config/session.php` | n/a | n/a | — | ✅ (file-content + runtime tests) |
| 7 Critical-paths audit | `Services/CriticalPaths` + verify cmd | 84,85 % | **88,89 %** | 85 % | ✅ |

## Drempel-ophoging

| Datum | `minMsi` | Rationale |
|-------|---------:|-----------|
| 17-04 | 48 | Baseline (53,78 %) − 5pp flake-marge |
| 22-04 (eerste pas) | 60 | 5 paden op target; brede baseline nog niet gemeten |
| **22-04 (tweede pas)** | **70** | **Brede `app/Services` baseline-run: 74 % (gemeten deze sessie), drempel gezet op 70 om flake-marge van 4 pp te behouden** |
| (gepland 01-07) | 75 | na kwartaal-run als bevestiging dat 74 % geen uitschieter is |

## Brede `app/Services` baseline (22-04-2026)

| Metric | Waarde |
|--------|--------|
| Scope | `app/Services` minus `Chaos`, `DocIntelligence` |
| Totaal mutaties | ~700 (gelijkaardig aan 17-04) |
| Mutation Code Coverage | **100 %** (was 87,4 %) |
| **Covered Code MSI** | **74 %** (was 53,78 %) |
| Drempel `minMsi` | 70 — 4 pp onder actual |

## AIProxy — false-positive floor (9 pp onder target)

De 19 resterende escapes zijn grotendeels niet-killable zonder
infrastructuur-wijziging:

| Categorie | # escapes | Waarom niet killable |
|-----------|----------:|----------------------|
| SQLite vs MySQL integer-coercion (`(int)` casts + Increment/Decrement op SUM/COUNT) | 8 | SQLite retourneert al int; MySQL retourneert string. Zonder MySQL-fixture komt de mutatie `(int) → cast weg` door |
| Sub-ms `RoundingFamily` op `round($t * 1000)` | 6 | floor/ceil/round-verschil < 1 ms, CI-jitter-gevoelig |
| `Http::fake` negeert timeouts | 2 | `->timeout(60) ±1` niet onderscheidbaar |
| Laravel auto-injecteert `Content-Type` | 1 | `ArrayItemRemoval` harmless — header komt terug |
| `Cache::put(..., 60)` TTL ±1 | 2 | 60 s wachten niet haalbaar in unit-test |

**Voor echte 90 %:** MySQL-integration fixture (geschat 3–4 u werk —
apart CI-profiel met `service: mysql:8`, fixture DB-migraties, coverage
alleen op `AIProxyService::getUsageStats`-call-pad). Gedocumenteerd in
`runbooks/infection-setup-plan.md` §2 als open item.

## Prod-bugs gevonden door mutation-testing

- **Device Trust** — `diffInDays(now()) < 7` altijd true voor future
  dates (Carbon retourneert negatief verschil). Gecorrigeerd naar
  `->lt(now()->addDays(7))`. Commit `0906ade`.
- **AutoFix** — `AutofixProposal::isRateLimited(string $file)` crashde
  op queue-job-exceptions die geen file-frame hebben. Gecorrigeerd
  naar `?string $file`. Commit `9bc30df`.

## Zie ook

- `mutation-baseline-2026-04-17.md` — vorige meting (53,78 %)
- `runbooks/infection-setup-plan.md` — run-log per pad + timeline
- `critical-paths-havuncore.md` — audit-bewijs per pad
