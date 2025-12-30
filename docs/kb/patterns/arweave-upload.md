# Pattern: Arweave Permanente Opslag

> Data permanent opslaan op de Arweave blockchain (200+ jaar gegarandeerd).

## Wanneer gebruiken

- Permanente archivering van belangrijke data
- Onveranderbare records (legal, memorial)
- Decentralized storage zonder vendor lock-in

## Wat is Arweave?

- **Blockchain-based storage** - Data wordt permanent opgeslagen
- **Pay once, store forever** - Eenmalige betaling, eeuwige opslag
- **Immutable** - Data kan niet worden gewijzigd of verwijderd
- **Decentralized** - Geen single point of failure

## Kosten

| Data size | Geschatte kosten |
|-----------|------------------|
| 1 KB | ~$0.000005 |
| 1 MB | ~$0.005 |
| 100 MB | ~$0.50 |
| 1 GB | ~$5 |

## Implementatie

### 1. .env configuratie

```env
ARWEAVE_GATEWAY=https://arweave.net
ARWEAVE_NETWORK=mainnet
ARWEAVE_MOCK=false
ARWEAVE_WALLET_ADDRESS_MAINNET=xxx
ARWEAVE_WALLET_ADDRESS_TESTNET=xxx
ARWEAVE_WALLET_KEY_PATH=/path/to/wallet.json
```

### 2. Arweave Service

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArweaveService
{
    private string $nodeUrl;
    private bool $isMockMode;
    private string $network;

    public function __construct()
    {
        $env = config('app.env');

        $this->isMockMode = config('services.arweave.mock', $env !== 'production');
        $this->network = config('services.arweave.network', 'mainnet');
        $this->nodeUrl = config('services.arweave.gateway', 'https://arweave.net');
    }

    /**
     * Upload data naar Arweave
     */
    public function upload(array $data, array $tags = []): string
    {
        if ($this->isMockMode) {
            return $this->createMockTransaction($data);
        }

        return $this->uploadToNetwork($data, $tags);
    }

    /**
     * Upload naar echt Arweave netwerk
     */
    private function uploadToNetwork(array $data, array $tags): string
    {
        $jsonData = json_encode($data);

        // Stap 1: Bereken kosten
        $cost = $this->calculateCost(strlen($jsonData));

        // Stap 2: Maak transactie
        $transaction = $this->createTransaction($jsonData, $tags);

        // Stap 3: Sign transactie (met wallet key)
        $signedTx = $this->signTransaction($transaction);

        // Stap 4: Submit naar netwerk
        $response = Http::post($this->nodeUrl . '/tx', $signedTx);

        if (!$response->successful()) {
            throw new \Exception('Arweave upload failed: ' . $response->body());
        }

        return $transaction['id'];
    }

    /**
     * Bereken upload kosten
     */
    public function calculateCost(int $bytes): float
    {
        $response = Http::get($this->nodeUrl . '/price/' . $bytes);
        $winstonCost = (int) $response->body();

        // Convert winston naar AR (1 AR = 10^12 winston)
        return $winstonCost / 1e12;
    }

    /**
     * Haal data op van Arweave
     */
    public function getData(string $transactionId): ?array
    {
        $response = Http::get($this->nodeUrl . '/' . $transactionId);

        if (!$response->successful()) {
            return null;
        }

        return json_decode($response->body(), true);
    }

    /**
     * Check transactie status
     */
    public function getStatus(string $transactionId): string
    {
        $response = Http::get($this->nodeUrl . '/tx/' . $transactionId . '/status');

        if ($response->status() === 202) {
            return 'pending';
        }

        if ($response->successful()) {
            $data = $response->json();
            return isset($data['block_height']) ? 'confirmed' : 'pending';
        }

        return 'not_found';
    }

    /**
     * Mock transactie voor development
     */
    private function createMockTransaction(array $data): string
    {
        $mockId = 'mock_' . bin2hex(random_bytes(16));

        Log::info('Arweave mock transaction created', [
            'transaction_id' => $mockId,
            'data_size' => strlen(json_encode($data)),
        ]);

        return $mockId;
    }
}
```

### 3. Database tracking

```php
// Migration
Schema::create('arweave_transactions', function (Blueprint $table) {
    $table->id();
    $table->string('transaction_id')->unique();
    $table->string('data_type'); // memorial, document, etc.
    $table->unsignedBigInteger('data_id');
    $table->json('transaction_data');
    $table->decimal('cost_ar', 20, 12)->nullable();
    $table->decimal('cost_usd', 10, 2)->nullable();
    $table->enum('status', ['pending', 'submitted', 'confirmed', 'failed']);
    $table->timestamp('submitted_at')->nullable();
    $table->timestamp('confirmed_at')->nullable();
    $table->timestamps();
});
```

### 4. Upload met metadata tags

```php
$arweave = app(ArweaveService::class);

$transactionId = $arweave->upload(
    data: [
        'type' => 'memorial',
        'name' => 'Jan Jansen',
        'content' => $memorialContent,
        'created_at' => now()->toIso8601String(),
    ],
    tags: [
        ['name' => 'Content-Type', 'value' => 'application/json'],
        ['name' => 'App-Name', 'value' => 'Herdenkingsportaal'],
        ['name' => 'App-Version', 'value' => '1.0'],
        ['name' => 'Memorial-Id', 'value' => $memorial->id],
    ]
);

// Update model
$memorial->update([
    'arweave_transaction_id' => $transactionId,
    'arweave_status' => 'submitted',
]);
```

### 5. Status monitoring

```php
// Console command
class CheckArweaveStatus extends Command
{
    public function handle()
    {
        $pending = ArweaveTransaction::where('status', 'submitted')
            ->where('submitted_at', '<', now()->subMinutes(10))
            ->get();

        foreach ($pending as $tx) {
            $status = app(ArweaveService::class)->getStatus($tx->transaction_id);

            if ($status === 'confirmed') {
                $tx->update([
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                ]);
            }
        }
    }
}
```

## Data ophalen

```php
// Via gateway URL
$url = "https://arweave.net/{$transactionId}";

// Of via service
$data = app(ArweaveService::class)->getData($transactionId);
```

## Best Practices

1. **Mock mode voor development** - Geen echte uploads in local
2. **Track alle transacties** - Database record per upload
3. **Status polling** - Confirmatie kan minuten duren
4. **Backup transaction IDs** - Verloren ID = verloren toegang
5. **Content-Type tags** - Voor juiste weergave in browser

## Environments

| Environment | Network | Mode |
|-------------|---------|------|
| local | - | Mock |
| staging | testnet | Real |
| production | mainnet | Real |

## Projecten die dit gebruiken

- Herdenkingsportaal (memorial archivering)

---

*Pattern toegevoegd: 2025-12-30*
