---
title: AIProxy MySQL-fixture plan — Infection MSI 81 → 90 %
type: runbook
scope: havuncore
last_check: 2026-04-22
---

# AIProxy MySQL-fixture plan — Infection MSI 81 → 90 %

> **Status:** Plan (geen code) — 2026-04-21
> **Scope:** `app/Services/AIProxyService.php::getUsageStats()`
> **Doel:** de 9 pp gap dichten die ontstaat doordat `(int)` casts op
> `SUM(...)` / `COUNT(*)` / `AVG(...)` onder SQLite al als int binnenkomen
> en dus onkilbaar zijn voor Infection.

## Probleem in één alinea

SQLite geeft `COUNT`/`SUM` terug als native PHP int (SQLite is type-loos,
PDO levert int). MySQL's `mysqlnd` driver levert diezelfde aggregaten als
`string` zolang `PDO::ATTR_STRINGIFY_FETCHES` default is en de kolom
geen `SUM(... AS UNSIGNED)` cast heeft. De 5 `(int)` casts in de
return-array van `getUsageStats()` zijn daarom **semantisch nodig in
productie** maar **overbodig in tests** — Infection verwijdert ze
(`CastInt` mutator) zonder dat er een assertion faalt. Resultaat: MSI
blijft op 81 % steken terwijl de target 90 % is.

## Wat is al bekend

| Component | Status |
|-----------|--------|
| `phpunit.xml` | `DB_CONNECTION=sqlite`, `:memory:` — hard-coded |
| `.env.testing` | idem SQLite in-memory |
| `tests/Unit/Services/AIProxyServiceTest.php` | gebruikt `RefreshDatabase` + `AIUsageLog::query()->insert(...)` |
| `.github/workflows/tests.yml` | enkel pdo_sqlite extension |
| `.github/workflows/mutation-test.yml` | enkel pdo_sqlite; `aiproxy` matrix-pad `min_msi: 75` (gap met target 90) |
| `composer.json` | géén expliciete `ext-pdo_mysql` requirement |
| `infection-critical-paths.json5` | `testFrameworkOptions=--testsuite=Feature,Unit --no-coverage` |

De 5 betrokken return-keys zitten in `getUsageStats()` regel 169-175
(totaal 5 CastInt-mutations + round/AVG nuances).

## Afwegingen

### Optie A — CI-only MySQL (GitHub Actions service)

**Hoe:** GitHub Actions `services.mysql: { image: mysql:8, env: {...} }`
in een **nieuw** matrix-pad of aparte job `aiproxy-mysql-msi`. Unit-tests
zelf blijven op SQLite. Alleen die ene mutation-run tegen MySQL.

- Pro: productie-parity, geen fragile mocks, geen dev-afhankelijkheid
  voor Henk (geen lokaal MySQL nodig).
- Pro: scope blijft chirurgisch — enkel `AIProxyService.php`
  getargeteerd, andere paden blijven snel op SQLite.
- Con: extra ~60 s opstarttijd per run voor MySQL health-check +
  migraties; Infection wordt trager.
- Con: `pdo_mysql` extension toevoegen aan `setup-php`; requires
  een migratie-run tegen echte DB (geen `:memory:`).
- Con: tests moeten `RefreshDatabase` tolerant blijven (Laravel ondersteunt
  het prima op MySQL, maar de run is per-test trager dan SQLite).

### Optie B — Dual-driver lokaal + CI (conditioneel skip)

**Hoe:** in `AIProxyServiceTest::setUp()` detect `env('DB_CONNECTION') === 'mysql'`;
dedicated subset tests `test_usage_stats_casts_*` alleen runnen als MySQL
beschikbaar, anders `markTestSkipped('MySQL required for CastInt kill')`.

- Pro: developer kan lokaal MySQL aanzetten en de run bevestigen.
- Con: Henk heeft **geen** MySQL lokaal op Windows; skipped tests ≠
  gedekte mutations → Infection stijgt pas als CI 'm ook runt → verkapt
  Optie A met meer ceremoniële spullen.
- Con: twee DB-configs bijhouden is onderhoudsoverhead (zie 5 bescherm-
  lagen; meer config = meer drift-risico).

### Optie C — Mock / stub `AIUsageLog` met string-typed stats

