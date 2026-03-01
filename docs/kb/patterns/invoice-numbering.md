# Factuurnummering - Havun Standaard

## Formaat

```
XX-JJJJMMDD-NNN
```

| Deel | Betekenis | Voorbeeld |
|------|-----------|-----------|
| `XX` | Project prefix (2 letters) | `JT` |
| `JJJJMMDD` | Datum | `20260301` |
| `NNN` | Volgnummer per dag | `001` |

## Project Prefixes

| Project | Prefix | Voorbeeld |
|---------|--------|-----------|
| HavunAdmin | HA | `HA-20260301-001` |
| Herdenkingsportaal | HP | `HP-20260301-001` |
| JudoToernooi | JT | `JT-20260301-001` |
| Studieplanner | SP | `SP-20260301-001` |
| Infosyst | IN | `IN-20260301-001` |
| HavunClub | HC | `HC-20260301-001` |
| SafeHavun | SH | `SH-20260301-001` |

## Implementatie

```php
/**
 * Generate invoice number: XX-YYYYMMDD-NNN
 */
public static function generateInvoiceNumber(string $prefix): string
{
    $date = now()->format('Ymd');
    $pattern = "{$prefix}-{$date}-%";

    $lastNumber = Invoice::where('invoice_number', 'like', $pattern)
        ->orderByDesc('invoice_number')
        ->value('invoice_number');

    if ($lastNumber) {
        $sequence = (int) substr($lastNumber, -3) + 1;
    } else {
        $sequence = 1;
    }

    return sprintf('%s-%s-%03d', $prefix, $date, $sequence);
}
```

## Regels

- Factuurnummers zijn **uniek** en **onwijzigbaar** na aanmaak
- Volgnummer begint elke dag opnieuw bij `001`
- Prefix is altijd 2 hoofdletters
- Datum is altijd `JJJJMMDD` (ISO 8601 zonder streepjes)
