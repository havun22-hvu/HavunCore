# 🔧 HavunCore - Shared Services Package

**v0.6.0** - Centraal package voor gedeelde functionaliteit tussen Havun projecten

📚 **[Complete Documentation Index →](INDEX.md)**

---

## 📦 Features

### **Core Services:**
- **Backup System** - Multi-project backup met 7-jaar retention & compliance
- **Vault** - Secure credential storage met encryptie
- **Task Orchestration** - Cross-project task automation
- **API Contracts** - Contract management & validation
- **Snippet Library** - Reusable code snippets
- **Push Notifications** - Real-time notification system

### **Integration Services:**
- **Memorial Reference** - Memorial UUID logic (12 chars)
- **Mollie** - Payment integration
- **Bunq** - Banking integration
- **Gmail** - Email integration

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
HavunCore/
├── src/                    # Source code
│   ├── Commands/          # 20+ Artisan commands
│   ├── Services/          # Core services (Vault, Backup, etc.)
│   ├── Models/            # Database models
│   └── Events/            # Event system
├── docs/                  # 📚 Complete documentation
│   ├── backup/           # Backup system docs
│   ├── api/              # API documentation
│   ├── setup/            # Setup guides
│   ├── guides/           # Quick references
│   ├── status/           # Status reports
│   ├── testing/          # Test documentation
│   └── claude/           # Claude AI guides
├── storage/              # Storage & data
│   ├── vault/           # Encrypted credentials
│   ├── api/             # OpenAPI specs
│   └── backups/         # Backup storage
├── config/              # Configuration
├── .github/workflows/   # CI/CD pipelines
├── INDEX.md            # 📚 Documentation index
├── ARCHITECTURE.md     # System architecture
├── VISION.md           # Project vision
└── CHANGELOG.md        # Version history
```

**📖 See [INDEX.md](INDEX.md) for complete documentation navigation**

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

## 📚 Documentation

**Quick Links:**
- 📖 [Complete Index](INDEX.md) - All documentation organized
- 🏗️ [Architecture](ARCHITECTURE.md) - System design
- 🎯 [Vision](VISION-HAVUNCORE-ORCHESTRATION.md) - Project goals
- 💾 [Backup System](docs/backup/BACKUP-SYSTEM-OVERZICHT.md) - Backup overview
- 🔌 [API Reference](docs/api/API-REFERENCE.md) - API docs
- ⚙️ [Setup Guide](docs/setup/SETUP.md) - Installation

---

## 📖 Licentie

Proprietary - Alleen voor Havun projecten

---

**Versie:** 0.6.0
**Auteur:** Henk van Velzen
**Laatste update:** 2025-11-22
