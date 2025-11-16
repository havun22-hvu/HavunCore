# MCP Server Setup voor HavunCore

**Datum:** 16 november 2025
**Status:** In Planning

---

## 🎯 Doel

MCP (Model Context Protocol) servers configureren voor **Claude Code** (VS Code) om:
1. Havun clients te beheren en op te vragen
2. Messages tussen projecten te delen
3. Persistent geheugen tussen Claude sessies

---

## 📋 Test Commando's die moeten werken

### In HavunCore chat:
```
List all Havun clients
```
**Verwacht resultaat:** havun + personal

### In Herdenkingsportaal chat:
```
Show my messages
```
**Verwacht resultaat:** Message van HavunCore over architectuur

---

## 🔧 Huidige Situatie

### Locatie
- **MCP data directory:** `D:\GitHub\havun-mcp\`
- **Status:** Alleen README.md aanwezig
- **Repository:** HavunCore @ `D:\GitHub\HavunCore\`

### Claude Interface
**✅ Claude Code** (VS Code terminal CLI)
- Dit is wat we gebruiken
- Config via `.vscode/settings.json` of VS Code User Settings
- Format:
  ```json
  {
    "mcpServers": {
      "server-name": {
        "command": "...",
        "args": [...],
        "env": {...}
      }
    }
  }
  ```

**❌ Claude Desktop** (NIET relevant!)
- Standalone app
- Config via `claude_desktop_config.json`
- Gebruiken we NIET

---

## 🤔 Open Vraag voor Morgen

**Wat voor MCP server(s) willen we?**

### Optie 1: Memory MCP (Standaard)
**Wat:**
- Persistent geheugen tussen Claude sessies
- Per project gescheiden (havuncore-memory.json, herdenkingsportaal-memory.json, etc.)

**Tools:**
- `memory_store` - Info opslaan
- `memory_retrieve` - Info ophalen
- `memory_delete` - Info verwijderen

**Config voor Claude Code:**
```json
{
  "mcpServers": {
    "havuncore-memory": {
      "command": "npx",
      "args": ["-y", "@modelcontextprotocol/server-memory"],
      "env": {
        "MEMORY_FILE_PATH": "D:\\GitHub\\havun-mcp\\havuncore-memory.json"
      }
    }
  }
}
```

**✅ Pro:**
- Werkt out-of-the-box
- Geen custom code nodig

**❌ Con:**
- Geen "List all Havun clients" tool
- Geen structured data queries

---

### Optie 2: Custom Havun MCP Server
**Wat:**
- Zelf gebouwde MCP server met custom tools
- TypeScript/JavaScript server

**Custom Tools:**
- `listClients` - Lijst van Havun clients tonen
- `getMessages` - Messages ophalen per client/project
- `storeMessage` - Message opslaan voor een project
- `getSharedKnowledge` - Shared kennis ophalen

**Bestanden:**
```
D:\GitHub\havun-mcp\
├── package.json
├── src/
│   ├── index.ts           ← Main MCP server
│   ├── tools/
│   │   ├── clients.ts     ← listClients tool
│   │   └── messages.ts    ← message tools
├── build/                 ← Compiled JS
│   └── index.js
└── data/
    ├── clients.json       ← Client data
    └── messages.json      ← Message data
```

**Config voor Claude Code:**
```json
{
  "mcpServers": {
    "havun": {
      "command": "node",
      "args": ["D:\\GitHub\\havun-mcp\\build\\index.js"]
    }
  }
}
```

**✅ Pro:**
- Exact de tools die we willen
- Structured data model
- Type-safe met TypeScript

**❌ Con:**
- Moet gebouwd worden
- Maintenance nodig

---

### Optie 3: Beide! 🎯
Memory MCP voor basis geheugen + Custom Havun MCP voor structured data

**Config:**
```json
{
  "mcpServers": {
    "havuncore-memory": {
      "command": "npx",
      "args": ["-y", "@modelcontextprotocol/server-memory"],
      "env": {
        "MEMORY_FILE_PATH": "D:\\GitHub\\havun-mcp\\havuncore-memory.json"
      }
    },
    "havun": {
      "command": "node",
      "args": ["D:\\GitHub\\havun-mcp\\build\\index.js"]
    }
  }
}
```

---

## 📝 Sessie Notes (16 nov 2025)

### Belangrijke Learnings
1. **Claude Desktop ≠ Claude Code**
   - `.mcp.json` is voor Claude Desktop
   - `.vscode/settings.json` is voor Claude Code
   - README in havun-mcp had Claude Desktop config → moet aangepast

2. **MCP Server Types**
   - Standard servers (Memory, Filesystem, etc.) via npx
   - Custom servers (eigen gebouwd) via node

3. **Test Setup**
   - Na MCP config: VS Code NIET herstarten nodig (werkt runtime)
   - Tools verschijnen met `mcp__` prefix
   - Bijvoorbeeld: `mcp__havun__listClients`

### Volgende Stappen
1. ✅ Deze notes vastleggen
2. ⏳ Kiezen: Memory / Custom / Beide?
3. ⏳ MCP server(s) configureren
4. ⏳ Testen met test commando's
5. ⏳ Documentatie updaten

---

## 🚀 Quick Start (na beslissing morgen)

### Memory MCP alleen
```bash
# 1. Create .vscode/settings.json in HavunCore
# 2. Add Memory MCP config
# 3. Test: "store this in memory: HavunCore uses PHP 8.2"
```

### Custom Havun MCP
```bash
# 1. cd D:\GitHub\havun-mcp
# 2. npm init -y
# 3. Setup TypeScript MCP server
# 4. Build & configure in .vscode/settings.json
# 5. Test: "List all Havun clients"
```

---

**📅 Created:** 16 november 2025 - 03:00
**🔄 Last Updated:** 16 november 2025 - 03:00
**👤 Author:** Claude Code sessie met @henkvu
