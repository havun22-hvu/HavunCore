# Doc Intelligence System - Plan & Handleiding

> **Status:** Planning
> **Doel:** Slimme documentatie-beheer met automatische consistentie-checks
> **Locatie:** HavunCore (centraal voor alle projecten)

---

## 🎯 Waarom dit systeem?

### Huidige problemen:
- Claude begint te coderen zonder docs te lezen
- MD files zijn inconsistent tussen projecten
- Informatie staat dubbel of is verouderd
- Niemand houdt actief de docs bij
- Kennis gaat verloren tussen sessies

### Oplossing:
Een intelligent systeem dat:
1. Alle docs indexeert en doorzoekbaar maakt (semantic search)
2. Automatisch inconsistenties en duplicaten detecteert
3. Proactief vragen stelt om docs up-to-date te houden
4. Claude dwingt om docs-first te werken

---

## 🏗️ Architectuur

```
┌─────────────────────────────────────────────────────────────────┐
│                         HavunCore                                │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                 PostgreSQL + pgvector                     │   │
│  │                                                           │   │
│  │  doc_embeddings     doc_issues        doc_relations      │   │
│  │  ├── project        ├── project       ├── source_doc     │   │
│  │  ├── file_path      ├── issue_type    ├── target_doc     │   │
│  │  ├── content        ├── details       ├── relation_type  │   │
│  │  ├── embedding      ├── status        └── auto_detected  │   │
│  │  ├── content_hash   └── detected_at                      │   │
│  │  └── updated_at                                          │   │
│  └──────────────────────────────────────────────────────────┘   │
│                              │                                   │
│                              ▼                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                      API Endpoints                        │   │
│  │                                                           │   │
│  │  POST /api/docs/index      - Indexeer MD files           │   │
│  │  POST /api/docs/search     - Semantic search             │   │
│  │  GET  /api/docs/issues     - Openstaande issues          │   │
│  │  POST /api/docs/resolve    - Issue oplossen              │   │
│  │  GET  /api/docs/audit      - Volledige audit rapport     │   │
│  └──────────────────────────────────────────────────────────┘   │
│                              │                                   │
└──────────────────────────────┼───────────────────────────────────┘
                               │
         ┌─────────────────────┼─────────────────────┐
         ▼                     ▼                     ▼
  ┌─────────────┐       ┌─────────────┐       ┌─────────────┐
  │  Claude     │       │  Webapp     │       │  Scheduled  │
  │  /start     │       │  Dashboard  │       │  Audit      │
  │             │       │             │       │  (2x/week)  │
  └─────────────┘       └─────────────┘       └─────────────┘
```

---

## 📊 Database Schema

### Tabel: doc_embeddings
```sql
CREATE TABLE doc_embeddings (
    id SERIAL PRIMARY KEY,
    project VARCHAR(50) NOT NULL,           -- 'herdenkingsportaal', 'havunadmin', etc.
    file_path VARCHAR(500) NOT NULL,        -- 'docs/SPEC.md', '.claude/context.md'
    content TEXT NOT NULL,                   -- Volledige inhoud van het bestand
    content_hash VARCHAR(64) NOT NULL,       -- SHA256 hash voor wijzigingsdetectie
    embedding vector(1536),                  -- OpenAI/Claude embedding
    updated_at TIMESTAMP DEFAULT NOW(),
    last_indexed_at TIMESTAMP DEFAULT NOW(),

    UNIQUE(project, file_path)
);
```

### Tabel: doc_issues
```sql
CREATE TABLE doc_issues (
    id SERIAL PRIMARY KEY,
    project VARCHAR(50) NOT NULL,
    issue_type VARCHAR(50) NOT NULL,        -- 'inconsistent', 'duplicate', 'outdated', 'missing', 'broken_link'
    severity VARCHAR(20) DEFAULT 'medium',  -- 'low', 'medium', 'high'
    title VARCHAR(255) NOT NULL,
    details TEXT NOT NULL,                  -- JSON met details
    affected_files TEXT[],                  -- Array van betrokken bestanden
    suggested_action TEXT,                  -- Wat te doen
    status VARCHAR(20) DEFAULT 'open',      -- 'open', 'in_progress', 'resolved', 'ignored'
    detected_at TIMESTAMP DEFAULT NOW(),
    resolved_at TIMESTAMP,
    resolved_by VARCHAR(100)                -- 'user' of 'claude'
);
```

