# 📖 Stap-voor-Stap Gebruiksaanwijzing HavunCore Orchestration

**Voor:** Henk van Unen
**Versie:** 1.0
**Datum:** 18 november 2025

---

## 🎯 Wat Je Hebt Gebouwd

Je hebt nu een **Multi-Claude Orchestration Platform** waarbij:

1. **HavunCore** = Command center waar je opdrachten geeft
2. **Meerdere Claude instances** werken parallel in verschillende projecten
3. **Vault** = Centrale opslag voor alle secrets (encrypted)
4. **Snippets** = Herbruikbare code templates
5. **MCP** = Communicatie tussen alle projecten

**Resultaat:** Development is 2-3x sneller door parallel werken!

---

## 📋 DEEL 1: Eerste Keer Setup (Eenmalig)

### A. HavunCore Setup (Command Center)

**Locatie:** `D:\GitHub\HavunCore`

#### Stap 1: Genereer Vault Encryption Key

```bash
cd D:\GitHub\HavunCore
php artisan havun:vault:generate-key
```

**Output:**
```
🔑 Generated vault encryption key:

HAVUN_VAULT_KEY=base64:abcd1234...

⚠️  IMPORTANT:
   1. Add this to your .env file
   2. Keep this key SECRET and SECURE
   3. If you lose this key, you cannot decrypt your vault
   4. Back up this key in a secure password manager
```

#### Stap 2: Voeg Key toe aan .env

```bash
# Open .env file
notepad .env

# Voeg toe:
HAVUN_VAULT_KEY=base64:abcd1234...

# Sla op
```

**⚠️ BEWAAR DEZE KEY VEILIG!** Zonder deze key kun je je secrets niet meer lezen.

#### Stap 3: Initialiseer Vault en Snippets

```bash
php artisan havun:vault:init
php artisan havun:snippet:init
```

**Output:**
```
🔐 Vault initialized successfully!
📁 Location: storage/vault/secrets.encrypted.json
🔑 Encryption: AES-256-CBC

📚 Snippet library initialized!
Default snippets added:
  • payments/mollie-payment-setup.php
  • api/rest-response-formatter.php
  • utilities/memorial-reference-service.php
```

#### Stap 4: Voeg Je Secrets Toe

```bash
# Mollie API keys
php artisan havun:vault:set mollie_api_key_test "test_xxx" --project=HavunAdmin --description="Mollie test key"
php artisan havun:vault:set mollie_api_key_live "live_xxx" --project=HavunAdmin --description="Mollie production key"

# Database passwords
php artisan havun:vault:set database_password_havunadmin "password123" --project=HavunAdmin
php artisan havun:vault:set database_password_herdenkingsportaal "password456" --project=Herdenkingsportaal

# API Tokens
php artisan havun:vault:set havunadmin_api_token "secret_token_abc" --project=Herdenkingsportaal --description="HavunAdmin API access token"

# Gmail OAuth (indien van toepassing)
php artisan havun:vault:set gmail_oauth_client_id "xxx.apps.googleusercontent.com"
php artisan havun:vault:set gmail_oauth_client_secret "xxx"

# Bunq API (indien van toepassing)
php artisan havun:vault:set bunq_api_token "xxx" --description="Bunq API token"
```

**Tip:** Voor extra veiligheid, gebruik geen value parameter - dan wordt gevraagd via prompt (verborgen):
```bash
php artisan havun:vault:set mollie_api_key_live --project=HavunAdmin
Enter secret value: [type wordt niet getoond]
```

#### Stap 5: Verifieer Setup

```bash
# Toon alle secrets (keys only, geen waarden)
php artisan havun:vault:list

# Check snippets
php artisan havun:snippet:list

# Test commands
php artisan list havun:
```

**✅ HavunCore setup compleet!**

---

### B. Setup Andere Projecten (HavunAdmin, Herdenkingsportaal, etc.)

#### Voor Elk Project:

**1. Installeer HavunCore Package**

```bash
# HavunAdmin
cd D:\GitHub\HavunAdmin
composer require havun/core

# Herdenkingsportaal
cd D:\GitHub\Herdenkingsportaal
composer require havun/core

# VPDUpdate
cd D:\GitHub\VPDUpdate
composer require havun/core
```

**2. Configureer .env**

Open `.env` in elk project en voeg toe:

