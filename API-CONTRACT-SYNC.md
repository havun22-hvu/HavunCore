# 📋 API Contract Synchronisatie

**Versie:** HavunCore v0.3.0+
**Datum:** 17 november 2025
**Doel:** Voorkom API mismatch tussen projecten

---

## 🎯 Probleem

**Zonder contract synchronisatie:**

```
HavunAdmin (Server)                 Herdenkingsportaal (Client)
===================                 ==========================

Verwacht:                           Stuurt:
{                                   {
  "memorial_reference": "...",        "memorial_ref": "...",  ← FOUT!
  "customer": {                       "client": {             ← FOUT!
    "name": "...",                      "naam": "..."         ← FOUT!
  }                                   }
}                                   }

Result: ❌ API call faalt
```

**Met contract synchronisatie:**

```
1. HavunAdmin registreert contract in MCP
2. Herdenkingsportaal valideert payload VOOR verzenden
3. Fout wordt direct gedetecteerd voor deployment
4. Bij contract change krijgt client automatisch notificatie
```

---

## 🏗️ Hoe Werkt Het?

### 1. Server (HavunAdmin) registreert contract

```php
// In HavunAdmin - bij deployment of handmatig
use Havun\Core\Services\APIContractRegistry;

$registry = app(APIContractRegistry::class);

$registry->registerEndpoint('invoice_sync', [
    'version' => '2.0',
    'endpoint' => 'POST /api/invoices/sync',
    'provider' => 'HavunAdmin',

    'required_fields' => [
        'memorial_reference',
        'customer',
        'customer.name',
        'customer.email',
        'invoice',
        'invoice.number',
        'invoice.amount',
        'invoice.vat_amount',
        'invoice.total_amount',
        'payment',
        'payment.mollie_payment_id',
        'payment.status',
    ],

    'optional_fields' => [
        'customer.phone',
        'customer.address',
        'invoice.due_date',
        'payment.method',
        'metadata',
    ],

    'field_types' => [
        'memorial_reference' => 'string',
        'customer.name' => 'string',
        'customer.email' => 'string',
        'invoice.amount' => 'float',
        'invoice.vat_amount' => 'float',
        'invoice.total_amount' => 'float',
    ],

    'deprecated_fields' => [
        // Bijvoorbeeld: 'old_field_name'
    ],

    'strict_mode' => false,  // Allow extra fields
]);

// Contract wordt opgeslagen in MCP voor alle projecten
```

### 2. Client (Herdenkingsportaal) valideert VOOR verzenden

```php
// In Herdenkingsportaal - voor API call
use Havun\Core\Traits\ValidatesAPIContract;

class SyncInvoiceJob
{
    use ValidatesAPIContract;

    public function handle(InvoiceSyncService $syncService)
    {
        // Prepare payload
        $invoiceData = $syncService->prepareInvoiceData($this->memorial, $this->payment);

        // ✅ VALIDATE BEFORE SENDING!
        $validation = $this->validateContract('invoice_sync', $invoiceData);

        if (!$validation['valid']) {
            // Payload is fout - log errors
            Log::error('Invoice sync payload invalid', [
                'errors' => $validation['errors'],
                'memorial_reference' => $this->memorial->memorial_reference,
            ]);

            // Don't send - zou toch falen
            throw new \Exception('Payload does not match API contract');
        }

        // Warnings loggen (deprecated fields, etc.)
        if (!empty($validation['warnings'])) {
            Log::warning('Invoice sync payload warnings', $validation['warnings']);
        }

        // ✅ Safe to send - contract is valid
        $response = $syncService->sendToHavunAdmin($invoiceData);
    }
}
```

### 3. Bij contract wijziging

```php
// In HavunAdmin - nieuwe versie van API
$oldContract = [/* v1.0 contract */];
$newContract = [
    'version' => '2.0',
    'required_fields' => [
        'memorial_reference',
        'customer',
        'customer.name',
        'customer.email',
        'customer_snapshot',  // ← NIEUW REQUIRED FIELD!
        // ...
    ],
];

// Detect breaking changes
$breakingChanges = $registry->detectBreakingChanges('invoice_sync', $oldContract, $newContract);

if (!empty($breakingChanges)) {
    // Automatisch notify clients
    $registry->reportBreakingChanges(
        'invoice_sync',
        $breakingChanges,
        consumers: ['Herdenkingsportaal']  // Who uses this API
    );
}

// Result: Herdenkingsportaal krijgt MCP bericht:
// "🚨 API BREAKING CHANGE: invoice_sync
//  New required fields added: customer_snapshot
//  Action required: Update your API client"
```

---

## 📖 Complete Voorbeelden

### Voorbeeld 1: HavunAdmin ↔ Herdenkingsportaal (Invoice Sync)

**HavunAdmin (Server):**

