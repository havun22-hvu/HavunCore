# 🔧 Setup Guide: HavunCore in Other Projects

**Voor:** HavunAdmin, Herdenkingsportaal, VPDUpdate, Client Projects

---

## 📋 Wat Moet Er Gebeuren?

Elk project moet HavunCore installeren om:
1. ✅ Tasks te kunnen ontvangen van HavunCore orchestration
2. ✅ Secrets op te halen uit de vault
3. ✅ Code snippets te gebruiken
4. ✅ Te communiceren via MCP

---

## ⚡ Snelle Installatie (10 minuten)

### Stap 1: Installeer HavunCore Package

**In HavunAdmin:**
```bash
cd D:\GitHub\HavunAdmin
composer require havun/core
```

**In Herdenkingsportaal:**
```bash
cd D:\GitHub\Herdenkingsportaal
composer require havun/core
```

**In VPDUpdate:**
```bash
cd D:\GitHub\VPDUpdate
composer require havun/core
```

### Stap 2: Publiceer Config (optioneel)

```bash
php artisan vendor:publish --tag=havun-config
```

Dit is optioneel - HavunCore werkt out-of-the-box.

### Stap 3: Configureer .env

Voeg toe aan `.env`:

```env
# MCP Server Configuration
MCP_URL=http://localhost:3000

# HavunCore Vault (gebruik DEZELFDE key als in HavunCore!)
HAVUN_VAULT_KEY=base64:xxx...

# Project Naam (voor MCP identificatie)
APP_NAME=HavunAdmin  # of Herdenkingsportaal, VPDUpdate, etc.
```

**⚠️ BELANGRIJK:** De `HAVUN_VAULT_KEY` moet **exact hetzelfde** zijn als in HavunCore!

**Vault key vinden:**
```bash
# In HavunCore
cat .env | grep HAVUN_VAULT_KEY
```

Kopieer deze waarde naar alle andere projecten.

### Stap 4: Test Installatie

```bash
php artisan list havun:
```

Je zou moeten zien:
```
havun:tasks:check        Check for pending tasks from HavunCore
havun:tasks:complete     Mark a task as completed
havun:tasks:fail         Mark a task as failed
havun:vault:get          Get secret from vault
havun:vault:list         List all secrets
```

---

## 🔄 Workflow: Tasks Ontvangen en Uitvoeren

### Stap 1: Check voor Tasks

```bash
php artisan havun:tasks:check
```

**Output voorbeeld:**
```
📥 Checking for tasks from HavunCore...

📋 Pending tasks (2):

[1] HIGH - task_001
    Backend API implementation for: Add installment payments
    Orchestration: orch_20251118_142035

[2] MEDIUM - task_002
    Add admin dashboard for installments
    Orchestration: orch_20251118_142035

Which task to display? (number, or "all" to see all, "exit" to quit)
```

### Stap 2: Bekijk Taak Details

Type `1` om task_001 te zien:

```
================================================================================
# 🎯 New Task from HavunCore

**Task ID:** task_001
**Orchestration:** orch_20251118_142035
**Priority:** HIGH
**Estimated Duration:** 30 minutes

## Description

Backend API implementation for: Add installment payments with 3-month and 6-month options

## Instructions

- 1. Create migration for installment_plans table
- 2. Create model: App\Models\InstallmentPlan
- 3. Create controller: App\Http\Controllers\API\InstallmentController
  - POST /api/payments/installment/create
  - GET /api/payments/installment/{id}
- 4. Integrate Mollie recurring payments
- 5. Write tests: tests/Feature/InstallmentPaymentTest.php

## Secrets Provided

✓ mollie_api_key

```json
{
    "mollie_api_key": "live_xxx..."
}
```

## Code Snippets

### payments/mollie-recurring-setup.php

**Usage:** Copy to app/Services/MollieRecurringService.php

```php
<?php
// Ready-to-use Mollie recurring payment code...
```

---

When complete, report back with:
`php artisan havun:tasks:complete task_001`
================================================================================
```

### Stap 3: Voer de Taak Uit

Nu kun je als Claude in dit project de taak uitvoeren:
1. Lees de instructies
2. Gebruik de provided secrets (al ingevuld!)
3. Kopieer de code snippets
4. Implementeer de feature
5. Test alles

### Stap 4: Markeer als Complete

```bash
php artisan havun:tasks:complete task_001 \
  --message="Created API endpoints with Mollie integration" \
  --files="app/Http/Controllers/API/InstallmentController.php,database/migrations/2025_11_18_create_installment_plans.php"
```