```env
# MCP Server
MCP_URL=http://localhost:3000

# Vault Key (EXACT DEZELFDE als in HavunCore!)
HAVUN_VAULT_KEY=base64:abcd1234...

# Project naam (belangrijk voor MCP routing)
APP_NAME=HavunAdmin
# OF
APP_NAME=Herdenkingsportaal
# OF
APP_NAME=VPDUpdate
```

**⚠️ KRITIEK:** De `HAVUN_VAULT_KEY` moet **EXACT HETZELFDE** zijn in alle projecten!

**3. Test Installatie**

```bash
# In elk project
php artisan list havun:

# Je moet zien:
# havun:tasks:check
# havun:tasks:complete
# havun:tasks:fail
# havun:vault:get
# havun:vault:list
```

**✅ Alle projecten setup compleet!**

---

### C. MCP Server Setup

**Locatie:** `D:\GitHub\havun-mcp`

#### Stap 1: Start MCP Server

```bash
cd D:\GitHub\havun-mcp
npm start
```

**Output:**
```
MCP Server running on http://localhost:3000
```

**Tip:** Laat dit draaien in een aparte terminal/command prompt.

#### Stap 2: Verifieer MCP Server

Open nieuwe terminal:
```bash
curl http://localhost:3000/health
```

**Expected:** `{"status":"ok"}`

**✅ MCP Server running!**

---

## 📋 DEEL 2: Dagelijks Gebruik

### Scenario 1: Nieuwe Feature Toevoegen

**Voorbeeld:** Je wilt "betalen in termijnen" toevoegen met 3-maands en 6-maands opties.

#### Stap 1: Open HavunCore in VS Code/Claude Code

```bash
cd D:\GitHub\HavunCore
code .
```

#### Stap 2: Orchestreer de Feature

In HavunCore terminal:
```bash
php artisan havun:orchestrate "Voeg betalen in termijnen toe met 3-maands en 6-maands opties via Mollie recurring payments"
```

**Output:**
```
🎯 HavunCore Task Orchestrator

Request: Voeg betalen in termijnen toe met 3-maands en 6-maands opties via Mollie recurring payments

📊 Analyzing request...

📋 Analysis Results:

Components identified:
  • payment_system
  • api

Projects affected:
  • HavunAdmin
  • Herdenkingsportaal

Secrets required:
  ✓ mollie_api_key

Code snippets available:
  ✓ payments/mollie-payment-setup.php
  ✓ api/rest-response-formatter.php

Database changes: Yes
API changes: Yes
Complexity: HIGH

🎯 Created 3 tasks:

┌──────────┬────────────────────┬──────────┬────────────────────────────────┬──────┬──────┐
│ ID       │ Project            │ Priority │ Description                    │ Time │ Deps │
├──────────┼────────────────────┼──────────┼────────────────────────────────┼──────┼──────┤
│ task_001 │ HavunAdmin         │ HIGH     │ Backend API implementation...  │ 45m  │ -    │
│ task_002 │ Herdenkingsportaal │ MEDIUM   │ Frontend implementation...     │ 30m  │ 1    │
│ task_003 │ HavunAdmin         │ LOW      │ Integration testing...         │ 20m  │ 1,2  │
└──────────┴────────────────────┴──────────┴────────────────────────────────┴──────┴──────┘

⏱️  Estimated duration: 45 minutes (parallel execution)
   Sequential would take: 95 minutes
   Time saved: 53%

📤 Tasks delegated via MCP!

Monitor progress with:
  php artisan havun:status orch_20251118_142035

Projects should check for tasks with:
  php artisan havun:tasks:check
```

#### Stap 3: Monitor Progress

```bash
php artisan havun:status orch_20251118_142035
```

**Output:**
```
🎯 Orchestration Status

ID: orch_20251118_142035
Description: Voeg betalen in termijnen toe met 3-maands en 6-maands opties via Mollie recurring payments
Status: ⏳ IN PROGRESS
Progress: 33% (1/3 tasks)

Created: 2025-11-18 14:20:35
Started: 2025-11-18 14:21:00

Estimated duration: 45 minutes
Elapsed time: 12 minutes
Estimated remaining: 33 minutes
Estimated completion: 15:05

📋 Tasks:

┌──────────┬────────────────────┬────────┬──────────┬──────────┬─────────────────────┐
│ ID       │ Project            │ Status │ Priority │ Duration │ Description         │
├──────────┼────────────────────┼────────┼──────────┼──────────┼─────────────────────┤
│ task_001 │ HavunAdmin         │ ✅     │ HIGH     │ 42m      │ Backend API impl... │
│ task_002 │ Herdenkingsportaal │ ⏳     │ MEDIUM   │ ~30m     │ Frontend impl...    │
│ task_003 │ HavunAdmin         │ ⏸️      │ LOW      │ ~20m     │ Integration test... │
└──────────┴────────────────────┴────────┴──────────┴──────────┴─────────────────────┘

🔗 Dependencies:
task_002 depends on: task_001
task_003 depends on: task_001, task_002

📌 Next Steps:
⏳ Waiting for task_002 to complete...
```

