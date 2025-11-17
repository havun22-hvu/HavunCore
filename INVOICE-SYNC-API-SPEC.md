# 📘 Invoice Sync API Specification

**Version:** 1.0
**Last Updated:** 17 november 2025
**Endpoint:** `POST /api/invoices/sync`
**Authentication:** Bearer Token

---

## 🎯 Overview

This API endpoint allows Herdenkingsportaal to synchronize invoice data to HavunAdmin for centralized bookkeeping and duplicate detection.

**Key Features:**
- ✅ **Idempotent** - Safe to retry (duplicate detection via `memorial_reference`)
- ✅ **Async** - Processed via queue jobs with retry logic
- ✅ **Validated** - Comprehensive validation of all required fields
- ✅ **Logged** - Full audit trail in both systems

---

## 🔐 Authentication

**Header:**
```
Authorization: Bearer {api_token}
```

**Token Location:**
- **HavunAdmin:** `.env` → `HAVUN_API_TOKEN`
- **Herdenkingsportaal:** `.env` → `HAVUNADMIN_API_TOKEN`

**Production Token Requirements:**
- Minimum 64 characters
- Cryptographically random
- Changed from default development token

**Generate Token:**
```bash
php -r "echo bin2hex(random_bytes(32));"
```

---

## 📨 Request Format

### Endpoint
```
POST https://havunadmin.havun.nl/api/invoices/sync
Content-Type: application/json
Authorization: Bearer {token}
```

### Request Body

```json
{
  "memorial_reference": "550e8400e29b",
  "customer": {
    "name": "Jan Jansen",
    "email": "jan@example.com",
    "phone": "+31612345678",
    "address": {
      "street": "Hoofdstraat 123",
      "city": "Amsterdam",
      "postal_code": "1011 AB",
      "country": "NL"
    }
  },
  "invoice": {
    "number": "INV-2025-00001",
    "date": "2025-11-17",
    "due_date": "2025-12-01",
    "amount": 19.95,
    "vat_amount": 4.19,
    "total_amount": 24.14,
    "description": "Digitaal monument: Jan Jansen",
    "lines": [
      {
        "description": "Digitaal monument - Premium",
        "quantity": 1,
        "unit_price": 19.95,
        "vat_rate": 21,
        "total": 19.95
      }
    ]
  },
  "payment": {
    "mollie_payment_id": "tr_WDqYK6vllg",
    "status": "paid",
    "method": "ideal",
    "paid_at": "2025-11-17T14:30:00+00:00"
  },
  "metadata": {
    "monument_id": 123,
    "monument_name": "Jan Jansen",
    "source": "herdenkingsportaal",
    "synced_at": "2025-11-17T14:31:00+00:00"
  }
}
```

---

## 📋 Field Specifications

### Root Level

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `memorial_reference` | string(12) | ✅ **Required** | Unique memorial identifier (first 12 chars of UUID) |

### Customer Object

| Field | Type | Required | Description | Validation |
|-------|------|----------|-------------|------------|
| `customer.name` | string | ✅ **Required** | Customer full name | Min: 2 chars |
| `customer.email` | string | ✅ **Required** | Customer email | Valid email format |
| `customer.phone` | string\|null | ⭕ Optional | Customer phone | E.164 format preferred |
| `customer.address` | object\|null | ⭕ Optional | Customer address | Can be null |
| `customer.address.street` | string\|null | ⭕ Optional | Street + house number | - |
| `customer.address.city` | string\|null | ⭕ Optional | City | - |
| `customer.address.postal_code` | string\|null | ⭕ Optional | Postal code | - |
| `customer.address.country` | string | ⭕ Optional | Country code (ISO 3166-1 alpha-2) | Default: "NL" |

**Note:** While address fields are optional, if provided they will be stored in `customer_snapshot` for historical record keeping.

### Invoice Object

| Field | Type | Required | Description | Validation |
|-------|------|----------|-------------|------------|
| `invoice.number` | string | ✅ **Required** | Unique invoice number | Format: `INV-YYYY-NNNNN` |
| `invoice.date` | date | ✅ **Required** | Invoice date | Format: `YYYY-MM-DD` |
| `invoice.due_date` | date\|null | ⭕ Optional | Payment due date | Format: `YYYY-MM-DD` |
| `invoice.amount` | float | ✅ **Required** | Amount **excl. VAT** | > 0.00 |
| `invoice.vat_amount` | float | ✅ **Required** | VAT amount (21%) | >= 0.00 |
| `invoice.total_amount` | float | ✅ **Required** | Total amount **incl. VAT** | > 0.00 |
| `invoice.description` | string | ⭕ Optional | Invoice description | Max: 500 chars |
| `invoice.lines` | array | ⭕ Optional | Invoice line items | Min: 1 item if provided |

