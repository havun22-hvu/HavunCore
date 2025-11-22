# 🔔 Melding voor Andere Projecten (HavunAdmin, Herdenkingsportaal, VPDUpdate)

**Datum:** 18 november 2025
**Van:** HavunCore v0.5.0
**Aan:** Alle Havun projects

---

## 📢 Belangrijke Update: Multi-Claude Orchestration Beschikbaar!

HavunCore heeft een **grote update** gekregen en kan nu taken delegeren naar jullie projecten!

### Wat betekent dit voor jou?

Als je Claude bent in **HavunAdmin**, **Herdenkingsportaal** of **VPDUpdate**:

1. ✅ Je ontvangt straks **gedetailleerde taken** van HavunCore
2. ✅ Taken bevatten **exacte instructies**, **secrets**, en **code snippets**
3. ✅ Je werkt **parallel** met andere Claude instances = veel sneller!
4. ✅ Je hebt toegang tot **centrale vault** voor alle secrets

---

## ⚡ Wat Moet Je Doen?

### Optie A: Wacht op Setup (Aanbevolen)

Henk zal binnenkort jouw project setuppen met:
```bash
composer require havun/core
```

**Je hoeft niets te doen - wacht tot je deze nieuwe commands ziet:**
```bash
php artisan list havun:
```

### Optie B: Zelf Setup (Gevorderd)

Als je ervoor wilt zorgen dat je klaar bent:

1. **Lees de setup guide:**
   - File: `D:\GitHub\HavunCore\SETUP-OTHER-PROJECTS.md`
   - Compleet met alle stappen

2. **Installeer HavunCore:**
   ```bash
   composer require havun/core
   ```

3. **Configureer .env:**
   ```env
   MCP_URL=http://localhost:3000
   HAVUN_VAULT_KEY=[krijg je van HavunCore]
   APP_NAME=HavunAdmin  # of jouw project naam
   ```

---

## 🎯 Hoe Werkt Het Straks?

### 1. HavunCore Delegeert Taak

Henk geeft opdracht in HavunCore:
```bash
php artisan havun:orchestrate "Add payment feature X"
```

HavunCore analyseert en creëert taken voor verschillende projecten.

### 2. Jij Checkt voor Taken

In jouw project (bijv. HavunAdmin):
```bash
php artisan havun:tasks:check
```

**Output:**
```
📥 Pending tasks (1):

[1] HIGH - task_001
    Create payment API endpoints
    Orchestration: orch_20251118_142035
```

### 3. Jij Bekijkt de Taak

Type `1` om details te zien:

```
# 🎯 New Task from HavunCore

**Task ID:** task_001
**Priority:** HIGH
**Estimated:** 30 minutes

## Description
Create payment API endpoints for feature X

## Instructions
1. Create migration...
2. Create controller...
3. Write tests...

## Secrets Provided
✓ mollie_api_key (already decrypted for you!)

## Code Snippets
[Ready-to-copy code hier...]
```

### 4. Jij Voert de Taak Uit

- Lees de instructies
- Gebruik de provided secrets (al ingevuld!)
- Kopieer de code snippets
- Implementeer volgens instructies

### 5. Jij Rapporteert Terug

```bash
php artisan havun:tasks:complete task_001 --message="Done!"
```

**Dit stuurt automatisch bericht naar HavunCore!**

---

## 📚 Documentatie

**Voor jou als Claude in ander project:**

1. **SETUP-OTHER-PROJECTS.md** - Complete setup guide
   - Locatie: `D:\GitHub\HavunCore\SETUP-OTHER-PROJECTS.md`
   - 10 minuten setup
   - Alle commands uitgelegd
   - Troubleshooting

2. **Workflow voorbeeld:**
   ```bash
   # Check tasks
   php artisan havun:tasks:check

   # Complete task
   php artisan havun:tasks:complete task_001 --message="API created"

   # If failed
   php artisan havun:tasks:fail task_001 "Missing credentials"
   ```

---

## 🔐 Vault Access

Na setup heb je toegang tot centrale vault:

```bash
# List all secrets
php artisan havun:vault:list

# Get specific secret
php artisan havun:vault:get mollie_api_key
```

**In code:**
```php
use Havun\Core\Services\VaultService;

$vault = app(VaultService::class);
$apiKey = $vault->get('mollie_api_key');
```

---

## ✅ Wanneer Ben Je Klaar?

Je bent klaar voor orchestration als:

- [ ] `composer.json` bevat `"havun/core": "^0.5.0"`
- [ ] `.env` bevat `HAVUN_VAULT_KEY`, `MCP_URL`, correct `APP_NAME`
- [ ] `php artisan list havun:` toont task commands
- [ ] `php artisan havun:tasks:check` werkt (mag "no tasks" tonen)
- [ ] `php artisan havun:vault:list` werkt

---

## 🆘 Hulp Nodig?

**Check documentatie:**
- `D:\GitHub\HavunCore\SETUP-OTHER-PROJECTS.md` - Setup guide
- `D:\GitHub\HavunCore\VISION-HAVUNCORE-ORCHESTRATION.md` - Complete visie
- `D:\GitHub\HavunCore\ORCHESTRATION-QUICKSTART.md` - Quick start

**Command help:**
```bash
php artisan help havun:tasks:check
php artisan help havun:tasks:complete
```

---

## 🎉 Voordelen voor Jou

1. **Duidelijke instructies** - Geen vage opdrachten meer
2. **Secrets provided** - Altijd de juiste API keys
3. **Code snippets** - Ready-to-use templates
4. **Parallel werken** - Terwijl andere Claudes andere taken doen
5. **Context behouden** - Taken zijn specifiek voor jouw project

---

## 📞 Contact

Als je vragen hebt, vraag Henk of check de documentatie in:
```
D:\GitHub\HavunCore\
```

---

**Welkom bij het Multi-Claude Orchestration Platform! 🚀🤖**

We gaan samen veel sneller werken!

---

**Belangrijke links:**
- Setup: `D:\GitHub\HavunCore\SETUP-OTHER-PROJECTS.md`
- Vision: `D:\GitHub\HavunCore\VISION-HAVUNCORE-ORCHESTRATION.md`
- Quick Start: `D:\GitHub\HavunCore\ORCHESTRATION-QUICKSTART.md`
- Deze melding: `D:\GitHub\HavunCore\SETUP-INSTRUCTIES-VOOR-ANDERE-PROJECTEN.md`
