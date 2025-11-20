# Claude-to-Claude Automation Plan

**Datum:** 19 november 2025
**Doel:** Volledig automatiseren van communicatie tussen Claude instances

---

## 🎯 Probleemstelling

**NU:**
```
HavunAdmin Claude maakt wijziging
  ↓ (JIJ copy-paste bericht)
Herdenkingsportaal Claude test wijziging
  ↓ (JIJ copy-paste response)
HavunAdmin Claude reageert op feedback
  ↓ (JIJ copy-paste weer)
... repeat ...
```

**STRAKS:**
```
HavunAdmin Claude maakt wijziging
  ↓ (MCP notification AUTOMATISCH)
Herdenkingsportaal Claude krijgt bericht EN test automatisch
  ↓ (MCP response AUTOMATISCH)
HavunAdmin Claude ziet resultaat EN reageert
  ↓ (allemaal automatisch!)
```

---

## ✅ Wat Er Al Werkt

1. **MCP Server** - Messaging tussen projecten
2. **Vault** - Secrets management
3. **Snippets** - Code library
4. **API Contracts** - Breaking change detection

**Test:**
```bash
# In HavunCore
mcp__havun__storeMessage project=HavunAdmin content="Test" tags='["test"]'
mcp__havun__getMessages project=HavunAdmin
# ✅ Werkt perfect!
```

---

## 🔧 Wat We Moeten Bouwen

### **Component 1: Auto-Notification System**

**File:** `src/Services/ProjectNotifier.php`

**Wanneer te gebruiken:**
- Bij API wijziging (bijvoorbeeld InvoiceSyncController)
- Bij breaking changes
- Bij test resultaten

**Voorbeeld:**
```php
// In HavunAdmin na API wijziging
use Havun\Core\Services\ProjectNotifier;

$notifier = app(ProjectNotifier::class);
$notifier->notifyClients('invoice_sync_api', [
    'from' => 'HavunAdmin',
    'type' => 'api_change',
    'message' => '
# API Updated: Nested Structure

Endpoint POST /api/invoices/sync now accepts nested structure:
{
  "customer": { ... },
  "invoice": { ... }
}

Please update your client code and test.
    ',
    'action_required' => true,
    'affected_projects' => ['Herdenkingsportaal'],
]);

// Herdenkingsportaal Claude krijgt automatisch notification!
```

### **Component 2: Auto-Polling System**

**File:** `src/Commands/CheckNotifications.php`

**Command:**
```bash
php artisan havun:check-notifications
```

**Wat doet het:**
1. Checkt MCP voor nieuwe berichten
2. Toont berichten aan gebruiker (of voert automatisch uit)
3. Markeert als gelezen

**Usage in projecten:**
```bash
# In Herdenkingsportaal terminal
php artisan havun:check-notifications

# Output:
# 📨 New notification from HavunAdmin:
#
# ─────────────────────────────────────
# From: HavunAdmin
# Type: api_change
# Priority: high
#
# API Updated: Nested Structure
#
# Endpoint POST /api/invoices/sync now accepts nested structure
# [... volledige bericht ...]
#
# ─────────────────────────────────────
#
# [1] Mark as read
# [2] Test the API now
# [3] Reply to HavunAdmin
#
# Your choice:
```

### **Component 3: Interactive Response System**

**Als gebruiker kiest "2. Test the API now":**
```php
// Automatically runs test
$result = $this->testInvoiceSyncAPI();

// Automatically sends response back to HavunAdmin
$notifier->sendReply('HavunAdmin', [
    'from' => 'Herdenkingsportaal',
    'in_reply_to' => $notification->id,
    'type' => 'test_result',
    'status' => $result->success ? 'success' : 'failed',
    'message' => $result->getMessage(),
    'details' => $result->getDetails(),
]);

// HavunAdmin Claude krijgt automatisch het test resultaat!
```

---

## 🚀 Implementation Phases

### **Phase 1: Basic Notification (30 min)**

**Build:**
- `ProjectNotifier` service
- `CheckNotifications` command
- Message templates

**Test:**
```bash
# In HavunAdmin
php artisan tinker
app(\Havun\Core\Services\ProjectNotifier::class)->notifyClients('test', [...]);

# In Herdenkingsportaal
php artisan havun:check-notifications
# Should show the notification
```

### **Phase 2: Auto-Response (45 min)**

**Build:**
- Interactive menu in CheckNotifications
- Auto-testing capabilities
- Reply mechanism

**Test:**
```bash
# Full workflow:
# 1. HavunAdmin sends notification
# 2. Herdenkingsportaal checks notifications
# 3. User selects "Test API"
# 4. Test runs automatically
# 5. Response sent back automatically
# 6. HavunAdmin receives test result
```

### **Phase 3: Task Orchestration (60 min)**

**Build volgens VISION doc:**
- `TaskOrchestrator` - Splits work into tasks
- `TaskReceiver` - Accepts and executes tasks
- `StatusMonitor` - Tracks progress

