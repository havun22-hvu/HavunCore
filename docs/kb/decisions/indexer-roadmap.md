# Roadmap: DocIndexer Optimalisaties

> Aanbevelingen van Gemini — verwerkt 2026-03-11
> Prioriteit: na Webapp-interface

## Architectuurprincipe

| Kenmerk | HavunCore (PHP) | havuncore-webapp (Node.js) |
|---------|-----------------|----------------------------|
| Rol | De Bibliothecaris (lezen & indexeren) | De Kapitein (beslissen & praten) |
| Data | Vult SQLite + embeddings | Leest SQLite + embeddings |
| AI | Genereert embeddings (Ollama) | Stuurt chat & API (Claude) |

**Kernregel:** HavunCore schrijft, Webapp leest. Nooit andersom.

---

## Item 1: WAL Mode (SQLite) — KLEIN, HOGE PRIORITEIT

**Probleem:** Als de indexer schrijft, blokkeert hij de webapp die leest.
**Oplossing:** WAL (Write-Ahead Logging) — lezen en schrijven kunnen tegelijk.

**Wat al bestaat:** `config/database.php` heeft `doc_intelligence` SQLite connectie, geen WAL ingesteld.

**Fix:**
```php
// config/database.php
'doc_intelligence' => [
    'driver' => 'sqlite',
    'database' => database_path('doc_intelligence.sqlite'),
    'prefix' => '',
    'foreign_key_constraints' => true,
    'options' => [
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ],
],
```
Plus in een ServiceProvider of migration:
```sql
PRAGMA journal_mode=WAL;
PRAGMA synchronous=NORMAL;
```

---

## Item 2: Metadata Verrijking (file_type detectie) — MEDIUM

**Probleem:** RAG geeft alle bestanden terug zonder onderscheid.
**Oplossing:** Bij indexering herkennen: `controller`, `model`, `migration`, `view`, `docs`, `config`, `test`.

**Wat al bestaat:** `DocEmbedding` model heeft geen `file_type` kolom.

**Stappenplan:**
1. Migration: `file_type VARCHAR(20) NULL` toevoegen aan `doc_embeddings`
2. DocIndexer: detectielogica op basis van pad/extensie:
   ```php
   protected function detectFileType(string $path): string {
       if (str_contains($path, '/Controllers/')) return 'controller';
       if (str_contains($path, '/Models/'))      return 'model';
       if (str_contains($path, '/migrations/'))  return 'migration';
       if (str_contains($path, '/views/'))       return 'view';
       if (str_contains($path, 'docs/'))         return 'docs';
       if (preg_match('/\.(md|txt)$/', $path))   return 'docs';
       if (preg_match('/\.(env|yaml|json)$/', $path)) return 'config';
       if (str_contains($path, '/tests/'))       return 'test';
       return 'code';
   }
   ```
3. ragService.js: `options.fileType` filter doorgeven aan SQL query
4. Frontend: filter-UI in ChatInterface ("Zoek in: alle / docs / controllers / models")

---

## Item 3: Health Check API — KLEIN

**Probleem:** Webapp heeft geen manier om te weten of de indexer gezond is.
**Wat al bestaat:** `GET /api/docs/stats` geeft al per-project counts + issue stats.

**Uitbreiden naar:**
```json
GET /api/docs/health
{
  "status": "healthy",
  "indexed_files": 973,
  "neural_embeddings": 514,
  "last_indexed_at": "2026-03-11 22:30:00",
  "db_locked": false,
  "db_size_mb": 45.2,
  "by_project": { "havuncore": 187, "judotoernooi": 87, ... }
}
```

**Webapp:** `StatusView.jsx` toont groen vinkje als `status === "healthy"`.

---

## Item 4: Smart Watcher (Auto Re-index) — GROOT

**Probleem:** Handmatig `php artisan docs:index all` draaien na elke codewijziging.
**Oplossing:** Filesystem watcher die gewijzigde bestanden direct herindexeert.

**Opties:**
| Aanpak | Pro | Con |
|--------|-----|-----|
| PHP `inotify` extension | Geen extra tools | Alleen Linux |
| Laravel Queue + Horizon | Robuust, retry logic | Extra setup |
| Artisan command met `--watch` flag | Simpel | Polling (elke 30s) |
| Node.js `chokidar` in webapp | Al beschikbaar | Cross-project coupling |

**Aanbeveling:** `--watch` polling flag in `docs:index`:
```bash
php artisan docs:index all --watch --interval=30
```
Draait als achtergrondproces, checkt elke 30s welke bestanden gewijzigd zijn op basis van `file_modified_at`.

**Al aanwezig:** `content_hash` en `file_modified_at` in `doc_embeddings` — basis voor change detection is er.

---

## Volgorde van uitvoering

1. **WAL mode** — 30 min werk, direct betere stabiliteit
2. **Health Check API uitbreiden** — 1 uur, Webapp kan status tonen
3. **Metadata/file_type** — halve dag, betere RAG filtering
4. **Smart Watcher** — 1 dag, volledig automatisch

## Status

- [ ] WAL mode
- [ ] Health Check API
- [ ] Metadata file_type
- [ ] Smart Watcher
