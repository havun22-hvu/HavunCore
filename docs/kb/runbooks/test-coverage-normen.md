# Runbook: Test Coverage Normen

> Verplichte Havun normen voor code coverage en hoe te meten/verbeteren.

## Verplichte Norm

**Alle projecten: minimaal 80% line coverage** (target 90%+).

## Huidige Stand (16 april 2026, PCOV + Jest)

| Project | Coverage | Tests | Status |
|---------|----------|-------|--------|
| SafeHavun | 94,22% | 302 | ✅ Mission-critical |
| JudoScoreBoard | 93,42% | (Jest 12-04) | ✅ Mission-critical |
| Infosyst | 91,51% | 834 | ✅ Mission-critical |
| HavunVet | 90,87% | 276 | ✅ Mission-critical |
| JudoToernooi | 89,84% | 3.257 | ✅ Enterprise |
| HavunAdmin | 89,75% | 3.180 | ✅ Enterprise |
| HavunCore | 87,4% | 795 | ✅ Enterprise |
| Studieplanner | 82,67% | 223 (Jest 12-04) | ✅ Enterprise |
| Herdenkingsportaal | 79,05% | 208+ | ⚠️ Bijna norm (1% te gaan) |

**Totaal:** 9.600+ tests, 17.000+ assertions. 8 van 9 projecten boven 80%.

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

- `docs/kb/decisions/enterprise-quality-standards.md` — waarom 80%
- `docs/kb/patterns/regression-guard-tests.md` — regression guard tests
- `docs/kb/runbooks/github-testing-plan.md` — CI/CD testing plan
