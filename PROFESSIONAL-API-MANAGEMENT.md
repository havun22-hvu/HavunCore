# 🏢 Professional API Management - Complete Guide

**Voor:** Henk
**Datum:** 17 november 2025
**Versie:** HavunCore v0.4.0

---

## 📚 Inhoudsopgave

1. [Het Probleem](#het-probleem)
2. [Hoe Professionele Bedrijven Het Doen](#hoe-professionele-bedrijven-het-doen)
3. [Wat Ik Voor Jou Heb Gebouwd](#wat-ik-voor-jou-heb-gebouwd)
4. [Hoe Het Werkt - Stap Voor Stap](#hoe-het-werkt---stap-voor-stap)
5. [Praktisch Voorbeeld](#praktisch-voorbeeld)
6. [Setup & Implementatie](#setup--implementatie)
7. [CI/CD Integration](#cicd-integration)
8. [Troubleshooting](#troubleshooting)

---

## 🎯 Het Probleem

### Wat Je Vroeg

> "Bij een API implementatie tussen HavunAdmin en Herdenkingsportaal (of VPDUpdate) moet er afstemming zijn in de code en dat beide programma's goed op elkaar afgestemd zijn"

### Het Echte Probleem

**Scenario:**

```
Herdenkingsportaal (Client)          HavunAdmin (Server)
=======================              ===================

Stuurt:                              Verwacht:
{                                    {
  "memorial_ref": "123",               "memorial_reference": "123",  ← Anders!
  "client": {                          "customer": {                 ← Anders!
    "naam": "Jan"                        "name": "Jan"               ← Anders!
  }                                    }
}                                    }

Result: 💥 API call fails!
```

**Wat Er Gebeurt:**
1. Herdenkingsportaal denkt dat het goed doet
2. HavunAdmin krijgt verkeerde data
3. API call faalt met "400 Bad Request"
4. Je merkt het PAS in productie 😱
5. Emergency fix nodig
6. Downtime voor klanten

**Kosten:**
- Development tijd: uren debuggen
- Productie downtime: verloren omzet
- Klant frustratie: slechte ervaring
- Team stress: 3am debugging

---

## 🏢 Hoe Professionele Bedrijven Het Doen

Laat me je laten zien hoe de tech giants dit oplossen:

### 1. **Stripe** (Payments API - $95 billion valuation)

**Hun Aanpak:**

```yaml
# openapi.yaml - Single Source of Truth
paths:
  /v1/charges:
    post:
      requestBody:
        schema:
          type: object
          required:
            - amount
            - currency
          properties:
            amount:
              type: integer
              minimum: 50  # Minimum 50 cents
            currency:
              type: string
              enum: [usd, eur]
```

**Tools:**
- OpenAPI spec voor alle endpoints
- Auto-generate client libraries (PHP, Python, JS)
- Interactive API explorer (Swagger UI)
- Validation in CI/CD

**Voordeel:**
- 1 file = source of truth
- Client libraries always in sync
- Breaking changes caught in CI

---

### 2. **Google** (gRPC - 10+ billion requests/day)

**Hun Aanpak:**

```protobuf
// invoice.proto
message Invoice {
  string memorial_reference = 1;  // Required
  Customer customer = 2;           // Required
  double amount = 3;               // Required
}

message Customer {
  string name = 1;
  string email = 2;
  optional string phone = 3;  // Optional
}
```

**Tools:**
- Protocol Buffers (binary, type-safe)
- Auto-generate code (25+ languages)
- Compile-time type checking
- Backwards compatible by design

**Voordeel:**
- Type safety = no runtime errors
- 10x faster than JSON
- Breaking changes = compile errors

---

### 3. **Netflix** (Microservices - 200+ services)

**Hun Aanpak:**

**Consumer (Frontend) writes test:**
```php
// tests/Pact/ApiTest.php
$pact->expects()
    ->uponReceiving('get user data')
    ->with([
        'method' => 'GET',
        'path' => '/users/123'
    ])
    ->willRespondWith([
        'status' => 200,
        'body' => ['id' => 123, 'name' => 'Jan']
    ]);
```

**Provider (Backend) verifies:**
```bash
# In CI/CD
pact verify --provider user-service --pact-url ./pacts/frontend-backend.json

# If backend changes and breaks contract → CI FAILS ❌
```

**Tools:**
- Pact (Consumer-Driven Contracts)
- Tests run in CI/CD
- Breaking changes = failing tests

**Voordeel:**
- Consumer defines what it needs
- Provider must meet expectations
- Catch issues before deployment

---

### 4. **ING Bank** (Banking API - €1.2 trillion assets)

**Hun Aanpak:**

**API Gateway (Kong/Apigee) enforces contracts:**

```yaml
# kong.yaml
services:
  - name: payment-api
    plugins:
      - name: request-validator
        config:
          body_schema: |
            {
              "type": "object",
              "required": ["amount", "account"],
              "properties": {
                "amount": {"type": "number", "minimum": 0.01}
              }
            }
```

**Tools:**
- API Gateway validates ALL requests
- Invalid requests = instant reject
- Centralized monitoring
- Rate limiting + security

**Voordeel:**
- Validation at gateway level
- No invalid data reaches backend
- Single point of control

---

### 5. **Shopify** (E-commerce API - $100+ billion)

**Hun Aanpak:**

**Versioned APIs:**
```
/admin/api/2024-10/products.json  ← Stable version
/admin/api/2025-01/products.json  ← New version

Both run in parallel for 12 months
Then 2024-10 deprecated
```

**Migration Guide:**
```markdown
## Breaking Changes in 2025-01

- `product_id` → `id` (renamed)
- `price` now includes tax (behavior change)

### Migration Steps:
1. Update client to use 2025-01
2. Change product_id to id
3. Adjust price calculations
4. Test thoroughly
5. Deploy within 3 months
```

**Voordeel:**
- Old clients keep working
- Grace period for migration
- Clear communication

---

## 🎯 Wat Ik Voor Jou Heb Gebouwd

Ik heb de BEST PRACTICES van deze bedrijven gecombineerd in HavunCore:

### Component 1: **OpenAPI/Swagger** (zoals Stripe)

**Wat Het Is:**
Een YAML file die je hele API beschrijft. Industry standard.

**Voorbeeld:**
```yaml
# storage/api/openapi.yaml
openapi: 3.0.3
info:
  title: HavunAdmin API
  version: 2.0.0

paths:
  /api/invoices/sync:
    post:
      requestBody:
        content:
          application/json:
            schema:
              type: object
              required:
                - memorial_reference
                - customer
              properties:
                memorial_reference:
                  type: string
                  pattern: '^[a-f0-9]{12}$'
                customer:
                  type: object
                  required:
                    - name
                    - email
```

**Wat Je Er Mee Kunt:**
1. **View in Swagger UI** - Interactive API explorer
2. **Auto-generate client code** - PHP, JavaScript, Python
3. **Validate in CI/CD** - Catch breaking changes
4. **Documentation** - Always up-to-date

**Hoe Je Het Genereert:**
```bash
php artisan havun:openapi:generate

# Output: storage/api/openapi.yaml
```

---

### Component 2: **API Contract Registry** (custom, zoals Google)

**Wat Het Is:**
Laravel service die contracts registreert en valideert.

**Server Side (HavunAdmin):**
```php
// Register wat je verwacht
app(APIContractRegistry::class)->registerEndpoint('invoice_sync', [
    'required_fields' => [
        'memorial_reference',
        'customer',
        'customer.name',
        'customer.email',
    ],
    'field_types' => [
        'memorial_reference' => 'string',
        'customer.email' => 'string',
    ],
]);

// Saved in MCP voor alle projecten
```

**Client Side (Herdenkingsportaal):**
```php
use Havun\Core\Traits\ValidatesAPIContract;

class SyncInvoiceJob
{
    use ValidatesAPIContract;

    public function handle()
    {
        $payload = $this->prepareData();

        // ✅ VALIDATE BEFORE SENDING
        $this->assertValidContract('invoice_sync', $payload);
        // ^ Throws exception if invalid

        $this->send($payload);
    }
}
```

**Wat Het Doet:**
1. Checkt required fields
2. Checkt field types
3. Waarschuwt deprecated fields
4. Throws exception als invalid
5. → Catch errors LOKAAL, niet in productie!

---

### Component 3: **Pact Contract Testing** (zoals Netflix)

**Wat Het Is:**
Consumer schrijft test die zegt: "Dit verwacht ik van de API".
Provider bewijst dat het kan voldoen.

**Herdenkingsportaal (Consumer):**
```php
// tests/Pact/InvoiceSyncPactTest.php
use Havun\Core\Testing\PactContractBuilder;

$pact = PactContractBuilder::invoiceSyncExample();
$pact->save('./pacts');

// Generates: pacts/herdenkingsportaal-havunadmin.json
```

**HavunAdmin (Provider):**
```bash
# In CI/CD pipeline
pact verify \
  --provider havunadmin \
  --pact-url ./pacts/herdenkingsportaal-havunadmin.json

# If API changed and breaks pact → CI FAILS ❌
```

**Voordeel:**
- Herdenkingsportaal defines expectations
- HavunAdmin must meet them
- Breaking changes caught in CI
- No integration environment needed

---

### Component 4: **CI/CD Validation** (zoals Shopify)

**Wat Het Is:**
GitHub Actions workflow die ALLES checkt bij elke pull request.

**Workflow:**
```yaml
# .github/workflows/api-contract-check.yml
on: pull_request

jobs:
  validate:
    steps:
      - Generate OpenAPI spec
      - Validate spec (Spectral linter)
      - Check breaking changes vs master
      - Comment on PR if breaking changes found
      - Block merge if incompatible
```

**Result:**
- PR met breaking change → Can't merge
- Team ziet: "🚨 Breaking change: removed field 'customer'"
- Fix first, then merge

---

## 🔄 Hoe Het Werkt - Stap Voor Stap

Laat me je door het HELE proces leiden:

### Stap 1: Server Registreert Contract

**In HavunAdmin:**

```php
// app/Console/Commands/RegisterAPIContracts.php
use Havun\Core\Services\APIContractRegistry;

class RegisterAPIContracts extends Command
{
    public function handle(APIContractRegistry $registry)
    {
        $registry->registerEndpoint('invoice_sync', [
            'version' => '2.0',
            'endpoint' => 'POST /api/invoices/sync',

            'required_fields' => [
                'memorial_reference',
                'customer',
                'customer.name',
                'customer.email',
                'invoice.number',
                'invoice.total_amount',
                'payment.mollie_payment_id',
            ],

            'field_types' => [
                'memorial_reference' => 'string',
                'invoice.total_amount' => 'float',
            ],

            'field_descriptions' => [
                'memorial_reference' => 'Unique memorial reference (12 hex chars)',
                'invoice.total_amount' => 'Total including VAT',
            ],
        ]);

        $this->info('✅ Contract registered in MCP');
    }
}
```

**Run bij deployment:**
```bash
php artisan api:register-contracts
```

**Wat Gebeurt:**
1. Contract saved in MCP
2. Alle projecten kunnen het lezen
3. OpenAPI spec kan gegenereerd worden

---

### Stap 2: Generate OpenAPI Spec

**In HavunAdmin:**

```bash
php artisan havun:openapi:generate --output=storage/api/openapi.yaml
```

**Output:**
```yaml
# storage/api/openapi.yaml
openapi: 3.0.3
info:
  title: HavunAdmin API
  version: 2.0.0

paths:
  /api/invoices/sync:
    post:
      operationId: invoice_sync
      summary: Sync invoice from Herdenkingsportaal
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/InvoiceSyncRequest'
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/InvoiceSyncResponse'
        '400':
          description: Bad request
        '422':
          description: Validation failed

components:
  schemas:
    InvoiceSyncRequest:
      type: object
      required:
        - memorial_reference
        - customer
      properties:
        memorial_reference:
          type: string
          description: Unique memorial reference (12 hex chars)
        customer:
          type: object
          required:
            - name
            - email
          properties:
            name:
              type: string
            email:
              type: string
```

**Wat Je Nu Hebt:**
- ✅ Complete API documentatie
- ✅ Machine-readable spec
- ✅ Can be validated
- ✅ Can generate client code

---

### Stap 3: Client Valideert Lokaal

**In Herdenkingsportaal:**

```php
// app/Jobs/SyncInvoiceJob.php
use Havun\Core\Traits\ValidatesAPIContract;

class SyncInvoiceJob implements ShouldQueue
{
    use ValidatesAPIContract;

    public function handle(InvoiceSyncService $syncService)
    {
        // Prepare invoice data
        $invoiceData = [
            'memorial_reference' => $this->memorial->memorial_reference,
            'customer' => [
                'name' => $this->memorial->customer_name,
                'email' => $this->memorial->customer_email,
            ],
            'invoice' => [
                'number' => $this->invoice->invoice_number,
                'total_amount' => $this->payment->amount * 1.21,
            ],
            'payment' => [
                'mollie_payment_id' => $this->payment->mollie_id,
                'status' => 'paid',
            ],
        ];

        // ✅ VALIDATE BEFORE SENDING
        $validation = $this->validateContract('invoice_sync', $invoiceData);

        if (!$validation['valid']) {
            // Log errors
            Log::error('Invoice sync validation failed', [
                'errors' => $validation['errors'],
                'memorial_reference' => $this->memorial->memorial_reference,
            ]);

            // Don't send - would fail anyway
            throw new \Exception('Payload does not match API contract: ' . implode(', ', $validation['errors']));
        }

        // Show warnings (deprecated fields, etc.)
        if (!empty($validation['warnings'])) {
            Log::warning('Invoice sync warnings', $validation['warnings']);
        }

        // ✅ SAFE TO SEND - contract valid
        $response = $syncService->sendToHavunAdmin($invoiceData);

        Log::info('Invoice synced successfully', [
            'invoice_id' => $response->invoiceId,
        ]);
    }
}
```

**Wat Gebeurt:**

1. **Prepare data** - Build payload
2. **Validate** - Check against contract
   - Missing field? → Error
   - Wrong type? → Error
   - Deprecated field? → Warning
3. **If valid** - Send to API
4. **If invalid** - Throw exception, log errors

**Voordeel:**
- Errors caught LOKAAL
- Not in production!
- Clear error messages
- Know exactly what's wrong

---

### Stap 4: CI/CD Catches Breaking Changes

**Scenario: HavunAdmin Wijzigt API**

**Developer in HavunAdmin:**
```php
// Adds new required field
$registry->registerEndpoint('invoice_sync', [
    'required_fields' => [
        'memorial_reference',
        'customer',
        'customer_snapshot',  // ← NIEUW REQUIRED FIELD!
    ],
]);
```

**Push to GitHub → Pull Request**

**CI/CD Workflow Runs:**

```bash
1. Generate new OpenAPI spec ✓
2. Compare with master branch spec ✓
3. Detect changes:
   - New required field: customer_snapshot ⚠️
   - Type: BREAKING CHANGE 🚨
4. Post comment on PR:
   "🚨 Breaking Change Detected!
    New required field: customer_snapshot
    This will break Herdenkingsportaal integration!"
5. Block merge until reviewed ❌
```

**Team Actions:**
1. Review breaking change
2. Decide: Really needed?
3. If yes:
   - Bump API version (v2.0 → v3.0)
   - Notify Herdenkingsportaal team via MCP
   - Create migration guide
   - Give 3-6 months grace period
4. If no:
   - Make field optional
   - Re-run CI

**Voordeel:**
- Breaking changes caught BEFORE merge
- Team must review
- Consumers notified
- Migration plan required

---

## 📖 Praktisch Voorbeeld - Van Start Tot Finish

Laat me je door een COMPLEET voorbeeld leiden:

### Scenario

**HavunAdmin** wil een nieuwe API maken voor **VPDUpdate** integratie.

### Stap 1: Design Contract (5 min)

**In HavunAdmin:**

```php
// config/api_contracts.php
return [
    'vpd_data_fetch' => [
        'version' => '1.0',
        'endpoint' => 'GET /api/vpd/data',
        'summary' => 'Fetch VPD data for sync',

        'required_fields' => [
            'version',  // API version
        ],

        'optional_fields' => [
            'filter',
            'limit',
            'offset',
        ],

        'field_types' => [
            'version' => 'string',
            'limit' => 'integer',
            'offset' => 'integer',
        ],

        'field_descriptions' => [
            'version' => 'VPD API version (e.g., "1.0")',
            'limit' => 'Max results (default: 100, max: 1000)',
        ],

        'examples' => [
            'version' => '1.0',
            'limit' => 100,
        ],
    ],
];
```

### Stap 2: Generate OpenAPI (1 min)

```bash
cd D:/GitHub/HavunAdmin
php artisan havun:openapi:generate

# Output: storage/api/openapi.yaml (18 KB)
```

### Stap 3: View in Swagger UI (2 min)

```bash
# Option A: Online editor
# 1. Copy storage/api/openapi.yaml
# 2. Go to https://editor.swagger.io/
# 3. Paste content
# 4. → Interactive API explorer!

# Option B: Local Swagger UI
npx swagger-ui-watcher storage/api/openapi.yaml
# → Opens browser with interactive docs
```

**Result:**
- See all endpoints
- Try API calls in browser
- Generate code samples (PHP, JS, Python)

### Stap 4: Implement Server Side (30 min)

**In HavunAdmin:**

```php
// app/Http/Controllers/Api/VPDController.php
use Illuminate\Http\Request;

class VPDController extends Controller
{
    public function getData(Request $request)
    {
        // Validate (Laravel does this automatically from OpenAPI)
        $validated = $request->validate([
            'version' => 'required|string',
            'limit' => 'integer|min:1|max:1000',
            'offset' => 'integer|min:0',
        ]);

        // Fetch VPD data
        $data = VPDService::fetch(
            version: $validated['version'],
            limit: $validated['limit'] ?? 100,
            offset: $validated['offset'] ?? 0
        );

        return response()->json([
            'data' => $data,
            'version' => $validated['version'],
            'count' => count($data),
        ]);
    }
}
```

```php
// routes/api.php
Route::get('/api/vpd/data', [VPDController::class, 'getData'])
    ->middleware('auth:api');
```

### Stap 5: Client Side Implementation (20 min)

**In HavunAdmin (yes, HavunAdmin calls VPDUpdate):**

```php
// app/Services/VPDSyncService.php
use Havun\Core\Traits\ValidatesAPIContract;
use Illuminate\Support\Facades\Http;

class VPDSyncService
{
    use ValidatesAPIContract;

    public function fetchData(string $version = '1.0', int $limit = 100)
    {
        // Prepare request params
        $params = [
            'version' => $version,
            'limit' => $limit,
        ];

        // ✅ VALIDATE BEFORE SENDING
        $this->assertValidContract('vpd_data_fetch', $params);

        // Call VPDUpdate API
        $response = Http::withToken(config('services.vpdupdate.api_token'))
            ->get(config('services.vpdupdate.api_url') . '/api/vpd/data', $params);

        if ($response->failed()) {
            throw new \Exception('VPD API call failed: ' . $response->body());
        }

        return $response->json();
    }
}
```

**Config:**
```php
// config/services.php
'vpdupdate' => [
    'api_url' => env('VPDUPDATE_API_URL', 'https://vpdupdate.example.com'),
    'api_token' => env('VPDUPDATE_API_TOKEN'),
],
```

### Stap 6: Write Pact Test (15 min)

**In HavunAdmin:**

```php
// tests/Pact/VPDDataFetchTest.php
use Havun\Core\Testing\PactContractBuilder;

class VPDDataFetchTest extends TestCase
{
    public function test_can_fetch_vpd_data()
    {
        $pact = new PactContractBuilder('HavunAdmin', 'VPDUpdate');

        $pact->addInteraction(
            description: 'A request to fetch VPD data',
            state: 'VPD data exists for version 1.0',
            request: [
                'method' => 'GET',
                'path' => '/api/vpd/data',
                'query' => [
                    'version' => '1.0',
                    'limit' => '100',
                ],
            ],
            response: [
                'status' => 200,
                'body' => [
                    'data' => [/* array of VPD items */],
                    'version' => '1.0',
                    'count' => 100,
                ],
            ]
        );

        $filepath = $pact->save('./pacts');
        $this->assertFileExists($filepath);
    }
}
```

**Run test:**
```bash
vendor/bin/phpunit tests/Pact/VPDDataFetchTest.php

# Generates: pacts/havunadmin-vpdupdate.json
```

### Stap 7: Share Pact with VPDUpdate (5 min)

```bash
# Send pact file to VPDUpdate team
cp pacts/havunadmin-vpdupdate.json ../VPDUpdate/pacts/

# Or commit to shared repo
git add pacts/
git commit -m "Add VPD data fetch pact"
git push
```

### Stap 8: VPDUpdate Verifies Pact (10 min)

**In VPDUpdate:**

```bash
# Install pact-php
composer require --dev pact-foundation/pact-php

# Verify provider can meet consumer expectations
pact verify \
  --provider vpdupdate \
  --pact-url ../HavunAdmin/pacts/havunadmin-vpdupdate.json \
  --provider-base-url http://localhost:8000

# ✅ All interactions verified!
# OR
# ❌ Failed: Missing field 'count' in response
```

**If verification fails:**
1. VPDUpdate knows EXACTLY what's wrong
2. Fix API to meet expectations
3. Re-run verification
4. ✅ Pass → Ready to deploy

### Stap 9: Add to CI/CD (10 min)

**In HavunAdmin:**

```yaml
# .github/workflows/test.yml
- name: Run Pact tests
  run: vendor/bin/phpunit tests/Pact/

- name: Publish pacts
  run: |
    # Upload to Pact Broker (optional)
    # Or commit to repo
    git add pacts/
    git commit -m "Update pacts" || true
```

**In VPDUpdate:**

```yaml
# .github/workflows/test.yml
- name: Verify pacts
  run: |
    pact verify \
      --provider vpdupdate \
      --pact-url ../HavunAdmin/pacts/havunadmin-vpdupdate.json

# If this fails → CI fails → Can't merge!
```

### Stap 10: Deploy & Monitor (ongoing)

**Deploy:**
```bash
# HavunAdmin
git tag v2.1.0
git push --tags

# VPDUpdate
git tag v1.0.0
git push --tags
```

**Monitor:**
```php
// Track API calls
Log::info('VPD data fetched', [
    'version' => $version,
    'count' => count($data),
    'duration_ms' => $duration,
]);

// Alert on failures
if ($failureRate > 0.05) {
    // Send Slack notification
    // "🚨 VPD API failure rate: 5%"
}
```

---

**Totale tijd:** ~1.5 uur voor complete setup
**Voordeel:** Catch ALL issues before production!

---

## 🚀 Setup & Implementatie

Nu gaan we het implementeren voor jouw projecten.

### Prerequisites

```bash
# 1. Update HavunCore in alle projecten
cd D:/GitHub/Herdenkingsportaal
composer update havun/core

cd D:/GitHub/HavunAdmin
composer update havun/core

# 2. Install OpenAPI tools (globally)
npm install -g @stoplight/spectral-cli
npm install -g @openapitools/openapi-generator-cli
npm install -g openapi-diff

# 3. Install Pact (optional, voor contract testing)
composer require --dev pact-foundation/pact-php
```

### Setup 1: HavunAdmin (Server)

**Stap 1: Create API contracts config**

```bash
cd D:/GitHub/HavunAdmin
touch config/api_contracts.php
```

```php
// config/api_contracts.php
<?php

return [
    'invoice_sync' => [
        'version' => '2.0',
        'endpoint' => 'POST /api/invoices/sync',
        'summary' => 'Sync invoice from Herdenkingsportaal',

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

        'optional_fields' => [
            'customer.phone',
            'invoice.due_date',
            'metadata',
        ],

        'field_types' => [
            'memorial_reference' => 'string',
            'invoice.total_amount' => 'float',
        ],

        'field_descriptions' => [
            'memorial_reference' => 'Unique memorial reference (12 hex chars)',
        ],

        'examples' => [
            'memorial_reference' => '550e8400e29b',
            'invoice.total_amount' => 24.14,
        ],
    ],
];
```

**Stap 2: Generate OpenAPI spec**

```bash
php artisan havun:openapi:generate

# Output:
# ✅ OpenAPI specification generated successfully!
# 📄 File: D:\GitHub\HavunAdmin\storage\api\openapi.yaml
# 📏 Size: 18.5 KB
```

**Stap 3: View in Swagger UI**

```bash
# Option A: Online
# 1. cat storage/api/openapi.yaml
# 2. Copy content
# 3. Go to https://editor.swagger.io/
# 4. Paste

# Option B: Local
npx swagger-ui-watcher storage/api/openapi.yaml
# → Opens browser at http://localhost:8080
```

**Stap 4: Commit spec to Git**

```bash
git add storage/api/openapi.yaml config/api_contracts.php
git commit -m "Add OpenAPI specification for Invoice Sync API"
git push
```

---

### Setup 2: Herdenkingsportaal (Client)

**Stap 1: Add validation to sync job**

```php
// app/Jobs/SyncInvoiceJob.php
use Havun\Core\Traits\ValidatesAPIContract;

class SyncInvoiceJob implements ShouldQueue
{
    use ValidatesAPIContract;  // ← Add this

    public function handle(InvoiceSyncService $syncService)
    {
        $invoiceData = $syncService->prepareInvoiceData($this->memorial, $this->payment);

        // ✅ ADD THIS VALIDATION
        try {
            $this->assertValidContract('invoice_sync', $invoiceData);
        } catch (\Exception $e) {
            Log::error('Invoice sync validation failed', [
                'error' => $e->getMessage(),
                'memorial_reference' => $this->memorial->memorial_reference,
            ]);
            throw $e;
        }

        // Continue with sync
        $response = $syncService->sendToHavunAdmin($invoiceData);
    }
}
```

**Stap 2: Test validation**

```bash
php artisan tinker
```

```php
$memorial = Memorial::first();
$payment = PaymentTransaction::first();

// This will validate and show errors
$job = new App\Jobs\SyncInvoiceJob($memorial, $payment);
$job->handle(app(InvoiceSyncService::class));

// If validation fails:
// Exception: "Payload does not match API contract: Missing required field: customer.email"
```

**Stap 3: Write Pact test (optional but recommended)**

```php
// tests/Pact/InvoiceSyncPactTest.php
<?php

namespace Tests\Pact;

use Havun\Core\Testing\PactContractBuilder;
use Tests\TestCase;

class InvoiceSyncPactTest extends TestCase
{
    public function test_can_sync_invoice_to_havunadmin()
    {
        $pact = PactContractBuilder::invoiceSyncExample();
        $filepath = $pact->save(base_path('pacts'));

        $this->assertFileExists($filepath);

        echo "\n✅ Pact generated: {$filepath}\n";
        echo "📤 Share this with HavunAdmin team\n";
    }
}
```

```bash
vendor/bin/phpunit tests/Pact/InvoiceSyncPactTest.php

# Output:
# ✅ Pact generated: D:/GitHub/Herdenkingsportaal/pacts/herdenkingsportaal-havunadmin.json
# 📤 Share this with HavunAdmin team
```

---

### Setup 3: CI/CD Integration

**Voor HavunAdmin:**

```bash
# Copy workflow file
cp D:/GitHub/HavunCore/.github/workflows/api-contract-check.yml \
   D:/GitHub/HavunAdmin/.github/workflows/
```

**Voor Herdenkingsportaal:**

```yaml
# .github/workflows/test.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2

      - name: Install dependencies
        run: composer install

      - name: Run tests
        run: vendor/bin/phpunit

      - name: Validate API payloads
        run: |
          # Test that payloads match contracts
          php artisan test --filter=ApiValidationTest
```

**Push to GitHub:**

```bash
git add .github/workflows/
git commit -m "Add CI/CD for API contract validation"
git push

# → GitHub Actions will run on every PR
# → Breaking changes will be caught automatically
```

---

## 🔍 CI/CD Integration - Hoe Het Werkt

### Wat Gebeurt Bij Pull Request

**Developer maakt PR in HavunAdmin:**

```bash
git checkout -b feature/add-customer-snapshot
# Make changes to API
git commit -m "Add customer_snapshot field to invoice API"
git push origin feature/add-customer-snapshot
# Create pull request
```

**GitHub Actions Workflow Start:**

```
┌─────────────────────────────────────────────────────────┐
│ Step 1: Generate OpenAPI spec                          │
│ → php artisan havun:openapi:generate                   │
│ → storage/api/openapi.yaml created                     │
└─────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────┐
│ Step 2: Validate spec                                  │
│ → spectral lint storage/api/openapi.yaml               │
│ → Check for errors in schema                           │
│ → ✅ Passed                                            │
└─────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────┐
│ Step 3: Compare with master branch                     │
│ → git show master:storage/api/openapi.yaml             │
│ → openapi-diff old.yaml new.yaml                       │
│ → 🚨 BREAKING CHANGE DETECTED!                         │
│                                                         │
│   Changes:                                              │
│   - New required field: customer_snapshot              │
│   - Type: object                                        │
│   - Severity: HIGH                                      │
└─────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────┐
│ Step 4: Comment on PR                                  │
│                                                         │
│   🚨 API Breaking Changes Detected                     │
│                                                         │
│   ### Changes                                           │
│   - **New required field:** customer_snapshot          │
│   - **Type:** object                                    │
│   - **Severity:** HIGH                                  │
│                                                         │
│   ⚠️ Action Required:                                   │
│   - Notify API consumers (Herdenkingsportaal)          │
│   - Update API version (2.0 → 3.0)                     │
│   - Add migration guide                                 │
│   - Give 3-6 months grace period                       │
└─────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────┐
│ Step 5: Block merge (optional)                         │
│ → CI status: ❌ FAILED                                 │
│ → Merge button disabled                                │
│ → Team must review breaking changes                    │
└─────────────────────────────────────────────────────────┘
```

**Result:**
- Developer ziet direct: "Breaking change!"
- Can't merge without review
- Team discusses: Is this necessary?
- If yes: Plan migration, notify consumers
- If no: Make field optional

---

## 🐛 Troubleshooting

### Probleem 1: "Contract not found"

**Symptoom:**
```php
$this->assertValidContract('invoice_sync', $payload);
// Exception: Contract not found for endpoint: invoice_sync
```

**Oplossing:**

```php
// Check if contract is registered
// In APIContractRegistry->getContract()

// Option A: Add to hardcoded contracts
private function getContracts(): array
{
    return [
        'invoice_sync' => [/* contract */],
    ];
}

// Option B: Load from config
// Create config/api_contracts.php
return [
    'invoice_sync' => [/* contract */],
];
```

---

### Probleem 2: "OpenAPI generation fails"

**Symptoom:**
```bash
php artisan havun:openapi:generate
# Error: No API contracts found
```

**Oplossing:**

```bash
# Check if config exists
ls -la config/api_contracts.php

# If not, create it:
cp D:/GitHub/HavunCore/config/api_contracts.example.php config/api_contracts.php

# Edit and add your contracts
```

---

### Probleem 3: "Validation false positives"

**Symptoom:**
```
Validation failed: Field 'invoice.vat_amount' has wrong type. Expected: float, got: integer
```

**Oplossing:**

```php
// PHP integers are valid for float fields
// Update field type checking in APIContractRegistry:

private function isCorrectType($value, string $expectedType): bool
{
    return match($expectedType) {
        'float', 'double' => is_float($value) || is_int($value),  // ← Accept int for float
        // ...
    };
}
```

---

### Probleem 4: "CI/CD workflow doesn't run"

**Symptoom:**
GitHub Actions workflow niet triggered bij PR.

**Oplossing:**

```yaml
# Check workflow file location:
# .github/workflows/api-contract-check.yml  ← Correct
# github/workflows/api-contract-check.yml   ← Wrong (missing dot)

# Check triggers:
on:
  pull_request:
    paths:
      - 'config/api_contracts.php'
      - 'storage/api/openapi.yaml'

# If you change other files, workflow won't run
# Solution: Add more paths or use:
on:
  pull_request:  # Triggers on ALL PRs
```

---

### Probleem 5: "Breaking change detection too sensitive"

**Symptoom:**
Adding optional field triggers breaking change alert.

**Oplossing:**

```yaml
# In .github/workflows/api-contract-check.yml
# Use different openapi-diff options:

openapi-diff old.yaml new.yaml \
  --fail-on-incompatible \    # Only fail on breaking changes
  --ignore-optional-fields    # Ignore new optional fields
```

---

## 🎯 Best Practices

### 1. API Versioning

```php
// Start all APIs at v1
'endpoint' => 'POST /api/v1/invoices/sync',

// Breaking change? Bump to v2
'endpoint' => 'POST /api/v2/invoices/sync',

// Keep v1 running for 6-12 months
// Then deprecate with warnings:
Route::post('/api/v1/invoices/sync', function() {
    return response()->json([
        'deprecated' => true,
        'message' => 'This API version is deprecated. Please upgrade to v2.',
        'sunset_date' => '2026-06-01',
    ], 426);  // HTTP 426 Upgrade Required
});
```

### 2. Contract Updates

```php
// Always bump version when changing contract
'version' => '2.1',  // Minor version for non-breaking changes
'version' => '3.0',  // Major version for breaking changes

// Document changes
'changelog' => [
    '3.0' => 'Added required field: customer_snapshot (BREAKING)',
    '2.1' => 'Added optional field: invoice.notes',
    '2.0' => 'Initial version',
],
```

### 3. Testing

```php
// Test validation in feature tests
public function test_invoice_sync_validation()
{
    $invalidPayload = [
        'memorial_ref' => '123',  // Wrong field name
    ];

    $validation = app(APIContractRegistry::class)
        ->validatePayload('invoice_sync', $invalidPayload);

    $this->assertFalse($validation['valid']);
    $this->assertContains('Missing required field: memorial_reference', $validation['errors']);
}
```

### 4. Documentation

```php
// Add examples to contracts
'examples' => [
    'memorial_reference' => '550e8400e29b',
    'customer.name' => 'Jan Jansen',
    'invoice.total_amount' => 24.14,
],

// Add descriptions
'field_descriptions' => [
    'memorial_reference' => 'Unique 12-character hex identifier',
    'invoice.total_amount' => 'Total amount in EUR including 21% VAT',
],
```

### 5. Migration Guides

```markdown
# Migration Guide: Invoice Sync API v2 → v3

## Breaking Changes

### 1. New Required Field: customer_snapshot

**What changed:**
- `customer_snapshot` is now required
- Must contain full customer data at time of purchase

**Why:**
- GDPR compliance
- Historical record keeping

**How to migrate:**

\`\`\`php
// Old (v2):
$payload = [
    'customer' => [
        'name' => $name,
        'email' => $email,
    ],
];

// New (v3):
$payload = [
    'customer' => [
        'name' => $name,
        'email' => $email,
    ],
    'customer_snapshot' => [  // ← ADD THIS
        'name' => $name,
        'email' => $email,
        'address' => $address,
        'phone' => $phone,
        'captured_at' => now(),
    ],
];
\`\`\`

**Timeline:**
- Now: v2 still works
- 2026-03-01: v2 deprecated warning
- 2026-06-01: v2 removed
```

---

## 🎉 Summary

**Wat Je Nu Hebt:**

1. ✅ **OpenAPI/Swagger** - Industry standard API specs
2. ✅ **Contract Validation** - Catch errors before sending
3. ✅ **Pact Testing** - Consumer-driven contracts
4. ✅ **CI/CD Integration** - Auto-detect breaking changes
5. ✅ **MCP Integration** - Cross-project communication

**Hoe Het Je Helpt:**

- 🚫 **No more API mismatches** - Validation catches errors
- 🚫 **No more production failures** - Test locally first
- 🚫 **No more breaking changes surprises** - CI alerts team
- ✅ **Clear documentation** - OpenAPI specs always up-to-date
- ✅ **Team alignment** - Everyone knows what to expect

**Wat Professionele Bedrijven Doen:**
- Stripe: OpenAPI + auto-generated clients
- Google: gRPC + Protocol Buffers
- Netflix: Pact contract testing
- Shopify: API versioning + migration guides
- ING Bank: API Gateway validation

**Wat Jij Nu Doet:**
- ✅ Zelfde level als tech giants
- ✅ Industry best practices
- ✅ Professional API management

---

**🚀 Klaar om te implementeren!**

Vragen? Check de troubleshooting sectie of vraag het me! 😊