**VAT Validation:**
```php
// HavunAdmin validates:
abs($vat_amount - ($amount * 0.21)) < 0.02  // Allow 2 cent rounding difference
abs($total_amount - ($amount + $vat_amount)) < 0.01
```

### Invoice Lines (Optional)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `lines[].description` | string | ✅ | Line item description |
| `lines[].quantity` | int | ✅ | Quantity |
| `lines[].unit_price` | float | ✅ | Price per unit (excl. VAT) |
| `lines[].vat_rate` | int | ✅ | VAT percentage (21) |
| `lines[].total` | float | ✅ | Line total (excl. VAT) |

### Payment Object

| Field | Type | Required | Description | Validation |
|-------|------|----------|-------------|------------|
| `payment.mollie_payment_id` | string\|null | ⭕ Optional | Mollie payment ID | Format: `tr_*` |
| `payment.status` | string | ✅ **Required** | Payment status | Enum: see below |
| `payment.method` | string | ⭕ Optional | Payment method | Default: "ideal" |
| `payment.paid_at` | datetime\|null | ⭕ Optional | Payment timestamp | ISO 8601 format |

**Payment Status Enum:**
- `pending` - Awaiting payment
- `paid` - Payment completed ✅
- `failed` - Payment failed
- `refunded` - Payment refunded
- `cancelled` - Payment cancelled

### Metadata Object (All Optional)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `metadata.monument_id` | int | ⭕ | Internal monument ID |
| `metadata.monument_name` | string | ⭕ | Monument name |
| `metadata.source` | string | ⭕ | Source system identifier |
| `metadata.synced_at` | datetime | ⭕ | Sync timestamp |

**Note:** All metadata fields are stored but not validated.

---

## 📤 Response Formats

### Success Response (200 OK)

**Scenario:** Invoice created successfully

```json
{
  "success": true,
  "invoice_id": 501,
  "memorial_reference": "550e8400e29b",
  "message": "Invoice created successfully"
}
```

**Scenario:** Invoice already exists (duplicate)

```json
{
  "success": true,
  "invoice_id": 501,
  "memorial_reference": "550e8400e29b",
  "message": "Invoice already exists (updated)",
  "duplicate": true
}
```

**Note:** Same HTTP status (200 OK) for both cases - **idempotent API design**

### Validation Error Response (422 Unprocessable Entity)

```json
{
  "success": false,
  "error": "Validation failed",
  "errors": {
    "customer.name": [
      "The customer name field is required."
    ],
    "customer.email": [
      "The customer email must be a valid email address."
    ],
    "invoice.amount": [
      "The invoice amount must be greater than 0."
    ],
    "invoice.vat_amount": [
      "VAT calculation mismatch (expected 4.19, got 4.20)"
    ]
  }
}
```

### Authorization Error Response (401 Unauthorized)

```json
{
  "success": false,
  "error": "Unauthorized",
  "message": "Invalid or missing API token"
}
```

### Server Error Response (500 Internal Server Error)

```json
{
  "success": false,
  "error": "Internal server error",
  "message": "An unexpected error occurred. Please contact support."
}
```

**Note:** Detailed error information is logged server-side but not exposed to client for security.

---

## 🔁 Duplicate Detection

### How It Works

**Unique Constraint:** `memorial_reference` (database level)

**First Request:**
```
POST /api/invoices/sync
{"memorial_reference": "550e8400e29b", ...}

↓

Database check: No existing invoice with this reference
Create new invoice (ID: 501)

↓

Response: {
  "success": true,
  "invoice_id": 501,
  "message": "Invoice created successfully"
}
```

**Second Request (duplicate):**
```
POST /api/invoices/sync
{"memorial_reference": "550e8400e29b", ...}

↓

Database check: Invoice exists (ID: 501)
Update existing invoice with new data

↓

Response: {
  "success": true,
  "invoice_id": 501,
  "message": "Invoice already exists (updated)",
  "duplicate": true
}
```

### Update Behavior

