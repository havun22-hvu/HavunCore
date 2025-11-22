# 🧪 Test Plan: PDF Facturen van Herdenkingsportaal → HavunAdmin

**Doel:** Facturen gemaakt in Herdenkingsportaal automatisch als PDF in HavunAdmin.

**Datum:** 18 november 2025
**Test:** Eerste echte orchestration test!

---

## 📋 Wat Gaan We Bouwen?

### Feature: Automatische PDF Factuur Sync

**Flow:**
```
Herdenkingsportaal
  ↓ Factuur aangemaakt
  ↓ PDF gegenereerd
  ↓ API call naar HavunAdmin
  ↓
HavunAdmin
  ↓ Factuur ontvangen
  ↓ PDF opgeslagen
  ↓ Gekoppeld aan memorial
  ✅ Beschikbaar in admin panel
```

---

## FASE 1: Voorbereiding (15 minuten)

### Stap 1.1: Check MCP Server

**Wat jij doet:**
```bash
# Check of MCP server draait
curl http://localhost:3000/health

# Als niet running:
cd D:\GitHub\havun-mcp
npm start
# Laat dit draaien in aparte terminal
```

**Expected output:**
```json
{"status":"ok"}
```

---

### Stap 1.2: Setup HavunAdmin met HavunCore

**Wat jij doet:**

**In HavunAdmin project:**
```bash
cd D:\GitHub\HavunAdmin

# Installeer HavunCore package
composer require havun/core

# Of als lokaal package:
# Voeg toe aan composer.json:
{
  "repositories": [
    {
      "type": "path",
      "url": "../HavunCore"
    }
  ],
  "require": {
    "havun/core": "^0.5.0"
  }
}
# Dan: composer update havun/core
```

**Configureer .env in HavunAdmin:**
```env
# Voeg toe aan D:\GitHub\HavunAdmin\.env

# MCP Server
MCP_URL=http://localhost:3000

# Project naam (voor MCP routing)
APP_NAME=HavunAdmin

# Vault key - KOMT LATER (eerst HavunCore initialiseren)
# HAVUN_VAULT_KEY=xxx
```

**Verify installatie:**
```bash
cd D:\GitHub\HavunAdmin
php artisan list havun:

# Je moet zien:
# havun:tasks:check
# havun:tasks:complete
# havun:tasks:fail
# havun:vault:get
# havun:vault:list
```

---

### Stap 1.3: Setup Herdenkingsportaal met HavunCore

**Wat jij doet:**

**In Herdenkingsportaal project:**
```bash
cd D:\GitHub\Herdenkingsportaal

# Installeer HavunCore
composer require havun/core
# Of via local path zoals bij HavunAdmin
```

**Configureer .env in Herdenkingsportaal:**
```env
# Voeg toe aan D:\GitHub\Herdenkingsportaal\.env

# MCP Server
MCP_URL=http://localhost:3000

# Project naam
APP_NAME=Herdenkingsportaal

# Vault key - KOMT LATER
# HAVUN_VAULT_KEY=xxx
```

**Verify:**
```bash
cd D:\GitHub\Herdenkingsportaal
php artisan list havun:
```

---

### Stap 1.4: Initialiseer Vault in HavunAdmin (Proxy)

Omdat HavunCore zelf geen Laravel app is, gebruiken we HavunAdmin als proxy:

**Wat jij doet:**
```bash
cd D:\GitHub\HavunAdmin

# Genereer vault key
php artisan havun:vault:generate-key
```

**Output:**
```
🔑 Generated vault encryption key:

HAVUN_VAULT_KEY=base64:abcd1234efgh5678...

⚠️  IMPORTANT:
   1. Add this to your .env file
   2. Keep this key SECRET and SECURE
```

**Kopieer deze key naar ALLE projecten:**

1. **HavunAdmin/.env:**
   ```env
   HAVUN_VAULT_KEY=base64:abcd1234efgh5678...
   ```

2. **Herdenkingsportaal/.env:**
   ```env
   HAVUN_VAULT_KEY=base64:abcd1234efgh5678...
   ```

3. **HavunCore/.env** (maak nieuwe .env):
   ```env
   HAVUN_VAULT_KEY=base64:abcd1234efgh5678...
   MCP_URL=http://localhost:3000
   APP_NAME=HavunCore
   ```

**Initialiseer vault:**
```bash
cd D:\GitHub\HavunAdmin
php artisan havun:vault:init
php artisan havun:snippet:init
```

---

### Stap 1.5: Voeg Secrets toe

**Wat jij doet:**
```bash
cd D:\GitHub\HavunAdmin

# HavunAdmin API token (voor Herdenkingsportaal)
php artisan havun:vault:set havunadmin_api_token --project=Herdenkingsportaal
# Prompt: type een random token, bijv: "havun_secret_2025_xyz123"

# Gmail/Mail credentials (voor PDF versturen)
php artisan havun:vault:set mail_from_address --project=HavunAdmin
# Type: jouw email adres

# Controleer
php artisan havun:vault:list
```

---

## FASE 2: Orchestratie Test (IK doe dit)

Nu kan ik de orchestration starten!

**Wat ik ga doen:**

