# 🚀 HavunCore - Setup & Installatie

**Complete installatiegids voor HavunCore package**

---

## 📋 Vereisten

- **PHP**: ^8.1
- **Laravel**: ^10.0 of ^11.0
- **Composer**: Latest version
- **Guzzle**: ^7.0 (automatisch geïnstalleerd)

---

## 🔧 Installatie

### **Optie 1: Lokale Development (Path Repository) - AANBEVOLEN**

Voor ontwikkeling waarbij je wijzigingen in HavunCore direct wilt testen:

#### **Stap 1: Clone HavunCore repository**

```bash
cd D:\GitHub
git clone https://github.com/havun/HavunCore.git
```

#### **Stap 2: Configureer path repository in je project**

In je project (Herdenkingsportaal, HavunAdmin, IDSee):

```bash
cd D:\GitHub\HavunAdmin  # of Herdenkingsportaal, IDSee
```

Bewerk `composer.json`:

```json
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

#### **Stap 3: Installeer package**

```bash
composer install
```

#### **Stap 4: Publiceer configuratie (optioneel)**

```bash
php artisan vendor:publish --tag=havun-config
```

Dit creëert `config/havun.php` waar je API keys kunt instellen.

---

### **Optie 2: Via GitHub (Productie)**

Voor productie omgevingen waar je een stabiele versie wilt:

```bash
composer require havun/core
```

> **Let op**: Deze optie is beschikbaar wanneer HavunCore op GitHub staat en tagged releases heeft.

---

## ⚙️ Configuratie

### **Environment Variabelen**

Voeg deze toe aan je `.env` bestand:

```env
# Mollie API
MOLLIE_API_KEY=test_xxxxxxxxxxxxxxxxxxxxxxxxxx
# Voor productie: live_xxxxxxxxxxxxxxxxxxxxxxxxxx

# Bunq API (TODO)
BUNQ_API_KEY=your_bunq_api_key
BUNQ_ENVIRONMENT=sandbox  # of production

# Gmail API (TODO)
GMAIL_CLIENT_ID=your_gmail_client_id
GMAIL_CLIENT_SECRET=your_gmail_client_secret
```

### **Config File (optioneel)**

Als je `php artisan vendor:publish --tag=havun-config` hebt uitgevoerd:

Bewerk `config/havun.php`:

```php
<?php

return [
    'mollie' => [
        'api_key' => env('MOLLIE_API_KEY'),
    ],

    'bunq' => [
        'api_key' => env('BUNQ_API_KEY'),
        'environment' => env('BUNQ_ENVIRONMENT', 'sandbox'),
    ],

    'gmail' => [
        'client_id' => env('GMAIL_CLIENT_ID'),
        'client_secret' => env('GMAIL_CLIENT_SECRET'),
    ],
];
```

---

## 🧪 Test Installatie

Test of HavunCore correct is geïnstalleerd:

### **Test 1: Memorial Reference Service**

```bash
php artisan tinker
```

```php
use Havun\Core\Services\MemorialReferenceService;

$service = new MemorialReferenceService();
$reference = $service->extractMemorialReference('Betaling voor 550e8400e29b');
echo $reference;  // Output: 550e8400e29b
```

### **Test 2: Mollie Service**

```php
use Havun\Core\Services\MollieService;

$mollie = new MollieService(env('MOLLIE_API_KEY'));
$payments = $mollie->listPayments(5);
dd($payments);
```

Als dit werkt zonder errors, is HavunCore correct geïnstalleerd! ✅

---

## 🔄 Updates

### **Lokale Development (Path Repository)**

Wanneer je wijzigingen maakt in HavunCore:

```bash
# In HavunCore directory
cd D:\GitHub\HavunCore
git add .
git commit -m "Add BunqService"
git push

# In je project directory
cd D:\GitHub\HavunAdmin
composer update havun/core

# Clear Laravel cache
php artisan config:clear
php artisan cache:clear
```

### **Via GitHub (Productie)**

```bash
composer update havun/core
```

---

## 🗂️ Project Structuur na Installatie

```
D:\GitHub\HavunAdmin/  (of Herdenkingsportaal, IDSee)
├── vendor/
│   └── havun/
│       └── core/  → Symlink naar D:\GitHub\HavunCore (bij path repository)
│
├── config/
│   └── havun.php  (na vendor:publish)
│
├── composer.json
└── .env
```

---

## ❓ Troubleshooting

### **Problem: "Class not found" error**

**Oplossing:**

```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### **Problem: Symlink werkt niet (Windows)**

**Oplossing:**

```bash
# Run als Administrator
composer install --prefer-source
```

### **Problem: Mollie API error "Invalid API key"**

**Oplossing:**

Check `.env`:
- Test key moet beginnen met `test_`
- Live key moet beginnen met `live_`
- Geen spaties voor/na key

```bash
php artisan config:clear
```

### **Problem: Wijzigingen in HavunCore worden niet gezien**

**Oplossing:**

```bash
cd D:\GitHub\HavunAdmin
composer update havun/core --prefer-source
php artisan config:clear
php artisan cache:clear
```

---

## 📦 Deployment naar Staging/Production

### **Staging (Hetzner)**

```bash
# SSH naar server
ssh root@188.245.159.115

# Ga naar project directory
cd /var/www/havunadmin/staging

# Clone HavunCore naast project
cd /var/www
git clone https://github.com/havun/HavunCore.git

# Update composer.json in project
cd /var/www/havunadmin/staging
# Bewerk composer.json met path repository

# Installeer
composer install --no-dev --optimize-autoloader

# Clear cache
php artisan config:clear
php artisan cache:clear
```

### **Production**

Identiek aan staging, maar in `/var/www/havunadmin/production`

---

## 🔐 Security Checklist

- [ ] ✅ `.env` nooit committen
- [ ] ✅ Test API keys op development/staging
- [ ] ✅ Live API keys alleen op production
- [ ] ✅ `composer install --no-dev` op production
- [ ] ✅ File permissions: `chmod 755` voor directories, `644` voor files

---

## 🆘 Support

Voor vragen of problemen:
- Check [README.md](README.md)
- Check [API-REFERENCE.md](API-REFERENCE.md)
- Check [INTEGRATION-GUIDE.md](INTEGRATION-GUIDE.md)

---

**Laatste update:** 2025-11-15
