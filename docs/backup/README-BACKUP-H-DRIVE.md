# 💾 Backup naar H: Drive - Instructies

## ✅ Backup Compleet!

**Datum:** 18 november 2025
**Locatie:** `H:\HavunCore-Backup-20251118\`
**Grootte:** ~1.2 MB
**Git Commit:** 6271fa9

---

## 📦 Wat is er gebackupt?

### Volledige HavunCore v0.5.0
- ✅ Alle source code (Services, Commands, Events, Listeners)
- ✅ Alle documentatie (Vision, Guides, API docs)
- ✅ Git repository (.git folder met complete history)
- ✅ Composer configuratie
- ✅ Storage directories (vault, snippets, orchestrations)

### Belangrijke Bestanden
```
H:\HavunCore-Backup-20251118\
├── src/
│   ├── Services/
│   │   ├── VaultService.php
│   │   ├── SnippetLibrary.php
│   │   └── TaskOrchestrator.php
│   └── Commands/ (13 new commands)
├── Documentation/
│   ├── VISION-HAVUNCORE-ORCHESTRATION.md
│   ├── STAP-VOOR-STAP-GEBRUIKSAANWIJZING.md
│   ├── SETUP-OTHER-PROJECTS.md
│   └── ORCHESTRATION-QUICKSTART.md
├── composer.json (v0.5.0)
├── CHANGELOG.md
├── .git/ (complete git history)
└── BACKUP-INFO.txt (restore instructions)
```

---

## 🔄 Restore Instructies

### Op Andere Computer/Locatie

**Stap 1: Kopieer Backup**
```bash
# Windows
xcopy /E /I /H H:\HavunCore-Backup-20251118 D:\GitHub\HavunCore

# Of gewoon via Explorer: kopieer hele folder
```

**Stap 2: Installeer Dependencies**
```bash
cd D:\GitHub\HavunCore
composer install
```

**Stap 3: Setup Environment**
```bash
# Kopieer .env.example naar .env
copy .env.example .env

# Genereer vault key
php artisan havun:vault:generate-key

# Voeg output toe aan .env:
# HAVUN_VAULT_KEY=base64:xxx...
```

**Stap 4: Initialize**
```bash
php artisan havun:vault:init
php artisan havun:snippet:init
```

**Stap 5: Verify**
```bash
php artisan list havun:
php artisan havun:vault:list
```

**✅ Klaar!**

---

## ⚠️ Belangrijke Notitie: .env Backup

De `.env` file staat **NIET** in git en is **NIET** in deze backup!

**De .env bevat:**
- `HAVUN_VAULT_KEY` (KRITISCH - zonder deze kan je vault niet decrypten!)
- `MCP_URL`
- Andere configuratie

### .env Backup Maken

**Handmatig .env backuppen:**
```bash
# Kopieer .env naar H: drive
copy D:\GitHub\HavunCore\.env H:\HavunCore-env-backup-20251118.txt

# Of als encrypted vault + .env samen:
copy D:\GitHub\HavunCore\.env H:\HavunCore-Backup-20251118\env-BACKUP.txt
copy D:\GitHub\HavunCore\storage\vault\secrets.encrypted.json H:\HavunCore-Backup-20251118\vault-BACKUP.json
```

**⚠️ ZEER BELANGRIJK:**
- Bewaar de `HAVUN_VAULT_KEY` veilig!
- Zonder deze key zijn je encrypted secrets onleesbaar
- Maak meerdere backups van deze key

---

## 📁 Extra Backups (Aanbevolen)

Voor complete system backup, backup ook:

```bash
# HavunAdmin
xcopy /E /I /H D:\GitHub\HavunAdmin H:\HavunAdmin-Backup-20251118

# Herdenkingsportaal
xcopy /E /I /H D:\GitHub\Herdenkingsportaal H:\Herdenkingsportaal-Backup-20251118

# VPDUpdate
xcopy /E /I /H D:\GitHub\VPDUpdate H:\VPDUpdate-Backup-20251118

# MCP Server
xcopy /E /I /H D:\GitHub\havun-mcp H:\havun-mcp-Backup-20251118
```

---

## 🔐 Vault en Secrets

### Als je de vault wilt meenemen:

**Backup:**
```bash
copy D:\GitHub\HavunCore\storage\vault\secrets.encrypted.json H:\vault-backup-20251118.json
```

**Restore:**
```bash
mkdir D:\GitHub\HavunCore\storage\vault
copy H:\vault-backup-20251118.json D:\GitHub\HavunCore\storage\vault\secrets.encrypted.json
```

**⚠️ Vergeet niet de HAVUN_VAULT_KEY in .env te zetten!**

---

## 📊 Orchestrations Backup

Als je actieve orchestrations hebt:

**Backup:**
```bash
xcopy /E /I D:\GitHub\HavunCore\storage\orchestrations H:\orchestrations-backup-20251118
```

**Restore:**
```bash
xcopy /E /I H:\orchestrations-backup-20251118 D:\GitHub\HavunCore\storage\orchestrations
```

---

## ✅ Checklist: Complete Backup

Voor complete, werkende restore op andere computer:

- [ ] HavunCore source code (H:\HavunCore-Backup-20251118\)
- [ ] .env file backup (met HAVUN_VAULT_KEY!)
- [ ] storage/vault/secrets.encrypted.json
- [ ] storage/orchestrations/*.json (indien actief)
- [ ] Andere projecten (HavunAdmin, Herdenkingsportaal, etc.)
- [ ] MCP server (havun-mcp)

---

## 🔄 Periodieke Backup Strategie

### Dagelijks (automatisch of handmatig)
```bash
# Update bestaande backup
xcopy /E /I /H /Y D:\GitHub\HavunCore H:\HavunCore-Backup-Latest

# Backup .env
copy D:\GitHub\HavunCore\.env H:\env-backup-latest.txt
```

### Wekelijks (gedateerd)
```bash
# Nieuwe backup met datum
xcopy /E /I /H D:\GitHub\HavunCore H:\HavunCore-Backup-%date:~-4,4%%date:~-10,2%%date:~-7,2%
```

### Bij Grote Changes
Maak altijd backup voor/na:
- Nieuwe versie releases (v0.5.0, v0.6.0, etc.)
- Grote refactors
- Database schema changes
- Vault key rotatie

---

## 📞 Support

**Restore problemen?**

1. Check BACKUP-INFO.txt in de backup folder
2. Check git log: `git log --oneline`
3. Verify composer: `composer install`
4. Check PHP version: `php -v` (need 8.1+)

**Documentatie:**
- Alle .md files in backup
- `php artisan help havun:orchestrate`
- Git history beschikbaar in backup

---

## 🎯 Quick Restore Test

Test of backup werkt:

```bash
# Maak test directory
mkdir D:\Test\HavunCore-Restore-Test

# Restore
xcopy /E /I /H H:\HavunCore-Backup-20251118 D:\Test\HavunCore-Restore-Test

# Test
cd D:\Test\HavunCore-Restore-Test
composer install
php artisan list havun:

# Cleanup
rd /s /q D:\Test\HavunCore-Restore-Test
```

---

**Backup Status:** ✅ COMPLEET

**Alles is veilig opgeslagen op H: drive en klaar voor restore op elke computer!**
