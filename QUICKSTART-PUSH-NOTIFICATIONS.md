# 🚀 Quick Start: Real-Time Push Notifications

**Versie:** 1.0.0
**Datum:** 19 november 2025
**Latency:** < 100ms tussen projecten!

---

## ⚡ Setup (Eenmalig - 2 minuten)

### Stap 1: Installeer Dependencies

```bash
cd D:\GitHub\havun-mcp
npm install
```

Dit installeert `chokidar` voor file watching.

### Stap 2: Start Notification Watcher (Per Project)

**Open DRIE terminals:**

**Terminal 1 - HavunCore:**
```bash
cd D:\GitHub\havun-mcp
npm run notify:havuncore
```

**Terminal 2 - Herdenkingsportaal:**
```bash
cd D:\GitHub\havun-mcp
npm run notify:herdenkingsportaal
```

**Terminal 3 - HavunAdmin:**
```bash
cd D:\GitHub\havun-mcp
npm run notify:havunadmin
```

**Je ziet:**
```
🔔 Notification Watcher started for HavunAdmin
📂 Watching: D:\GitHub\havun-mcp\notifications\HavunAdmin\new
⏰ Waiting for notifications...
```

✅ **Klaar!** Laat deze terminals **open** op de achtergrond.

---

## 📨 Notifications Versturen

### Optie 1: Via Command (Makkelijkst)

```bash
# In HavunAdmin project
php artisan havun:notify Herdenkingsportaal "API updated to nested structure" \
  --type=api_change \
  --priority=high \
  --action

# Of kort:
php artisan havun:notify Herdenkingsportaal "Test bericht"
```

### Optie 2: Via PHP Code

```php
// In je controller/service
use Havun\Core\Services\PushNotifier;

$notifier = app(PushNotifier::class);

// Simpel bericht
$notifier->send('Herdenkingsportaal', [
    'type' => 'info',
    'message' => 'Dit is een test bericht!',
]);

// API wijziging
$notifier->notifyAPIChange(
    targetProject: 'Herdenkingsportaal',
    message: '
# API Updated: Nested Structure

The Invoice Sync API now uses nested structure:

```json
{
  "customer": { "name": "..." },
  "invoice": { "amount": 10.00 }
}
```

Please update InvoiceSyncService.
    ',
    breaking: true,
    deadline: '2025-11-26'
);

// Test resultaat
$notifier->notifyTestResult(
    targetProject: 'HavunAdmin',
    success: true,
    message: 'Invoice sync tests: 5/5 passed',
    metadata: ['tests' => 5, 'passed' => 5]
);

// Deployment
$notifier->notifyDeployment(
    targetProject: 'Herdenkingsportaal',
    version: 'v2.1.0',
    changes: [
        'Fixed invoice sync timeout',
        'Added customer snapshot field',
    ]
);

// Broadcast naar meerdere projecten
$notifier->broadcast(
    ['Herdenkingsportaal', 'VPDUpdate'],
    [
        'type' => 'update',
        'message' => 'HavunCore v0.6.0 released!',
    ]
);
```

---

## 🔔 Notifications Ontvangen

**Automatisch!**

Als de notification watcher draait, zie je **instant**:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔔 NEW NOTIFICATION FROM HAVUNADMIN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Type: api_change
Time: 2025-11-19 23:45:32
Priority: high

# API Updated: Nested Structure

The Invoice Sync API now uses nested structure...

⚠️  ACTION REQUIRED!
⏰ Deadline: 2025-11-26
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

### Check Notification History

```bash
php artisan havun:check-notifications --history

# Output:
# 📋 Last 10 notifications:
#
# 1. 🔧 🟠 From: HavunAdmin
#    Type: api_change
#    Time: 2025-11-19 23:45:32
#
#    API Updated: Nested Structure
#    ...
```

### Check Pending Count

```bash
php artisan havun:check-notifications

# Output:
# 📨 You have 3 pending notification(s)
#
# 🔔 Start the notification watcher to see them:
#    cd D:\GitHub\havun-mcp
#    npm run notify:Herdenkingsportaal
```

---

## 🎯 Complete Workflow Example

### Scenario: HavunAdmin wijzigt API

**Terminal 1 (HavunAdmin Claude):**

```bash
# Na API wijziging in InvoiceSyncController
php artisan havun:notify Herdenkingsportaal \
  "API now requires customer_snapshot field. See migration guide in docs." \
  --type=breaking_change \
  --priority=urgent \
  --action \
  --deadline=2025-11-26
```

