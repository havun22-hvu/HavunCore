# Project: Infosyst

**URL:** https://infosyst.havun.nl (read-only wiki)
**Type:** Laravel + SQLite (lokaal) + Git sync + Ollama AI
**Status:** In development / partially live

## Wat is het?

Gedistribueerde kennisbank ("Henkiepedia") met:
- Lokaal artikelen invoeren (SQLite + PWA, offline mogelijk)
- Sync via Git (JSON files in `/content/articles/`)
- Online read-only wiki op server (MySQL)
- AI chat via Ollama op Henk's PC (64GB RAM)

## Architectuur

```
Lokaal (redacteur)          GitHub              Server (read-only)
Laravel + SQLite    →  JSON files  →   Laravel + MySQL
+ PWA offline          + versie-              + Henkiepedia wiki
+ export → JSON        geschiedenis           + AI chat (tunnel → Henk's PC)
+ git push                                    + GEEN import/invoer
```

## Omgevingen

| Omgeving | URL | Pad |
|----------|-----|-----|
| Local | localhost:8005 | `D:\GitHub\infosyst` |
| Production | infosyst.havun.nl | `/var/www/infosyst/production` |

## Core Features

- **Content Import** - URL plakken of bestand uploaden, Gemini analyseert, interactieve review
- **Auto-detectie** - YouTube URL → video, andere URL → website, bestand → document/audio
- **Artikel Audit Trail** - Wie aanmaakte/bewerkte, wijzigingsgeschiedenis
- **AI Chat** - Ollama op Henk's PC, tunnel naar server
- **Multi-redacteur** - Elke redacteur werkt lokaal, sync via Git

## Tech Stack

**Framework:** Laravel + SQLite (lokaal), MySQL (server)
**AI:** Ollama (lokaal op Henk's PC, 64GB RAM)
**Sync:** Git + JSON files
**Import:** Gemini API voor content analyse

## Server

```bash
# Deploy
cd /var/www/infosyst/production
git pull origin main
composer install --no-dev
php artisan migrate --force
php artisan optimize:clear
```

---

*Laatste update: 11 maart 2026*
