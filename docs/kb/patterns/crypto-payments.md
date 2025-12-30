# Pattern: Crypto Betalingen

> Cryptocurrency betalingen met XRP, ADA (Cardano) en SOL (Solana).

## Wanneer gebruiken

- Alternatieve betaalmethode naast iDEAL/creditcard
- Internationale betalingen zonder banken
- Privacy-vriendelijke betalingen

## Ondersteunde currencies

| Currency | Naam | Decimalen |
|----------|------|-----------|
| XRP | Ripple | 6 |
| ADA | Cardano | 6 |
| SOL | Solana | 9 |

## Architectuur

```
User → Kiest crypto → QR code met wallet adres + bedrag
                    ↓
              Betaalt via wallet app
                    ↓
              Monitoring service checkt blockchain
                    ↓
              Payment confirmed → Order verwerkt
```

## 1. Crypto Price Service (CoinGecko API)

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CryptoPriceService
{
    private const API_URL = 'https://api.coingecko.com/api/v3/simple/price';
    private const CACHE_DURATION = 300; // 5 minuten

    private const SUPPORTED = [
        'ripple' => 'XRP',
        'cardano' => 'ADA',
        'solana' => 'SOL',
    ];

    private const FALLBACK_PRICES = [
        'XRP' => 0.50,
        'ADA' => 0.35,
        'SOL' => 25.00,
    ];

    public function getAllPrices(): array
    {
        return Cache::remember('crypto_prices', self::CACHE_DURATION, function() {
            try {
                $response = Http::timeout(5)->get(self::API_URL, [
                    'ids' => implode(',', array_keys(self::SUPPORTED)),
                    'vs_currencies' => 'eur',
                ]);

                if (!$response->successful()) {
                    return self::FALLBACK_PRICES;
                }

                $data = $response->json();
                $prices = [];

                foreach (self::SUPPORTED as $coinId => $symbol) {
                    $prices[$symbol] = $data[$coinId]['eur'] ?? self::FALLBACK_PRICES[$symbol];
                }

                return $prices;
            } catch (\Exception $e) {
                return self::FALLBACK_PRICES;
            }
        });
    }

    public function convertEurToCrypto(float $eurAmount, string $symbol): float
    {
        $prices = $this->getAllPrices();
        $pricePerUnit = $prices[$symbol] ?? 1;

        return round($eurAmount / $pricePerUnit, 6);
    }
}
```

## 2. XRP Payment Service

```php
<?php

namespace App\Services;

use Illuminate\Support\Str;

class XrpPaymentService
{
    private string $xrpAddress;
    private CryptoPriceService $priceService;

    public function __construct(CryptoPriceService $priceService)
    {
        $this->xrpAddress = config('services.xrp.address');
        $this->priceService = $priceService;
    }

    public function createPaymentRequest(float $eurAmount, string $description): array
    {
        $paymentId = 'xrp_' . Str::random(16);
        $xrpAmount = $this->priceService->convertEurToCrypto($eurAmount, 'XRP');

        // XRP payment URI (voor wallet apps)
        $xrpUri = sprintf(
            'xrp:%s?amount=%s&dt=%s',
            $this->xrpAddress,
            $xrpAmount,
            crc32($paymentId) // destination tag voor identificatie
        );

        return [
            'id' => $paymentId,
            'amount_eur' => $eurAmount,
            'amount_xrp' => $xrpAmount,
            'address' => $this->xrpAddress,
            'uri' => $xrpUri,
            'qr_code' => $this->generateQrCode($xrpUri),
            'status' => 'pending',
            'memo' => $paymentId,
        ];
    }

    private function generateQrCode(string $data): string
    {
        // Gebruik SimpleQrCodeService of andere QR library
        return SimpleQrCodeService::generate($data, 200);
    }
}
```

## 3. .env configuratie

```env
# Wallet adressen voor ontvangst
XRP_WALLET_ADDRESS=rXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
ADA_WALLET_ADDRESS=addr1qXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
SOL_WALLET_ADDRESS=XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
```

## 4. Frontend integratie

```javascript
// Crypto betaling selecteren
const cryptoOptions = [
    { symbol: 'XRP', name: 'Ripple', icon: 'xrp.svg' },
    { symbol: 'ADA', name: 'Cardano', icon: 'ada.svg' },
    { symbol: 'SOL', name: 'Solana', icon: 'sol.svg' },
];

// Na selectie: toon QR code + wallet adres
async function initCryptoPayment(symbol, eurAmount) {
    const response = await fetch('/api/crypto/payment', {
        method: 'POST',
        body: JSON.stringify({ symbol, amount: eurAmount }),
    });

    const payment = await response.json();

    // Toon QR code
    document.getElementById('qr-code').src = payment.qr_code;
    document.getElementById('amount').textContent = `${payment.amount_crypto} ${symbol}`;
    document.getElementById('address').textContent = payment.address;

    // Start polling voor bevestiging
    pollPaymentStatus(payment.id);
}
```

## 5. Payment monitoring

```php
// Console command voor blockchain monitoring
class MonitorCryptoPayments extends Command
{
    public function handle()
    {
        $pendingPayments = CryptoPayment::where('status', 'pending')
            ->where('created_at', '>', now()->subHours(24))
            ->get();

        foreach ($pendingPayments as $payment) {
            if ($this->checkBlockchain($payment)) {
                $payment->update(['status' => 'confirmed']);
                // Trigger order processing
            }
        }
    }
}
```

## Best Practices

1. **Altijd fallback prijzen** - API kan falen
2. **Cache prijzen** - Niet elke request naar CoinGecko
3. **Destination tag/memo** - Voor identificatie van betalingen
4. **Timeout op pending** - Verval na 24 uur
5. **Confirmations wachten** - Niet na 1 confirmation al verwerken

## Projecten die dit gebruiken

- Herdenkingsportaal (XRP, ADA, SOL)

---

*Pattern toegevoegd: 2025-12-30*
