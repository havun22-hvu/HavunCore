# Model Traits Pattern

> **Probleem:** Fat model met 500+ regels en meerdere concerns door elkaar.
> **Oplossing:** Extract method groups naar traits in `app/Models/Concerns/`.

## Wanneer Toepassen

- Model > 500 regels
- Duidelijke concerns (privacy, state management, media, archivering)
- Methods gebruiken alleen `$this` properties/methods

## Voorbeeld: Herdenkingsportaal

**Memorial model** (1874 → 622 regels) + 6 traits:

```
app/Models/
├── Concerns/
│   ├── Memorial/
│   │   ├── HasPrivacySettings.php    # privacy methods + scopes
│   │   ├── HasMemorialState.php      # status transitions
│   │   ├── HasArweaveArchive.php     # blockchain archivering
│   │   ├── HasMemorialPhotos.php     # foto management methods
│   │   ├── HasMemorialSharing.php    # share links, QR codes
│   │   └── HasMemorialMonument.php   # monument koppeling
│   └── ...
└── Memorial.php                       # basis model (622 regels)
```

## Stappen

### 1. Groepeer methods per concern

```
// Privacy concern
isPublic()            → HasPrivacySettings
isPrivate()           → HasPrivacySettings
scopePublic($query)   → HasPrivacySettings
updatePrivacy()       → HasPrivacySettings

// State concern
publish()             → HasMemorialState
unpublish()           → HasMemorialState
archive()             → HasMemorialState
isPublished()         → HasMemorialState
```

### 2. Maak trait in Concerns directory

```php
// app/Models/Concerns/Memorial/HasPrivacySettings.php
namespace App\Models\Concerns\Memorial;

trait HasPrivacySettings
{
    public function isPublic(): bool
    {
        return $this->privacy === 'public';
    }

    public function isPrivate(): bool
    {
        return $this->privacy === 'private';
    }

    public function scopePublic($query)
    {
        return $query->where('privacy', 'public');
    }

    public function updatePrivacy(string $level): void
    {
        $this->update(['privacy' => $level]);
    }
}
```

### 3. Use trait in model

```php
// app/Models/Memorial.php
namespace App\Models;

use App\Models\Concerns\Memorial\HasPrivacySettings;
use App\Models\Concerns\Memorial\HasMemorialState;
use App\Models\Concerns\Memorial\HasArweaveArchive;
use App\Models\Concerns\Memorial\HasMemorialPhotos;
use App\Models\Concerns\Memorial\HasMemorialSharing;
use App\Models\Concerns\Memorial\HasMemorialMonument;

class Memorial extends Model
{
    use HasPrivacySettings;
    use HasMemorialState;
    use HasArweaveArchive;
    use HasMemorialPhotos;
    use HasMemorialSharing;
    use HasMemorialMonument;

    // $fillable, $casts, relationships, boot() blijven HIER
}
```

## Wat NIET verplaatsen

Deze onderdelen moeten ALTIJD in het model zelf blijven:

```php
class Memorial extends Model
{
    // ✅ Blijft in model
    protected $fillable = [...];
    protected $casts = [...];
    protected $appends = [...];

    // ✅ Blijft in model — Eloquent relationships
    public function user() { return $this->belongsTo(User::class); }
    public function photos() { return $this->hasMany(Photo::class); }

    // ✅ Blijft in model — boot/booted
    protected static function booted(): void { ... }

    // ✅ Blijft in model — accessors/mutators voor DB kolommen
    protected function name(): Attribute { ... }
}
```

## Naamconventie

| Type | Prefix | Voorbeeld |
|------|--------|-----------|
| Feature concern | `Has` | `HasPrivacySettings` |
| State/lifecycle | `Has` | `HasMemorialState` |
| Query scopes | `Has` + `Scopes` | `HasMemorialScopes` |
| Berekeningen | `Calculates` | `CalculatesStatistics` |

## Zie Ook

- `docs/kb/decisions/enterprise-quality-standards.md` — waarom max 500 regels
- `docs/kb/patterns/controller-splitting.md` — zelfde pattern voor controllers
- `docs/kb/patterns/service-extraction.md` — zelfde pattern voor services
