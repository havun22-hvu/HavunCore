# 📌 Claude Code Instructies voor HavunCore

**Project:** HavunCore - Shared Services Package
**Type:** Composer package (havun/core)

---

## 🔗 Shared Context

**BELANGRIJK:** Lees altijd eerst de shared context:
```
D:\GitHub\havun-mcp\PROJECT-CONTEXT.md
```

Dit bestand bevat:
- Overzicht van alle Havun clients en projecten
- Memorial reference systeem uitleg
- Cross-project dependencies
- Recente wijzigingen

---

## 🎯 Project Specifieke Info

**Wat is HavunCore?**
- Centrale Composer package voor gedeelde services
- Gebruikt door: Herdenkingsportaal, HavunAdmin, IDSee
- Versie: 0.1.0-dev

**Services:**
- ✅ `MemorialReferenceService` - Memorial UUID → 12 char reference
- ✅ `MollieService` - Mollie payment integration
- ⏳ `BunqService` - TODO
- ⏳ `GmailService` - TODO

---

## 📚 Documentatie

- `README.md` - Quick start guide
- `SETUP.md` - Installation & configuration
- `API-REFERENCE.md` - Complete API docs
- `INTEGRATION-GUIDE.md` - Integration examples
- `ARCHITECTURE.md` - Design decisions
- `MCP-SETUP.md` - MCP planning

---

## 🔧 Development Workflow

**Wijzigingen maken:**
1. Edit code in HavunCore
2. Commit + push
3. `composer update havun/core` in consuming projects

**Testen:**
```bash
# In dependent project (Herdenkingsportaal, HavunAdmin):
composer update havun/core
php artisan config:clear
php artisan cache:clear
```

---

## 💡 Bij vragen over andere projecten

**Herdenkingsportaal:**
→ Lees: `D:\GitHub\Herdenkingsportaal\CLAUDE-INSTRUCTIONS.md`

**HavunAdmin:**
→ Lees: `D:\GitHub\HavunAdmin\CLAUDE-INSTRUCTIONS.md`

**Shared context:**
→ Lees altijd: `D:\GitHub\havun-mcp\PROJECT-CONTEXT.md`
