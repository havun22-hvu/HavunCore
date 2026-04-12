# Runbook: Test Coverage Normen

> Verplichte Havun normen voor code coverage en hoe te meten/verbeteren.

## Verplichte Norm

**Alle projecten: minimaal 82.5% line coverage** (target 90%+).

## Huidige Stand (april 2026)

| Project | Coverage | Tests | Status |
|---------|----------|-------|--------|
| HavunCore | 98.4% | ~150 | ✅ Boven target |
| SafeHavun | 95.9% | ~280 | ✅ Boven target |
| HavunAdmin | 92.6% | ~450 | ✅ Boven target |
| Studieplanner | 88.7% | ~320 | ✅ Boven norm |
| Herdenkingsportaal | 85.2% | ~400 | ✅ Boven norm |
| JudoToernooi | 82.5% | ~300 | ✅ Op norm |
| Infosyst | 82.5% | ~100 | ✅ Op norm |
| JudoScoreBoard | n/a | n/a | Frontend (apart) |

## Coverage Meten

```bash
# Vereist: pcov of xdebug extensie
php artisan test --coverage

# Met minimale drempel (CI/CD):
php artisan test --coverage --min=82.5

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

- `docs/kb/decisions/enterprise-quality-standards.md` — waarom 82.5%
- `docs/kb/patterns/regression-guard-tests.md` — regression guard tests
- `docs/kb/runbooks/github-testing-plan.md` — CI/CD testing plan
