# Controller Splitting Pattern

> **Probleem:** Fat controller met 800+ regels is ononderhoudbaar en moeilijk testbaar.
> **Oplossing:** Split in subdirectory controllers per feature group.

## Wanneer Toepassen

- Controller > 800 regels
- Methods zijn logisch te groeperen per feature (upload, publish, settings, etc.)
- Meerdere developers werken aan dezelfde controller

## Voorbeeld: Herdenkingsportaal

**MemorialController** (4602 → 716 regels) gesplitst in 8 controllers:

```
app/Http/Controllers/
├── Memorial/
│   ├── MemorialUploadController.php      # foto/video uploads
│   ├── MemorialMonumentController.php    # monument beheer
│   ├── MemorialPublishController.php     # publicatie workflow
│   ├── MemorialPrivacyController.php     # privacy instellingen
│   ├── MemorialArweaveController.php     # blockchain archivering
│   ├── MemorialShareController.php       # delen functionaliteit
│   ├── MemorialQrController.php          # QR code generatie
│   └── MemorialSettingsController.php    # overige instellingen
├── MemorialController.php                # CRUD basis (716 regels)
└── Concerns/
    └── MemorialAuthorizationTrait.php    # gedeelde auth helpers
```

## Stappen

### 1. Identificeer method groups

Groepeer methods die bij hetzelfde feature horen:
```
upload()          → MemorialUploadController
storePhoto()      → MemorialUploadController
deletePhoto()     → MemorialUploadController
publishMemorial() → MemorialPublishController
unpublish()       → MemorialPublishController
```

### 2. Maak subdirectory

```bash
mkdir app/Http/Controllers/Memorial/
```

### 3. Verplaats methods fysiek

```php
// app/Http/Controllers/Memorial/MemorialUploadController.php
namespace App\Http\Controllers\Memorial;

use App\Http\Controllers\Controller;
use App\Models\Memorial;

class MemorialUploadController extends Controller
{
    public function upload(Request $request, Memorial $memorial) { ... }
    public function storePhoto(Request $request, Memorial $memorial) { ... }
    public function deletePhoto(Memorial $memorial, Photo $photo) { ... }
}
```

### 4. Shared helpers → trait

```php
// app/Http/Controllers/Concerns/MemorialAuthorizationTrait.php
namespace App\Http\Controllers\Concerns;

trait MemorialAuthorizationTrait
{
    protected function authorizeMemorialAccess(Memorial $memorial): void
    {
        if ($memorial->user_id !== auth()->id()) {
            abort(403);
        }
    }
}
```

### 5. Update routes

```php
// routes/web.php — VOOR
Route::post('/memorial/{memorial}/upload', [MemorialController::class, 'upload']);
Route::post('/memorial/{memorial}/publish', [MemorialController::class, 'publishMemorial']);

// routes/web.php — NA
use App\Http\Controllers\Memorial\MemorialUploadController;
use App\Http\Controllers\Memorial\MemorialPublishController;

Route::post('/memorial/{memorial}/upload', [MemorialUploadController::class, 'upload']);
Route::post('/memorial/{memorial}/publish', [MemorialPublishController::class, 'publishMemorial']);
```

### 6. Run tests na elke verplaatsing

```bash
# Na elke controller verplaatsing
php artisan test --filter=Memorial
php artisan route:list | grep memorial  # check routes nog kloppen
```

## Anti-Pattern

❌ **Extends parent controller** — methods blijven in parent, geen echte reductie:
```php
// FOUT: dit lost niets op
class MemorialUploadController extends MemorialController
{
    // parent heeft nog steeds alle methods
}
```

✅ **Eigen controller** — methods fysiek verplaatst, parent wordt kleiner:
```php
// GOED: volledig onafhankelijk
class MemorialUploadController extends Controller
{
    use MemorialAuthorizationTrait;
    // eigen methods
}
```

## Zie Ook

- `docs/kb/decisions/enterprise-quality-standards.md` — waarom max 800 regels
- `docs/kb/patterns/service-extraction.md` — zelfde pattern voor services
