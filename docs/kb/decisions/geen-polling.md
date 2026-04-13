# Beslissing: Geen Polling — Altijd WebSocket/Reverb

> **Geldt voor:** ALLE Havun projecten
> **Datum:** 21 maart 2026
> **Bijgewerkt:** 13 april 2026
> **Aanleiding:** JudoScoreBoard ontwerp + HavunCore webapp polling cleanup

## Beslissing

**NOOIT polling gebruiken voor data updates.** Altijd WebSocket via Laravel Reverb.

Dit geldt voor:
- Real-time data (scores, chat, status)
- Periodieke data (berichten, taken, projectstatus)
- QR login status
- Elke situatie waar je "elke X seconden" data ophaalt

## Waarom

- Polling verspilt bandbreedte en server resources
- Polling heeft inherente vertraging (interval-afhankelijk)
- WebSocket is instant push — geen onnodige requests
- Laravel Reverb is al opgezet in onze stack
- Polling schaalt niet — 100 gebruikers = 100x meer requests

## Wanneer wel een GET endpoint (eenmalig)

- **Initieel ophalen** bij app start of reconnect (1x, niet in een loop)
- **Fallback** na WebSocket disconnect — eenmalig huidige state ophalen, daarna weer WebSocket
- **Op gebruiker-actie** — gebruiker klikt "ververs" knop

## Wat NOOIT mag

- `setInterval` + fetch/axios voor data updates
- "Poll elke X seconden" patronen
- Fallback polling naast WebSocket
- Polling als "makkelijke oplossing" i.p.v. Reverb event

## Wat WEL mag met setInterval

- **UI timers** — countdown, klok, stopwatch (geen server calls)
- **Camera/sensor** — QR scanner frame capture (hardware, geen API)
- **Service Worker** — browser update check (lifecycle, niet onze keuze)

## Toepassing per project

| Project | Real-time methode | Poort intern | Poort extern |
|---------|------------------|-------------|-------------|
| JudoToernooi | Reverb | 8080 | 443 via nginx `/app` |
| JudoScoreBoard | Reverb (via JudoToernooi) | 8080 | 443 |
| HavunCore Webapp | Socket.io (Node.js backend) | 3001 | 443 via nginx |
| Herdenkingsportaal | Reverb | 8080 | 443 via nginx `/app` |
| HavunAdmin | Reverb | 8080 | 443 via nginx `/app` |
| Infosyst | Reverb | 8080 | 443 via nginx `/app` |
| SafeHavun | Reverb | 8080 | 443 via nginx `/app` |
| Studieplanner | Reverb | 8080 | 443 via nginx `/app` |

### HavunCore Webapp specifiek

De webapp backend is Node.js met Socket.io (niet Laravel Reverb). Socket.io IS een WebSocket — geen polling. De frontend verbindt via:

```javascript
const socket = socketIO(API_URL, {
  transports: ['websocket', 'polling'],  // Socket.io transport fallback, niet onze polling
});
```

> **Let op:** `transports: ['websocket', 'polling']` is Socket.io's eigen transport protocol fallback. Dit is NIET dezelfde "polling" als `setInterval + fetch`. Socket.io probeert eerst WebSocket, valt terug op long-polling als WebSocket niet beschikbaar is. Dit is acceptabel.

## Bekende schendingen (te fixen)

Per 13 april 2026 gevonden in HavunCore webapp frontend:

| Bestand | Polling | Moet worden |
|---------|---------|-------------|
| `MessagesView.jsx` | `setInterval(fetchMessages, 30000)` | Socket.io event |
| `ProjectsView.jsx` | `setInterval(fetchProjects, 60000)` | Socket.io event |
| `StatusView.jsx` | `setInterval(fetchStatus, 30000)` | Socket.io event |
| `TasksView.jsx` | `setInterval(fetchTasks, 30000)` | Socket.io event |
| `QrLogin.jsx` | Polling fallback voor QR status | Verwijderd in Login.jsx, QrLogin.jsx is verouderd |

---

*Beslissing: 21 maart 2026*
*Bijgewerkt: 13 april 2026 — webapp polling schendingen gedocumenteerd*