**Example:**
```bash
# In HavunCore
php artisan havun:orchestrate "Fix invoice sync nested structure"

# Creates 2 tasks:
# Task 1 → HavunAdmin: Update API validation
# Task 2 → Herdenkingsportaal: Update client code

# Both Claude instances receive tasks automatically
# Both execute in parallel
# HavunCore monitors and reports when done
```

---

## 💡 Smart Features We Can Add

### **1. Context-Aware Testing**

Als HavunAdmin stuurt "API changed", dan kan Herdenkingsportaal automatisch:
```php
// Detect what changed
$changes = $notification->detectChanges();

// Run relevant tests
if ($changes->affects('invoice_sync')) {
    $this->runInvoiceSyncTests();
}

// Auto-reply with results
```

### **2. Code Snippet Sharing**

Als HavunAdmin maakt nieuwe code, kan het automatisch snippets delen:
```php
$notifier->notifyClients('new_feature', [
    'message' => 'New feature: Installment payments',
    'snippets' => [
        'payments/installment-client-integration.php' => $code,
    ],
    'usage' => 'Copy to app/Services/PaymentService.php',
]);
```

### **3. Bi-Directional Validation**

**Provider side (HavunAdmin):**
```php
// After API change
$notifier->requestValidation('invoice_sync_api', [
    'consumers' => ['Herdenkingsportaal'],
    'test_data' => [...],
    'expected_response' => [...],
]);
```

**Consumer side (Herdenkingsportaal):**
```php
// Auto-runs validation
$result = $this->validateAPIChange($notification);

// Auto-replies
$notifier->sendValidationResult($result);
```

---

## 📋 Real-World Workflow

### **Scenario: API Breaking Change**

**Step 1: HavunAdmin maakt wijziging**
```php
// In HavunAdmin - After changing InvoiceSyncController
app(ProjectNotifier::class)->notifyClients('invoice_sync_api', [
    'type' => 'breaking_change',
    'message' => 'API now requires `customer_snapshot` field',
    'migration_guide' => '...',
    'affected_endpoints' => ['POST /api/invoices/sync'],
    'deadline' => '2025-11-26', // 7 days
]);
```

**Step 2: Herdenkingsportaal krijgt notificatie**
```bash
php artisan havun:check-notifications

# Output:
# 🚨 BREAKING CHANGE from HavunAdmin!
#
# API now requires `customer_snapshot` field
#
# Migration guide: [...]
# Deadline: 2025-11-26 (7 days)
#
# Actions:
# [1] Show migration guide
# [2] Update code now (auto-generate)
# [3] Remind me tomorrow
```

**Step 3: Gebruiker kiest "2. Update code now"**
```php
// Auto-updates code using snippet from HavunAdmin
$this->updateInvoiceSyncService($notification->getSnippet());

// Auto-tests
$result = $this->testInvoiceSyncAPI();

// Auto-replies
$notifier->sendReply('HavunAdmin', [
    'status' => 'migrated',
    'test_result' => $result,
    'message' => 'Successfully migrated to new API structure',
]);
```

**Step 4: HavunAdmin krijgt bevestiging**
```bash
php artisan havun:check-notifications

# Output:
# ✅ Migration confirmed: Herdenkingsportaal
#
# Status: migrated
# Test result: ✅ All tests passing
# Message: Successfully migrated to new API structure
#
# All consumers migrated: 1/1 ✅
```

---

## 🎯 End Goal

**Volledig geautomatiseerde workflow:**

```
┌─────────────────────────────────────────────────┐
│ Developer workflow (wat JIJ ziet):              │
│                                                  │
│ 1. Open HavunAdmin terminal                    │
│ 2. "Update API to nested structure"            │
│ 3. Claude maakt wijziging                       │
│ 4. "Notify consumers"                           │
│ 5. DONE - Rest gaat automatisch!               │
│                                                  │
│ Later:                                          │
│ 1. Open HavunCore terminal                     │
│ 2. "Check status"                               │
│ 3. "✅ All consumers tested and migrated"      │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│ Wat er AUTOMATISCH gebeurt:                    │
│                                                  │
│ • HavunAdmin stuurt notification via MCP       │
│ • Herdenkingsportaal ziet notification         │
│ • Test draait automatisch                       │
│ • Response gaat terug naar HavunAdmin          │
│ • HavunAdmin update status                     │
│ • JIJ krijgt samenvatting                      │
└─────────────────────────────────────────────────┘
```

**Geen copy-paste meer!** 🎉

---

## ⏰ Timeline

**Phase 1:** Basic Notification - **30 min**
**Phase 2:** Auto-Response - **45 min**
**Phase 3:** Task Orchestration - **60 min**

**TOTAAL: 2.25 uur** voor complete automation!

---

## 🚦 Next Steps

1. **Vandaag:** Build Phase 1 (Basic Notification)
2. **Morgen:** Build Phase 2 (Auto-Response)
3. **Later deze week:** Build Phase 3 (Task Orchestration)

**Dan heb je:**
- ✅ Geen copy-paste meer nodig
- ✅ Automatische testing
- ✅ Real-time status updates
- ✅ Parallel execution van taken
- ✅ Complete oversight

---

**Ready to build?** 🚀