**Output:**
```
✅ Notification sent to Herdenkingsportaal
   📧 Type: breaking_change
   📊 Priority: urgent
   ⚠️  Action required!
   ⏰ Deadline: 2025-11-26

💡 Tip: The target project will see this notification instantly
   if they're running: npm run notify:Herdenkingsportaal
```

---

**Terminal 2 (Herdenkingsportaal - met watcher):**

**INSTANT zie je:**
```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔔 NEW NOTIFICATION FROM HAVUNADMIN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Type: breaking_change
Time: 2025-11-19 23:47:15
Priority: urgent

API now requires customer_snapshot field. See migration guide in docs.

⚠️  ACTION REQUIRED!
⏰ Deadline: 2025-11-26
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

**Herdenkingsportaal Claude:**

```php
// Update code volgens migration guide
// Test de nieuwe API

// Stuur bevestiging terug
app(PushNotifier::class)->send('HavunAdmin', [
    'type' => 'confirmation',
    'message' => '✅ Migrated to new API structure. All tests passing.',
]);
```

---

**Terminal 1 (HavunAdmin - met watcher):**

**INSTANT zie je:**
```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔔 NEW NOTIFICATION FROM HERDENKINGSPORTAAL
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Type: confirmation
Time: 2025-11-19 23:52:00

✅ Migrated to new API structure. All tests passing.
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

**Total tijd: 5 minuten! (Was eerst uren van copy-pasten)**

---

## 📋 Notification Types

### 1. **info** (standaard)
Algemene informatie, geen actie vereist.

```php
$notifier->send('Project', [
    'type' => 'info',
    'message' => 'Server maintenance scheduled for tonight',
]);
```

### 2. **api_change**
API wijziging (non-breaking).

```php
$notifier->notifyAPIChange('Project', 'Added optional field: phone_number');
```

### 3. **breaking_change**
Breaking change met deadline.

```php
$notifier->notifyAPIChange(
    'Project',
    'Removed deprecated endpoint /api/old',
    breaking: true,
    deadline: '2025-12-01'
);
```

### 4. **test_result**
Automatische test resultaten.

```php
$notifier->notifyTestResult(
    'HavunAdmin',
    success: false,
    message: 'Invoice sync tests: 4/5 passed, 1 failed',
    metadata: ['failed_test' => 'Large payload handling']
);
```

### 5. **deployment**
Deployment notifications.

```php
$notifier->notifyDeployment(
    'Herdenkingsportaal',
    'v2.1.0',
    ['Bug fixes', 'Performance improvements']
);
```

### 6. **request**
Verzoek om actie.

```php
$notifier->requestAction(
    'Herdenkingsportaal',
    'Please run invoice sync tests with latest API',
    deadline: '2025-11-20'
);
```

### 7. **confirmation**
Bevestiging na actie.

```php
$notifier->send('HavunAdmin', [
    'type' => 'confirmation',
    'message' => '✅ Tests completed successfully',
]);
```

---

## ⚙️ Priority Levels

```php
'priority' => 'urgent'   // 🔴 Requires immediate attention
'priority' => 'high'     // 🟠 Should be addressed today
'priority' => 'normal'   // 🟡 Standard (default)
'priority' => 'low'      // 🟢 FYI only
```

---

## 🎨 Markdown Formatting

Notifications support **full Markdown**:

```php
$notifier->send('Project', [
    'message' => '
# 🎉 New Feature Released!

## Installment Payments

Customers can now pay in **3 or 6 monthly installments**.

### API Endpoint
`POST /api/payments/installment/create`

### Next Steps
- [ ] Update checkout flow
- [ ] Add UI for installment selector
- [x] API documentation updated

**Deadline:** 2025-11-26
    ',
]);
```

---

## 🔧 Troubleshooting

### Watcher niet gestart?

```bash
cd D:\GitHub\havun-mcp
npm install  # Installeer dependencies eerst
npm run notify:herdenkingsportaal
```

### Notifications niet zichtbaar?

**Check of directories bestaan:**
```bash
dir D:\GitHub\havun-mcp\notifications\Herdenkingsportaal\new
```

**Als directory niet bestaat, maak aan:**
```bash
mkdir D:\GitHub\havun-mcp\notifications\Herdenkingsportaal\new
mkdir D:\GitHub\havun-mcp\notifications\Herdenkingsportaal\read
```

### Command niet gevonden?

**Zorg dat HavunCore geïnstalleerd is:**
```bash
composer require havun/core
# Of update
composer update havun/core
```

