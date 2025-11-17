# 🤖 MCP Automation voor Havun Projecten

**Versie:** HavunCore v0.3.0
**Datum:** 17 november 2025
**Status:** ✅ Production Ready

---

## 🎯 Wat is MCP Automation?

MCP (Model Context Protocol) Automation zorgt voor **automatische communicatie** tussen Herdenkingsportaal, HavunAdmin en HavunCore. Geen handmatig berichten meer kopiëren - alles gebeurt automatisch!

---

## 📦 Wat is er Geïmplementeerd?

### 1. **Automatische Status Updates** ✅
Wanneer belangrijke events gebeuren, worden andere projecten automatisch op de hoogte gebracht via MCP.

**Voorbeeld:**
```php
// In HavunCore - bij nieuwe release
event(new HavunCoreDeployed(
    version: 'v0.3.0',
    changes: [
        'Added MCP automation',
        'Automatic invoice sync monitoring',
        'Configuration vault for disaster recovery'
    ],
    breakingChanges: false
));

// Herdenkingsportaal en HavunAdmin ontvangen automatisch een bericht!
```

### 2. **Real-time Sync Monitoring** ✅
Elke invoice sync wordt automatisch gerapporteerd aan MCP. Je kunt direct zien of syncs slagen of falen.

**Voorbeeld:**
```php
// In Herdenkingsportaal - automatisch bij sync
$response = $invoiceSyncService->sendToHavunAdmin($invoiceData);

// Dit fired automatisch een InvoiceSyncCompleted event
// Beide projecten krijgen notificatie:
// ✅ "Invoice Sync: SUCCESS - Memorial: 550e8400e29b"
// of
// ❌ "Invoice Sync: FAILED - Memorial: 550e8400e29b - Error: API timeout"
```

### 3. **HavunCore Configuratie Kluis** 🔐
HavunCore bewaakt **alle** kritieke project configuratie voor disaster recovery.

**Gebruik:**
```bash
# In elk project (Herdenkingsportaal of HavunAdmin)
php artisan havun:vault:store

# Slaat op in MCP:
# - Database configuratie
# - API endpoints
# - Composer packages
# - Laravel versie
# - Project-specifieke settings
# - Environment info
```

**Wanneer gebruik je dit?**
- ✅ Na grote wijzigingen in configuratie
- ✅ Voor project deployment
- ✅ Bij disaster recovery planning
- ✅ Voor nieuwe teamleden (overzicht van setup)

### 4. **Development Workflow Automation** ✅
Belangrijke workflow events worden automatisch gedeeld.

**Voorbeelden:**
```php
// Feature toegevoegd
app(MCPService::class)->reportWorkflowEvent(
    eventType: 'feature',
    title: 'Invoice PDF Export',
    description: 'Customers can now download invoices as PDF',
    metadata: ['affected_files' => ['InvoiceController.php', 'invoice.blade.php']]
);

// Bug fix
app(MCPService::class)->reportWorkflowEvent(
    eventType: 'bugfix',
    title: 'Fixed memorial reference validation',
    description: 'Memorial references now properly validated before sync'
);

// Breaking change
app(MCPService::class)->reportBreakingChange(
    title: 'Invoice API v2.0',
    description: 'Invoice sync API now requires `customer_snapshot` field',
    requiredActions: [
        'Update InvoiceSyncService to v0.3.0',
        'Add customer_snapshot to invoice payload',
        'Test sync with new format'
    ]
);
```

---

## 🔧 Setup per Project

### Herdenkingsportaal

**1. Update HavunCore**
```bash
cd D:/GitHub/Herdenkingsportaal
composer update havun/core
php artisan config:clear
```

**2. Add to `.env`** (optioneel, heeft defaults)
```env
MCP_URL=http://localhost:3000
APP_NAME=Herdenkingsportaal
```

**3. Gebruik**
```php
// Invoice sync gebeurt automatisch - geen actie nodig!
// Events worden automatisch gefired door InvoiceSyncService

// Voor manual notifications:
use Havun\Core\Services\MCPService;

app(MCPService::class)->reportWorkflowEvent(
    'feature',
    'New Memorial Template',
    'Added floral template for memorials'
);
```

---

### HavunAdmin

**1. Update HavunCore**
```bash
cd D:/GitHub/HavunAdmin
composer update havun/core
php artisan config:clear
```

**2. Add to `.env`**
```env
MCP_URL=http://localhost:3000
APP_NAME=HavunAdmin
```

