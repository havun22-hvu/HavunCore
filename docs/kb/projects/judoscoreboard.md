# Project: JudoScoreBoard

**Type:** Expo React Native (TypeScript) Android app
**Doel:** Judo scorebord voor scheidsrechter-tablets/smartphones, gekoppeld aan JudoToernooi
**Backend:** JudoToernooi Laravel app (https://judotournament.org)
**Status:** Nieuw project (21 maart 2026), basis opgezet

> **Waarom standalone app?** Timer moet doorlopen op achtergrond (foreground service). Werkt niet betrouwbaar in PWA/browser.

## Repositories

| Component | Lokaal | Server |
|-----------|--------|--------|
| App (Expo) | `D:\GitHub\JudoScoreBoard` | `/var/www/judoscoreboard/` |
| Backend (Laravel) | `D:\GitHub\JudoToernooi\laravel` | `/var/www/judotoernooi/laravel` |

**GitHub:** havun22-hvu/judoscoreboard (app), havun22-hvu/judotoernooi (backend)

## Twee interfaces

| Interface | Device | Functie |
|-----------|--------|---------|
| **Bediening** (Android app) | Tablet, smartphone | Alle knoppen: timer, score, shido, osaekomi |
| **Display** (Web/Blade) | TV, LCD, projector, browser | Alleen weergave, geen knoppen, **gespiegeld** |

### Spiegeling (IJF standaard)

| Interface | Links | Rechts |
|-----------|-------|--------|
| **Bediening** (tafelofficial) | Blauw | Wit |
| **Display** (scheidsrechter/publiek) | Wit | Blauw |

## Tech Stack

| Component | Keuze |
|-----------|-------|
| Framework | Expo SDK 55 + React Native 0.83 |
| Taal | TypeScript 5.9 |
| Package | nl.havun.judoscoreboard |
| Backend API | JudoToernooi `/api/scoreboard/*` |
| Real-time | Laravel Reverb (WebSocket) |
| Distributie | APK via eigen server (sideloading) |
| Display | Blade + Alpine.js + Reverb (in JudoToernooi) |
| Orientatie | Altijd landscape |
| State | React Context |
| Navigation | React Navigation 7 (stack) |

### Dependencies

- `expo-av` — geluidseffecten (match end, osaekomi, ippon)
- `expo-keep-awake` — scherm aan tijdens wedstrijd
- `expo-notifications` — foreground service voor timer
- `expo-task-manager` — background timer
- `expo-updates` — OTA updates
- `expo-application` — versie check
- `@react-native-async-storage/async-storage` — offline opslag

## API Endpoints (backend = JudoToernooi)

```
POST /api/scoreboard/auth           → Login (code + PIN) → Bearer token
GET  /api/scoreboard/current-match  → Huidige wedstrijd ophalen
POST /api/scoreboard/result         → Uitslag terugsturen
POST /api/scoreboard/state          → Live state naar display (event-based)
POST /api/scoreboard/heartbeat      → Keep alive
```

### Sync strategie: event-based

Bediening stuurt alleen events bij state changes, niet continu state.
Display draait eigen timer lokaal. ~20-30 requests per wedstrijd.

Events: `match.start`, `timer.start`, `timer.stop`, `timer.reset`,
`score.update`, `osaekomi.start`, `osaekomi.stop`, `match.end`

## WebSocket Channels (Reverb)

| Channel | Richting | Data |
|---------|----------|------|
| `scoreboard.{toernooiId}.{matId}` | Server → App | Wedstrijd assignment |
| `scoreboard-display.{toernooiId}.{matId}` | Server → Display | Live score/timer state |

## Scoring Regels (IJF 2024)

| Actie | Regel |
|-------|-------|
| Waza-ari (W) | 0, 1, 2 — 2x waza-ari = awasete ippon |
| Yuko (Y) | Aparte teller, optellend |
| Ippon (I) | Direct = wedstrijd voorbij |
| Shido | 3 kaartjes, 3e = hansoku-make = ippon tegenstander |
| Osaekomi | 5-9s = yuko, 10-19s = waza-ari, 20s = ippon |
| Golden Score | Bij gelijkspel, timer telt omhoog, eerste score wint |
| Timer | 2-5 min instelbaar, rood bij 30s |

## Schermen flow

```
LoginScreen → [code+PIN] → WaitingScreen → [wedstrijd via WS] → ControlScreen → [uitslag POST] → WaitingScreen
                                 ↓ (display modus)
                            DisplayScreen (luistert alleen op WS)
```

## Frontend Structuur

```
src/
├── components/     # UI componenten (timer, score panels, shido cards)
├── constants/      # config.ts (API URL), theme.ts
├── hooks/          # Custom hooks
├── screens/        # Login, Waiting, Control, Display
├── services/       # api.ts, websocket
├── store/          # React Context (match state, auth)
├── types/          # TypeScript interfaces
└── utils/          # Helpers
```

## APK Distributie

- Eigen server: `/var/www/judoscoreboard/`
- OTA updates via expo-updates (`checkAutomatically: ON_LOAD`)
- Versie check: `GET /api/scoreboard/version`
- Build: `eas build --platform android --profile production`

## Vereisten

- **Landscape** altijd (forced in app.json)
- **Background timer** via foreground service (sticky notification)
- **Offline resilient:** timer draait door, uitslag ge-queued, sync bij reconnect
- **Wake lock** tijdens wedstrijd (expo-keep-awake)
- **Geluiden:** match end, osaekomi milestone, ippon fanfare
- **Dark mode** standaard

## Development

```bash
# App starten
cd D:\GitHub\JudoScoreBoard
npx expo start --android

# Backend (JudoToernooi)
cd D:\GitHub\JudoToernooi\laravel
php artisan serve --port=8007
```

---

*Laatste update: 21 maart 2026*
