# 🏗️ HavunCore - Architecture & Design

**Architectuur beslissingen en design patterns voor HavunCore**

---

## 📐 Overzicht

HavunCore is een **shared Laravel package** die gemeenschappelijke services en business logic centraliseert voor meerdere Havun projecten.

### **Kernprincipes:**

1. **DRY (Don't Repeat Yourself)** - Eén bron van waarheid voor memorial reference logic
2. **Single Responsibility** - Elke service heeft één duidelijke verantwoordelijkheid
3. **Framework Agnostic Core** - Services zijn bruikbaar met of zonder Laravel
4. **Testability** - Alle services zijn unit-testbaar zonder database
5. **Loose Coupling** - Services zijn onafhankelijk, geen circulaire dependencies

---

## 🎯 Design Decisions

### Decision 1: Memorial Reference = 12 Characters

**Waarom?**
- UUID's (36 chars) zijn te lang voor handmatige invoer
- Eerste 12 chars bieden voldoende uniciteit (2^48 = 281 trillion combinaties)
- Korter = makkelijker te communiceren (telefoon, email)
- Hyphens verwijderd voor eenvoud (550e8400e29b ipv 550e-8400-e29b)

**Implementatie:**
```php
// UUID: 550e8400-e29b-41d4-a716-446655440000
// Reference: 550e8400e29b (eerste 12 chars zonder hyphens)
```

**Trade-offs:**
- ✅ Kortere codes
- ✅ Betere gebruikerservaring
- ⚠️ Zeer kleine kans op collision (verwaarloosbaar bij <1M monuments)

---

### Decision 2: Shared Package via Composer

**Opties overwogen:**

#### **Optie A: Monorepo** ❌
Alle projecten in 1 repository met shared code.

**Nadelen:**
- Moeilijk om apart te deployen
- Te groot voor Git
- Merge conflicts

#### **Optie B: Git Submodules** ❌
HavunCore als submodule in elk project.

**Nadelen:**
- Complex beheer
- Sync problemen
- Developer friction

#### **Optie C: Composer Package** ✅ GEKOZEN
HavunCore als standalone Composer package.

**Voordelen:**
- ✅ Standard PHP workflow
- ✅ Versioning via tags
- ✅ Makkelijk te updaten (`composer update`)
- ✅ Lokale development via path repository
- ✅ Productie via GitHub

**Implementatie:**
```json
{
  "repositories": [
    {"type": "path", "url": "../HavunCore"}
  ],
  "require": {
    "havun/core": "@dev"
  }
}
```

---

### Decision 3: Services Pattern (niet Facades)

**Waarom Services en geen Laravel Facades?**

**Services:**
```php
$mollie = new MollieService(env('MOLLIE_API_KEY'));
$payment = $mollie->createPayment(...);
```

**Facades:**
```php
Mollie::createPayment(...);
```

**Keuze: Services** ✅

**Redenen:**
- ✅ **Testbaarheid**: Makkelijk te mocken in tests
- ✅ **Framework agnostic**: Werkt buiten Laravel
- ✅ **Expliciete dependencies**: Duidelijk welke dependencies nodig zijn
- ✅ **Type hinting**: IDE autocomplete werkt beter
- ✅ **Dependency injection**: Via constructor

**Trade-off:**
- ⚠️ Iets meer boilerplate code
- ✅ Maar: betere code quality en testbaarheid

---

### Decision 4: Metadata in Mollie (niet Description)

**Opties voor memorial reference opslag in Mollie:**

#### **Optie A: In description field** ❌
```php
$description = "Monument Opa Jan - Ref: 550e8400e29b";
```

**Nadelen:**
- Description is voor klant zichtbaar
- Parsing nodig om reference eruit te halen
- Foutgevoelig

#### **Optie B: In metadata field** ✅ GEKOZEN
```php
$metadata = ['memorial_reference' => '550e8400e29b'];
```

**Voordelen:**
- ✅ Structured data
- ✅ Niet zichtbaar voor klant
- ✅ Makkelijk te extracten
- ✅ Mollie best practice

---

### Decision 5: Exception Handling Strategy

**Strategie:** Services throwen `\Exception`, controllers handlen errors.

**Voorbeeld:**
```php
// Service
public function createPayment(...): array
{
    try {
        $response = $this->client->post('payments', [...]);
        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        throw new \Exception('Mollie payment creation failed: ' . $e->getMessage());
    }
}

// Controller
try {
    $payment = $mollie->createPayment(...);
} catch (\Exception $e) {
    Log::error('Payment failed', ['error' => $e->getMessage()]);
    return back()->with('error', 'Betaling mislukt');
}
```

**Waarom?**
- ✅ Services blijven framework-agnostic (geen Laravel-specific exceptions)
- ✅ Controllers bepalen error handling (log, user message, retry, etc.)
- ✅ Simpel en consistent

---

## 🧩 Package Structure

```
D:\GitHub\HavunCore/
│
├── src/
│   ├── Services/               # Business logic
│   │   ├── MollieService.php
│   │   ├── BunqService.php (TODO)
│   │   ├── GmailService.php (TODO)
│   │   └── MemorialReferenceService.php
│   │
│   ├── Traits/                 # Reusable traits (TODO)
│   │   └── HasMemorialReference.php
│   │
│   ├── Config/                 # Config files (TODO)
│   │   └── havun.php
│   │
│   └── HavunCoreServiceProvider.php  # Laravel integration
│
├── tests/                      # PHPUnit tests (TODO)
│   └── Services/
│       └── MemorialReferenceServiceTest.php
│
├── composer.json               # Package definition
├── .gitignore
│
├── README.md                   # Quick start guide
├── SETUP.md                    # Installation & configuration
├── API-REFERENCE.md            # Complete API documentation
├── INTEGRATION-GUIDE.md        # Integration examples
├── ARCHITECTURE.md             # This file
└── CHANGELOG.md                # Version history
```

---

## 🔄 Service Dependencies

```
MemorialReferenceService  (geen dependencies)
    ↑
    |
MollieService  (gebruikt MemorialReferenceService)
    ↑
    |
BunqService  (gebruikt MemorialReferenceService)
    ↑
    |
GmailService  (gebruikt MemorialReferenceService)
```

**Dependency Injection:**
```php
// MollieService accepteert MemorialReferenceService als optionele dependency
public function __construct(
    string $apiKey,
    MemorialReferenceService $memorialService = null
) {
    $this->memorialService = $memorialService ?? new MemorialReferenceService();
}
```

**Voordelen:**
- ✅ Testbaar (kan mock service injecten)
- ✅ Flexibel (kan custom service injecten)
- ✅ Backwards compatible (werkt zonder injection)

---

## 📊 Data Flow

### Flow 1: Herdenkingsportaal Payment → HavunAdmin Reconciliation

```
┌─────────────────────┐
│ Herdenkingsportaal  │
│                     │
│ Monument            │
│ UUID: 550e8400-...  │
└──────────┬──────────┘
           │
           │ MemorialReferenceService.fromUuid()
           ↓
      550e8400e29b
           │
           │ MollieService.createPayment()
           ↓
┌─────────────────────┐
│   Mollie API        │
│                     │
│ metadata:           │
│   memorial_ref:     │
│   550e8400e29b      │
└──────────┬──────────┘
           │
           │ Customer pays
           │ Webhook fired
           ↓
┌─────────────────────┐
│   HavunAdmin        │
│                     │
│ SyncController      │
│ .syncMollie()       │
└──────────┬──────────┘
           │
           │ MollieService.listPayments()
           │ MollieService.extractMemorialReference()
           ↓
      550e8400e29b
           │
           │ Database query
           ↓
┌─────────────────────┐
│   Invoice           │
│   memorial_ref:     │
│   550e8400e29b      │
│                     │
│ ✅ MATCHED!         │
└─────────────────────┘
```

---

## 🔐 Security Considerations

### API Keys

**DO:**
- ✅ Store in `.env` file
- ✅ Use test keys in development
- ✅ Use live keys only in production
- ✅ Never commit `.env` to Git

**DON'T:**
- ❌ Hard-code API keys in source
- ❌ Share API keys in Slack/Email
- ❌ Use live keys in staging

### Memorial Reference

**Niet als security token gebruiken!**

Memorial reference is **geen** authenticatie token. Het is puur voor matching/linking.

**Waarom niet als security token?**
- ❌ Voorspelbaar (eerste 12 chars van UUID)
- ❌ Kan gelekt worden via emails, logs, etc.
- ❌ Geen expiration

**Wel gebruiken voor:**
- ✅ Transaction matching
- ✅ Duplicate detection
- ✅ Cross-system linking

---

## 🧪 Testing Strategy

### Unit Tests

Test services **zonder** externe dependencies:

```php
class MemorialReferenceServiceTest extends TestCase
{
    public function test_extract_memorial_reference()
    {
        $service = new MemorialReferenceService();
        $reference = $service->extractMemorialReference('550e8400e29b');

        $this->assertEquals('550e8400e29b', $reference);
    }

    public function test_from_uuid()
    {
        $service = new MemorialReferenceService();
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $reference = $service->fromUuid($uuid);

        $this->assertEquals('550e8400e29b', $reference);
    }
}
```

### Integration Tests

Test services **met** externe API's (Mollie test mode):

```php
class MollieServiceTest extends TestCase
{
    public function test_create_payment_with_memorial_reference()
    {
        $mollie = new MollieService(env('MOLLIE_TEST_KEY'));

        $payment = $mollie->createPayment(
            amount: 10.00,
            description: 'Test',
            memorialReference: '550e8400e29b'
        );

        $this->assertArrayHasKey('id', $payment);
        $this->assertEquals('550e8400e29b', $payment['metadata']['memorial_reference']);
    }
}
```

### Mock Tests

Test controllers **zonder** echte API calls:

```php
class PaymentControllerTest extends TestCase
{
    public function test_payment_creation()
    {
        // Mock MollieService
        $mollie = Mockery::mock(MollieService::class);
        $mollie->shouldReceive('createPayment')
            ->once()
            ->andReturn(['id' => 'tr_test123']);

        $this->app->instance(MollieService::class, $mollie);

        // Test controller
        $response = $this->post('/payment/create', [...]);
        $response->assertRedirect();
    }
}
```

---

## 🚀 Performance Considerations

### Database Indexing

**CRITICAL:** Altijd index op `memorial_reference`:

```sql
CREATE INDEX idx_memorial_reference ON invoices(memorial_reference);
CREATE INDEX idx_memorial_reference ON transactions(memorial_reference);
```

**Waarom?**
- Zoeken op memorial_reference is core functie
- Zonder index: full table scan (slow!)
- Met index: instant lookup

### Caching Strategy

**Services zijn stateless** - geen caching binnen HavunCore.

**Caching gebeurt in consuming application:**

```php
// In HavunAdmin
$payments = Cache::remember('mollie_payments', 300, function () use ($mollie) {
    return $mollie->listPayments(50);
});
```

**Waarom niet in HavunCore?**
- ✅ Consuming app bepaalt cache strategy
- ✅ Simpelere services
- ✅ Flexibeler

### API Rate Limiting

**Mollie rate limits:**
- 60 requests/minute (test mode)
- 600 requests/minute (live mode)

**Best practice:**
```php
// Batch sync, don't call API in loop
$payments = $mollie->listPayments(50);  // ✅ 1 API call

// NOT this:
foreach ($invoices as $invoice) {
    $payment = $mollie->getPayment($invoice->payment_id);  // ❌ N API calls!
}
```

---

## 🔮 Future Architecture

### Planned Features

#### 1. **Event System**
```php
// Fire event when payment matched
event(new PaymentMatched($transaction, $invoice));

// Listen in HavunAdmin
class SendPaymentConfirmation implements ShouldQueue
{
    public function handle(PaymentMatched $event)
    {
        Mail::to($event->invoice->customer)->send(...);
    }
}
```

#### 2. **Queue Support**
```php
// Async sync
dispatch(new SyncMolliePayments());
```

#### 3. **Webhook Handler Service**
```php
// Centralized webhook handling
$webhookService = new WebhookService();
$webhookService->handleMollie($request);
```

#### 4. **HasMemorialReference Trait**
```php
// In Model
use Havun\Core\Traits\HasMemorialReference;

class Invoice extends Model
{
    use HasMemorialReference;
}

// Auto-adds methods:
$invoice->memorial_reference
$invoice->formatted_reference
```

---

## 📈 Versioning Strategy

**Semantic Versioning (SemVer):**

- **Major** (1.0.0 → 2.0.0): Breaking changes
- **Minor** (1.0.0 → 1.1.0): New features (backwards compatible)
- **Patch** (1.0.0 → 1.0.1): Bug fixes

**Current version:** 0.1.0-dev (pre-release)

**Release plan:**
- 0.1.0 - MVP (MemorialReferenceService, MollieService)
- 0.2.0 - Add BunqService
- 0.3.0 - Add GmailService
- 1.0.0 - Stable release (production ready)

---

## 🤝 Contributing

### Development Workflow

```bash
# 1. Clone HavunCore
git clone https://github.com/havun/HavunCore.git
cd HavunCore

# 2. Create feature branch
git checkout -b feature/bunq-service

# 3. Install dependencies
composer install

# 4. Make changes
# ... code ...

# 5. Run tests
./vendor/bin/phpunit

# 6. Commit & push
git add .
git commit -m "Add BunqService"
git push origin feature/bunq-service

# 7. Create PR on GitHub
```

### Code Standards

- ✅ PSR-12 coding style
- ✅ Type hints voor alle parameters
- ✅ Return type declarations
- ✅ PHPDoc comments
- ✅ Unit tests voor nieuwe features

---

## 📚 References

**Packages gebruikt:**
- [Guzzle](https://docs.guzzlephp.org/) - HTTP client
- [Laravel](https://laravel.com/docs) - Framework integration

**API Documentatie:**
- [Mollie API](https://docs.mollie.com/reference/v2/payments-api)
- [Bunq API](https://doc.bunq.com/) (TODO)
- [Gmail API](https://developers.google.com/gmail/api) (TODO)

---

**Laatste update:** 2025-11-15
**Auteur:** Henk van Velzen
