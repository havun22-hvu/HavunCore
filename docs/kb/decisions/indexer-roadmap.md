# Roadmap: DocIndexer Optimalisaties

> Aanbevelingen van Gemini — verwerkt 2026-03-11
> Status: bijgewerkt 2026-03-17

## Architectuurprincipe

| Kenmerk | HavunCore (PHP) | havuncore-webapp (Node.js) |
|---------|-----------------|----------------------------|
| Rol | De Bibliothecaris (lezen & indexeren) | De Kapitein (beslissen & praten) |
| Data | Vult SQLite + embeddings | Leest SQLite + embeddings |
| AI | Genereert embeddings (Ollama) | Stuurt chat & API (Claude) |

**Kernregel:** HavunCore schrijft, Webapp leest. Nooit andersom.

---

## Item 1: WAL Mode (SQLite) — GEDAAN

**Status:** Geïmplementeerd in `DocIndexer::__construct()` (runtime PRAGMA)

```php
DB::connection('doc_intelligence')->statement('PRAGMA journal_mode=WAL');
DB::connection('doc_intelligence')->statement('PRAGMA synchronous=NORMAL');
```

---

## Item 2: Metadata Verrijking (file_type detectie) — GEDAAN

**Status:** Geïmplementeerd 2026-03-17

**Wat is gebouwd:**
- Migration: `file_type VARCHAR(20)` kolom op `doc_embeddings`
- `DocIndexer::detectFileType()` — detecteert 14 types op basis van pad:
  `docs`, `model`, `controller`, `middleware`, `service`, `command`, `migration`, `route`, `config`, `view`, `test`, `support`, `structure`, `code`
- `DocEmbedding::scopeOfType()` — query scope voor filtering
- `docs:search --type=X` — CLI filter
- `GET /api/docs/search?type=X` — API filter
- `GET /api/docs/health` — toont `by_type` breakdown

**Voorbeeld:**
```bash
php artisan docs:search "authentication" --type=model --limit=5
```

---

## Item 3: Health Check API — GEDAAN

**Status:** Geïmplementeerd 2026-03-17

**Endpoint:** `GET /api/docs/health` (Bearer token vereist)

**Response:**
```json
{
  "status": "healthy",
  "indexed_files": 1779,
  "neural_embeddings": 1265,
  "tfidf_embeddings": 514,
  "last_indexed_at": "2026-03-17T...",
  "open_issues": 88,
  "db_size_mb": 48.2,
  "ollama_available": true,
  "by_project": { "havuncore": 216, ... },
  "by_type": { "docs": 102, "model": 16, ... }
}
```

---

## Item 4: Smart Watcher (Auto Re-index) — GEDAAN

**Status:** Geïmplementeerd als `docs:watch` command

**Gebruik:**
```bash
php artisan docs:watch              # continu, elke 30 sec
php artisan docs:watch --once       # enkele cyclus
php artisan docs:watch --interval=60 # elke 60 sec
```

**Werking:** Vergelijkt SHA256 hashes, herindexeert alleen gewijzigde bestanden, cleanup orphaned entries.

---

## Item 5: IssueDetector verbeteringen — GEDAAN

**Status:** Geïmplementeerd 2026-03-17

**Problemen opgelost:**
- Cross-project duplicate detection genereerde 5928 false positives (shared files als `.claude/commands/`)
- Code files (models, controllers) werden als duplicates gemarkeerd door structurele gelijkenis

**Oplossing:**
- Duplicate detection draait nu alleen BINNEN hetzelfde project (niet cross-project)
- Shared file patterns worden automatisch overgeslagen (`.claude/commands/`, `_structure/`, `CLAUDE.md`)
- Code file types worden uitgesloten van duplicate detection (model, controller, migration, etc.)
- Threshold verhoogd van 0.85 naar 0.90

**Resultaat:** Van 5928 naar 6 echte duplicates voor havuncore.

---

## Status

- [x] WAL mode (2026-03-11)
- [x] Health Check API (2026-03-17)
- [x] Metadata file_type (2026-03-17)
- [x] Smart Watcher (2026-03-16)
- [x] IssueDetector fix (2026-03-17)

## Volgende stappen (optioneel)

- [ ] Qdrant migratie — wanneer KB > 5000 bestanden (zie `qdrant-migration-plan.md`)
- [ ] Frontend file_type filter in havuncore-webapp ChatInterface
- [ ] ragService.js integratie met file_type filter
