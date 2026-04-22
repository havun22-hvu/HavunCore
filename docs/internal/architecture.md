---
title: HavunCore Database & Metadata Architectuur
type: reference
scope: havuncore
last_check: 2026-04-22
---

# HavunCore Database & Metadata Architectuur

> **Zie ook:** `docs/kb/projects/havuncore-webapp.md` voor de volledige webapp/Command Center architectuur.

## 1. Database Structuur (SQLite)
De tabel `doc_embeddings` is de centrale opslag voor de kennisbank:
- `id`: Unieke identifier.
- `project`: De naam van het bronproject.
- `file_path`: Relatief pad naar het bestand (gebruikt voor metadata-extractie).
- `content`: De ruwe tekstinhoud van het bestand.
- `embedding`: JSON-array van de word-frequency vector.
- `file_modified_at`: Timestamp van de laatste wijziging.

## 2. Metadata Verwerking
Metadata wordt momenteel afgeleid uit het `file_path`. De `DocIndexer` bepaalt op basis van de extensie hoe een bestand gelezen moet worden.

## 3. Uitbreiden voor nieuwe bestandstypen
Om een nieuw type (bijv. `.pdf`) toe te voegen:
1. Pas de `DocIndexer.php` aan om de nieuwe extensie te herkennen.
2. Voeg een parser-methode toe die de ruwe tekst uit het doelbestand extraheert.
3. De `HavunAIBridge` en de Node.js backend (`backend/src/routes/orchestrate.js`) zullen deze nieuwe data automatisch meenemen zodra de index is ververst.

## 4. Node.js backend (webapp)
Dezelfde SQLite-database wordt gebruikt door de Node.js backend:
- **Koppeling:** `backend/src/app.js` opent `database/doc_intelligence.sqlite` (of `HAVUNCORE_DB_PATH`) en zet `app.locals.db`.
- **Hybrid flow:** `backend/src/routes/orchestrate.js` — route `POST /api/intelligent`: top 15 uit `doc_embeddings` → Command-R filtert → Claude antwoordt. Zie `docs/internal/context-filter-flow.md`.
