# Factuurnummering - Havun Standaard

## Formaat

```
XX-JJJJMMDD-[herkenbare code]
```

| Deel | Betekenis | Voorbeeld |
|------|-----------|-----------|
| `XX` | Project prefix (2 letters) | `JT` |
| `JJJJMMDD` | Factuurdatum | `20260301` |
| `code` | Herkenbare code (verschilt per project) | `001`, `noordzee-cup`, `cc80b72f993e` |

**Doel:** Elke factuur is herleidbaar naar het project, de datum, en het specifieke object (toernooi, memorial, klant, etc.)

## Project Prefixes & Code

| Project | Prefix | Code deel | Voorbeeld |
|---------|--------|-----------|-----------|
| HavunAdmin | HA | volgnummer per dag | `HA-20260301-001` |
| Herdenkingsportaal | HP | memorial UUID (12 chars) | `HP-20260301-cc80b72f993e` |
| JudoToernooi | JT | toernooi slug | `JT-20260301-noordzee-cup` |
| HavunCore | HC | volgnummer per dag | `HC-20260301-001` |
| Infosyst | IS | volgnummer per dag | `IS-20260301-001` |
| Studieplanner | SP | volgnummer per dag | `SP-20260301-001` |
| SafeHavun | SH | volgnummer per dag | `SH-20260301-001` |

### Varianten per project

**Herdenkingsportaal (HP):** `HP-JJJJMMDD-{memorial-uuid-12}`
- Eerste 12 chars van memorial UUID → direct herleidbaar naar het memorial

**JudoToernooi (JT):** `JT-JJJJMMDD-{toernooi-slug}`
- Slug van het toernooi → direct herleidbaar naar welk toernooi

**Overige projecten:** `XX-JJJJMMDD-NNN`
- Volgnummer per dag, begint bij `001`

## Implementatie

### Standaard (volgnummer)

```php
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

### Herdenkingsportaal (memorial UUID)

```php
$invoiceNumber = sprintf('HP-%s-%s', now()->format('Ymd'), substr($memorial->uuid, 0, 12));
```

### JudoToernooi (toernooi slug)

```php
$invoiceNumber = sprintf('JT-%s-%s', now()->format('Ymd'), $toernooi->slug);
```

## Regels

- Factuurnummers zijn **uniek** en **onwijzigbaar** na aanmaak
- Prefix is altijd 2 hoofdletters
- Datum is altijd `JJJJMMDD` (ISO 8601 zonder streepjes)
- Code moet het factuurnummer **herleidbaar** maken naar het onderliggende object