```bash
# In HavunAdmin (als proxy voor HavunCore)
php artisan havun:orchestrate "Maak automatische PDF factuur sync van Herdenkingsportaal naar HavunAdmin. Facturen gemaakt in Herdenkingsportaal moeten als PDF in HavunAdmin admin panel komen."
```

**Dit zal:**
1. ✅ Analyseren wat nodig is
2. ✅ Taken maken voor HavunAdmin en Herdenkingsportaal
3. ✅ Secrets resolven uit vault
4. ✅ Snippets toevoegen
5. ✅ Taken delegeren via MCP

---

## FASE 3: Claude in Andere Projecten (AUTOMATISCH)

**Wat gebeurt er:**

### In HavunAdmin Claude Session:
```bash
php artisan havun:tasks:check
```

**Output:**
```
📥 Pending tasks (2):

[1] HIGH - task_001
    Create API endpoint voor PDF factuur ontvangst

[2] MEDIUM - task_002
    Admin panel weergave voor gesynct facturen
```

Claude in HavunAdmin krijgt:
- Exacte instructies
- API token uit vault (al gedecrypt!)
- Code snippets voor PDF handling
- Expected request/response formats

### In Herdenkingsportaal Claude Session:
```bash
php artisan havun:tasks:check
```

**Output:**
```
📥 Pending tasks (1):

[1] HIGH - task_003
    PDF generatie en sync naar HavunAdmin API
```

Claude in Herdenkingsportaal krijgt:
- Instructies voor PDF generatie
- API token uit vault
- API endpoint details
- Code snippets voor HTTP calls

---

## VERIFICATIE STAPPEN

### Stap V1: Check MCP Berichten

**Wat jij doet:**
```bash
cd D:\GitHub\HavunAdmin
php artisan tinker
```

In tinker:
```php
$mcp = app(\Havun\Core\Services\MCPService::class);
$messages = $mcp->getMessages('HavunAdmin');
dd($messages);
```

Je moet berichten zien met task details.

### Stap V2: Check Vault Access

**Wat jij doet:**
```bash
# In HavunAdmin
cd D:\GitHub\HavunAdmin
php artisan havun:vault:list

# In Herdenkingsportaal
cd D:\GitHub\Herdenkingsportaal
php artisan havun:vault:list
```

Beide moeten dezelfde secrets tonen.

### Stap V3: Test Orchestration

**Wat ik doe (zodra jij FASE 1 compleet hebt):**

Start orchestration en monitor status.

---

## 🚨 Mogelijke Problemen & Oplossingen

### Probleem: "composer require havun/core" failed

**Oplossing:** Gebruik local path:

In `D:\GitHub\HavunAdmin\composer.json`:
```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../HavunCore"
    }
  ],
  "require": {
    "havun/core": "^0.5.0"
  }
}
```

Dan:
```bash
composer update havun/core
```

### Probleem: "HAVUN_VAULT_KEY not set"

**Oplossing:** Zorg dat EXACT dezelfde key in alle .env files staat.

### Probleem: MCP server not responding

**Oplossing:**
```bash
cd D:\GitHub\havun-mcp
npm start
```

Laat draaien in aparte terminal.

### Probleem: "Command not found: havun:tasks:check"

**Oplossing:**
```bash
composer dump-autoload
php artisan cache:clear
php artisan config:clear
```

---

## ✅ Checklist Voor Jou (FASE 1)

Vink af wat je hebt gedaan:

- [ ] MCP server draait (curl http://localhost:3000/health geeft {"status":"ok"})
- [ ] HavunAdmin: composer require havun/core SUCCESS
- [ ] HavunAdmin: php artisan list havun: toont commands
- [ ] HavunAdmin: .env bevat MCP_URL en APP_NAME=HavunAdmin
- [ ] Herdenkingsportaal: composer require havun/core SUCCESS
- [ ] Herdenkingsportaal: php artisan list havun: toont commands
- [ ] Herdenkingsportaal: .env bevat MCP_URL en APP_NAME=Herdenkingsportaal
- [ ] Vault key gegenereerd: php artisan havun:vault:generate-key
- [ ] HAVUN_VAULT_KEY toegevoegd aan ALLE .env files (HavunAdmin, Herdenkingsportaal, HavunCore)
- [ ] Vault geïnitialiseerd: php artisan havun:vault:init
- [ ] Snippets geïnitialiseerd: php artisan havun:snippet:init
- [ ] API token opgeslagen: php artisan havun:vault:set havunadmin_api_token
- [ ] php artisan havun:vault:list toont secrets in alle projecten

**Als alles ✅ is, zeg je: "FASE 1 COMPLEET" dan start ik FASE 2!**

---

## Wat Ik Ga Doen (FASE 2 - na jouw setup)

```bash
# Start orchestration
php artisan havun:orchestrate "Maak automatische PDF factuur sync van Herdenkingsportaal naar HavunAdmin"

# Monitor
php artisan havun:status orch_xxx --watch

# Creëer taken voor:
# - HavunAdmin: API endpoint + admin panel
# - Herdenkingsportaal: PDF generatie + API call
```

**Dan delegeer ik taken via MCP naar de andere Claude instances!**

---

**Laat me weten wanneer je FASE 1 hebt afgerond, dan gaan we verder! 🚀**
