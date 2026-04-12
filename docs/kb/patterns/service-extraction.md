# Service Extraction Pattern

> **Probleem:** Fat service met 600+ regels bevat te veel verantwoordelijkheden.
> **Oplossing:** Extract cohesieve method groups naar helper classes in een subdirectory.

## Wanneer Toepassen

- Service > 600 regels
- Duidelijke method groups die samenhangen (berekeningen, validatie, formatting)
- Main service wordt een facade die helpers orchestreert

## Voorbeeld: JudoToernooi

**EliminatieService** (1570 → 786 regels) + 3 helpers:

```
app/Services/
├── Eliminatie/
│   ├── EliminatieCalculator.php     # score berekeningen, ranking
│   ├── EliminatieBracketBuilder.php # bracket structuur opbouwen
│   └── EliminatieValidator.php      # validatie regels
└── EliminatieService.php            # main service (786 regels)
```

## Stappen

### 1. Identificeer cohesieve method groups

Zoek methods die:
- Dezelfde data nodig hebben
- Samen worden aangeroepen
- Een duidelijk sub-domein vormen

```
calculateScore()      → EliminatieCalculator
calculateRanking()    → EliminatieCalculator
buildBracket()        → EliminatieBracketBuilder
assignPositions()     → EliminatieBracketBuilder
validateCategory()    → EliminatieValidator
validateWeightClass() → EliminatieValidator
```

### 2. Maak subdirectory

```bash
mkdir app/Services/Eliminatie/
```

### 3. Extract naar helper class

```php
// app/Services/Eliminatie/EliminatieCalculator.php
namespace App\Services\Eliminatie;

class EliminatieCalculator
{
    public function calculateScore(Match $match): int { ... }
    public function calculateRanking(Category $category): Collection { ... }
}
```

### 4. Main service als facade via composition

```php
// app/Services/EliminatieService.php
namespace App\Services;

use App\Services\Eliminatie\EliminatieCalculator;
use App\Services\Eliminatie\EliminatieBracketBuilder;
use App\Services\Eliminatie\EliminatieValidator;

class EliminatieService
{
    public function __construct(
        private EliminatieCalculator $calculator,
        private EliminatieBracketBuilder $bracketBuilder,
        private EliminatieValidator $validator,
    ) {}

    public function processCategory(Category $category): void
    {
        $this->validator->validateCategory($category);
        $bracket = $this->bracketBuilder->buildBracket($category);
        $ranking = $this->calculator->calculateRanking($category);
        // orchestratie logica blijft hier
    }
}
```

### 5. Constructor injection

Laravel's container resolved automatisch:
```php
// In controller — geen wijziging nodig
public function __construct(private EliminatieService $service) {}
```

### 6. Run tests na elke extractie

```bash
php artisan test --filter=Eliminatie
```

## Anti-Pattern

❌ **Te kleine extracties** — 100 regels is het minimum voor een helper:
```php
// FOUT: class met 2 methods en 50 regels
class EliminatieFormatter
{
    public function formatScore($score) { return number_format($score, 1); }
    public function formatTime($time) { return $time->format('H:i'); }
}
```

❌ **God helper** — één helper die alles krijgt:
```php
// FOUT: helper is net zo groot als originele service
class EliminatieHelper
{
    // 1200 regels — je hebt het probleem alleen verplaatst
}
```

✅ **Balanced extraction** — 150-400 regels per helper, duidelijk domein.

## Zie Ook

- `docs/kb/decisions/enterprise-quality-standards.md` — waarom max 600 regels
- `docs/kb/patterns/controller-splitting.md` — zelfde pattern voor controllers
- `docs/kb/patterns/model-traits.md` — zelfde pattern voor models
