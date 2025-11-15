# 🔧 HavunCore - Shared Services Package

**Centraal package voor gedeelde functionaliteit tussen Havun projecten**

---

## 📦 Wat zit erin?

### **Services:**
- **MemorialReferenceService** - Memorial UUID logic (eerste 12 chars)
- **MollieService** - Mollie payment integration
- **BunqService** - Bunq banking (TODO)
- **GmailService** - Gmail integration (TODO)

### **Traits:**
- **HasMemorialReference** - Voor models met memorial_reference field (TODO)

---

## 🚀 Installatie

### **Lokale Development (Path Repository)**

**In je project (Herdenkingsportaal, HavunAdmin, IDSee):**

```json
// composer.json
{
  "repositories": [
    {
      "type": "path",
      "url": "../HavunCore"
    }
  ],
  "require": {
    "havun/core": "@dev"
  }
}
```

```bash
composer install
```

### **Via GitHub (Later - als package stable is)**

```bash
composer require havun/core
```

---

## 💻 Gebruik

### **Memorial Reference Service**

```php
use Havun\Core\Services\MemorialReferenceService;

$service = new MemorialReferenceService();

// Extract from text
$reference = $service->extractMemorialReference('Betaling voor 550e8400e29b');
// → "550e8400e29b"

// Validate
$valid = $service->isValidReference('550e8400e29b');
// → true

// From full UUID
$reference = $service->fromUuid('550e8400-e29b-41d4-a716-446655440000');
// → "550e8400e29b"

// Format for display
$formatted = $service->formatReference('550e8400e29b');
// → "550e-8400-e29b"
```

### **Mollie Service**

```php
use Havun\Core\Services\MollieService;

$mollie = new MollieService(env('MOLLIE_API_KEY'));

// Create payment with memorial reference
$payment = $mollie->createPayment(
    amount: 19.95,
    description: 'Monument Opa Jan',
    memorialReference: '550e8400e29b',
    redirectUrl: 'https://example.com/return',
    webhookUrl: 'https://example.com/webhook'
);

// Get payment
$payment = $mollie->getPayment('tr_WDqYK6vllg');

// Extract memorial reference
$reference = $mollie->extractMemorialReference($payment);

// List recent payments
$payments = $mollie->listPayments(limit: 20);

// Check if paid
if ($mollie->isPaid($payment)) {
    // Payment successful!
}
```

---

## 🔗 Projecten die HavunCore gebruiken

- **Herdenkingsportaal** - Memorial website (Laravel)
- **HavunAdmin** - Bedrijfsadministratie (Laravel)
- **IDSee** - Consultancy project (Laravel)

---

## 📁 Project Structuur

```
D:\GitHub\HavunCore/
├── src/
│   ├── Services/
│   │   ├── MollieService.php
│   │   ├── BunqService.php (TODO)
│   │   ├── GmailService.php (TODO)
│   │   └── MemorialReferenceService.php
│   ├── Traits/
│   │   └── HasMemorialReference.php (TODO)
│   ├── Config/
│   │   └── havun.php
│   └── HavunCoreServiceProvider.php
│
├── composer.json
├── README.md
└── .gitignore
```

---

## 🔄 Development Workflow

### **Wijzigingen maken in HavunCore:**

```bash
# 1. Edit code in HavunCore
cd D:\GitHub\HavunCore
# ... edit files ...

# 2. Commit + push
git add .
git commit -m "Add Bunq service"
git push

# 3. Update in dependent projects
cd D:\GitHub\Herdenkingsportaal
composer update havun/core

# HavunCore wijzigingen zijn nu beschikbaar!
```

### **Test wijzigingen lokaal:**

```bash
# In project (bijv. Herdenkingsportaal):
composer update havun/core

# Laravel cache clear
php artisan config:clear
php artisan cache:clear
```

---

## 🎯 TODO

- [ ] BunqService implementeren
- [ ] GmailService implementeren
- [ ] HasMemorialReference trait
- [ ] Unit tests toevoegen
- [ ] Config file publiceren
- [ ] Laravel Service Provider afmaken

---

## 📖 Licentie

Proprietary - Alleen voor Havun projecten

---

**Versie:** 0.1.0-dev
**Auteur:** Henk van Velzen
**Laatste update:** {{ date }}