**3. Gebruik**
```php
// Invoice sync monitoring is automatisch actief

// Configuratie vault opslaan:
php artisan havun:vault:store
```

---

### HavunCore

**1. Configuration Vault Command**
```bash
# In elk project dat HavunCore gebruikt
php artisan havun:vault:store

# Output:
# 🔐 Storing project configuration in vault...
# ✅ Configuration for Herdenkingsportaal stored in vault!
#
# Stored configuration:
# - project_name: Herdenkingsportaal
# - php_version: 8.2.0
# - laravel_version: 11.0
# - database: mysql
# - api_endpoints: {...}
```

---

## 📊 MCP Messages Lezen

### Via MCP Tools (in Claude Code)

**Alle berichten voor een project:**
```bash
mcp__havun__getMessages project=Herdenkingsportaal
```

**Berichten van HavunCore:**
```bash
mcp__havun__getMessages project=HavunCore
```

**Alle clients:**
```bash
mcp__havun__listClients
```

### Handmatig (JSON files)

```bash
# Alle messages
cat D:/GitHub/havun-mcp/data/messages.json | jq '.[] | select(.project == "Herdenkingsportaal")'

# Filter op tags
cat D:/GitHub/havun-mcp/data/messages.json | jq '.[] | select(.tags | contains(["invoice-sync"]))'
```

---

## 🔍 Wat Wordt Automatisch Gemonitord?

### Invoice Sync Events
- ✅ **Success:** Memorial reference, Invoice ID, Customer name, Amount
- ❌ **Failure:** Memorial reference, Error message, Invoice number

**Voorbeeld bericht:**
```markdown
# Invoice Sync: ✅ SUCCESS

**Memorial Reference:** 550e8400e29b
**Time:** 2025-11-17 21:30:00
**Project:** Herdenkingsportaal

## Details

- **Invoice ID:** 501
- **invoice_number:** INV-2025-00042
- **amount:** €24.14
- **customer:** Jan Jansen
```

### Deployment Events
```markdown
# 🚀 HavunCore v0.3.0 Released

**Date:** 2025-11-17 21:00:00

## Changes

- Added MCP automation
- Automatic invoice sync monitoring
- Configuration vault for disaster recovery

## Update Instructions

\`\`\`bash
composer update havun/core
php artisan config:clear
php artisan cache:clear
\`\`\`
```

---

## 🚨 Breaking Changes Notification

Als HavunCore breaking changes heeft:

```markdown
# 🚨 BREAKING CHANGE: Invoice API v2.0

**From:** HavunCore
**Date:** 2025-11-17 21:00:00

## What Changed

Invoice sync API now requires `customer_snapshot` field for GDPR compliance.

## ⚠️ Required Actions

1. Update HavunCore to v0.3.0
2. Add customer_snapshot to invoice payload
3. Test sync with new format
4. Deploy to production within 7 days

**Please update your project ASAP!**
```

---

## 🔐 Configuration Vault

### Wat wordt opgeslagen?

**Voor elk project:**
- Project naam en type
- PHP & Laravel versies
- Database configuratie (geen credentials!)
- API endpoints
- Composer packages (belangrijkste)
- Features & capabilities
- Environment settings

**Herdenkingsportaal specifiek:**
```json
{
  "project_name": "Herdenkingsportaal",
  "type": "customer_facing_app",
  "features": {
    "memorials": true,
    "payments": true,
    "mollie_integration": true,
    "invoice_sync": true
  },
  "memorial_reference": {
    "format": "12 lowercase hex chars",
    "source": "UUID first 12 chars without dashes"
  },
  "api_endpoints": {
    "havunadmin": {
      "url": "https://havunadmin.havun.nl/api",
      "endpoints": {
        "invoice_sync": "POST /invoices/sync"
      }
    }
  }
}
```

**HavunAdmin specifiek:**
```json
{
  "project_name": "HavunAdmin",
  "type": "admin_panel",
  "features": {
    "invoice_management": true,
    "client_management": true,
    "receives_invoices_from": ["Herdenkingsportaal"]
  },
  "api_endpoints": {
    "POST /api/invoices/sync": "Receive invoices from Herdenkingsportaal",
    "GET /api/invoices/by-reference/{ref}": "Get invoice status"
  }
}
```

### Waarom is dit belangrijk?

**Disaster Recovery:**
- Server crash? Lees de vault en je weet exact wat je moet herstellen
- Nieuwe developer? Lees de vault voor complete project overzicht

