# 🔗 HavunCore - Integration Guide

**Praktische voorbeelden voor het integreren van HavunCore in je Laravel projecten**

---

## 📋 Inhoudsopgave

1. [Herdenkingsportaal Integration](#herdenkingsportaal-integration)
2. [HavunAdmin Integration](#havunadmin-integration)
3. [IDSee Integration](#idsee-integration)
4. [Laravel Service Container](#laravel-service-container)
5. [Database Schema](#database-schema)
6. [Complete Workflow Examples](#complete-workflow-examples)

---

## Herdenkingsportaal Integration

### Gebruik Case: Monument Payment Flow

**Scenario:** Klant betaalt voor monument via Mollie, memorial reference wordt automatisch toegevoegd.

#### **1. Controller Setup**

```php
<?php

namespace App\Http\Controllers;

use Havun\Core\Services\MollieService;
use Havun\Core\Services\MemorialReferenceService;
use App\Models\Monument;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected MollieService $mollie;
    protected MemorialReferenceService $memorialService;

    public function __construct()
    {
        $this->mollie = new MollieService(config('services.mollie.key'));
        $this->memorialService = new MemorialReferenceService();
    }

    public function create(Monument $monument)
    {
        // Generate memorial reference from monument UUID
        $memorialReference = $this->memorialService->fromUuid($monument->id);

        // Create Mollie payment
        $payment = $this->mollie->createPayment(
            amount: 19.95,
            description: "Monument: {$monument->name}",
            memorialReference: $memorialReference,
            redirectUrl: route('payment.return'),
            webhookUrl: route('payment.webhook')
        );

        // Store payment ID in database
        $monument->update([
            'mollie_payment_id' => $payment['id'],
            'payment_status' => 'pending'
        ]);

        // Redirect to Mollie checkout
        return redirect($payment['_links']['checkout']['href']);
    }

    public function webhook(Request $request)
    {
        $paymentId = $request->input('id');
        $payment = $this->mollie->getPayment($paymentId);

        if ($this->mollie->isPaid($payment)) {
            // Extract memorial reference
            $reference = $this->mollie->extractMemorialReference($payment);

            // Find monument
            $monument = Monument::whereRaw(
                "LOWER(REPLACE(id, '-', '')) LIKE ?",
                [$reference . '%']
            )->first();

            if ($monument) {
                $monument->update([
                    'payment_status' => 'paid',
                    'paid_at' => now()
                ]);

                // Send confirmation email, etc.
            }
        }

        return response()->json(['status' => 'success']);
    }
}
```

#### **2. Model Setup**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Havun\Core\Services\MemorialReferenceService;

class Monument extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',  // UUID
        'name',
        'mollie_payment_id',
        'payment_status',
        'paid_at'
    ];

    /**
     * Get memorial reference (eerste 12 chars van UUID)
     */
    public function getMemorialReferenceAttribute(): string
    {
        $service = new MemorialReferenceService();
        return $service->fromUuid($this->id);
    }

    /**
     * Get formatted memorial reference voor display
     */
    public function getFormattedReferenceAttribute(): string
    {
        $service = new MemorialReferenceService();
        return $service->formatReference($this->memorial_reference);
    }
}
```

#### **3. Blade View**

```blade
{{-- resources/views/monument/show.blade.php --}}

<div class="monument-details">
    <h1>{{ $monument->name }}</h1>

    <p>
        <strong>Referentienummer:</strong>
        <code>{{ $monument->formatted_reference }}</code>
    </p>

    @if($monument->payment_status === 'paid')
        <div class="alert alert-success">
            ✅ Betaald op {{ $monument->paid_at->format('d-m-Y H:i') }}
        </div>
    @else
        <a href="{{ route('payment.create', $monument) }}" class="btn btn-primary">
            Betaal nu (€19,95)
        </a>
    @endif
</div>
```

---

## HavunAdmin Integration

### Gebruik Case: Transaction Matching & Reconciliation

**Scenario:** Automatisch matchen van inkomende betalingen (Mollie, Bunq, Gmail) aan invoices via memorial reference.

#### **1. Sync Controller**

```php
<?php

namespace App\Http\Controllers;

use Havun\Core\Services\MollieService;
use Havun\Core\Services\MemorialReferenceService;
use App\Models\Invoice;
use App\Models\Transaction;

class SyncController extends Controller
{
    protected MollieService $mollie;
    protected MemorialReferenceService $memorialService;

    public function __construct()
    {
        $this->mollie = new MollieService(config('services.mollie.key'));
        $this->memorialService = new MemorialReferenceService();
    }

    public function syncMollie()
    {
        // Fetch last 50 payments
        $payments = $this->mollie->listPayments(50);

        $synced = 0;
        $matched = 0;

        foreach ($payments as $payment) {
            if (!$this->mollie->isPaid($payment)) {
                continue;
            }

            // Check if already synced
            if (Transaction::where('mollie_payment_id', $payment['id'])->exists()) {
                continue;
            }

            // Extract memorial reference
            $reference = $this->mollie->extractMemorialReference($payment);

            // Create transaction
            $transaction = Transaction::create([
                'mollie_payment_id' => $payment['id'],
                'amount' => $payment['amount']['value'],
                'description' => $payment['description'],
                'memorial_reference' => $reference,
                'source' => 'mollie',
                'transaction_date' => $payment['paidAt'],
                'status' => 'synced'
            ]);

            $synced++;

            // Try to match to invoice
            if ($reference && $invoice = Invoice::where('memorial_reference', $reference)->first()) {
                $transaction->update([
                    'invoice_id' => $invoice->id,
                    'status' => 'matched'
                ]);

                $invoice->update([
                    'payment_status' => 'paid',
                    'paid_at' => $payment['paidAt']
                ]);

                $matched++;
            }
        }

        return response()->json([
            'synced' => $synced,
            'matched' => $matched
        ]);
    }
}
```

#### **2. Invoice Model with Memorial Reference**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Havun\Core\Services\MemorialReferenceService;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'amount',
        'description',
        'memorial_reference',  // 12 chars
        'payment_status',
        'paid_at'
    ];

    /**
     * Scope: Find by memorial reference
     */
    public function scopeByMemorialReference($query, string $reference)
    {
        return $query->where('memorial_reference', strtolower($reference));
    }

    /**
     * Get formatted reference voor display
     */
    public function getFormattedReferenceAttribute(): string
    {
        if (!$this->memorial_reference) {
            return 'N/A';
        }

        $service = new MemorialReferenceService();
        return $service->formatReference($this->memorial_reference);
    }

    /**
     * Relation: Transactions
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
```

#### **3. Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('memorial_reference', 12)->nullable()->after('description');
            $table->index('memorial_reference');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['memorial_reference']);
            $table->dropColumn('memorial_reference');
        });
    }
};
```

---

## IDSee Integration

### Gebruik Case: Client Invoice with Optional Memorial Reference

**Scenario:** Consultancy project waar sommige klanten ook Herdenkingsportaal gebruiken.

#### **1. Invoice Creation**

```php
<?php