When duplicate is detected:
- ✅ **Updated:** `invoice_date`, `due_date`, `amounts`, `description`
- ✅ **Updated:** `payment_status`, `paid_at`, `mollie_payment_id`
- ✅ **Updated:** `customer_snapshot` (historical data may change)
- ❌ **Not updated:** `invoice_id`, `memorial_reference` (immutable)

**Why update on duplicate?**
- Payment status may change (pending → paid)
- Customer may update their details
- Invoice amounts may be corrected

---

## 🧪 Test Scenarios

### 1. Successful Invoice Creation

**Request:**
```bash
curl -X POST https://havunadmin.havun.nl/api/invoices/sync \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "memorial_reference": "test12345678",
    "customer": {
      "name": "Test User",
      "email": "test@example.com"
    },
    "invoice": {
      "number": "INV-2025-99999",
      "date": "2025-11-17",
      "amount": 10.00,
      "vat_amount": 2.10,
      "total_amount": 12.10
    },
    "payment": {
      "status": "paid"
    }
  }'
```

**Expected Response:** `200 OK` with `invoice_id`

### 2. Duplicate Invoice (Idempotent Retry)

**Request:** Same as above (exact same `memorial_reference`)

**Expected Response:** `200 OK` with `duplicate: true`

### 3. Missing Required Field

**Request:**
```json
{
  "memorial_reference": "test12345678",
  "customer": {
    "name": "Test User"
    // Missing: email (required!)
  },
  "invoice": {
    "number": "INV-2025-99999",
    "date": "2025-11-17",
    "amount": 10.00,
    "vat_amount": 2.10,
    "total_amount": 12.10
  },
  "payment": {
    "status": "paid"
  }
}
```

**Expected Response:** `422 Validation Error`

### 4. Invalid VAT Calculation

**Request:**
```json
{
  "memorial_reference": "test12345678",
  "customer": {
    "name": "Test User",
    "email": "test@example.com"
  },
  "invoice": {
    "number": "INV-2025-99999",
    "date": "2025-11-17",
    "amount": 10.00,
    "vat_amount": 5.00,  // Wrong! Should be 2.10 (21%)
    "total_amount": 15.00
  },
  "payment": {
    "status": "paid"
  }
}
```

**Expected Response:** `422 Validation Error` - "VAT calculation mismatch"

### 5. Invalid Bearer Token

**Request:** Same as test 1, but with wrong/missing token

**Expected Response:** `401 Unauthorized`

---

## 📊 Status Sync (GET Endpoint)

### Endpoint
```
GET /api/invoices/by-reference/{memorial_reference}
```

### Use Cases

**When to use:**
- ✅ **Refunds** - Customer requests money back
- ✅ **Disputes** - Chargeback by bank
- ⭕ **Corrections** - Invoice amount needs updating
- ❌ **Periodic sync** - Don't use for batch sync (too expensive)

### Request Example

```bash
curl -X GET https://havunadmin.havun.nl/api/invoices/by-reference/550e8400e29b \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Response Example

```json
{
  "invoice_id": 501,
  "memorial_reference": "550e8400e29b",
  "invoice_number": "INV-2025-00042",
  "status": "paid",
  "amount": 19.95,
  "total": 24.14,
  "paid_at": "2025-11-17T14:30:00+00:00",
  "refunded_at": null,
  "created_at": "2025-11-17T10:00:00+00:00",
  "updated_at": "2025-11-17T14:30:00+00:00"
}
```

### Status Field Values

| Status | Meaning | Action in Herdenkingsportaal |
|--------|---------|------------------------------|
| `pending` | Not yet paid | Show "Awaiting payment" |
| `paid` | Payment completed | Show "Paid" ✅ |
| `refunded` | Money returned to customer | Update memorial status to "Refunded" |
| `cancelled` | Invoice cancelled | Show "Cancelled" |
| `failed` | Payment failed | Show "Payment failed" |

---

## 🚨 Error Handling

### Client-Side (Herdenkingsportaal)

**Retry Logic:**
- Retry on: `500`, `502`, `503`, `504` (server errors)
- Retry on: Network timeout
- Do NOT retry on: `401`, `422` (client errors)
- Max retries: 3
- Backoff: 60 seconds between retries

**Example (Laravel Queue Job):**
```php
class SyncInvoiceJob implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 60;

    public function handle(InvoiceSyncService $syncService): void
    {
        try {
            $response = $syncService->sendToHavunAdmin($invoiceData);

            if (!$response->isSuccessful()) {
                throw new \Exception($response->getError());
            }
        } catch (\Exception $e) {
            Log::error('Invoice sync failed', ['error' => $e->getMessage()]);
            throw $e; // Re-throw to trigger retry
        }
    }
}
```

### Server-Side (HavunAdmin)

**Logging:**
```php
Log::info('Invoice sync request received', [
    'memorial_reference' => $data['memorial_reference'],
    'invoice_number' => $data['invoice']['number'],
]);

