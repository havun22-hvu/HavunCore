# 🎯 Antwoord op Invoice Sync Problemen

**Van:** HavunCore Development Team
**Aan:** HavunAdmin + Herdenkingsportaal Teams
**Datum:** 17 november 2025
**Status:** Probleem geïdentificeerd + Oplossing voorgesteld

---

## 📊 SITUATIE ANALYSE

### Wat is het kernprobleem?

**HavunAdmin:**
- Invoice model heeft `memorial_reference` field in database ✅
- MAAR: De `Invoice::createFromHerdenkingsportaal()` method **ontbreekt** ❌
- De documentatie beweerde dat deze op regel 580 staat, maar dat is niet waar

**Herdenkingsportaal:**
- API spec is onduidelijk over verplichte velden
- Geen invoices tabel (alleen payment_transactions)
- Onzeker over data structuur

**HavunCore:**
- `InvoiceSyncService` verwacht data van een Monument/Payment object
- Maar Herdenkingsportaal heeft geen Monument model met alle vereiste velden

---

## ✅ OPLOSSING: 3-STAPPEN PLAN

### STAP 1: HavunAdmin - Voeg Ontbrekende Method Toe

**Bestand:** `D:\GitHub\HavunAdmin\app\Models\Invoice.php`

**Voeg toe na regel 577 (voor de laatste `}`):**

```php
/**
 * Create or update invoice from Herdenkingsportaal sync data
 *
 * @param array $data Invoice data from Herdenkingsportaal
 * @return self
 */
public static function createFromHerdenkingsportaal(array $data): self
{
    \Log::info('Creating/updating invoice from Herdenkingsportaal', [
        'memorial_reference' => $data['memorial_reference'] ?? null,
    ]);

    // Find existing invoice by memorial_reference (idempotent)
    $invoice = self::where('memorial_reference', $data['memorial_reference'])->first();

    if ($invoice) {
        \Log::info('Invoice already exists, updating', [
            'invoice_id' => $invoice->id,
            'memorial_reference' => $data['memorial_reference'],
        ]);
    } else {
        \Log::info('Creating new invoice from Herdenkingsportaal');
        $invoice = new self();
    }

    // Map Herdenkingsportaal data to HavunAdmin Invoice fields
    $invoice->fill([
        'type' => 'income',
        'invoice_number' => $data['invoice']['number'],
        'memorial_reference' => $data['memorial_reference'],
        'invoice_date' => $data['invoice']['date'],
        'due_date' => $data['invoice']['due_date'] ?? null,
        'payment_date' => $data['payment']['paid_at'] ?
            \Carbon\Carbon::parse($data['payment']['paid_at'])->format('Y-m-d') : null,
        'description' => $data['invoice']['description'],
        'subtotal' => $data['invoice']['amount'],
        'vat_amount' => $data['invoice']['vat_amount'],
        'vat_percentage' => 21.00,
        'total' => $data['invoice']['total_amount'],
        'status' => self::mapPaymentStatus($data['payment']['status']),
        'payment_method' => $data['payment']['method'] ?? 'mollie',
        'mollie_payment_id' => $data['payment']['mollie_payment_id'] ?? null,
        'source' => 'herdenkingsportaal',
        'external_reference' => $data['metadata']['monument_id'] ?? null,

        // Customer snapshot (bewaar exact zoals het was)
        'customer_snapshot' => [
            'name' => $data['customer']['name'],
            'email' => $data['customer']['email'],
            'phone' => $data['customer']['phone'] ?? null,
            'address' => $data['customer']['address'] ?? null,
        ],

        // Match metadata
        'match_confidence' => 100, // Auto-synced = 100% confidence
        'match_notes' => 'Automatically synced from Herdenkingsportaal via InvoiceSyncService',
    ]);

    $invoice->save();

    \Log::info('Invoice saved successfully', [
        'invoice_id' => $invoice->id,
        'invoice_number' => $invoice->invoice_number,
        'memorial_reference' => $invoice->memorial_reference,
    ]);

    return $invoice;
}

/**
 * Map Mollie payment status to HavunAdmin invoice status
 */
private static function mapPaymentStatus(string $mollieStatus): string
{
    return match($mollieStatus) {
        'paid' => 'paid',
        'open' => 'pending',
        'pending' => 'pending',
        'expired' => 'cancelled',
        'canceled' => 'cancelled',
        'failed' => 'failed',
        default => 'pending',
    };
}
```

