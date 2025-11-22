# 📚 HavunCore - API Reference

**Complete API documentatie voor alle HavunCore services**

---

## 📖 Inhoudsopgave

1. [MemorialReferenceService](#memorialreferenceservice)
2. [MollieService](#mollieservice)
3. [BunqService](#bunqservice-todo)
4. [GmailService](#gmailservice-todo)

---

## MemorialReferenceService

**Namespace:** `Havun\Core\Services\MemorialReferenceService`

**Beschrijving:** Service voor memorial reference extractie en validatie. Memorial reference = eerste 12 characters van monument UUID (zonder hyphens).

### Constructor

```php
public function __construct()
```

Geen parameters nodig.

**Voorbeeld:**
```php
use Havun\Core\Services\MemorialReferenceService;

$service = new MemorialReferenceService();
```

---

### extractMemorialReference()

```php
public function extractMemorialReference(string $text): ?string
```

Extraheert memorial reference uit text string.

**Parameters:**
- `$text` (string) - Text waarin gezocht wordt naar memorial reference

**Return:**
- `string|null` - Memorial reference (12 alphanumeric chars) of `null` indien niet gevonden

**Voorbeeld:**
```php
$text = "Betaling voor monument 550e8400e29b ontvangen";
$reference = $service->extractMemorialReference($text);
// Result: "550e8400e29b"

$text = "Monument: 550e8400-e29b-41d4-a716-446655440000";
$reference = $service->extractMemorialReference($text);
// Result: "550e8400e29b" (hyphens verwijderd)

$text = "Geen reference hier";
$reference = $service->extractMemorialReference($text);
// Result: null
```

**Pattern matching:**
- Zoekt naar 12 alphanumeric characters (`[a-f0-9]{12}`)
- Alternatief: UUID met hyphens, haalt eerste 12 chars eruit
- Case insensitive, return altijd lowercase

---

### isValidReference()

```php
public function isValidReference(string $reference): bool
```

Valideert of string een geldige memorial reference is.

**Parameters:**
- `$reference` (string) - String om te valideren

**Return:**
- `bool` - `true` indien geldig, `false` indien ongeldig

**Voorbeeld:**
```php
$service->isValidReference('550e8400e29b');  // true
$service->isValidReference('550e-8400-e29b');  // false (hyphens)
$service->isValidReference('550e8400');  // false (te kort)
$service->isValidReference('GGGGGGGGGGGG');  // false (ongeldige chars)
```

**Validatie regels:**
- Exact 12 characters lang
- Alleen hexadecimal characters (`a-f`, `0-9`)
- Geen hyphens of andere tekens

---

### fromUuid()

```php
public function fromUuid(string $uuid): string
```

Genereert memorial reference van volledige UUID.

**Parameters:**
- `$uuid` (string) - Volledige UUID (met of zonder hyphens)

**Return:**
- `string` - Memorial reference (eerste 12 chars, lowercase, zonder hyphens)

**Voorbeeld:**
```php
$uuid = '550e8400-e29b-41d4-a716-446655440000';
$reference = $service->fromUuid($uuid);
// Result: "550e8400e29b"

$uuid = '550e8400e29b41d4a716446655440000';  // Zonder hyphens
$reference = $service->fromUuid($uuid);
// Result: "550e8400e29b"
```

---

### formatReference()

```php
public function formatReference(string $reference): string
```

Formatteert reference voor display (met hyphens).

**Parameters:**
- `$reference` (string) - Memorial reference om te formatteren

**Return:**
- `string` - Geformatteerde reference (`xxxx-xxxx-xxxx`)

**Voorbeeld:**
```php
$reference = '550e8400e29b';
$formatted = $service->formatReference($reference);
// Result: "550e-8400-e29b"

$invalid = 'invalid';
$formatted = $service->formatReference($invalid);
// Result: "invalid" (ongewijzigd bij ongeldige input)
```

---

## MollieService

**Namespace:** `Havun\Core\Services\MollieService`

**Beschrijving:** Service voor Mollie payment integration met memorial reference support.

### Constructor

```php
public function __construct(
    string $apiKey,
    MemorialReferenceService $memorialService = null
)
```

**Parameters:**
- `$apiKey` (string) - Mollie API key (`test_...` of `live_...`)
- `$memorialService` (MemorialReferenceService, optional) - Memorial reference service instance

**Voorbeeld:**
```php
use Havun\Core\Services\MollieService;

// Simpel
$mollie = new MollieService(env('MOLLIE_API_KEY'));

// Met custom memorial service
$memorialService = new MemorialReferenceService();
$mollie = new MollieService(env('MOLLIE_API_KEY'), $memorialService);
```

---

### createPayment()

```php
public function createPayment(
    float $amount,
    string $description,
    ?string $memorialReference = null,
    ?string $redirectUrl = null,
    ?string $webhookUrl = null
): array
```

Creëert nieuwe Mollie payment.

**Parameters:**
- `$amount` (float) - Bedrag in euro's (bijv. `19.95`)
- `$description` (string) - Payment omschrijving
- `$memorialReference` (string, optional) - Memorial reference (wordt in metadata opgeslagen)
- `$redirectUrl` (string, optional) - URL waar klant naartoe wordt gestuurd na betaling
- `$webhookUrl` (string, optional) - URL voor webhook notificaties

**Return:**
- `array` - Mollie payment object

**Throws:**
- `\Exception` - Bij API errors

**Voorbeeld:**
```php
$payment = $mollie->createPayment(
    amount: 19.95,
    description: 'Monument Opa Jan',
    memorialReference: '550e8400e29b',
    redirectUrl: 'https://herdenkingsportaal.nl/bedankt',
    webhookUrl: 'https://herdenkingsportaal.nl/webhook/mollie'
);

// Payment ID
$paymentId = $payment['id'];  // "tr_WDqYK6vllg"

// Checkout URL (stuur klant hierheen)
$checkoutUrl = $payment['_links']['checkout']['href'];

// Status
$status = $payment['status'];  // "open"
```

**Payment response structuur:**
```php
[
    'id' => 'tr_WDqYK6vllg',
    'amount' => [
        'currency' => 'EUR',
        'value' => '19.95'
    ],
    'description' => 'Monument Opa Jan',
    'status' => 'open',
    'metadata' => [
        'memorial_reference' => '550e8400e29b'
    ],
    '_links' => [
        'checkout' => [
            'href' => 'https://www.mollie.com/checkout/...'
        ]
    ]
]
```

---

### getPayment()

```php
public function getPayment(string $paymentId): array
```

Haalt payment details op.

**Parameters:**
- `$paymentId` (string) - Mollie payment ID

**Return:**
- `array` - Mollie payment object

**Throws:**
- `\Exception` - Bij API errors

**Voorbeeld:**
```php
$payment = $mollie->getPayment('tr_WDqYK6vllg');

echo $payment['status'];  // "paid"
echo $payment['amount']['value'];  // "19.95"
```

---

### extractMemorialReference()

```php
public function extractMemorialReference(array $payment): ?string
```

Extraheert memorial reference uit payment metadata.

**Parameters:**
- `$payment` (array) - Mollie payment object

**Return:**
- `string|null` - Memorial reference of `null` indien niet aanwezig

**Voorbeeld:**
```php
$payment = $mollie->getPayment('tr_WDqYK6vllg');
$reference = $mollie->extractMemorialReference($payment);
// Result: "550e8400e29b" (of null)
```

---

### listPayments()

```php
public function listPayments(int $limit = 50): array
```

Haalt lijst met recente payments op.

**Parameters:**
- `$limit` (int, default: 50) - Maximum aantal payments (max 250)

**Return:**
- `array` - Array met payment objects

**Throws:**
- `\Exception` - Bij API errors

**Voorbeeld:**
```php
$payments = $mollie->listPayments(limit: 20);

foreach ($payments as $payment) {
    echo $payment['id'] . ' - ' . $payment['status'] . PHP_EOL;
}

// Output:
// tr_WDqYK6vllg - paid
// tr_ABC123XYZ - open
// tr_DEF456UVW - expired
```

---

### isPaid()

```php
public function isPaid(array $payment): bool
```

Checkt of payment betaald is.

**Parameters:**
- `$payment` (array) - Mollie payment object

**Return:**
- `bool` - `true` indien status = "paid"

**Voorbeeld:**
```php
$payment = $mollie->getPayment('tr_WDqYK6vllg');

if ($mollie->isPaid($payment)) {
    // Payment successful!
    echo "Betaling ontvangen!";
} else {
    echo "Status: " . $payment['status'];
}
```

**Mogelijke statussen:**
- `open` - Wacht op betaling
- `paid` - Betaald ✅
- `expired` - Verlopen
- `canceled` - Geannuleerd
- `failed` - Mislukt

---

## BunqService (TODO)

**Namespace:** `Havun\Core\Services\BunqService`

**Status:** 🚧 Nog te implementeren

**Geplande functionaliteit:**
- `listTransactions()` - Haal bank transacties op
- `extractMemorialReference()` - Extract reference uit transactie description
- `getBalance()` - Haal saldo op
- `webhookHandler()` - Verwerk Bunq webhooks

---

## GmailService (TODO)

**Namespace:** `Havun\Core\Services\GmailService`

**Status:** 🚧 Nog te implementeren

**Geplande functionaliteit:**
- `searchEmails()` - Zoek emails op criteria
- `downloadAttachment()` - Download PDF attachment
- `extractMemorialReference()` - Extract reference uit email body
- `markAsProcessed()` - Label email als processed

---

## Error Handling

Alle services kunnen `\Exception` throwen bij errors:

```php
use Havun\Core\Services\MollieService;

try {
    $payment = $mollie->createPayment(
        amount: 19.95,
        description: 'Test payment'
    );
} catch (\Exception $e) {
    // Handle error
    Log::error('Mollie payment failed: ' . $e->getMessage());

    // Show user-friendly message
    return back()->with('error', 'Betaling kon niet worden gestart');
}
```

---

## Type Hints & Return Types

Alle methods gebruiken strict typing:

- ✅ Type hints voor parameters
- ✅ Return type declarations
- ✅ Nullable types (`?string`)
- ✅ Array shapes in docblocks

**Voorbeeld:**
```php
/**
 * @param float $amount
 * @param string $description
 * @param string|null $memorialReference
 * @return array{id: string, status: string, amount: array}
 */
public function createPayment(
    float $amount,
    string $description,
    ?string $memorialReference = null
): array
```

---

## Testing

Alle services zijn testbaar met PHPUnit:

```php
use PHPUnit\Framework\TestCase;
use Havun\Core\Services\MemorialReferenceService;

class MemorialReferenceServiceTest extends TestCase
{
    public function test_extract_memorial_reference()
    {
        $service = new MemorialReferenceService();
        $reference = $service->extractMemorialReference('550e8400e29b');

        $this->assertEquals('550e8400e29b', $reference);
    }
}
```

---

**Laatste update:** 2025-11-15