```php
// File: app/Http/Controllers/Api/InvoiceSyncController.php
use Havun\Core\Services\APIContractRegistry;

class InvoiceSyncController extends Controller
{
    public function __construct()
    {
        // Register contract on boot (or via command)
        $this->registerContract();
    }

    private function registerContract()
    {
        app(APIContractRegistry::class)->registerEndpoint('invoice_sync', [
            'version' => '2.0',
            'endpoint' => 'POST /api/invoices/sync',
            'provider' => 'HavunAdmin',
            'required_fields' => [
                'memorial_reference',
                'customer',
                'customer.name',
                'customer.email',
                'invoice.number',
                'invoice.total_amount',
                'payment.mollie_payment_id',
                'payment.status',
            ],
            'field_types' => [
                'memorial_reference' => 'string',
                'invoice.total_amount' => 'float',
            ],
        ]);
    }

    public function store(InvoiceSyncRequest $request)
    {
        // Laravel validation handles required fields
        // But contract is documented in MCP for clients

        $invoice = Invoice::createFromHerdenkingsportaal($request->validated());

        return response()->json([
            'success' => true,
            'invoice_id' => $invoice->id,
        ]);
    }
}
```

**Herdenkingsportaal (Client):**

```php
// File: app/Jobs/SyncInvoiceJob.php
use Havun\Core\Traits\ValidatesAPIContract;

class SyncInvoiceJob implements ShouldQueue
{
    use ValidatesAPIContract;

    public function handle(InvoiceSyncService $syncService)
    {
        // Prepare data
        $invoiceData = $syncService->prepareInvoiceData($this->memorial, $this->payment);

        // ✅ VALIDATE CONTRACT
        $this->assertValidContract('invoice_sync', $invoiceData);
        // ^ Throws exception if invalid

        // Send to HavunAdmin
        $response = $syncService->sendToHavunAdmin($invoiceData);
    }
}
```

---

### Voorbeeld 2: HavunAdmin ↔ VPDUpdate

**VPDUpdate (Server):**

```php
// Register contract
app(APIContractRegistry::class)->registerEndpoint('vpd_data', [
    'version' => '1.0',
    'endpoint' => 'GET /api/vpd/data',
    'provider' => 'VPDUpdate',
    'required_fields' => [
        'version',  // ← Nieuw sinds v1.0
    ],
    'optional_fields' => [
        'filter',
        'limit',
        'offset',
    ],
    'field_types' => [
        'version' => 'string',
        'limit' => 'integer',
    ],
]);
```

**HavunAdmin (Client):**

```php
use Havun\Core\Traits\ValidatesAPIContract;

class VPDSyncService
{
    use ValidatesAPIContract;

    public function fetchData()
    {
        $params = [
            'version' => '1.0',  // ← Required!
            'limit' => 100,
        ];

        // Validate before HTTP call
        $validation = $this->validateContract('vpd_data', $params);

        if (!$validation['valid']) {
            throw new \Exception('VPD API params invalid: ' . implode(', ', $validation['errors']));
        }

        // Safe to call API
        return Http::get('https://vpdupdate.example.com/api/vpd/data', $params);
    }
}
```

---

## 🚨 Breaking Change Detection

### Wat is een breaking change?

1. **Nieuw required field** = BREAKING
   ```php
   Old: required_fields = ['name']
   New: required_fields = ['name', 'email']  ← BREAKING!
   ```

2. **Removed field** = BREAKING
   ```php
   Old: optional_fields = ['phone', 'address']
   New: optional_fields = ['phone']  ← 'address' removed = BREAKING!
   ```

3. **Changed field type** = BREAKING
   ```php
   Old: 'amount' => 'integer'
   New: 'amount' => 'string'  ← BREAKING!
   ```

4. **Nieuwe optional field** = NOT breaking
   ```php
   Old: optional_fields = ['phone']
   New: optional_fields = ['phone', 'address']  ← OK, not breaking
   ```

### Automatische notificatie

```php
// In HavunAdmin - bij API update
$oldContract = $this->getOldContract('invoice_sync');
$newContract = $this->getNewContract('invoice_sync');

$breakingChanges = app(APIContractRegistry::class)
    ->detectBreakingChanges('invoice_sync', $oldContract, $newContract);

if (!empty($breakingChanges)) {
    // Stuur MCP bericht naar consumers
    app(APIContractRegistry::class)->reportBreakingChanges(
        endpointId: 'invoice_sync',
        breakingChanges: $breakingChanges,
        consumers: ['Herdenkingsportaal']
    );
}

// Herdenkingsportaal krijgt:
// "🚨 API BREAKING CHANGE: invoice_sync
//
//  ### New required fields added: customer_snapshot
//  - Type: new_required_field
//  - Severity: high
//
//  ### Field 'amount' type changed from integer to string
//  - Type: type_changed
//  - Severity: high
//
//  ⚠️ Action Required
//  Update your API client to match the new contract."
```

---

## 🔧 Setup

### 1. Registreer APIContractRegistry in Service Provider

**In HavunCore:**

```php
// src/HavunCoreServiceProvider.php
public function register(): void
{
    // ...

    $this->app->singleton(APIContractRegistry::class, function ($app) {
        return new APIContractRegistry(
            mcp: $app->make(MCPService::class),
            projectName: config('app.name')
        );
    });
}
```