**Waarom deze aanpak?**
- ✅ Idempotent (veilig om meerdere keren te draaien)
- ✅ Customer snapshot (historische klantgegevens bewaard)
- ✅ Logging voor debugging
- ✅ Status mapping (Mollie → HavunAdmin)
- ✅ Gebruikt bestaande Invoice tabel (geen migration nodig!)

---

### STAP 2: Herdenkingsportaal - Invoices Tabel Toevoegen

**Probleem:** Herdenkingsportaal heeft geen invoices tabel, alleen payment_transactions

**Oplossing:** Migration maken die invoices genereert uit bestaande data

**WAAROM NODIG?**
1. ✅ **Fiscaal verplicht** - Belastingdienst vereist 7 jaar bewaarplicht van facturen
2. ✅ **Klanten kunnen facturen downloaden** - Moet in Herdenkingsportaal beschikbaar zijn
3. ✅ **Factuurnummering moet uniek zijn** - Chronologisch en zonder gaps
4. ✅ **BTW informatie correct bewaren** - Voor aangifte
5. ✅ **Scheiding betaling vs factuur** - Technisch en fiscaal correct

**Migration maken:**

```bash
# In Herdenkingsportaal project:
php artisan make:migration create_invoices_table
```

**Migration bestand:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Monument reference
            $table->foreignId('memorial_id')->constrained('memorials')->onDelete('cascade');
            $table->string('memorial_reference', 12)->unique()->index();

            // Invoice identification
            $table->string('invoice_number', 50)->unique();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            // Amounts
            $table->decimal('subtotal', 10, 2); // excl. BTW
            $table->decimal('vat_amount', 10, 2);
            $table->decimal('vat_percentage', 5, 2)->default(21.00);
            $table->decimal('total', 10, 2); // incl. BTW

            // Customer snapshot (JSON - historische gegevens)
            $table->json('customer_snapshot');

            // Payment info
            $table->foreignId('payment_transaction_id')->nullable()
                ->constrained('payment_transactions')->onDelete('set null');
            $table->string('mollie_payment_id', 50)->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])
                ->default('pending');
            $table->timestamp('paid_at')->nullable();

            // Sync status
            $table->timestamp('synced_to_havunadmin_at')->nullable();
            $table->integer('havunadmin_invoice_id')->nullable();

            // Description
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('invoice_number');
            $table->index('payment_status');
            $table->index('synced_to_havunadmin_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
```

**Velduitleg:**

| Veld | Type | Verplicht | Uitleg |
|------|------|-----------|--------|
| `memorial_id` | FK | ✅ | Link naar monument |
| `memorial_reference` | string(12) | ✅ | Eerste 12 chars van UUID (uniek!) |
| `invoice_number` | string | ✅ | `INV-2025-00001` (chronologisch) |
| `invoice_date` | date | ✅ | Datum factuur aangemaakt |
| `subtotal` | decimal | ✅ | Bedrag excl. BTW |
| `vat_amount` | decimal | ✅ | BTW bedrag (21%) |
| `total` | decimal | ✅ | Totaal incl. BTW |
| `customer_snapshot` | JSON | ✅ | Klantgegevens op moment van aankoop |
| `payment_transaction_id` | FK | ❌ | Link naar betaling (kan null zijn) |
| `synced_to_havunadmin_at` | timestamp | ❌ | Wanneer gesynchroniseerd |

**Waarom `customer_snapshot` als JSON?**
- Klantgegevens kunnen later veranderen (adres, naam, etc.)
- Factuur moet originele gegevens bewaren
- Fiscaal verplicht: factuur mag niet wijzigen

---

### STAP 3: Herdenkingsportaal - Invoice Model & Generator

**A. Invoice Model:**

```bash
php artisan make:model Invoice
```

**Bestand:** `app/Models/Invoice.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'memorial_id',
        'memorial_reference',
        'invoice_number',
        'invoice_date',
        'due_date',
        'subtotal',
        'vat_amount',
        'vat_percentage',
        'total',
        'customer_snapshot',
        'payment_transaction_id',
        'mollie_payment_id',
        'payment_status',
        'paid_at',
        'synced_to_havunadmin_at',
        'havunadmin_invoice_id',
        'description',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'synced_to_havunadmin_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'total' => 'decimal:2',
        'customer_snapshot' => 'array',
    ];

    public function memorial(): BelongsTo
    {
        return $this->belongsTo(Memorial::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    /**
     * Generate next invoice number
     */
    public static function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $lastInvoice = self::where('invoice_number', 'like', "INV-{$year}-%")
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            // Extract number from "INV-2025-00042"
            preg_match('/INV-\d{4}-(\d+)/', $lastInvoice->invoice_number, $matches);
            $nextNumber = intval($matches[1] ?? 0) + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('INV-%d-%05d', $year, $nextNumber);
    }

    /**
     * Create invoice from memorial and payment
     */
    public static function createFromPayment(Memorial $memorial, PaymentTransaction $payment): self
    {
        // Check if invoice already exists for this payment
        $existing = self::where('payment_transaction_id', $payment->id)->first();
        if ($existing) {
            return $existing;
        }

        $invoice = new self([
            'memorial_id' => $memorial->id,
            'memorial_reference' => $memorial->memorial_reference,
            'invoice_number' => self::generateInvoiceNumber(),
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),

            // Amounts (assuming payment amount is excl. BTW)
            'subtotal' => $payment->amount,
            'vat_amount' => round($payment->amount * 0.21, 2),
            'vat_percentage' => 21.00,
            'total' => round($payment->amount * 1.21, 2),

            // Customer snapshot
            'customer_snapshot' => [
                'name' => $memorial->customer_name,
                'email' => $memorial->customer_email,
                'phone' => $memorial->customer_phone,
                'address' => [
                    'street' => $memorial->customer_street ?? null,
                    'city' => $memorial->customer_city ?? null,
                    'postal_code' => $memorial->customer_postal_code ?? null,
                    'country' => $memorial->customer_country ?? 'NL',
                ],
            ],

            // Payment info
            'payment_transaction_id' => $payment->id,
            'mollie_payment_id' => $payment->mollie_id,
            'payment_status' => $payment->status,
            'paid_at' => $payment->paid_at,

            'description' => "Digitaal monument: {$memorial->name}",
        ]);

        $invoice->save();

        \Log::info('Invoice created from payment', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'memorial_reference' => $invoice->memorial_reference,
        ]);

        return $invoice;
    }
}
```

**B. Update SyncInvoiceJob:**

**Bestand:** `app/Jobs/SyncInvoiceJob.php`

```php
public function handle(InvoiceSyncService $syncService): void
{
    \Log::info('Starting invoice sync to HavunAdmin', [
        'memorial_id' => $this->memorial->id,
        'payment_id' => $this->payment->id,
    ]);

    try {
        // STAP 1: Maak eerst een Invoice in Herdenkingsportaal
        $invoice = \App\Models\Invoice::createFromPayment($this->memorial, $this->payment);

        // STAP 2: Prepare data voor HavunAdmin
        $invoiceData = [
            'memorial_reference' => $invoice->memorial_reference,
            'customer' => $invoice->customer_snapshot,
            'invoice' => [
                'number' => $invoice->invoice_number,
                'date' => $invoice->invoice_date->format('Y-m-d'),
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'amount' => (float) $invoice->subtotal,
                'vat_amount' => (float) $invoice->vat_amount,
                'total_amount' => (float) $invoice->total,
                'description' => $invoice->description,
                'lines' => [
                    [
                        'description' => $invoice->description,
                        'quantity' => 1,
                        'unit_price' => (float) $invoice->subtotal,
                        'vat_rate' => (int) $invoice->vat_percentage,
                        'total' => (float) $invoice->subtotal,
                    ],
                ],
            ],
            'payment' => [
                'mollie_payment_id' => $invoice->mollie_payment_id,
                'status' => $invoice->payment_status,
                'method' => $this->payment->method ?? 'ideal',
                'paid_at' => $invoice->paid_at?->toIso8601String(),
            ],
            'metadata' => [
                'monument_id' => $this->memorial->id,
                'monument_name' => $this->memorial->name,
                'source' => 'herdenkingsportaal',
                'synced_at' => now()->toIso8601String(),
            ],
        ];

        // STAP 3: Send naar HavunAdmin
        $response = $syncService->sendToHavunAdmin($invoiceData);

        if ($response->isSuccessful()) {
            // STAP 4: Update sync status
            $invoice->update([
                'synced_to_havunadmin_at' => now(),
                'havunadmin_invoice_id' => $response->data['invoice_id'] ?? null,
            ]);

            \Log::info('Invoice synced successfully', [
                'herdenkingsportaal_invoice_id' => $invoice->id,
                'havunadmin_invoice_id' => $response->data['invoice_id'],
            ]);
        } else {
            throw new \Exception('Sync failed: ' . $response->getError());
        }

    } catch (\Exception $e) {
        \Log::error('Invoice sync failed', [
            'error' => $e->getMessage(),
        ]);
        throw $e;
    }
}
```

---

## 📋 API SPECIFICATIE (Antwoord op Herdenkingsportaal's vragen)

### 1. Verplichte vs Optionele Velden

**VERPLICHT:**
- ✅ `memorial_reference` (string, 12 chars)
- ✅ `customer.name` (string)
- ✅ `customer.email` (string, valid email)
- ✅ `invoice.number` (string, uniek)
- ✅ `invoice.date` (date, YYYY-MM-DD)
- ✅ `invoice.amount` (float, excl. BTW)
- ✅ `invoice.vat_amount` (float)
- ✅ `invoice.total_amount` (float, incl. BTW)
- ✅ `payment.mollie_payment_id` (string, nullable)
- ✅ `payment.status` (enum: paid/pending/failed/refunded)

**OPTIONEEL:**
- ⭕ `customer.phone` (nullable)
- ⭕ `customer.address.street` (nullable)
- ⭕ `customer.address.city` (nullable)
- ⭕ `customer.address.postal_code` (nullable)
- ⭕ `invoice.due_date` (nullable)
- ⭕ `payment.method` (default: "ideal")
- ⭕ `payment.paid_at` (nullable)
- ⭕ `metadata.*` (all optional)

### 2. Duplicate Handling

**Unique constraint:** `memorial_reference` (database level)

**Gedrag bij duplicate:**
```
Request: {"memorial_reference": "HPRT-2025-001"}

