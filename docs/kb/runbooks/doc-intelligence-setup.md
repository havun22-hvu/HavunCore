# Doc Intelligence System - Setup & Gebruikshandleiding

> Volledig overzicht van het Doc Intelligence systeem: vector embeddings, code-indexering, structuur-analyse en auto-sync.

## Wat is het?

Een AI-gestuurde kennisbank die alle MD docs EN code van 13 Havun projecten indexeert met Ollama vector embeddings. Vergelijkbaar met Cursor's codebase indexing, maar cross-project en met semantisch zoeken.

## Vereisten

- PHP 8.2+
- SQLite (standaard beschikbaar)
- **Ollama** (lokaal draaiend op poort 11434) met `nomic-embed-text` model
- Fallback: TF-IDF als Ollama niet beschikbaar is

## Setup

### Stap 1: Database aanmaken

```bash
cd D:\GitHub\HavunCore

# Windows:
type nul > database\doc_intelligence.sqlite

# Linux:
touch database/doc_intelligence.sqlite
```

### Stap 2: Migraties draaien

```bash
php artisan migrate --database=doc_intelligence
```

### Stap 3: Ollama model installeren

```bash
ollama pull nomic-embed-text
```

### Stap 4: Eerste indexering

```bash
# Indexeer alles (MD + code) voor alle projecten
php artisan docs:index all --force

# Genereer structuur-overzichten
php artisan docs:structure all --force

# Detecteer issues
php artisan docs:detect --index
```

## Beschikbare Commando's

| Commando | Beschrijving |
|----------|--------------|
| `docs:index [project]` | Indexeer MD + code bestanden |
| `docs:index [project] --no-code` | Alleen MD bestanden |
| `docs:index [project] --force` | Forceer herindexering |
| `docs:structure [project]` | Genereer structuur-overzicht (models, controllers, routes, etc.) |
| `docs:watch` | Auto-sync: detecteer changes en herindexeer (continu) |
| `docs:watch --once` | Enkele sync-cyclus |
| `docs:watch --interval=60` | Interval in seconden (default: 30) |
| `docs:search "query"` | Semantisch zoeken in alle docs |
| `docs:search "query" --project=X` | Zoeken binnen 1 project |
| `docs:search "query" --type=model` | Zoeken op file type (docs, model, controller, service, route, migration, config, view, command, middleware, test, support, code, structure) |
| `docs:detect [project]` | Detecteer issues (duplicaten, inconsistenties) |
| `docs:issues [project]` | Toon open issues |
| `docs:issues --resolve=ID` | Markeer issue als opgelost |
| `docs:issues --ignore=ID` | Negeer issue |

## Wat wordt geindexeerd?

### MD Documenten
Alle `.md` bestanden in elk project (excl. vendor, node_modules, .git, storage).

### Code Bestanden (nieuw, maart 2026)
Code wordt niet raw opgeslagen maar als gestructureerde samenvatting:

| Bestandstype | Wat wordt geextraheerd |
|-------------|----------------------|
| **PHP classes** | Namespace, class naam, methods (signatures), properties, constants, Eloquent relations, fillable, casts |
| **Routes** | Alle Route::get/post/etc definities met middleware en namen |
| **Migrations** | Tabel naam, kolommen met types en modifiers |
| **Config** | Config keys en comments |
| **Blade templates** | @extends, @section, @component, @include, DO NOT REMOVE comments |
| **JS/TS** | Imports, exports, functies, classes |

**Gescande directories:**
`app/Models`, `app/Http/Controllers`, `app/Http/Middleware`, `app/Services`, `app/Contracts`, `app/DTOs`, `app/Enums`, `app/Events`, `app/Jobs`, `app/Console/Commands`, `app/Traits`, `app/Exceptions`, `config`, `routes`, `database/migrations`

Ondersteunt ook geneste layout (bijv. `laravel/app/Models` voor JudoToernooi).

### Structuur-index (nieuw, maart 2026)
Per project wordt een structureel overzicht gegenereerd met:
- Alle models (met relaties, method count, LOC)
- Alle controllers (met method count, LOC)
- Alle services, middleware, enums, commands
- Aantal migrations
- Alle routes (method, URI, bestand)
- Composer & NPM packages
- Laravel/PHP versies

### Auto-sync (nieuw, maart 2026)
`docs:watch` draait als achtergrondproces en:
- Detecteert gewijzigde bestanden via SHA256 hash vergelijking
- Herindexeert alleen gewijzigde bestanden (delta)
- Verwijdert orphaned entries (bestanden die niet meer bestaan)
- Configureerbaar interval (default 30 sec)