**Hoe:** vervang `AIUsageLog::where(...)->...->first()` via
`Mockery::mock('overload:App\Models\AIUsageLog')` of via een locally
bound `resolve(AIUsageLog::class, fn() => new FakeStatsObject())` die
`stdClass` met **string**-properties retourneert (zoals mysqlnd zou doen).

- Pro: nul CI-impact, nul extra infra, runt in milliseconden.
- Pro: bewijst letterlijk de `(int)` cast semantiek zonder echte DB.
- Pro: past op het SaaS-principe — cast moet werken voor **alle**
  klanten/databases; een typed stub is dus eigenlijk de juiste unit-
  grens (we testen `getUsageStats`, niet de Eloquent-driver).
- Con: mocking Eloquent static queries is rommelig; `overload:` vereist
  test-isolatie (eigen process).
- Con: makkelijker alternatief: refactor van `getUsageStats()` om de
  query achter een kleine collaborator te zetten (`AIUsageStatsReader`)
  die injectable is — maar dat is wél broncode-wijziging.
- Con: risico op "mock-theater" — je test wat je mockt. VP-17 /
  durable-tests: een test die productie-drift (bv. mysqlnd
  stringify-flag uit) niet vangt is minder waardevol dan A.

## Aanbevolen: Optie A (CI-only MySQL service)

**Reden in drie regels:**

1. **SaaS-mindset:** productie draait op MySQL — de test moet dat ook.
   Optie C bewijst alleen wat we *denken* dat MySQL doet.
2. **Onderhoudsminimaal:** één extra CI-job, geen lokale dev-setup,
   geen mock-scaffolding in code die fragile is.
3. **Chirurgisch:** alleen de AIProxy mutation-job is trager; Unit/Feature
   tests en overige matrix-paden blijven SQLite + snel.

## Concrete stappen (Optie A, volgorde voor volgende sessie)

### Stap 1 — phpunit-groep voor MySQL-gevoelige tests

In `tests/Unit/Services/AIProxyServiceTest.php`:

- Voeg PHPUnit-attribute `#[Group('mysql-fixture')]` toe aan:
  - `test_usage_stats_returns_exact_integer_sums_not_rounded`
  - `test_usage_stats_unknown_period_falls_back_to_day_window`
  - eventueel een nieuwe `test_usage_stats_casts_string_aggregates_to_int`
    die expliciet assert `assertIsInt` + `assertSame(0, $stats['total_requests'])`
    voor de lege-tenant variant.
- Laat alle andere tests ongemoeid (zij blijven op SQLite draaien).

Rationale: de `@group` label laat ons **zelfde testbestand** op twee
drivers runnen zonder dubbele fixtures te hoeven bijhouden.

### Stap 2 — extra GitHub Actions job `aiproxy-mysql-msi`

Nieuw matrix-item in `.github/workflows/mutation-test.yml`:

```yaml
aiproxy-mysql-msi:
  name: MSI gate — aiproxy (MySQL)
  runs-on: ubuntu-latest
  timeout-minutes: 25
  services:
    mysql:
      image: mysql:8.0
      env:
        MYSQL_ROOT_PASSWORD: root
        MYSQL_DATABASE: havuncore_test
      ports: ['3306:3306']
      options: >-
        --health-cmd="mysqladmin ping -h 127.0.0.1 -proot"
        --health-interval=10s --health-timeout=5s --health-retries=10
  steps:
    - uses: actions/checkout@v4
    - uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: mbstring, pdo_mysql, sqlite3, pdo_sqlite
        coverage: pcov
    - name: .env met MySQL
      run: |
        cp .env.example .env
        echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
        echo "DB_CONNECTION=mysql"                       >> .env
        echo "DB_HOST=127.0.0.1"                         >> .env
        echo "DB_PORT=3306"                              >> .env
        echo "DB_DATABASE=havuncore_test"                >> .env
        echo "DB_USERNAME=root"                          >> .env
        echo "DB_PASSWORD=root"                          >> .env
    - run: composer install --no-interaction --prefer-dist
    - run: php artisan migrate --force
    - name: Infection — aiproxy (MySQL)
      run: |
        vendor/bin/infection \
          --configuration=infection-critical-paths.json5 \
          --filter="app/Services/AIProxyService.php" \
          --threads=4 \
          --min-msi=90 --min-covered-msi=90 \
          --logger-github --no-progress --no-interaction
```