**Clear cache:**
```bash
php artisan config:clear
php artisan cache:clear
```

**Check of command bestaat:**
```bash
php artisan list | grep havun:notify
```

### Notifications blijven in "new" folder?

De watcher verplaatst notifications automatisch naar "read" na weergeven.

Als watcher **niet** draait, blijven ze in "new" folder.

**Start watcher:**
```bash
npm run notify:herdenkingsportaal
```

---

## 💡 Tips & Best Practices

### 1. Start Watcher in Aparte Terminal

```bash
# Terminal 1: Claude Code
# Terminal 2: Notification Watcher (laat draaien)
```

### 2. Use Descriptive Messages

**Goed:**
```php
$notifier->send('Project', [
    'message' => '
# API Breaking Change

Endpoint: POST /api/invoices/sync
Change: Added required field `customer_snapshot`

Migration:
1. Update InvoiceSyncService
2. Add customer snapshot to payload
3. Run tests

Deadline: 2025-11-26
    ',
]);
```

**Slecht:**
```php
$notifier->send('Project', [
    'message' => 'API changed',
]);
```

### 3. Set Appropriate Priority

- **urgent** = Requires immediate action (breaking changes, production down)
- **high** = Should address today (API changes, test failures)
- **normal** = Standard communication (deployments, feature updates)
- **low** = FYI only (informational updates)

### 4. Include Deadlines for Breaking Changes

```php
$notifier->notifyAPIChange(
    'Project',
    'API v2.0 migration required',
    breaking: true,
    deadline: '2025-11-26'  // 7 days from now
);
```

### 5. Use Metadata for Structured Data

```php
$notifier->send('Project', [
    'type' => 'test_result',
    'message' => 'Tests completed',
    'metadata' => [
        'total_tests' => 50,
        'passed' => 48,
        'failed' => 2,
        'duration_seconds' => 45,
        'failed_tests' => [
            'test_invoice_sync_large_payload',
            'test_api_timeout_handling',
        ],
    ],
]);
```

---

## 🚀 Geavanceerde Usage

### Auto-notify na Deploy

```php
// In deployment script
app(PushNotifier::class)->broadcast(
    ['Herdenkingsportaal', 'VPDUpdate', 'ClientProject'],
    [
        'type' => 'deployment',
        'message' => "HavunAdmin v{$version} deployed to production",
        'metadata' => [
            'version' => $version,
            'deployed_at' => now()->toIso8601String(),
            'environment' => 'production',
        ],
    ]
);
```

### Auto-notify na Test Failures

```php
// In test suite
if ($testResult->failed()) {
    app(PushNotifier::class)->sendUrgent('HavunAdmin', [
        'type' => 'test_result',
        'message' => "❌ Invoice sync tests FAILED\n\nFailed: {$testResult->failures}\nSee logs for details.",
    ]);
}
```

### Notification in Event Listener

```php
namespace App\Listeners;

use App\Events\InvoiceSyncFailed;
use Havun\Core\Services\PushNotifier;

class NotifyAdminOfFailedSync
{
    public function handle(InvoiceSyncFailed $event)
    {
        app(PushNotifier::class)->send('HavunAdmin', [
            'type' => 'api_error',
            'priority' => 'high',
            'message' => "
Invoice sync failed for memorial: {$event->memorial->reference}

Error: {$event->error->getMessage()}

Please investigate.
            ",
        ]);
    }
}
```

---

## 📊 Performance

**Latency:** < 100ms tussen send en receive
**CPU Usage:** < 1% (file watcher)
**File Size:** ~1-5KB per notification
**Cleanup:** Old notifications in `read/` folder can be deleted periodically

---

## ✅ Checklist

### Setup
- [ ] `npm install` in havun-mcp
- [ ] Watcher gestart voor elk project
- [ ] Commands beschikbaar (`php artisan list | grep havun:notify`)

### Testing
- [ ] Test notification verstuurd
- [ ] Notification ontvangen instant
- [ ] History kan bekeken worden
- [ ] Broadcast naar meerdere projecten werkt

### Production
- [ ] Watcher draait als achtergrond proces
- [ ] Notifications worden gebruikt bij API wijzigingen
- [ ] Team weet hoe notifications te versturen/ontvangen

---

**Geen copy-paste meer - alleen maar instant communication!** 🚀

Voor vragen of issues, zie `REAL-TIME-PUSH-NOTIFICATIONS.md` voor complete documentatie.