### 2. In Server Project (HavunAdmin, VPDUpdate, etc.)

**Optie A: Via Artisan Command**

```php
// app/Console/Commands/RegisterAPIContracts.php
use Havun\Core\Services\APIContractRegistry;

class RegisterAPIContracts extends Command
{
    protected $signature = 'api:register-contracts';

    public function handle(APIContractRegistry $registry)
    {
        // Invoice Sync API
        $registry->registerEndpoint('invoice_sync', [/* contract */]);

        // Other APIs
        $registry->registerEndpoint('other_api', [/* contract */]);

        $this->info('✅ API contracts registered in MCP');
    }
}
```

```bash
# Run bij deployment
php artisan api:register-contracts
```

**Optie B: In Controller Constructor**

```php
class InvoiceSyncController extends Controller
{
    public function __construct(APIContractRegistry $registry)
    {
        $registry->registerEndpoint('invoice_sync', [/* contract */]);
    }
}
```

**Optie C: In Config File**

```php
// config/api_contracts.php
return [
    'invoice_sync' => [
        'version' => '2.0',
        'endpoint' => 'POST /api/invoices/sync',
        // ...
    ],
];

// In Service Provider
foreach (config('api_contracts') as $id => $contract) {
    app(APIContractRegistry::class)->registerEndpoint($id, $contract);
}
```

### 3. In Client Project (Herdenkingsportaal, HavunAdmin, etc.)

**Gewoon trait gebruiken:**

```php
use Havun\Core\Traits\ValidatesAPIContract;

class YourJob
{
    use ValidatesAPIContract;

    public function handle()
    {
        $payload = [...];
        $this->assertValidContract('endpoint_id', $payload);
        // Throws exception if invalid
    }
}
```

---

## 📊 Contract Checking Workflow

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│  1. SERVER (HavunAdmin)                                        │
│     Register Contract                                           │
│     ↓                                                           │
│     APIContractRegistry->registerEndpoint('invoice_sync', [...])│
│     ↓                                                           │
│     MCP stores contract                                         │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  2. CLIENT (Herdenkingsportaal)                                │
│     Before API Call                                             │
│     ↓                                                           │
│     validateContract('invoice_sync', $payload)                  │
│     ↓                                                           │
│     Check required fields ✓                                     │
│     Check field types ✓                                         │
│     Check deprecated fields ⚠️                                   │
│     ↓                                                           │
│     IF VALID:    Send API request                              │
│     IF INVALID:  Throw exception / Log error                   │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  3. CONTRACT UPDATE (HavunAdmin)                               │
│     New API Version                                             │
│     ↓                                                           │
│     detectBreakingChanges(old, new)                            │
│     ↓                                                           │
│     IF BREAKING:                                                │
│       reportBreakingChanges() → MCP                            │
│       ↓                                                         │
│       Herdenkingsportaal receives alert                        │
│       "🚨 API BREAKING CHANGE - Action required"               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## ✅ Best Practices

### 1. Altijd valideren in client

```php
// ❌ BAD - No validation
$response = Http::post($url, $payload);

// ✅ GOOD - Validate first
$this->assertValidContract('endpoint_id', $payload);
$response = Http::post($url, $payload);
```

### 2. Versioning

```php
// Contract versie meegeven
'version' => '2.0',  // SemVer

// Bij breaking change: major version bump
'1.0' → '2.0'  (breaking)
'1.0' → '1.1'  (non-breaking, new optional fields)
```

### 3. Deprecation warnings

```php
// Mark fields as deprecated BEFORE removing
'deprecated_fields' => [
    'old_customer_field',  // Will be removed in v3.0
],

// Client krijgt warning maar API werkt nog
// Na grace period: remove in v3.0
```

### 4. Register bij deployment

```bash
# In deployment script
php artisan api:register-contracts

# Of in CI/CD pipeline
./deploy.sh && php artisan api:register-contracts
```

### 5. Monitor MCP messages

```bash
# Check voor breaking change notifications
mcp__havun__getMessages project=Herdenkingsportaal | jq '.[] | select(.tags | contains(["breaking-change"]))'
```

---

## 🎯 Benefits

✅ **Catch errors VOOR deployment** - Validate locally
✅ **Automatic breaking change detection** - No surprises
✅ **Clear API documentation** - Contracts in MCP
✅ **Type safety** - Field type checking
✅ **Deprecation warnings** - Graceful migration
✅ **Multi-project sync** - All clients stay in sync

---

## 🚀 Roadmap

**v0.3.0 (Now):**
- ✅ Manual contract registration
- ✅ Client-side validation
- ✅ Breaking change detection

**v0.4.0 (Future):**
- [ ] Automatic contract generation from Laravel FormRequest
- [ ] Contract versioning in database
- [ ] API contract dashboard (web UI)
- [ ] Automatic client code generation

**v0.5.0 (Future):**
- [ ] OpenAPI/Swagger integration
- [ ] GraphQL schema support
- [ ] Real-time contract updates via WebSockets

---

**Klaar om API's te synchroniseren!** 🎊
