# Pattern: QR Code URL Matching

> **Probleem:** Scanner herkent QR codes niet door hardcoded URLs

## Het Probleem

JavaScript scanner checkt op specifieke externe URL:

```javascript
// FOUT - hardcoded URL
if (!data.includes('havuncore.havun.nl/approve')) {
    return; // Negeerde ALLE andere QR codes
}
```

Maar QR code wordt gegenereerd met lokale app URL:

```php
// Dit genereert: staging.havunadmin.havun.nl/qr/approve?token=xxx
$approveUrl = route('qr.approve', ['token' => $session->token]);
```

**Mismatch:**

| Wat | URL |
|-----|-----|
| Scanner verwachtte | `havuncore.havun.nl/approve` |
| QR code bevatte | `staging.havunadmin.havun.nl/qr/approve` |

De scanner negeerde dus ELKE QR code omdat de URL niet matchte.

## Oplossing

Check alleen op het pad, niet de hele URL:

```javascript
// CORRECT - check op pad
if (!data.includes('/qr/approve') && !data.includes('/approve')) {
    return;
}
```

## Vuistregels

1. **Nooit hardcoded URLs** in JavaScript voor dezelfde app
2. **Gebruik Blade helpers** voor dynamische URLs:
   - `{{ config('app.url') }}`
   - `{{ route('...') }}`
3. **Check alleen op pad** (`/qr/approve`) niet de hele URL
4. **Test op alle omgevingen:** localhost, staging, production

## Waar dit kan voorkomen

- QR code login (HavunAdmin, Herdenkingsportaal)
- Deep links
- OAuth callbacks
- Webhook URLs

---

*Gedocumenteerd: 2026-01-09 - Probleem opgelost in HavunAdmin*