namespace App\Http\Controllers;

use Havun\Core\Services\MemorialReferenceService;
use App\Models\ClientInvoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string',
            'memorial_link' => 'nullable|string'  // Optional monument URL
        ]);

        $memorialReference = null;

        // Check if memorial link contains reference
        if ($validated['memorial_link']) {
            $service = new MemorialReferenceService();
            $memorialReference = $service->extractMemorialReference($validated['memorial_link']);

            // Validate
            if ($memorialReference && !$service->isValidReference($memorialReference)) {
                return back()->withErrors([
                    'memorial_link' => 'Ongeldige memorial reference gevonden'
                ]);
            }
        }

        $invoice = ClientInvoice::create([
            'client_id' => $validated['client_id'],
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'memorial_reference' => $memorialReference
        ]);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Factuur aangemaakt');
    }
}
```

---

## Laravel Service Container

### Dependency Injection Setup

Register HavunCore services in `AppServiceProvider`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Havun\Core\Services\MollieService;
use Havun\Core\Services\MemorialReferenceService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Memorial Reference Service (singleton)
        $this->app->singleton(MemorialReferenceService::class, function ($app) {
            return new MemorialReferenceService();
        });

        // Mollie Service (singleton)
        $this->app->singleton(MollieService::class, function ($app) {
            return new MollieService(
                config('services.mollie.key'),
                $app->make(MemorialReferenceService::class)
            );
        });
    }
}
```

Nu kun je dependency injection gebruiken:

```php
<?php

namespace App\Http\Controllers;

use Havun\Core\Services\MollieService;

class PaymentController extends Controller
{
    public function __construct(
        protected MollieService $mollie
    ) {}

    public function index()
    {
        $payments = $this->mollie->listPayments(20);
        return view('payments.index', compact('payments'));
    }
}
```

---

## Database Schema

### Recommended Schema voor Memorial Reference Support

```sql
-- Invoices/Monuments table
CREATE TABLE invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT,
    memorial_reference VARCHAR(12) NULL,  -- ← Key field
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_memorial_reference (memorial_reference),
    INDEX idx_payment_status (payment_status)
);

-- Transactions table
CREATE TABLE transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NULL,
    mollie_payment_id VARCHAR(50) NULL,
    bunq_transaction_id VARCHAR(50) NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT,
    memorial_reference VARCHAR(12) NULL,  -- ← Key field
    source ENUM('mollie', 'bunq', 'gmail', 'herdenkingsportaal') NOT NULL,
    transaction_date TIMESTAMP NOT NULL,
    status ENUM('synced', 'matched', 'duplicate') DEFAULT 'synced',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_memorial_reference (memorial_reference),
    INDEX idx_source (source),
    INDEX idx_mollie_payment_id (mollie_payment_id),

    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
);
```