Log::error('Invoice sync failed', [
    'memorial_reference' => $data['memorial_reference'],
    'error' => $exception->getMessage(),
]);
```

**Database Transaction:**
```php
DB::transaction(function () use ($data) {
    $invoice = Invoice::createFromHerdenkingsportaal($data);
    return $invoice;
});
```

---

## 📝 Implementation Notes

### HavunAdmin Implementation

**File:** `app/Models/Invoice.php`

**Method:** `createFromHerdenkingsportaal(array $data): self`

**Key Features:**
- ✅ Idempotent (safe to call multiple times)
- ✅ Validates VAT calculation
- ✅ Stores customer snapshot (historical record)
- ✅ Maps payment status (Mollie → HavunAdmin)
- ✅ Comprehensive logging

**Database:**
- Table: `invoices`
- Unique constraint: `memorial_reference`
- Indexes: `memorial_reference`, `invoice_number`, `status`

### Herdenkingsportaal Implementation

**File:** `app/Jobs/SyncInvoiceJob.php`

**Process:**
1. Create Invoice record in Herdenkingsportaal
2. Prepare data for HavunAdmin API
3. Send HTTP POST request
4. Handle response (success/error)
5. Update sync status

**Configuration:**
```env
HAVUNADMIN_API_URL=https://havunadmin.havun.nl/api
HAVUNADMIN_API_TOKEN=your_secure_64_char_token_here
```

---

## 🔒 Security Considerations

### API Token Security

**DO:**
- ✅ Use HTTPS only (TLS 1.2+)
- ✅ Store token in `.env` (never in code)
- ✅ Use different tokens for dev/staging/production
- ✅ Rotate tokens periodically (every 6 months)
- ✅ Use Bearer token authentication

**DON'T:**
- ❌ Commit tokens to Git
- ❌ Share tokens via email/Slack
- ❌ Use weak/predictable tokens
- ❌ Expose tokens in logs
- ❌ Use HTTP (insecure)

### Data Privacy

**Customer Data:**
- Stored in `customer_snapshot` (encrypted at rest)
- GDPR compliant (7-year retention for invoices)
- Not shared with third parties

**Logging:**
- PII (personal identifiable info) is masked in logs
- Example: Email `jan@example.com` → `j**@example.com`

---

## 📈 Performance

### API Limits

**Rate Limiting:**
- Max requests: 100 per minute per IP
- Burst: 10 requests per second
- Exceeded: `429 Too Many Requests`

**Timeout:**
- Request timeout: 30 seconds
- Database query timeout: 10 seconds

**Payload Size:**
- Max request body: 1 MB
- Exceeded: `413 Payload Too Large`

### Best Practices

**DO:**
- ✅ Process invoices via queue (async)
- ✅ Batch updates if syncing multiple invoices
- ✅ Cache API responses when appropriate

**DON'T:**
- ❌ Sync invoices in real-time during checkout
- ❌ Poll GET endpoint repeatedly (use webhooks instead)
- ❌ Send duplicate requests without timeout

---

## 📞 Support

**Technical Issues:**
- Email: development@havunadmin.nl
- Response time: < 4 hours (business days)

**API Changes:**
- Breaking changes: 30 days notice
- New features: Announced via changelog
- Deprecations: 6 months notice

**Monitoring:**
- API status: https://status.havunadmin.nl
- Uptime SLA: 99.9%

---

## 📜 Changelog

### Version 1.0 (2025-11-17)
- ✅ Initial API specification
- ✅ POST /api/invoices/sync endpoint
- ✅ GET /api/invoices/by-reference/{ref} endpoint
- ✅ Bearer token authentication
- ✅ Idempotent duplicate handling
- ✅ Comprehensive validation

---

**API Specification Version:** 1.0
**Last Updated:** 17 november 2025
**Status:** Production Ready ✅