**Dit stuurt automatisch een bericht terug naar HavunCore via MCP!**

### Stap 5 (optioneel): Als het Misgaat

```bash
php artisan havun:tasks:fail task_001 "Missing Mollie API credentials in production"
```

---

## 🔐 Secrets Ophalen uit Vault

Je kunt altijd secrets ophalen:

```bash
# Toon alle beschikbare secrets
php artisan havun:vault:list

# Haal een specifieke secret op (verborgen)
php artisan havun:vault:get mollie_api_key

# Toon de waarde
php artisan havun:vault:get mollie_api_key --show
```

**In code:**
```php
use Havun\Core\Services\VaultService;

class PaymentController extends Controller
{
    public function __construct(private VaultService $vault)
    {
    }

    public function createPayment()
    {
        $apiKey = $this->vault->get('mollie_api_key');

        // Use the key...
    }
}
```

---

## 🤖 Automatische Workflow voor Claude

Als Claude in een ander project dan HavunCore:

### Bij het Starten van een Sessie

1. **Check voor nieuwe tasks:**
   ```bash
   php artisan havun:tasks:check
   ```

2. **Als er tasks zijn:**
   - Bekijk de task details
   - Lees de instructies zorgvuldig
   - Gebruik de provided secrets
   - Kopieer de code snippets
   - Implementeer volgens de instructies

3. **Na implementatie:**
   ```bash
   php artisan havun:tasks:complete <task_id> \
     --message="Short summary" \
     --files="file1.php,file2.php"
   ```

4. **Bij problemen:**
   ```bash
   php artisan havun:tasks:fail <task_id> "Reason why it failed"
   ```

### Periodiek Checken

Als je bezig bent met andere dingen, check elke 30 minuten:
```bash
php artisan havun:tasks:check
```

---

## 📡 MCP Communicatie

### Hoe Werkt Het?

```
HavunCore (Orchestrator)
    │
    │ MCP Protocol (HTTP)
    ▼
MCP Server (http://localhost:3000)
    │
    ├─→ HavunAdmin
    ├─→ Herdenkingsportaal
    ├─→ VPDUpdate
    └─→ Client Projects
```

**Berichten Structuur:**

**Task Delegation (HavunCore → Project):**
```json
{
  "project": "HavunAdmin",
  "content": "# Task details...",
  "tags": ["task", "orch_xxx", "task_001"]
}
```

**Task Completion (Project → HavunCore):**
```json
{
  "project": "HavunCore",
  "content": "✅ Task task_001 completed",
  "tags": ["task_completed", "orch_xxx", "task_001"]
}
```

### MCP Server Setup

**Ensure MCP server is running:**
```bash
# Check if running
curl http://localhost:3000/health

# If not running, start it (in havun-mcp project)
cd D:\GitHub\havun-mcp
npm start
```

---

## 🧪 Testen

### Test 1: Vault Access

```bash
php artisan havun:vault:list
```

**Expected:** Lijst van alle secrets (zonder waarden)

### Test 2: MCP Connectivity

```bash
php artisan tinker
```

```php
$mcp = app(\Havun\Core\Services\MCPService::class);
$messages = $mcp->getMessages(config('app.name'));
dd($messages);
```

**Expected:** Array van messages (kan leeg zijn)

### Test 3: Task Check

```bash
php artisan havun:tasks:check
```

**Expected:** "No pending tasks" of lijst van tasks

### Test 4: Complete Flow

**In HavunCore:**
```bash
php artisan havun:orchestrate "Test task delegation" --dry-run
```

**In target project:**
```bash
php artisan havun:tasks:check
```

---

## 🚨 Troubleshooting

### "Command not found: havun:tasks:check"

**Probleem:** HavunCore package niet correct geïnstalleerd

**Oplossing:**
```bash
composer require havun/core
php artisan cache:clear
php artisan config:clear
```

### "HAVUN_VAULT_KEY not set"

**Probleem:** Vault key ontbreekt in .env

**Oplossing:**
```bash
# In HavunCore, kopieer de key
cat .env | grep HAVUN_VAULT_KEY

# Plak in .env van dit project
echo "HAVUN_VAULT_KEY=base64:xxx..." >> .env
```

### "Failed to decrypt vault"

**Probleem:** Vault key is niet hetzelfde als in HavunCore

**Oplossing:** Zorg dat EXACT dezelfde key gebruikt wordt in alle projecten.

### "No tasks found"

