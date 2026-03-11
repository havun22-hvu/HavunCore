# Project: HavunCore Webapp

**URL:** http://localhost:8000 (lokaal) / https://havuncore.havun.nl (server)
**Type:** Node.js/Express backend + frontend webapp
**Doel:** Command Center voor alle Havun projecten op D:/GitHub/
**Status:** In ontwikkeling — fundering staat, nu naar functionele orchestratie

## Wat is het?

Een dynamische workspace manager die fungeert als cockpit voor 500+ bestanden verdeeld over meerdere projecten. Geen statische tool — elk project heeft eigen tab, context en terminal.

## Architectuur

```
D:/GitHub/[project]/          ← projectroot per tab
        ↓
backend/ (Node.js poort 5175) ← API + AI hybride
        ↓
frontend (poort 8000)         ← Command Center UI
```

## Core Features

### 1. Dynamic Workspace (Tabs)
- **Project Browser** — opent mappen van `D:/GitHub/` als tab
- **State Persistence** — geopende tabs + actief project in `localStorage`
- **Context Isolation** — elke tab heeft eigen `projectRoot`; backend past `cwd` aan
- **Git Clone** — via Project Browser → genereert `git clone` voorstel als TerminalCard (cyaan) → na clone auto-tab

### 2. AI Strategie (Hybride)

| Laag | Tool | Kosten | Doel |
|------|------|--------|------|
| **RAG/Search** | Ollama (Command-R, lokaal) | Gratis | SQLite doorzoeken → top 10 fragmenten |
| **Generatie** | Claude API (`CLAUDE_MASTER_KEY`) | Betaald | AI-antwoorden in webapp |

**Regel:** Ollama filtert ALTIJD eerst (15k → ~2k tokens) voordat data naar Claude API gaat.

**Opmerking:** Gebruiker werkt primair in VS Code + Claude Max. Webapp API = fallback/op-reis optie.

### 3. UI Componenten

- **TerminalCard (cyaan)** — terminal output, voorstellen voor bash-commando's
- **FileCard** — bestand bekijken/wijzigen voorstellen (`propose_file_edit`)
- **Token Monitor** — visuele indicator geschat token-verbruik + kosten per AI-interactie
- **"Open in VS Code" knop** (optioneel) — opent map in lokale VS Code

### 4. SSH Bridge
- Backend voorbereid voor SSH-commando's naar Hetzner server (188.245.159.115)
- Use cases: `git pull`, status-checks, deploy triggers
- Security: geen destructieve acties zonder UI-goedkeuring

## Environment Variables (backend)

```env
CLAUDE_MASTER_KEY=sk-ant-api03-...   # HavunCore API key (webapp AI)
PROJECT_ROOT_DEFAULT=D:/GitHub/
ANTHROPIC_API_KEY=                   # zelfde als CLAUDE_MASTER_KEY of alias
HAVUNCORE_DB_PATH=database/doc_intelligence.sqlite
PORT=5175
```

> ⚠️ `CLAUDE_MASTER_KEY` ≠ botsen met andere keys in andere projecten — webapp-only.

## Security Regels

- Geen destructieve acties (delete, force-push, drop) zonder goedkeuring via UI
- TerminalCards tonen voorstellen — gebruiker keurt goed
- FileCards tonen diff — gebruiker bevestigt

## API Onafhankelijkheid (KRITIEK)

> De basisfuncties werken ALTIJD zonder API-key:
> - Tabs wisselen
> - Mappen browsen
> - Terminal gebruiken
> - Git clonen

AI is een extra laag, geen vereiste voor de core.

## Gerelateerde Docs

- `docs/internal/architecture.md` — SQLite structuur
- `docs/internal/context-filter-flow.md` — Command-R → Claude flow
- `backend/src/routes/orchestrate.js` — hybride AI route (`POST /api/intelligent`)
- `backend/src/app.js` — Express server

---

*Laatste update: 11 maart 2026 — initiële architectuur handover (Gemini → HavunCore)*
