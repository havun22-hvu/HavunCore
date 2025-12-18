# PostcodeService - Adres Lookup

> Nederlandse postcode + huisnummer → volledig adres

## API

Gebruikt **PDOK** (Publieke Dienstverlening Op de Kaart) - gratis overheids-API.

- Geen API key nodig
- Geen rate limits (redelijk gebruik)
- 30 dagen cache

## Gebruik in je project

### 1. Kopieer de service

Kopieer `app/Services/PostcodeService.php` van HavunCore naar je project.

### 2. Basis gebruik

```php
use App\Services\PostcodeService;

$service = new PostcodeService();

// Lookup adres
$address = $service->lookup('1234AB', '10');

// Result:
[
    'street' => 'Hoofdstraat',
    'house_number' => '10',
    'house_letter' => null,
    'addition' => null,
    'postcode' => '1234AB',
    'city' => 'Amsterdam',
    'municipality' => 'Amsterdam',
    'province' => 'Noord-Holland',
    'latitude' => 52.3676,
    'longitude' => 4.9041,
    'full_address' => 'Hoofdstraat 10, 1234AB Amsterdam',
]
```

### 3. In een Controller

```php
public function lookupAddress(Request $request)
{
    $request->validate([
        'postcode' => 'required|string',
        'huisnummer' => 'required|string',
    ]);

    $service = new PostcodeService();
    $address = $service->lookup(
        $request->postcode,
        $request->huisnummer
    );

    if (!$address) {
        return response()->json(['error' => 'Adres niet gevonden'], 404);
    }

    return response()->json($address);
}
```

### 4. Als API endpoint

```php
// routes/web.php of routes/api.php
Route::get('/api/postcode/{postcode}/{huisnummer}', function ($postcode, $huisnummer) {
    $service = new \App\Services\PostcodeService();
    $address = $service->lookup($postcode, $huisnummer);

    return $address
        ? response()->json($address)
        : response()->json(['error' => 'Niet gevonden'], 404);
});
```

### 5. JavaScript/Frontend

```javascript
async function lookupAddress(postcode, huisnummer) {
    const response = await fetch(`/api/postcode/${postcode}/${huisnummer}`);
    if (!response.ok) return null;
    return await response.json();
}

// Voorbeeld: auto-fill formulier
document.getElementById('postcode').addEventListener('blur', async function() {
    const postcode = this.value;
    const huisnummer = document.getElementById('huisnummer').value;

    if (postcode && huisnummer) {
        const address = await lookupAddress(postcode, huisnummer);
        if (address) {
            document.getElementById('straat').value = address.street;
            document.getElementById('plaats').value = address.city;
        }
    }
});
```

## Validatie

```php
$service = new PostcodeService();

// Check geldig formaat
$service->isValidPostcode('1234AB');  // true
$service->isValidPostcode('1234 AB'); // true
$service->isValidPostcode('0000AA');  // false (begint met 0)
$service->isValidPostcode('12345');   // false

// Normaliseer
$service->normalizePostcode('1234 ab'); // '1234AB'
```

## Afstand berekenen

```php
$distance = $service->getDistance('1234AB', '10', '5678CD', '20');
// Returns: float (kilometers) of null
```

## Caching

- Resultaten worden 30 dagen gecached via Laravel Cache
- Cache key: `postcode:{postcode}:{huisnummer}`
- Clear cache: `php artisan cache:clear`

---

*Bron: HavunCore `app/Services/PostcodeService.php`*