### Tabel: doc_relations
```sql
CREATE TABLE doc_relations (
    id SERIAL PRIMARY KEY,
    source_project VARCHAR(50) NOT NULL,
    source_file VARCHAR(500) NOT NULL,
    target_project VARCHAR(50) NOT NULL,
    target_file VARCHAR(500) NOT NULL,
    relation_type VARCHAR(50) NOT NULL,     -- 'references', 'duplicates', 'contradicts', 'extends'
    confidence DECIMAL(3,2),                -- 0.00 - 1.00
    auto_detected BOOLEAN DEFAULT true,
    details TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

---

## 🔍 Issue Types

| Type | Beschrijving | Voorbeeld |
|------|--------------|-----------|
| `inconsistent` | Zelfde info, verschillende waarden | Prijs X vs Prijs Y |
| `duplicate` | Zelfde info op meerdere plekken | Mollie setup in 2 bestanden |
| `outdated` | Lang niet bijgewerkt | STYLING.md > 90 dagen oud |
| `missing` | Functionaliteit zonder docs | Guest checkout niet gedocumenteerd |
| `broken_link` | Verwijzing naar niet-bestaand bestand | Link naar OLD-FLOW.md |
| `orphaned` | Doc zonder gerelateerde code | FEATURE.md maar feature verwijderd |

---

## 🖥️ Gebruikersinterfaces

### 1. Claude CLI (/start)

Bij elke sessie start:

```
> /start

✓ MD files gelezen (5 bestanden)
✓ Database geraadpleegd

⚠️ 2 openstaande doc issues:

┌─────────────────────────────────────────────────────────────┐
│ 1️⃣  INCONSISTENT: Pakket prijzen                            │
│                                                              │
│     SPEC.md regel 23:      "Standaard: [prijs A]"           │
│     HANDOVER.md regel 45:  "Standaard: [prijs B]"           │
│                                                              │
│     Welke is correct?                                        │
│     [A] Prijs A  [B] Prijs B  [C] Later oplossen            │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ 2️⃣  MISSING: Guest checkout                                 │
│                                                              │
│     Code bevat guest checkout functionaliteit               │
│     Geen documentatie gevonden                              │
│                                                              │
│     [A] Nu documenteren  [B] Later                          │
└─────────────────────────────────────────────────────────────┘

Jouw keuze:
```

### 2. Webapp Dashboard

```
┌─────────────────────────────────────────────────────────────┐
│  📊 Doc Intelligence Dashboard                               │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Filter: [Alle projecten ▼]  [Alle types ▼]  [Open ▼]       │
│                                                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ Herdenkingsportaal                           3 issues   ││
│  │ ├── ⚠️ Prijs inconsistentie (high)          [Bekijk]   ││
│  │ ├── 📅 STYLING.md verouderd (medium)        [Bekijk]   ││
│  │ └── ❓ Guest checkout missing (low)         [Bekijk]   ││
│  └─────────────────────────────────────────────────────────┘│
│                                                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ HavunAdmin                                   1 issue    ││
│  │ └── 📋 Duplicate: BTW regels (medium)       [Bekijk]   ││
│  └─────────────────────────────────────────────────────────┘│
│                                                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ Studieplanner                                0 issues ✓ ││
│  └─────────────────────────────────────────────────────────┘│
│                                                              │
│  [🔄 Herindexeer alles]  [📊 Genereer rapport]              │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### 3. 2-Wekelijkse Audit

Automatisch rapport via cron job:

