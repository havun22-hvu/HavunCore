---
title: Runbook: Test Coverage Normen
type: runbook
scope: havuncore
last_check: 2026-07-24
---

# Runbook: Test Coverage Normen

> Hoe je dekking meet en beoordeelt. **Er is sinds 24-07-2026 geen drempel meer** — zie hieronder.

## De norm (gewijzigd 24-07-2026)

**Er is geen coveragedrempel meer.** Niet 80%, niet 85%, geen `--min=`. In plaats daarvan: *zo
hoog mogelijke dekking met uitsluitend zinvolle tests* — het percentage is de uitkomst, niet het
doel. Besluit + onderbouwing: [`decisions/coverage-drempel-vervalt-2026-07-24.md`](../decisions/coverage-drempel-vervalt-2026-07-24.md).

Een test telt alleen als hij een **contract, invariant, bug-regressie of domeinregel** vastlegt —
de vier categorieën uit [`zinvolle-tests.md`](../patterns/zinvolle-tests.md). Padding schrappen mag
het percentage laten zakken; dat is winst.

**Waar je wél op stuurt:**

| Signaal | Wat het betekent |
|---|---|
| Betaal-/auth-/tenant-code lager gedekt dan modellen | Er is op gemak gedekt, niet op risico — dit is het belangrijkste signaal |
| Een testbestand met een percentage in de naam of het docblock | Padding by design (`Push90Test`, `Last825Test`, `MaxServiceCoverageTest`) |
| `assertStatus(500)` als verwacht resultaat | Een kapot foutpad vastgelegd als norm — [`coverage-test-cementeert-bug.md`](../patterns/coverage-test-cementeert-bug.md) |
| Coverage-cijfer zonder meetdatum in een `CLAUDE.md` | Een claim, geen feit |

## Stand per project

Cijfers verouderen; ze staan daarom **niet meer** in dit runbook. Per project met meetdatum in
`docs/testschuld.md` van dat project, en samengevat in de HavunCore-handover.

Gemeten 24-07-2026: Studieplanner-api **91,9% / 322 tests** — waarvan een aanwijsbaar deel padding.

## Coverage Meten

```bash
# Vereist: pcov of xdebug extensie
php artisan test --coverage

# Coverage rapport als XML (voor CI):
php artisan test --coverage-clover=coverage.xml
```

### pcov vs xdebug

- **pcov** (aanbevolen): sneller, alleen coverage, geen debugger
- **xdebug**: trager maar heeft ook debugger/profiler
- Installatie: `pecl install pcov` of in `php.ini`: `extension=pcov`

## Test Patterns per Project

### Standaard Laravel Test

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_example(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
    }
}
```

### External API's mocken

```php
// HTTP facade fake voor externe API calls
Http::fake([
    'api.mollie.com/*' => Http::response(['status' => 'paid'], 200),
    'gateway.arweave.net/*' => Http::response(['id' => 'tx123'], 200),
]);
```

### Factories gebruiken

```php
// Altijd factories, nooit handmatige DB inserts
$memorial = Memorial::factory()
    ->hasPhotos(3)
    ->create(['status' => 'published']);
```

## Project-Specifieke Aandachtspunten

### HavunAdmin

- **TenantComposer cache**: kan tests beïnvloeden, mock of clear cache in setUp()
- **Langzame tests**: gebruik `--parallel` of `paratest` voor snelheid
- **Paratest**: `composer require brianium/paratest --dev`, dan `php artisan test --parallel`

### Herdenkingsportaal

- **Memory**: `memory_limit=2G` nodig in `php.ini` voor test suite
- **Imagick/GD**: image processing tests falen zonder extensions → mock of skip
- **~90% blokkade**: Imagick/GD dependency blokkeert hogere coverage, mock deze calls

### JudoToernooi

- **SQLite CHECK constraints**: SQLite is strenger dan MySQL op CHECK constraints, gebruik `$this->app['config']->set('database.default', 'mysql')` of aparte test database
- **Python solver**: `eliminatie_solver.py` is niet testbaar via PHPUnit → apart testen
- **Auth guard**: altijd `auth('organisator')` gebruiken, niet `auth()`

## Verboden

⛔ **Tests NOOIT op staging of production draaien!**

- JudoToernooi: `.env` op server overschrijft SQLite config → production database wordt gebruikt
- RefreshDatabase migreert en wiped de database
- Altijd lokaal testen, coverage in CI/CD pipeline meten

## Zie Ook

- `docs/kb/patterns/zinvolle-tests.md` — wat wel/niet testen (kernregel: geen padding)
- `docs/kb/decisions/coverage-drempel-vervalt-2026-07-24.md` — waarom de drempel verviel
- `docs/kb/runbooks/github-testing-plan.md` — CI/CD testing plan
