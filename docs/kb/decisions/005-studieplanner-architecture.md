# Decision 005: Studieplanner Architecture

**Datum:** 22 december 2025
**Status:** Besloten
**Project:** Studieplanner

## Context

Studieplanner heeft functionaliteit nodig voor:
- Mentor-leerling koppeling
- Push notificaties bij start/stop studiesessie
- Evaluaties na sessies
- Chat functie tussen mentor en leerling

## Beslissing

**Eigen Laravel backend op bestaande server**, geen externe services.

### Gekozen aanpak

| Component | Keuze | Reden |
|-----------|-------|-------|
| Backend | Laravel (eigen app) | Al deployed op server |
| Notificaties MVP | Database + polling | Zero dependencies |
| Notificaties later | Laravel WebPush | Native, geen vendor lock-in |
| Chat | Polling (5 sec) | Simpel, werkt voor 1-op-1 |
| Realtime | Niet nodig voor MVP | Polling is goed genoeg |

### Afgewezen alternatieven

| Optie | Reden afgewezen |
|-------|-----------------|
| Firebase | Overkill, vendor lock-in, extra complexity |
| Pusher | Externe dependency, kosten |
| HavunCore API | Niet de plek voor project-specifieke logic |

## API Endpoints (voorstel)

```
POST /api/session/start      - Leerling start sessie
POST /api/session/stop       - Leerling stopt sessie (+ evaluatie)
GET  /api/session/active     - Mentor pollt voor updates
GET  /api/session/{id}       - Sessie details + evaluatie
POST /api/chat/send          - Bericht versturen
GET  /api/chat/messages      - Berichten ophalen (polling)
```

## Database tabellen (voorstel)

```sql
study_sessions:
  id, student_id, mentor_id, started_at, stopped_at,
  status, evaluation_text, rating, mentor_feedback

messages:
  id, session_id, sender_id, message, created_at
```

## HavunCore integratie

- Backup: Daily 05:00, 1 jaar retention
- Vault: Credentials centraal beheerd
- Task Queue: Optioneel voor later

## Gevolgen

- Studieplanner is volledig standalone
- Geen externe service dependencies
- Makkelijk te debuggen en onderhouden
- Later uitbreidbaar naar WebPush indien nodig
