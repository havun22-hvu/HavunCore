# Werkzaamheden Overzicht

> **Doel:** Kort overzicht voor urenregistratie (belastingen). Bijwerken per dag.

**Datum:** 2026-03-10

---

## Kort overzicht voor urenregistratie (belastingen)

HavunCore: ontwikkeling AI-systeem gekoppeld aan kennisbank (Doc Intelligence): vraag → zoeken in documenten (embeddings/SQLite) → antwoord via Ollama (Command-R). Node-backend uitgebreid: hybrid flow (context filteren met Ollama, antwoord via Claude). Documentatie en start-workflow opgeschoond (rittenregistratie verwijderd).

---

## Detail (optioneel)

### AI-systeem + kennisbank + Ollama (hoofdlevering)

| Wat | Waar |
|-----|------|
| **HavunAIBridge (PHP)** | Vraag → PDO op `doc_intelligence.sqlite` (tabel `doc_embeddings`) → cosine similarity → Ollama (Command-R). Script: `scripts/HavunAIBridge.php`. System prompt, 10 min timeout, `num_ctx` 24576, foutanalyse bij falen, progress [1/3][2/3][3/3]. |
| **Kennisbank / Doc Intelligence** | Zelfde SQLite `database/doc_intelligence.sqlite` als bron. Index: `php artisan docs:index all --force`. Embeddings in `doc_embeddings`; HavunAIBridge en Node-backend gebruiken deze data. |
| **Node backend – hybrid flow** | Express + SQLite (`HAVUNCORE_DB_PATH` / `database/doc_intelligence.sqlite`). Route `POST /api/intelligent`: top 15 uit `doc_embeddings` → Command-R filtert context → Claude (Anthropic Sonnet) antwoordt. `backend/src/app.js`, `backend/src/routes/orchestrate.js`. |
| **Documentatie** | `docs/kb/reference/havun-ai-bridge.md`, `docs/internal/context-filter-flow.md`, `docs/internal/architecture.md`. INDEX.md en OVERZICHT.md bijgewerkt met HavunAIBridge + hybrid flow. |

### Overig

| Wat | Waar |
|-----|------|
| Rittenregistratie uit start-flow | HavunCore + 6 projecten: `.claude/commands/start.md`, `smallwork.md`, `runbooks/sync-start-command.md`. |
| Werkzaamheden-overzicht | Dit document (alleen vandaag); gelinkt vanuit INDEX.md en OVERZICHT.md. |

---

## Uren

- **Vandaag:** onderbouw met dit overzicht; invullen in `urenregistratie-2026.csv` (Datum;Uren;Project;Onderdeel). Project o.a. **HavunCore**, onderdeel o.a. AI-systeem / kennisbank / Ollama.
- **Detail/maand:** [reference/urenregistratie-2026.md](reference/urenregistratie-2026.md)