```
📊 Doc Audit Rapport - 4 januari 2026
═══════════════════════════════════════

Gescand: 9 projecten, 47 MD bestanden

SAMENVATTING
────────────
✅ 6 projecten zonder issues
⚠️ 3 projecten met issues (7 totaal)

ISSUES PER PROJECT
──────────────────
Herdenkingsportaal (3):
  🔴 HIGH: Prijs inconsistentie SPEC.md vs HANDOVER.md
  🟡 MED:  STYLING.md niet bijgewerkt (90 dagen)
  🟢 LOW:  Guest checkout niet gedocumenteerd

HavunAdmin (2):
  🟡 MED:  BTW regels staan dubbel
  🟢 LOW:  AUTH.md verouderd

Judotoernooi (2):
  🟡 MED:  Broken link naar OLD-RULES.md
  🟢 LOW:  Scoring systeem niet gedocumenteerd

ACTIE VEREIST
─────────────
3 high/medium issues vereisen aandacht.
Klik hier om op te lossen: https://havuncore.havun.nl/docs/issues

───────────────────────────────────────
Volgende audit: 18 januari 2026
```

---

## 🔧 Technische Componenten

### 1. Indexer Service
- Scant alle MD files in alle projecten
- Genereert embeddings via Claude/OpenAI API
- Vergelijkt content hashes voor wijzigingen
- Detecteert relaties tussen documenten

### 2. Issue Detector
- Vergelijkt embeddings voor duplicaten (cosine similarity > 0.85)
- Zoekt tegenstrijdige informatie (zelfde onderwerp, andere waarden)
- Checkt file timestamps voor verouderde docs
- Scant code voor ongedocumenteerde features

### 3. API Layer
- REST endpoints voor zoeken en issue management
- MCP tool integratie voor Claude CLI
- Webhook voor real-time updates

### 4. Scheduler
- Cron job voor 2-wekelijkse volledige scan
- Dagelijkse snelle check op kritieke issues
- Notificatie bij nieuwe high-severity issues

---

## 📋 Implementatie Stappenplan

### Fase 1: Database Setup
- [ ] pgvector extensie installeren op server
- [ ] Database tabellen aanmaken
- [ ] Test data invoeren

### Fase 2: Indexer
- [ ] Script om MD files te scannen
- [ ] Embedding generatie (Claude API)
- [ ] Content hash berekening
- [ ] Initiële indexering van alle projecten

### Fase 3: Issue Detection
- [ ] Duplicaat detectie (embedding similarity)
- [ ] Inconsistentie detectie
- [ ] Verouderde docs detectie
- [ ] Missing docs detectie (code scan)

### Fase 4: API Endpoints
- [ ] POST /api/docs/search
- [ ] GET /api/docs/issues
- [ ] POST /api/docs/resolve
- [ ] GET /api/docs/audit

### Fase 5: CLI Integratie
- [ ] /start aanpassen om issues te tonen
- [ ] Interactieve vraag-flow
- [ ] Automatische doc updates

### Fase 6: Webapp Dashboard
- [ ] Issues overzicht pagina
- [ ] Detail view per issue
- [ ] Resolve interface

### Fase 7: Scheduled Audit
- [ ] Cron job setup
- [ ] Rapport generatie
- [ ] Email/webhook notificatie

---

## ✅ Gemaakte Keuzes (4 jan 2026)

| Vraag | Keuze |
|-------|-------|
| Embedding API | Claude API |
| Database | PostgreSQL + pgvector (nieuw op server, naast MySQL) |
| Indexeren | Dagelijks + bij /end van elk project |
| Projecten | Alle 9 direct |
| Notificaties | Bij /start + webapp dashboard (geen email) |

---

## 📚 Gerelateerde Documentatie

- [docs-first-workflow.md](../runbooks/docs-first-workflow.md) - De DOCS-FIRST regel
- [claude-werkwijze.md](../runbooks/claude-werkwijze.md) - LEES-DENK-DOE-DOCUMENTEER
- [PKM-SYSTEEM.md](../PKM-SYSTEEM.md) - Kennisstructuur
- [md-file-audit.md](../runbooks/md-file-audit.md) - Huidige audit procedure

---

*Laatst bijgewerkt: 4 januari 2026*