Response (200 OK):
{
  "success": true,
  "invoice_id": 501,
  "memorial_reference": "HPRT-2025-001",
  "message": "Invoice already exists (updated)",
  "duplicate": true
}
```

**Geen error bij duplicate** - Idempotent API design ✅

### 3. Response Format

**Success (200 OK):**
```json
{
  "success": true,
  "invoice_id": 501,
  "memorial_reference": "HPRT-2025-001",
  "message": "Invoice created successfully"
}
```

**Validation Error (422):**
```json
{
  "success": false,
  "error": "Validation failed",
  "errors": {
    "customer.email": ["The email field is required"],
    "invoice.amount": ["The amount must be greater than 0"]
  }
}
```

**Server Error (500):**
```json
{
  "success": false,
  "error": "Internal server error"
}
```

### 4. HTTP Status Codes

| Code | Meaning | When |
|------|---------|------|
| 200 | OK | Invoice created/updated successfully |
| 401 | Unauthorized | Invalid API token |
| 422 | Validation Error | Required fields missing or invalid |
| 500 | Server Error | Database error, unexpected exception |

### 5. VAT Berekening

**Jullie formaat is correct:**
```json
{
  "invoice": {
    "amount": 19.95,      // excl. BTW
    "vat_amount": 4.19,   // 21% BTW
    "total_amount": 24.14 // incl. BTW
  }
}
```

**Validatie in HavunAdmin:**
- Check: `vat_amount ≈ amount * 0.21`
- Check: `total_amount ≈ amount + vat_amount`

### 6. Status Sync (GET endpoint)

**Wanneer gebruiken:**
- ✅ Bij **refunds** (klant krijgt geld terug)
- ✅ Bij **payment disputes** (chargeback)
- ⭕ Bij **invoice corrections** (verkeerd bedrag) - Optioneel
- ❌ **Niet** bij periodieke sync (te duur)

**Response:**
```json
{
  "invoice_id": 501,
  "memorial_reference": "HPRT-2025-001",
  "status": "paid",
  "paid_at": "2025-11-17T14:30:00+00:00",
  "refunded_at": null
}
```

**Mogelijke statussen:**
- `pending` - Nog niet betaald
- `paid` - Betaald
- `refunded` - Terugbetaald
- `cancelled` - Geannuleerd
- `failed` - Mislukt

---

## 🚀 IMPLEMENTATIE VOLGORDE

### Voor HavunAdmin:

1. ✅ **Voeg method toe aan Invoice model** (5 minuten)
   ```bash
   # Edit: app/Models/Invoice.php
   # Add: createFromHerdenkingsportaal() method
   ```

2. ✅ **Test de endpoint** (10 minuten)
   ```bash
   php artisan tinker
   >>> $data = ['memorial_reference' => 'test123', ...];
   >>> $invoice = \App\Models\Invoice::createFromHerdenkingsportaal($data);
   ```

3. ✅ **Commit & push** (2 minuten)

**Totaal:** ~20 minuten

### Voor Herdenkingsportaal:

1. ✅ **Maak migration** (10 minuten)
   ```bash
   php artisan make:migration create_invoices_table
   # Kopieer migration code hierboven
   php artisan migrate
   ```

2. ✅ **Maak Invoice model** (15 minuten)
   ```bash
   php artisan make:model Invoice
   # Kopieer model code hierboven
   ```

3. ✅ **Update SyncInvoiceJob** (10 minuten)
   ```php
   # Wijzig handle() method om eerst Invoice te maken
   ```

4. ✅ **Test end-to-end** (20 minuten)
   ```bash
   php artisan queue:work --once
   # Check logs
   # Verify invoice in Herdenkingsportaal DB
   # Verify invoice in HavunAdmin DB
   ```

5. ✅ **Commit & push** (2 minuten)

**Totaal:** ~1 uur

---

## 🎯 AANBEVELING

**Optie A: Herdenkingsportaal krijgt invoices tabel** ✅ AANBEVOLEN

**Waarom?**
1. ✅ Fiscaal verplicht (7 jaar bewaarplicht)
2. ✅ Klanten kunnen facturen downloaden
3. ✅ Scheiding betaling vs factuur is technisch correct
4. ✅ HavunAdmin sync wordt simpeler (geen complexe mapping)
5. ✅ Toekomstbestendig (andere payment providers)

**Optie B: Direct payment_transactions gebruiken** ❌ NIET AANBEVOLEN

**Waarom niet?**
- ❌ Geen factuurnummering
- ❌ Geen BTW administratie
- ❌ Geen historische klantgegevens
- ❌ Fiscaal niet correct

**Optie C: Centrale API** ❌ TE COMPLEX

**Waarom niet?**
- ❌ Veel meer werk
- ❌ Overhead voor simpel probleem
- ❌ HavunCore is al de centrale API!

---

## ✅ ACTIE ITEMS

### HavunAdmin Team:
- [ ] Add `Invoice::createFromHerdenkingsportaal()` method
- [ ] Test method met test data
- [ ] Commit & push
- [ ] **ETA:** 20 minuten

### Herdenkingsportaal Team:
- [ ] Create invoices migration
- [ ] Create Invoice model
- [ ] Update SyncInvoiceJob
- [ ] Test end-to-end
- [ ] Commit & push
- [ ] **ETA:** 1 uur

### HavunCore Team (ikzelf):
- [ ] Update InvoiceSyncService docs
- [ ] Add API spec document
- [ ] Update CHANGELOG.md
- [ ] **ETA:** 30 minuten

---

## 📞 SUPPORT

Vragen? Problemen tijdens implementatie?

**Contact:** HavunCore Development Team
**Response tijd:** Binnen 2 uur (werkdagen)

---

**Met vriendelijke groet,**

**HavunCore Development Team**

*"Samen maken we het goed!"* 🚀
