# 🚀 Orchestration Quick Start Guide

**HavunCore v0.5.0** - Multi-Claude Task Orchestration

---

## 📋 Wat is dit?

HavunCore is nu een **command center** waar je opdrachten geeft en meerdere Claude instances parallel aan het werk zet in verschillende projecten.

**Voorbeeld:**
```bash
php artisan havun:orchestrate "Voeg betalen in termijnen toe"
```

Dit creëert automatisch:
- Task 1 → HavunAdmin (Backend API)
- Task 2 → Herdenkingsportaal (Frontend)
- Task 3 → HavunAdmin (Admin dashboard)

Alle taken worden **parallel** uitgevoerd = **2-3x sneller** dan sequentieel.

---

## ⚡ Snelle Setup (5 minuten)

### Stap 1: Genereer Vault Encryption Key

```bash
php artisan havun:vault:generate-key
```

Kopieer de output naar je `.env`:
```env
HAVUN_VAULT_KEY=base64:xxx...
```

### Stap 2: Initialiseer Vault & Snippets

```bash
php artisan havun:vault:init
php artisan havun:snippet:init
```

### Stap 3: Voeg Secrets Toe

```bash
# Mollie API key
php artisan havun:vault:set mollie_api_key "live_xxx" --project=HavunAdmin

# HavunAdmin API token
php artisan havun:vault:set havunadmin_api_token "secret_token" --project=Herdenkingsportaal

# Database password
php artisan havun:vault:set database_password "password"
```

### Stap 4: Klaar! 🎉

Je kunt nu orchestreren:

```bash
php artisan havun:orchestrate "Voeg installment betalingen toe"
```

---

## 🎯 Basis Commando's

### Orchestration

**Nieuwe orchestration starten:**
```bash
php artisan havun:orchestrate "Add feature X"
```

**Preview zonder delegeren:**
```bash
php artisan havun:orchestrate "Add feature X" --dry-run
```

**Status bekijken:**
```bash
php artisan havun:status
php artisan havun:status orch_20251118_142035
php artisan havun:status orch_20251118_142035 --watch  # Auto-refresh elke 10s
```

### Vault

**Secret toevoegen:**
```bash
php artisan havun:vault:set api_key "value"
php artisan havun:vault:set api_key --project=HavunAdmin  # Waarde via prompt
```

**Secret ophalen:**
```bash
php artisan havun:vault:get api_key
php artisan havun:vault:get api_key --show  # Toon waarde
```

**Alle secrets tonen:**
```bash
php artisan havun:vault:list
php artisan havun:vault:list --project=HavunAdmin
```

### Snippets

**Snippets tonen:**
```bash
php artisan havun:snippet:list
php artisan havun:snippet:list --category=payments
php artisan havun:snippet:list --tag=mollie
```

**Snippet bekijken:**
```bash
php artisan havun:snippet:get payments/mollie-payment-setup
php artisan havun:snippet:get payments/mollie-payment-setup --copy  # Naar clipboard
```

### Tasks (in andere projecten)

**Check voor taken:**
```bash
cd ../HavunAdmin
php artisan havun:tasks:check
```

**Taak voltooien:**
```bash
php artisan havun:tasks:complete task_001 --message="Backend API created"
```

**Taak als gefaald markeren:**
```bash
php artisan havun:tasks:fail task_001 "Missing Mollie credentials"
```

---

## 💡 Praktische Voorbeelden

### Voorbeeld 1: Payment Feature Toevoegen

```bash
php artisan havun:orchestrate "Add iDEAL payment option to checkout"
```

**Resultaat:**
```
🎯 Created 2 tasks:
- task_001: HavunAdmin - Add iDEAL payment endpoint (30m)
- task_002: Herdenkingsportaal - Add iDEAL to checkout page (25m)

Estimated: 30 minutes (parallel)
Sequential would be: 55 minutes
Time saved: 45%
```

### Voorbeeld 2: Nieuwe Client Project Setup

```bash
php artisan havun:orchestrate "Setup new client project 'GedenktekenenPortaal' with HavunAdmin integration"
```

**Resultaat:**
```
🎯 Created 3 tasks:
- task_001: HavunCore - Create Laravel project (10m)
- task_002: GedenktekenenPortaal - Configure and install HavunCore (15m)
- task_003: HavunAdmin - Register new client and generate API token (10m)

Estimated: 15 minutes (parallel)
```

### Voorbeeld 3: API Endpoint Toevoegen

```bash
php artisan havun:orchestrate "Add API endpoint to export memorial data as CSV"
```

**Resultaat:**
```
🎯 Created 2 tasks:
- task_001: HavunAdmin - Create CSV export endpoint (25m)
- task_002: HavunAdmin - Write tests for CSV export (15m)

Estimated: 40 minutes (sequential - task_002 depends on task_001)
```

---

## 🔄 Complete Workflow

### 1. Orchestreren
```bash
php artisan havun:orchestrate "Add email notifications for payment confirmations"
```

Output:
```
📊 Analysis Results:

Components identified:
  • email
  • payment_system

Projects affected:
  • HavunAdmin
  • Herdenkingsportaal

Secrets required:
  ✓ gmail_oauth_credentials
  ✓ mollie_api_key

🎯 Created 2 tasks:
- task_001: HavunAdmin - Email notification service (30m)
- task_002: Herdenkingsportaal - Trigger email on payment (20m)

📤 Tasks delegated via MCP!

Monitor with: php artisan havun:status orch_20251118_142035
```

### 2. Status Monitoren
```bash
php artisan havun:status orch_20251118_142035
```

