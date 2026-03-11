# Plan: Qdrant Vector DB Migratie

> **Status:** On hold — uitvoeren als KB > ~5000 bestanden
> **Beslissing:** Blijf bij SQLite zolang performance geen probleem is

## Waarom SQLite nu volstaat

- Huidige KB: ~1000 bestanden (973 embeddings)
- Hardware: 64 GB RAM — brute-force cosine in JS is raak
- Minder bewegende onderdelen = snellere ontwikkeling

## Waarom Qdrant later beter is

SQLite + JS brute-force laadt alle embeddings in geheugen per query.
Qdrant gebruikt ANN-indexering (Approximate Nearest Neighbor) — orders sneller bij 5000+ bestanden.

Voorbeeld:
- "opslag-logica" vindt ook "repositories" en "persistence"
- Zonder het woord "opslag" in de tekst te hoeven hebben

## Implementatiestappen (één dag werk)

### 1. Qdrant opstarten (Windows binary)

```bash
# Download qdrant.exe van https://github.com/qdrant/qdrant/releases
# Start als lokaal proces:
./qdrant.exe --config config.yaml
# Draait op http://localhost:6333
# Data in D:/Qdrant/storage/
```

`config.yaml`:
```yaml
storage:
  storage_path: D:/Qdrant/storage
service:
  host: 127.0.0.1
  http_port: 6333
```

### 2. PHP DocIndexer aanpassen

Na SQLite upsert: ook naar Qdrant sturen.

```php
// Na updateOrCreate in DocIndexer::indexFile()
if (is_array($embedding) && isset($embedding[0])) {
    $this->upsertQdrant($project, $relativePath, $embedding);
}

protected function upsertQdrant(string $project, string $filePath, array $vector): void
{
    Http::post('http://localhost:6333/collections/havuncore_kb/points', [
        'points' => [[
            'id'      => crc32($project . $filePath), // stabiele integer ID
            'vector'  => $vector,
            'payload' => ['project' => $project, 'file_path' => $filePath],
        ]],
    ]);
}
```

### 3. ragService.js aanpassen

Vervang `_fetchCandidates()` door Qdrant search:

```js
// Huidig (SQLite brute-force):
async _fetchCandidates(project, limit) { /* SQLite query */ }

// Nieuw (Qdrant ANN):
async _fetchCandidates(queryEmbedding, project, limit) {
    const body = { vector: queryEmbedding, limit, with_payload: true };
    if (project) body.filter = { must: [{ key: 'project', match: { value: project } }] };
    const res = await fetch(`http://localhost:6333/collections/havuncore_kb/points/search`, {
        method: 'POST', body: JSON.stringify(body), headers: { 'Content-Type': 'application/json' }
    });
    return (await res.json()).result; // [{ id, score, payload }]
}
```

### 4. Bestaande embeddings migreren

Eenmalig script om SQLite embeddings naar Qdrant te laden:

```php
php artisan qdrant:migrate  // te bouwen bij migratie
```

## Trigger voor migratie

- KB groeit boven **5000 bestanden**, OF
- RAG search latency > **500ms** bij gemiddelde query

## Wat nu al goed staat (SQLite)

- `ragService.js` heeft gescheiden `_fetchCandidates()` methode → alleen die vervangen
- PHP indexer slaat `embedding_model` op → weten welke records neural zijn
- nomic-embed-text 768-dim → zelfde model voor Qdrant, geen herindexering nodig