**Belangrijke nuances:**

- De bestaande `aiproxy` matrix-entry blijft (min_msi: 75, SQLite) —
  géén regressie voor de snelle PR-gate. De nieuwe job is de
  **90-%-gate** tegen MySQL.
- Eventueel: verplaats de SQLite-aiproxy entry naar `min_msi: 81`
  (huidige floor) en laat de MySQL-job de 90-ceiling doen. Dat maakt
  beide runs stabiel + detecteert regressies onafhankelijk.

### Stap 3 — override `DB_CONNECTION` in phpunit.xml voor MySQL-job

Optie 1: `phpunit.xml` ongewijzigd laten en CI-env via `.env` forceren
(Laravel `--env=testing` leest `.env.testing` maar Infection runt
default-env). Stop de `<env name="DB_CONNECTION" value="sqlite"/>`
regel in een `<php>`-block dat via `PHPUNIT_DB_CONNECTION` env te
overriden is — of makkelijker:

Optie 2: maak `phpunit.mysql.xml` als thin wrapper die `<env
name="DB_CONNECTION" value="mysql"/>` zet, en verwijs Infection via
`--test-framework-options='--configuration=phpunit.mysql.xml'`.

**Aanbevolen:** optie 2 — expliciet, geen env-magie, reviewable.

### Stap 4 — migratie `ai_usage_logs` op MySQL valideren

Check dat `database/migrations/*_create_ai_usage_logs_table.php`:

- `input_tokens`, `output_tokens`, `total_tokens` als `unsignedInteger`
  of `unsignedBigInteger` staan (niet `string`).
- `execution_time_ms` idem.
- Geen `->default(0)` op kolommen die in de test als `null` worden
  verwacht (anders breekt `(int) ($stats->total_input_tokens ?? 0)`-tak
  stilletjes).

Geen wijziging verwacht — gewoon controleren.

### Stap 5 — lokaal droogtest (Henk's Windows)

Niet van toepassing. Henk heeft geen MySQL lokaal; deze job wordt
alleen in CI bewezen. **Dit is conform `feedback_claude_owns_infra.md`:**
Claude runt 'm via GitHub Actions, Henk hoeft niets lokaal op te starten.

### Stap 6 — MSI-target stapsgewijs

1. PR met job toegevoegd, `--min-msi=81` (behoud huidige floor, CI
   groen).
2. Na groen CI: verhoog naar 85, check resultaten.
3. Na groen bij 85: naar 90 (eind-target).

Rationale: grote sprongen → CI-frustratie; incrementeel conform
`feedback_weekly_cadence.md`.

## Geschatte inspanning

| Taak | Tijd |
|------|------|
| `phpunit.mysql.xml` + `#[Group]` attributes | 15 min |
| GH Actions job schrijven + mysql service tunen | 30 min |
| Eerste CI-run debuggen (health-check timing, migrate flaky) | 30-60 min |
| Stapsgewijs MSI 81 → 85 → 90 (over 1-3 runs) | 30 min |
| **Totaal realistisch** | **2 - 2,5 uur** |

## Open vragen (aan Henk)

1. **Mag de bestaande `aiproxy` SQLite-entry omhoog naar `min_msi: 81`?**
   Alternatief: hem laten staan op 75 als tweede-verdedigingslinie. Mijn
   voorstel: 81 (geen reden voor floor onder de echte huidige stand).
2. **Nieuwe MySQL-job alleen op `pull_request` of óók op cron?** Voorstel:
   zelfde triggers als de rest van `mutation-test.yml` (PR + cron +
   workflow_dispatch). Kosten zijn marginaal (één mysql-container/run).
3. **Accepteer je dat de MySQL-job CI-runtime met ~4-6 min verlengt?**
   Kan ook parallel draaien (fail-fast: false) zodat andere paden niet
   wachten.

## Links

- Bron: `app/Services/AIProxyService.php:148-176`
- Testen: `tests/Unit/Services/AIProxyServiceTest.php:418-448`
- CI: `.github/workflows/mutation-test.yml:83-154`
- Config: `infection-critical-paths.json5`, `phpunit.xml`
- Kwaliteitsnorm: `docs/kb/reference/test-quality-policy.md` (mutation-focus)
- Mutation-docs: `docs/kb/runbooks/mutation-testing-infection.md`