Output:
```
🎯 Orchestration Status

Status: ⏳ IN PROGRESS
Progress: 50% (1/2 tasks)

📋 Tasks:
┌──────────┬────────────────────┬────────┬──────────┬──────────┐
│ ID       │ Project            │ Status │ Priority │ Duration │
├──────────┼────────────────────┼────────┼──────────┼──────────┤
│ task_001 │ HavunAdmin         │ ✅     │ HIGH     │ 28m      │
│ task_002 │ Herdenkingsportaal │ ⏳     │ MEDIUM   │ ~20m     │
└──────────┴────────────────────┴────────┴──────────┴──────────┘

📌 Next Steps:
⏳ Waiting for task_002 to complete...
```

### 3. In Andere Projecten

**In HavunAdmin:**
```bash
cd ../HavunAdmin
php artisan havun:tasks:check
```

Output:
```
📥 Pending tasks from HavunCore:

[1] HIGH PRIORITY - task_001
    Create email notification service
    Orchestration: orch_20251118_142035
    Estimated: 30 minutes

Which task to display? (number, "all", "exit")
```

Type `1` om de volledige taak te zien met:
- Gedetailleerde instructies
- Benodigde secrets (al ingevuld!)
- Code snippets (ready to copy)
- API contracts

### 4. Taak Voltooien

Na het uitvoeren van de taak:
```bash
php artisan havun:tasks:complete task_001 \
  --message="Email service created with templates" \
  --files="app/Services/EmailNotificationService.php,app/Mail/PaymentConfirmation.php"
```

Output:
```
✅ Task marked as complete!
   HavunCore has been notified

Files modified:
  • app/Services/EmailNotificationService.php
  • app/Mail/PaymentConfirmation.php
```

### 5. Verificatie in HavunCore

Terug in HavunCore:
```bash
php artisan havun:status orch_20251118_142035
```

Output:
```
🎯 Orchestration Status

Status: ✅ COMPLETED
Progress: 100% (2/2 tasks)

All tasks completed!
Total time: 45 minutes
Estimated was: 50 minutes
```

---

## 🎨 Tips & Tricks

### Natural Language is Key

De orchestrator begrijpt natuurlijke taal. Wees specifiek:

**Goed:**
```bash
php artisan havun:orchestrate "Add 3-month and 6-month installment payment options with Mollie recurring payments"
```

**Te vaag:**
```bash
php artisan havun:orchestrate "payment stuff"
```

### Dry Run First

Test eerst wat er zou gebeuren:
```bash
php artisan havun:orchestrate "Big complex feature" --dry-run
```

Bekijk de taken, pas je opdracht aan indien nodig, run dan zonder `--dry-run`.

### Watch Mode voor Lange Orchestrations

```bash
php artisan havun:status orch_xxx --watch
```

Refresht elke 10 seconden automatisch. Stopt automatisch als alles klaar is.

### Secrets Per Project

Voeg project-specifieke secrets toe:
```bash
php artisan havun:vault:set mollie_key_test "test_xxx" --project=HavunAdmin
php artisan havun:vault:set mollie_key_live "live_xxx" --project=HavunAdmin
```

Haal alleen secrets op voor specifiek project:
```bash
php artisan havun:vault:list --project=HavunAdmin
```

### Snippets Toevoegen

Voeg je eigen snippets toe:
```bash
# Via bestand
echo "<?php // your code" > /tmp/snippet.php
php artisan havun:snippet:add my-category/my-snippet < /tmp/snippet.php

# Of direct in code editor
# Plaats in: storage/snippets/my-category/my-snippet.php
# Voeg metadata toe: storage/snippets/my-category/my-snippet.php.meta.json
```

### JSON Output voor Scripting

Alle status commando's ondersteunen `--json`:
```bash
php artisan havun:status --json | jq '.[] | select(.status == "in_progress")'
php artisan havun:vault:list --json
```

---

## 🚨 Troubleshooting

### "HAVUN_VAULT_KEY not set"

Genereer een key:
```bash
php artisan havun:vault:generate-key
```

Voeg toe aan `.env`:
```env
HAVUN_VAULT_KEY=base64:xxx...
```

### "Orchestration not found"

Check bestaande orchestrations:
```bash
php artisan havun:status --all
```

Orchestration files staan in: `storage/orchestrations/*.json`

### "Task not found"

Check of MCP berichten correct zijn:
```bash
# In het project waar de taak zou moeten zijn
php artisan havun:tasks:check
```

Mogelijk is MCP server niet bereikbaar of is het bericht nog niet verzonden.

### "Secret not found in vault"

List alle secrets:
```bash
php artisan havun:vault:list
```

Voeg toe indien nodig:
```bash
php artisan havun:vault:set <key> <value>
```

---

## 📚 Meer Informatie

**Complete Visie Document:**
- `VISION-HAVUNCORE-ORCHESTRATION.md` (1200+ lines)
- Architectuur diagrammen
- Gedetailleerde voorbeelden
- Vergelijking met industry leaders

**Changelog:**
- `CHANGELOG.md` - Alle versie wijzigingen

**Commands Overzicht:**
```bash
php artisan list havun:
```

---

## 🎯 Volgende Stappen

1. **Setup Vault** - Voeg al je secrets toe
2. **Review Snippets** - Bekijk en voeg snippets toe
3. **Test Orchestration** - Probeer een kleine feature
4. **Setup Other Projects** - Installeer HavunCore in HavunAdmin & Herdenkingsportaal
5. **Go Live** - Gebruik voor echte features!

---

**Veel plezier met Multi-Claude Orchestration! 🚀🤖**

Voor vragen of problemen, check de vision document of CHANGELOG.
