# Tap 1: Context Filter (Command-R → Claude)

In de Node.js backend (webapp) zorgt deze flow ervoor dat Command-R (Ollama) de context eerst "voorkauwt". Daardoor stuur je geen duizenden dure, irrelevante tokens naar Claude.

**Implementatie:** `backend/src/routes/orchestrate.js` — route `POST /intelligent`.

## Hybrid Flow in orchestrate.js

- **Context Window Management:** 15k+ tekens lokaal laten filteren; alleen de "essence" gaat naar Claude.
- **Kostenbeheersing:** Lokale machine (64 GB RAM, Command-R) doet het voorbereidende werk; minder Anthropic-tokens.
- **Code Agent (Fase 4):** Claude kan vanuit deze route de `edit_file` tool aansturen als hij een bestand wil wijzigen.

```javascript
// STAP 1: Lokaal zoeken (Ranking) — top 15 uit doc_embeddings
const rawContext = await db.all(
    "SELECT content, file_path FROM doc_embeddings WHERE project = ? LIMIT 15", [project]
);

// STAP 2: Lokaal filteren met Command-R (64GB RAM power)
const filteredContext = await callOllamaFilter(instruction, rawContext);

// STAP 3: De 'Mastermind' (Claude) aanroepen
const finalResponse = await callClaudeWithFilteredContext(instruction, filteredContext);
```

## Stappen

| Stap | Wat | Waarom |
|------|-----|--------|
| **1** | `getRawContext(db, project)` | Haal top 15 uit `doc_embeddings` (zelfde SQLite als HavunAIBridge). Voor ranking op relevantie: similarity-search of HavunCore API. |
| **2** | `callOllamaFilter(instruction, rawContext)` | Command-R filtert lokaal: alleen ECHT relevante fragmenten blijven over. |
| **3** | `callClaudeWithFilteredContext(instruction, filteredContext)` | Claude krijgt alleen gefilterde context → scherper antwoord, minder tokens. Fase 4: Claude kan `edit_file` tool aansturen. |

## Implementatie (huidige code)

- **SQLite-koppeling** (`backend/src/app.js`): `sqlite3.Database(dbPath)` met `dbPath = process.env.HAVUNCORE_DB_PATH || path.join(__dirname, '../../database/doc_intelligence.sqlite')`. `app.locals.db = db` zodat routes dezelfde DB gebruiken als de PHP-indexer.
- **getRawContext(db, project):** Gebruikt `dbAll(db, sql, params)` (promise-wrapper om sqlite3 callbacks). Query: `SELECT content, file_path FROM doc_embeddings [WHERE project = ?] LIMIT 15`. Voor ranking op relevantie: later similarity-search of HavunCore API aanroepen.
- **callOllamaFilter(instruction, rawContext):** POST naar `http://127.0.0.1:11434/api/generate`, model `command-r`, system prompt om alleen relevante fragmenten te behouden, `options: { num_ctx: 24576, temperature: 0.2 }`.
- **callClaudeWithFilteredContext(instruction, filteredContext):** Anthropic SDK `anthropic.messages.create` met model `claude-3-5-sonnet-20241022`, max_tokens 4096, system "HavunCore Mastermind", één user message met gefilterde context + vraag. Return `msg.content[0].text`.

## Backend-start (SQLite + env)

In `backend/src/app.js` wordt dezelfde SQLite-database gekoppeld als de PHP-indexer:

- **HAVUNCORE_DB_PATH** (optioneel): pad naar `doc_intelligence.sqlite`; default: `backend/../../database/doc_intelligence.sqlite` (HavunCore-repo).
- **ANTHROPIC_API_KEY**: verplicht voor Stap 3 (Claude). Zet in `.env` of omgeving.

Start: `cd backend && npm install && npm start`. Luistert op `PORT` (default 8009). Route: `POST /api/intelligent` met body `{ "instruction": "...", "project": "havuncore" }`.

## Gerelateerd

- HavunAIBridge (PHP): `scripts/HavunAIBridge.php` – PDO + cosine + Ollama.
- Doc Intelligence API: `routes/api.php` (docs search), tabel `doc_embeddings`.
- Server backend: `havuncore.havun.nl` → `backend/` (node.js).