---

## Complete Workflow Examples

### Workflow 1: Herdenkingsportaal Payment → HavunAdmin Sync

```php
// STEP 1: Herdenkingsportaal - Customer pays
// File: Herdenkingsportaal/app/Http/Controllers/PaymentController.php

use Havun\Core\Services\MollieService;
use Havun\Core\Services\MemorialReferenceService;

$memorialService = new MemorialReferenceService();
$mollie = new MollieService(env('MOLLIE_API_KEY'));

// Monument UUID: 550e8400-e29b-41d4-a716-446655440000
$monument = Monument::find($monumentId);
$reference = $memorialService->fromUuid($monument->id);
// → "550e8400e29b"

$payment = $mollie->createPayment(
    amount: 19.95,
    description: "Monument {$monument->name}",
    memorialReference: $reference  // ← Stored in Mollie metadata
);

// STEP 2: Mollie - Payment completed
// Mollie webhook calls: POST /webhook/mollie

// STEP 3: HavunAdmin - Sync Mollie payments
// File: HavunAdmin/app/Http/Controllers/SyncController.php

$payments = $mollie->listPayments(50);

foreach ($payments as $payment) {
    if ($mollie->isPaid($payment)) {
        $reference = $mollie->extractMemorialReference($payment);
        // → "550e8400e29b"

        Transaction::create([
            'amount' => $payment['amount']['value'],
            'memorial_reference' => $reference,
            'source' => 'mollie'
        ]);
    }
}

// STEP 4: HavunAdmin - Match to Invoice
// File: HavunAdmin/app/Services/TransactionMatchingService.php

$transaction = Transaction::where('memorial_reference', '550e8400e29b')->first();
$invoice = Invoice::where('memorial_reference', '550e8400e29b')->first();

if ($invoice) {
    $transaction->update(['invoice_id' => $invoice->id]);
    $invoice->update(['payment_status' => 'paid']);
}

// ✅ Payment matched and reconciled!
```

### Workflow 2: Extract Reference from Email

```php
// Gmail email body: "Betaling ontvangen voor monument 550e8400e29b"

use Havun\Core\Services\MemorialReferenceService;

$service = new MemorialReferenceService();
$emailBody = "Betaling ontvangen voor monument 550e8400e29b";

$reference = $service->extractMemorialReference($emailBody);
// → "550e8400e29b"

// Find related invoice
$invoice = Invoice::where('memorial_reference', $reference)->first();

if ($invoice) {
    Transaction::create([
        'invoice_id' => $invoice->id,
        'memorial_reference' => $reference,
        'source' => 'gmail',
        'description' => $emailBody
    ]);
}
```

---

## Testing Integration

### Feature Test Example

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Havun\Core\Services\MollieService;
use Havun\Core\Services\MemorialReferenceService;

class PaymentIntegrationTest extends TestCase
{
    public function test_mollie_payment_with_memorial_reference()
    {
        $mollie = new MollieService(config('services.mollie.test_key'));
        $memorialService = new MemorialReferenceService();

        $reference = '550e8400e29b';

        $payment = $mollie->createPayment(
            amount: 10.00,
            description: 'Test payment',
            memorialReference: $reference
        );

        $this->assertArrayHasKey('id', $payment);
        $this->assertEquals($reference, $payment['metadata']['memorial_reference']);

        // Extract reference
        $extractedReference = $mollie->extractMemorialReference($payment);
        $this->assertEquals($reference, $extractedReference);
    }
}
```

---

## Best Practices

### ✅ DO:

- **Altijd** memorial reference in lowercase opslaan
- **Altijd** valideer reference met `isValidReference()` voor database storage
- **Gebruik** database indexes op `memorial_reference` column
- **Log** alle sync acties voor debugging
- **Handle** duplicate transactions gracefully

### ❌ DON'T:

- **Nooit** memorial reference met hyphens opslaan in database
- **Nooit** assumeren dat reference bestaat - always check `null`
- **Nooit** hard-coded API keys in code

---

## Troubleshooting

### Problem: Memorial reference niet gevonden in payment

```php
$payment = $mollie->getPayment('tr_xxx');
$reference = $mollie->extractMemorialReference($payment);

if (!$reference) {
    Log::warning('No memorial reference in payment', [
        'payment_id' => $payment['id'],
        'metadata' => $payment['metadata']
    ]);

    // Check description
    $reference = $memorialService->extractMemorialReference(
        $payment['description']
    );
}
```

### Problem: Transaction matching fails

```php
$reference = '550e8400e29b';
$invoice = Invoice::where('memorial_reference', $reference)->first();

if (!$invoice) {
    Log::error('No invoice found for memorial reference', [
        'reference' => $reference,
        'similar_invoices' => Invoice::whereRaw(
            "memorial_reference LIKE ?",
            [$reference . '%']
        )->get()
    ]);
}
```

---

**Laatste update:** 2025-11-15
