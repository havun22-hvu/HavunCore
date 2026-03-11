# HavunAIBridge – Vraag → KB context → Ollama

> Script: `scripts/HavunAIBridge.php`  
> Doel: gebruikersvraag omzetten in zoekopdracht op de vector-DB, context ophalen, en naar lokale Ollama sturen (POST met system prompt).

## Opbouw

- **SQLite/vector-connectie:** Direct PDO naar dezelfde database als het /kb-commando: `database/doc_intelligence.sqlite`, tabel `doc_embeddings`. Geen Laravel Eloquent voor de zoekstap.
- **Cosine similarity:** De brug berekent zelf de vergelijking: vraag wordt omgezet in een word-frequency-embedding (zelfde algoritme als DocIndexer), daarna voor elke rij uit `doc_embeddings` de cosine similarity met de opgeslagen `embedding` (JSON), sorteren op relevantie, top N gebruiken als context.
- **Ollama JSON payload:** POST naar `http://127.0.0.1:11434/api/generate` met `model`, `stream: false`, **`system`** (de niet-passieve instructies) en **`prompt`** (KB-context + user question in Markdown).
- **Foutafhandeling:** Bij falen (PDO, Ollama) toont het script een uitgebreide foutanalyse (oorzaken, acties, stack trace).

## Vereisten

- HavunCore doc Intelligence geïndexeerd: `php artisan docs:index all --force`
- Ollama draait lokaal op poort 11434; model beschikbaar (bijv. `ollama pull llama3`)

## Gebruik

```bash
cd D:\GitHub\HavunCore
php scripts/HavunAIBridge.php "Jouw vraag hier"
# of vraag via stdin
echo "Hoe deploy ik?" | php scripts/HavunAIBridge.php
```

## Configuratie

- **OLLAMA_URL** (env): default `http://127.0.0.1:11434`
- **OLLAMA_MODEL** (env): default `llama3`
- In het script: `CONTEXT_LIMIT_CHARS`, `CONTEXT_PER_DOC_CHARS`, `SEARCH_LIMIT`

## Gerelateerd

- `/kb` command: `.claude/commands/kb.md` (zoekt via `docs:search`, geen Ollama)
- Doc Intelligence: `docs/kb/runbooks/doc-intelligence-setup.md`, `docs/kb/projects/doc-intelligence-system.md`
- Vector-DB: `doc_embeddings` (migratie `2026_01_04_000001_create_doc_embeddings_table.php`), `App\Services\DocIntelligence\DocIndexer`
