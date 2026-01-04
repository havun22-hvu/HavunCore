# Doc Intelligence System - Setup Guide

> Setup instructies voor het Doc Intelligence systeem

## Vereisten

- PHP 8.2+
- Composer
- SQLite (standaard beschikbaar)

## Stap 1: Database aanmaken

De Doc Intelligence system gebruikt een aparte SQLite database.

```bash
cd D:\GitHub\HavunCore

# Maak de SQLite database file aan
touch database/doc_intelligence.sqlite

# Of op Windows:
type nul > database\doc_intelligence.sqlite
```

## Stap 2: Migraties draaien

```bash
cd D:\GitHub\HavunCore

# Run de migraties voor de doc_intelligence connectie
php artisan migrate --database=doc_intelligence
```

## Stap 3: Initiële indexering

Indexeer alle MD files van alle projecten:

```bash
# Indexeer alle projecten
php artisan docs:index all

# Of één specifiek project
php artisan docs:index herdenkingsportaal
```

## Stap 4: Issue detectie draaien

Na indexering, detecteer issues:

```bash
# Detecteer issues in alle projecten
php artisan docs:detect

# Of met indexering in één commando
php artisan docs:detect --index

# Of voor één project
php artisan docs:detect herdenkingsportaal
```

## Dagelijks gebruik

### Bij /start van een Claude sessie

Het systeem checkt automatisch op issues:

```bash
php artisan docs:issues --project=[project]
```

### Bij /end van een Claude sessie

Indexeer de wijzigingen:

```bash
php artisan docs:index [project]
php artisan docs:detect [project]
```

## Beschikbare commando's

| Commando | Beschrijving |
|----------|--------------|
| `php artisan docs:index [project]` | Indexeer MD files |
| `php artisan docs:index all` | Indexeer alle projecten |
| `php artisan docs:detect [project]` | Detecteer issues |
| `php artisan docs:issues [project]` | Toon open issues |
| `php artisan docs:issues --resolve=ID` | Los issue op |
| `php artisan docs:issues --ignore=ID` | Negeer issue |
| `php artisan docs:search "query"` | Zoek in docs |

## Troubleshooting

### "Database not found"

```bash
# Controleer of de database file bestaat
ls database/doc_intelligence.sqlite

# Zo niet, maak aan:
touch database/doc_intelligence.sqlite

# En run migraties opnieuw:
php artisan migrate --database=doc_intelligence
```

### "Table not found"

```bash
# Run migraties
php artisan migrate --database=doc_intelligence
```

### "No results found"

```bash
# Herindexeer met force flag
php artisan docs:index all --force
```

## Architectuur

```
HavunCore/
├── database/
│   └── doc_intelligence.sqlite      ← SQLite database
│
├── app/
│   ├── Models/DocIntelligence/
│   │   ├── DocEmbedding.php         ← Document + embedding
│   │   ├── DocIssue.php             ← Gevonden issues
│   │   └── DocRelation.php          ← Relaties tussen docs
│   │
│   ├── Services/DocIntelligence/
│   │   ├── DocIndexer.php           ← Indexeer service
│   │   └── IssueDetector.php        ← Issue detectie
│   │
│   └── Console/Commands/
│       ├── DocIndexCommand.php
│       ├── DocDetectIssuesCommand.php
│       ├── DocIssuesCommand.php
│       └── DocSearchCommand.php
│
└── config/
    └── database.php                  ← doc_intelligence connectie
```
