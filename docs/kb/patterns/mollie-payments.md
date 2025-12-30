# Pattern: Mollie Betalingen

> iDEAL, creditcard en andere betaalmethodes via Mollie API.

## Wanneer gebruiken

- Online betalingen (iDEAL, creditcard, Bancontact, etc.)
- Webshop checkout
- Donaties en abonnementen

## Account

- **Provider:** Mollie
- **Dashboard:** https://my.mollie.com
- **Credentials:** Zie `.claude/context.md`

## Implementatie (HTTP-based, geen SDK)

### 1. .env configureren

```env
MOLLIE_TEST_API_KEY=test_xxxxx
MOLLIE_LIVE_API_KEY=live_xxxxx
```

### 2. Config (config/services.php)

```php
'mollie' => [
    'key' => env('MOLLIE_TEST_API_KEY'),
    'live_key' => env('MOLLIE_LIVE_API_KEY'),
],
```

### 3. Service class

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MollieService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.mollie.com/v2';

    public function __construct()
    {
        $this->apiKey = config('app.env') === 'production'
            ? config('services.mollie.live_key')
            : config('services.mollie.key');
    }

    /**
     * Create a payment
     */
    public function createPayment(array $data): object
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl . '/payments', $data);

        if (!$response->successful()) {
            throw new \Exception('Mollie API error: ' . $response->body());
        }

        return $response->object();
    }

    /**
     * Get payment status
     */
    public function getPayment(string $paymentId): object
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->get($this->apiUrl . '/payments/' . $paymentId);

        return $response->object();
    }
}
```

### 4. Payment aanmaken

```php
$mollie = app(MollieService::class);

$payment = $mollie->createPayment([
    'amount' => [
        'currency' => 'EUR',
        'value' => '29.95',
    ],
    'description' => 'Bestelling #12345',
    'redirectUrl' => route('payment.return'),
    'webhookUrl' => route('payment.webhook'),
    'metadata' => [
        'order_id' => 12345,
    ],
]);

// Redirect naar Mollie checkout
return redirect($payment->_links->checkout->href);
```

### 5. Webhook handler

```php
// routes/web.php
Route::post('/payment/webhook', [PaymentController::class, 'webhook'])
    ->name('payment.webhook');

// Controller
public function webhook(Request $request)
{
    $paymentId = $request->input('id');

    $mollie = app(MollieService::class);
    $payment = $mollie->getPayment($paymentId);

    if ($payment->status === 'paid') {
        // Verwerk betaling
        $orderId = $payment->metadata->order_id;
        Order::find($orderId)->markAsPaid();
    }

    return response('OK', 200);
}
```

## Payment statuses

| Status | Betekenis |
|--------|-----------|
| `open` | Wacht op actie klant |
| `pending` | In behandeling |
| `paid` | Betaald |
| `failed` | Mislukt |
| `canceled` | Geannuleerd |
| `expired` | Verlopen |

## Test vs Live

```php
// Automatisch juiste key kiezen
$isProduction = config('app.env') === 'production';
$apiKey = $isProduction
    ? config('services.mollie.live_key')
    : config('services.mollie.key');
```

## Best Practices

1. **Altijd webhooks gebruiken** - Niet vertrouwen op redirect
2. **Idempotency** - Webhook kan meerdere keren komen
3. **Logging** - Log alle transacties
4. **Geen SDK** - HTTP client is lichter en flexibeler

## Projecten die dit gebruiken

- Herdenkingsportaal

---

*Pattern toegevoegd: 2025-12-30*