#### Stap 4: Open HavunAdmin in Nieuwe VS Code Window

```bash
cd D:\GitHub\HavunAdmin
code .
```

#### Stap 5: In HavunAdmin Claude - Check Tasks

In HavunAdmin terminal:
```bash
php artisan havun:tasks:check
```

**Output:**
```
📥 Checking for tasks from HavunCore...

📋 Pending tasks (1):

[1] HIGH - task_001
    Backend API implementation for: Voeg betalen in termijnen toe...
    Orchestration: orch_20251118_142035

Which task to display? (number, or "all" to see all, "exit" to quit)
```

Type `1`:

```
================================================================================
# 🎯 New Task from HavunCore

**Task ID:** task_001
**Orchestration:** orch_20251118_142035
**Priority:** HIGH
**Estimated Duration:** 45 minutes

## Description

Backend API implementation for: Voeg betalen in termijnen toe met 3-maands en 6-maands opties via Mollie recurring payments

## Instructions

- 1. Create database migration for installment_plans table
- 2. Create or update Eloquent models
- 3. Create API controller with required endpoints
- 4. Add routes to api.php
- 5. Implement request validation
- 6. Implement business logic
- 7. Integrate with Mollie API using provided snippet
- 8. Set up webhook handling
- 9. Write unit and feature tests
- 10. Update API documentation/OpenAPI spec if applicable

## Secrets Provided

✓ mollie_api_key

```json
{
    "mollie_api_key": "live_xxx..."
}
```

## Code Snippets

### payments/mollie-payment-setup.php

**Usage:** Copy to your controller and configure webhook URL

```php
<?php

namespace App\Http\Controllers;

use Mollie\Laravel\Facades\Mollie;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function create(Request $request)
    {
        $payment = Mollie::api()->payments->create([
            'amount' => [
                'currency' => 'EUR',
                'value' => '24.14',
            ],
            'description' => 'Memorial Payment',
            'redirectUrl' => route('payment.success'),
            'webhookUrl' => route('payment.webhook'),
            'metadata' => [
                'memorial_reference' => $request->memorial_reference,
                'order_id' => $request->order_id,
            ],
        ]);

        return redirect($payment->getCheckoutUrl());
    }

    public function webhook(Request $request)
    {
        $paymentId = $request->input('id');
        $payment = Mollie::api()->payments->get($paymentId);

        if ($payment->isPaid() && !$payment->hasRefunds()) {
            // Payment successful
            $metadata = $payment->metadata;
            // Process order...
        } elseif ($payment->isFailed()) {
            // Payment failed
        }

        return response('', 200);
    }
}
```

### api/rest-response-formatter.php

**Usage:** Use in all API controllers for consistent responses

```php
<?php

namespace App\Http\Responses;