## Embeddings

| Eigenschap | Waarde |
|-----------|--------|
| **Model** | nomic-embed-text (Ollama) |
| **Dimensies** | 768 |
| **Grootte** | 274MB |
| **Max tokens** | 8192 |
| **Fallback** | TF-IDF (woordfrequenties, 100 features) |
| **Similarity** | Cosine similarity (berekend in PHP) |

**Env variabelen:**
- `OLLAMA_URL` (default: `http://127.0.0.1:11434`)
- `OLLAMA_EMBEDDING_MODEL` (default: `nomic-embed-text`)

## Dagelijks Gebruik

### Bij /start van een sessie
```bash
php artisan docs:issues [project]
```

### Bij /end van een sessie
```bash
php artisan docs:index [project]
php artisan docs:detect [project]
```

### Continu draaien (optioneel)
```bash
php artisan docs:watch --interval=60
```

## API Endpoints

| Endpoint | Method | Beschrijving |
|----------|--------|-------------|
| `/api/docs/search?q=query` | GET | Semantisch zoeken (optioneel: `&project=X&type=model&limit=5`) |
| `/api/docs/health` | GET | Systeemstatus: indexed files, embeddings, Ollama status, file types |
| `/api/docs/stats` | GET | Statistieken per project |
| `/api/docs/issues` | GET | Open issues (optioneel: `&project=X&type=duplicate`) |
| `/api/docs/read?project=X&path=Y` | GET | Lees specifiek document |

Alle endpoints vereisen Bearer token (`config('services.doc_intelligence.api_token')`) of `X-KB-Token` header.

## File Types

Elk geïndexeerd bestand krijgt een `file_type` label:

| Type | Beschrijving |
|------|-------------|
| `docs` | MD documenten |
| `model` | Eloquent models (`app/Models/`) |
| `controller` | HTTP controllers (`app/Http/Controllers/`) |
| `service` | Service classes (`app/Services/`) |
| `middleware` | HTTP middleware |
| `command` | Artisan commands |
| `migration` | Database migrations |
| `route` | Route definities |
| `config` | Config bestanden |
| `view` | Blade templates |
| `test` | Test bestanden |
| `support` | Enums, DTOs, Events, Jobs, Traits, Exceptions, Contracts |
| `structure` | Auto-generated structuur-overzicht |
| `code` | Overige code bestanden |

## Architectuur

```
HavunCore/
├── database/
│   └── doc_intelligence.sqlite         <- SQLite database
│
├── app/
│   ├── Models/DocIntelligence/
│   │   ├── DocEmbedding.php            <- Document + embedding
│   │   ├── DocIssue.php                <- Gevonden issues
│   │   └── DocRelation.php             <- Relaties tussen docs
│   │
│   ├── Services/DocIntelligence/
│   │   ├── DocIndexer.php              <- MD + code indexering
│   │   ├── StructureIndexer.php        <- Structuur-analyse per project
│   │   └── IssueDetector.php           <- Issue detectie
│   │
│   ├── Http/Controllers/Api/
│   │   └── DocIntelligenceController.php <- API endpoints (search, health, stats, issues, read)
│   │
│   └── Console/Commands/
│       ├── DocIndexCommand.php         <- docs:index
│       ├── DocStructureCommand.php     <- docs:structure
│       ├── DocWatchCommand.php         <- docs:watch (auto-sync)
│       ├── DocSearchCommand.php        <- docs:search
│       ├── DocDetectIssuesCommand.php  <- docs:detect
│       └── DocIssuesCommand.php        <- docs:issues
│
└── config/
    └── database.php                     <- doc_intelligence connectie
```

## Troubleshooting

### "Database not found"
```bash
touch database/doc_intelligence.sqlite
php artisan migrate --database=doc_intelligence
```

### "Ollama niet bereikbaar"
```bash
# Check of Ollama draait
curl http://127.0.0.1:11434/api/tags

# Start Ollama
ollama serve
```
Systeem valt automatisch terug op TF-IDF als Ollama onbereikbaar is.

### "No results found"
```bash
php artisan docs:index all --force
php artisan docs:structure all --force
```

## Statistieken (14 maart 2026)

| Project | MD docs | Code files | Structuur | Totaal |
|---------|---------|-----------|-----------|--------|
| HavunCore | 99 | 109 | 1 | 209 |
| JudoToernooi | 87 | 304 | 1 | 392 |
| HavunAdmin | 89 | 218 | 1 | 308 |
| + 10 andere projecten | ~200 | ~400 | 10 | ~610 |
| **Totaal** | | | | **~1753** |

---

*Laatste update: 17 maart 2026*