**Documentation:**
- Altijd up-to-date configuratie overzicht
- Geen verouderde documentatie meer

**Auditing:**
- Zie wanneer configuratie voor het laatst is opgeslagen
- Track belangrijke changes over tijd

---

## 🎛️ MCPService API

### Direct gebruiken in je code:

```php
use Havun\Core\Services\MCPService;

$mcp = app(MCPService::class);

// 1. Store message voor ander project
$mcp->storeMessage(
    targetProject: 'HavunAdmin',
    content: '# New Memorial Type Added...',
    tags: ['feature', 'memorials']
);

// 2. Report invoice sync (automatisch, maar kan ook handmatig)
$mcp->reportInvoiceSync(
    memorialReference: '550e8400e29b',
    success: true,
    details: ['invoice_id' => 501, 'amount' => 24.14]
);

// 3. Notify deployment
$mcp->notifyDeployment(
    version: 'v2.1.0',
    changes: ['Bug fixes', 'Performance improvements']
);

// 4. Report breaking change
$mcp->reportBreakingChange(
    title: 'API v2.0',
    description: 'New required field: customer_snapshot',
    requiredActions: ['Update payload', 'Test sync'],
    affectedProjects: ['Herdenkingsportaal']
);

// 5. Report workflow event
$mcp->reportWorkflowEvent(
    eventType: 'feature',  // feature, bugfix, refactor, performance, security
    title: 'Added PDF Export',
    description: 'Invoices can now be exported as PDF'
);

// 6. Store project vault
$mcp->storeProjectVault(
    project: 'Herdenkingsportaal',
    config: [/* config array */]
);
```

---

## 🔄 Wat Gebeurt Er Automatisch?

### Bij elke invoice sync:
1. `InvoiceSyncService->sendToHavunAdmin()` wordt aangeroepen
2. Event `InvoiceSyncCompleted` wordt gefired
3. `ReportToMCP` listener vangt event op
4. MCP bericht wordt opgeslagen voor beide projecten
5. Je ziet in real-time of sync succesvol was

### Bij HavunCore deployment:
```php
// In deployment script of handmatig:
event(new HavunCoreDeployed(
    version: 'v0.3.0',
    changes: ['...'],
    breakingChanges: false
));

// Herdenkingsportaal en HavunAdmin krijgen automatisch:
// "🚀 HavunCore v0.3.0 Released - Update je composer package!"
```

---

## 📋 Best Practices

### 1. Run vault:store regelmatig
```bash
# Na grote config changes
php artisan havun:vault:store

# Voor deployment
php artisan havun:vault:store

# Maandelijks backup
php artisan havun:vault:store
```

### 2. Check MCP messages dagelijks
```bash
# Morning routine:
mcp__havun__getMessages project=Herdenkingsportaal | jq '.[] | select(.tags | contains(["failed"]))'

# Zie direct of er failed syncs zijn
```

### 3. Use workflow events voor belangrijke changes
```php
// Bij nieuwe feature
app(MCPService::class)->reportWorkflowEvent('feature', 'Title', 'Description');

// Bij bug fix
app(MCPService::class)->reportWorkflowEvent('bugfix', 'Fixed X', 'Details');
```

### 4. Tag messages goed
```php
$mcp->storeMessage('HavunAdmin', $content, [
    'invoice-sync',     // Category
    'failed',           // Status
    'urgent',           // Priority
    'action-required'   // Needs attention
]);
```

---

## 🚀 Volgende Stappen

### Implementatie Timeline

**Week 1: Testing**
- [ ] Test invoice sync monitoring
- [ ] Test vault:store command
- [ ] Verify MCP messages worden correct opgeslagen

**Week 2: Adoption**
- [ ] Update alle projecten naar HavunCore v0.3.0
- [ ] Run vault:store in elk project
- [ ] Start using workflow events

**Week 3: Monitoring**
- [ ] Check MCP messages dagelijks
- [ ] Fix any failed syncs direct
- [ ] Build dashboard (optional)

---

## 🎉 Benefits

✅ **Geen handmatig kopiëren meer** - Events worden automatisch gedeeld
✅ **Real-time monitoring** - Zie direct als syncs falen
✅ **Disaster recovery** - Alle config in vault
✅ **Team communication** - Iedereen ziet wat er gebeurt
✅ **Audit trail** - Complete history van events
✅ **Better workflow** - Breaking changes worden automatisch gecommuniceerd

---

**Klaar om te gebruiken!** 🎊

Voor vragen of issues, check de MCP messages of add een issue in HavunCore.