trait RestResponse
{
    protected function success($data = null, string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function error(string $message = 'Error', int $code = 400, $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
```

---

When complete, report back with:
`php artisan havun:tasks:complete task_001`
================================================================================
```

#### Stap 6: HavunAdmin Claude Voert Taak Uit

Nu voer je als Claude in HavunAdmin de taak uit:
1. Lees alle instructies
2. Gebruik de mollie_api_key uit de secrets
3. Kopieer de code snippets
4. Maak de migration, models, controllers
5. Schrijf tests
6. Test alles

#### Stap 7: Markeer Taak als Complete

```bash
php artisan havun:tasks:complete task_001 \
  --message="Created installment payment API with Mollie recurring integration" \
  --files="app/Http/Controllers/API/InstallmentController.php,database/migrations/2025_11_18_create_installment_plans_table.php,app/Models/InstallmentPlan.php,tests/Feature/InstallmentPaymentTest.php"
```

**Output:**
```
✅ Task marked as complete!
   HavunCore has been notified

Files modified:
  • app/Http/Controllers/API/InstallmentController.php
  • database/migrations/2025_11_18_create_installment_plans_table.php
  • app/Models/InstallmentPlan.php
  • tests/Feature/InstallmentPaymentTest.php
```

**Dit stuurt automatisch een bericht naar HavunCore via MCP!**

#### Stap 8: Herhaal voor Herdenkingsportaal

```bash
cd D:\GitHub\Herdenkingsportaal
code .
```

In Herdenkingsportaal terminal:
```bash
php artisan havun:tasks:check
# Nu is task_002 beschikbaar (want task_001 is klaar)
```

Voer task_002 uit en complete:
```bash
php artisan havun:tasks:complete task_002 --message="Added installment option to checkout"
```

#### Stap 9: Check Status in HavunCore

Terug in HavunCore:
```bash
php artisan havun:status orch_20251118_142035
```

**Output:**
```
Status: ✅ COMPLETED
Progress: 100% (3/3 tasks)

All tasks completed!
Total time: 48 minutes
Estimated was: 45 minutes
```

**✅ Feature compleet!**

---

### Scenario 2: Secret Toevoegen of Updaten

```bash
cd D:\GitHub\HavunCore

# Nieuwe secret toevoegen
php artisan havun:vault:set new_api_key --project=HavunAdmin
Enter secret value: [type verborgen]

# Bestaande secret updaten
php artisan havun:vault:set mollie_api_key_live --project=HavunAdmin
Enter secret value: [nieuwe waarde]

# Secret bekijken
php artisan havun:vault:get mollie_api_key_live --show
```

---

### Scenario 3: Code Snippet Toevoegen

```bash
cd D:\GitHub\HavunCore

# Maak snippet bestand
mkdir -p storage/snippets/emails
nano storage/snippets/emails/payment-confirmation.blade.php

# Voeg code toe, sla op

# Optioneel: metadata toevoegen
nano storage/snippets/emails/payment-confirmation.blade.php.meta.json
```

**Metadata voorbeeld:**
```json
{
  "description": "Email template for payment confirmations",
  "language": "blade",
  "tags": ["email", "payment", "template"],
  "usage": "Copy to resources/views/emails/payment-confirmation.blade.php",
  "dependencies": [],
  "created_at": "2025-11-18T15:00:00Z",
  "updated_at": "2025-11-18T15:00:00Z"
}
```

**Verificatie:**
```bash
php artisan havun:snippet:list
php artisan havun:snippet:get emails/payment-confirmation
```

---

### Scenario 4: Status Monitoren met Watch Mode

```bash
php artisan havun:orchestrate "Complex multi-project feature"

# Output toont: orch_20251118_160000

# Start watch mode
php artisan havun:status orch_20251118_160000 --watch
```

**Scherm refresht elke 10 seconden automatisch. Stopt zodra alles compleet is.**

---

## 📋 DEEL 3: Backup naar USB Stick (H:)

### Volledige Backup Maken

```bash
# Ga naar HavunCore
cd D:\GitHub\HavunCore

# Maak backup directory
mkdir -p H:\Backup\HavunCore_2025-11-18

# Kopieer hele project (inclusief .git)
xcopy /E /I /H /Y . H:\Backup\HavunCore_2025-11-18

# Verifieer
dir H:\Backup\HavunCore_2025-11-18
```

**⚠️ BELANGRIJK:** De vault encryption key staat in `.env` - zorg dat deze meekomt!

### Alleen Vault en Orchestrations Backup

```bash
# Backup vault
xcopy /E /I /Y storage\vault H:\Backup\HavunCore_vault_2025-11-18

# Backup orchestrations
xcopy /E /I /Y storage\orchestrations H:\Backup\HavunCore_orchestrations_2025-11-18

# Backup .env (bevat vault key!)
copy .env H:\Backup\HavunCore_env_2025-11-18.txt
```

### Restore vanaf USB Stick

Op andere computer:

```bash
# Kopieer van USB naar lokale disk
xcopy /E /I /H H:\Backup\HavunCore_2025-11-18 D:\GitHub\HavunCore

# Installeer dependencies
cd D:\GitHub\HavunCore
composer install

# Controleer .env
cat .env | grep HAVUN_VAULT_KEY

# Test
php artisan havun:vault:list
php artisan havun:status
```

**✅ Backup compleet!**

---

## 📋 DEEL 4: Troubleshooting

### Probleem: "HAVUN_VAULT_KEY not set"

**Oplossing:**
```bash
# Genereer nieuwe key
php artisan havun:vault:generate-key

# OF herstel van backup
copy H:\Backup\HavunCore_env_2025-11-18.txt .env

# Verifieer
grep HAVUN_VAULT_KEY .env
```

### Probleem: "Failed to decrypt vault"

**Oorzaak:** Vault key is veranderd of verkeerd

**Oplossing:**
```bash
# Herstel correcte .env vanaf backup
copy H:\Backup\HavunCore_env_2025-11-18.txt .env

# OF als je de key weet maar niet de vault:
# Maak nieuwe vault
rm storage\vault\secrets.encrypted.json
php artisan havun:vault:init
# Voeg secrets opnieuw toe
```

### Probleem: "No tasks found" maar er zijn wel tasks gedelegeerd

**Check:**
```bash
# 1. Is MCP server running?
curl http://localhost:3000/health

# 2. Klopt APP_NAME?
grep APP_NAME .env

# 3. Check MCP messages direct
php artisan tinker
```

In tinker:
```php
$mcp = app(\Havun\Core\Services\MCPService::class);
$messages = $mcp->getMessages(config('app.name'));
dd($messages);
```

### Probleem: Taak is gestart maar niet als in_progress gemarkeerd

**Oplossing:**
```bash
# Manueel status updaten in HavunCore
cd D:\GitHub\HavunCore

# Via tinker
php artisan tinker
```

```php
$orch = app(\Havun\Core\Services\TaskOrchestrator::class);
$orch->updateTaskStatus('orch_xxx', 'task_001', 'in_progress');
```

---

## 📋 DEEL 5: Checklists

### Checklist: Nieuwe Feature Starten

- [ ] Open HavunCore in VS Code/Claude Code
- [ ] Check of MCP server running is
- [ ] Run: `php artisan havun:orchestrate "feature description"`
- [ ] Note orchestration ID
- [ ] Open affected projects in separate VS Code windows
- [ ] In each project: `php artisan havun:tasks:check`
- [ ] Execute tasks
- [ ] Mark as complete: `php artisan havun:tasks:complete task_xxx`
- [ ] Monitor in HavunCore: `php artisan havun:status orch_xxx`
- [ ] Verify all tasks completed

### Checklist: Einde van de Dag Backup

- [ ] Commit all changes in all projects
- [ ] Run backup: `xcopy /E /I /H /Y D:\GitHub\HavunCore H:\Backup\HavunCore_YYYY-MM-DD`
- [ ] Backup vault: `copy D:\GitHub\HavunCore\storage\vault\secrets.encrypted.json H:\Backup\vault_YYYY-MM-DD.json`
- [ ] Backup .env: `copy D:\GitHub\HavunCore\.env H:\Backup\env_YYYY-MM-DD.txt`
- [ ] Verify backups exist on H:

### Checklist: Nieuwe Computer Setup

- [ ] Kopieer HavunCore van H: naar D:\GitHub\HavunCore
- [ ] Install composer: `composer install`
- [ ] Verifieer .env bevat HAVUN_VAULT_KEY
- [ ] Test: `php artisan havun:vault:list`
- [ ] Setup MCP server: `cd havun-mcp && npm install && npm start`
- [ ] Setup other projects (HavunAdmin, etc.)
- [ ] Test complete flow

---

## 📞 Support

**Documentatie:**
- `VISION-HAVUNCORE-ORCHESTRATION.md` - Complete visie (1200+ lines)
- `ORCHESTRATION-QUICKSTART.md` - Quick start guide
- `SETUP-OTHER-PROJECTS.md` - Project setup guide
- `STAP-VOOR-STAP-GEBRUIKSAANWIJZING.md` - Deze guide
- `CHANGELOG.md` - Versie geschiedenis

**Commands Help:**
```bash
php artisan help havun:orchestrate
php artisan help havun:status
php artisan help havun:vault:set
php artisan help havun:tasks:check
```

**List All Commands:**
```bash
php artisan list havun:
```

---

**Veel succes met HavunCore Multi-Claude Orchestration! 🚀🤖**

**Henk, je hebt nu een professioneel orchestration platform zoals Google, Netflix en Stripe gebruiken!**