**Mogelijke oorzaken:**
1. MCP server is niet bereikbaar
2. Geen tasks gedelegeerd vanuit HavunCore
3. APP_NAME in .env klopt niet

**Check:**
```bash
# Is MCP server running?
curl http://localhost:3000/health

# Klopt APP_NAME?
grep APP_NAME .env

# Zijn er tasks in HavunCore?
cd D:\GitHub\HavunCore
php artisan havun:status
```

### "Secret not found in vault"

**Probleem:** Secret bestaat niet of is alleen voor ander project

**Oplossing:**
```bash
# In HavunCore, check welke secrets er zijn
cd D:\GitHub\HavunCore
php artisan havun:vault:list

# Voeg secret toe indien nodig
php artisan havun:vault:set <key> <value> --project=HavunAdmin
```

---

## 📚 Beschikbare Services

Na installatie zijn beschikbaar:

### VaultService
```php
use Havun\Core\Services\VaultService;

$vault = app(VaultService::class);
$secret = $vault->get('api_key');
```

### MemorialReferenceService
```php
use Havun\Core\Services\MemorialReferenceService;

$service = app(MemorialReferenceService::class);
$reference = $service->generate();
$isValid = $service->validate($reference);
```

### MollieService
```php
use Havun\Core\Services\MollieService;

$mollie = app(MollieService::class);
$payment = $mollie->createPayment([...]);
```

### InvoiceSyncService
```php
use Havun\Core\Services\InvoiceSyncService;

$sync = app(InvoiceSyncService::class);
$result = $sync->syncInvoice($memorialRef, $invoiceData);
```

### MCPService
```php
use Havun\Core\Services\MCPService;

$mcp = app(MCPService::class);
$messages = $mcp->getMessages('HavunAdmin');
```

---

## 🎯 Best Practices

### 1. Check Tasks Regelmatig

Start elke Claude sessie met:
```bash
php artisan havun:tasks:check
```

### 2. Complete Tasks Direct

Nadat je een task hebt afgerond, markeer direct als complete:
```bash
php artisan havun:tasks:complete task_xxx --message="Done" --files="..."
```

Dit zorgt dat:
- HavunCore weet dat de taak klaar is
- Dependent tasks kunnen starten
- Progress tracking accuraat is

### 3. Gebruik Provided Secrets

Als een task secrets bevat, gebruik die:
```json
{
  "mollie_api_key": "live_xxx"
}
```

**Niet** zelf secrets uit vault halen - ze zijn al provided!

### 4. Volg Instructies Exact

De task instructies zijn specifiek en in volgorde:
```
1. Create migration
2. Create model
3. Create controller
4. Write tests
```

Volg deze volgorde voor consistentie.

### 5. Report Failures Early

Als je tegen een blocker aanloopt, fail direct:
```bash
php artisan havun:tasks:fail task_xxx "Missing database credentials"
```

Niet wachten - dit bespaart tijd.

---

## 🔄 Update Workflow

Als HavunCore updates krijgt:

```bash
cd D:\GitHub\HavunAdmin
composer update havun/core

# Clear caches
php artisan cache:clear
php artisan config:clear
```

Check nieuwe commands:
```bash
php artisan list havun:
```

---

## 📖 Meer Documentatie

**In HavunCore repository:**
- `VISION-HAVUNCORE-ORCHESTRATION.md` - Complete visie
- `ORCHESTRATION-QUICKSTART.md` - Quick start guide
- `CHANGELOG.md` - Versie geschiedenis

**Commands documentatie:**
```bash
php artisan help havun:tasks:check
php artisan help havun:tasks:complete
php artisan help havun:vault:get
```

---

## ✅ Checklist: Project Setup Complete

- [ ] `composer require havun/core` uitgevoerd
- [ ] `HAVUN_VAULT_KEY` toegevoegd aan .env (EXACT dezelfde als HavunCore)
- [ ] `APP_NAME` correct ingesteld in .env
- [ ] `MCP_URL` toegevoegd aan .env
- [ ] `php artisan list havun:` toont commands
- [ ] `php artisan havun:vault:list` werkt
- [ ] `php artisan havun:tasks:check` werkt
- [ ] Test task completion getest

**Als alles ✅ is: Project is klaar voor orchestration!**

---

**Setup voltooid! Dit project kan nu:**
1. ✅ Tasks ontvangen van HavunCore
2. ✅ Secrets ophalen uit vault
3. ✅ Communiceren via MCP
4. ✅ Shared services gebruiken

**Veel succes met multi-Claude development! 🚀**
