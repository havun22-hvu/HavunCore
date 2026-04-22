---
title: Project: JudoScoreBoard
type: reference
scope: havuncore
last_check: 2026-04-22
---

# Project: JudoScoreBoard

**Type:** Expo React Native (TypeScript) Android app
**Doel:** Judo scorebord — standalone OF gekoppeld aan JudoToernooi
**Backend:** JudoToernooi Laravel app (https://judotournament.org) — optioneel
**Status:** Basis functioneel (21 maart 2026)

> **Waarom standalone app?** Timer moet doorlopen op achtergrond (foreground service). Werkt niet betrouwbaar in PWA/browser.

## Repositories

| Component | Lokaal | Server |
|-----------|--------|--------|
| App (Expo) | `D:\GitHub\JudoScoreBoard` | `/var/www/judoscoreboard/` |
| Backend (Laravel) | `D:\GitHub\JudoToernooi\laravel` | `/var/www/judotoernooi/laravel` |

**GitHub:** havun22-hvu/judoscoreboard (app), havun22-hvu/judotoernooi (backend)

## Twee modi

| Modus | API | WebSocket | Gebruik |
|-------|-----|-----------|---------|
| **Standalone** | Nee | Nee | Training, klein toernooi, los gebruik |
| **Gekoppeld** | Ja | Ja | Toernooi met JudoToernooi systeem |

## Twee interfaces

| Interface | Device | Type | Functie |
|-----------|--------|------|---------|
| **Bediening** | Tablet, smartphone | Android APK | Alle knoppen: timer, score, shido, osaekomi |
| **Display** | TV, LCD, projector | Web (Blade + Reverb) | Alleen weergave, geen knoppen, **gespiegeld** |

### Spiegeling (IJF standaard)

| Interface | Links | Rechts |
|-----------|-------|--------|
| **Bediening** (tafelofficial) | Blauw | Wit |
| **Display** (scheidsrechter/publiek) | Wit | Blauw |

## Communicatie (gekoppeld modus)

**GEEN POLLING — altijd WebSocket (Reverb)**

| Richting | Methode | Endpoint/Channel |
|----------|---------|-----------------|
| Mat → App | WebSocket (Reverb) | `scoreboard.{toernooiId}.{matId}` |
| App → Mat | REST POST | `/api/scoreboard/result` |
| App → Display | POST → Reverb | `/api/scoreboard/event` → `scoreboard-display.{toernooiId}.{matId}` |

`GET /current-match` alleen bij (re)connect, NIET voor polling.

### API Endpoints

```
POST /api/scoreboard/auth           → Login (code + PIN) → Bearer token
GET  /api/scoreboard/current-match  → Huidige wedstrijd (eenmalig bij reconnect)
POST /api/scoreboard/result         → Uitslag terugsturen
POST /api/scoreboard/event          → Sync event naar display (event-based)
POST /api/scoreboard/heartbeat      → Keep alive
GET  /api/scoreboard/version        → Update check (public)
```

### Event-based sync (niet continu)

~20-30 requests per wedstrijd. Display draait eigen timer lokaal.

Events: `match.start`, `timer.start`, `timer.stop`, `timer.reset`,
`score.update`, `osaekomi.start`, `osaekomi.stop`, `match.end`

## Scoring Regels

| Actie | Regel |
|-------|-------|
| Yuko (Y) | Aparte teller, optellend |
| Waza-ari (W) | 0, 1, 2 — 2x = awasete ippon |
| Ippon (I) | Direct = wedstrijd voorbij |
| Shido | 3 kaartjes, 3e = hansoku-make |
| Osaekomi | 5-9s=Y, 10-19s=W, 20s=I |
| Golden Score | Bij gelijkspel, timer omhoog, eerste score wint |
| Timer | 2-5 min instelbaar |

### Geluiden

Alleen 2: match einde (timer=0) en osaekomi einde (W+W of I bij 20s)

## App schermen

| Scherm | Functie |
|--------|---------|
| HomeScreen | Keuze: Standalone / Gekoppeld + Settings/Help/About |
| LoginScreen | Code + PIN (gekoppeld modus) |
| WaitingScreen | WebSocket listener voor wedstrijd assignment |
| StandaloneSetupScreen | Namen invoeren, duur kiezen |
| ControlScreen | Scoring interface (Y/W/I, shido, osaekomi, timer) |
| SettingsScreen | Modus, timer default, geluid, verbinding |
| HelpScreen | Bediening uitleg, scoring regels |
| AboutScreen | Versie, ontwikkelaar, contact |

## Reverb verbinding (kritiek)

De scoreboard API (`POST /api/scoreboard/auth`) retourneert Reverb config aan de app.
Het LCD display (`scoreboard-live.blade.php`) haalt config uit `config('app.url')`.

**NOOIT `env()` gebruiken in controllers of Blade views** — retourneert `null` na `config:cache`.

Troubleshooting: `HavunCore/docs/kb/runbooks/reverb-troubleshoot.md`
Safeguards: `HavunCore/docs/kb/decisions/reverb-safeguards-2026-04-05.md`

## Documentatie

| Document | Locatie |
|----------|---------|
| App docs | `JudoScoreBoard/docs/` (ARCHITECTUUR, SCORING, API, CONNECTIE) |
| Feature spec | `JudoToernooi/laravel/docs/2-FEATURES/SCOREBORD-APP.md` |
| Expo setup | `HavunCore/docs/kb/runbooks/expo-android-app-setup.md` |
| Geen polling | `HavunCore/docs/kb/decisions/geen-polling.md` |
| Reverb pattern | `HavunCore/docs/kb/patterns/reverb-laravel.md` |
| Reverb troubleshoot | `HavunCore/docs/kb/runbooks/reverb-troubleshoot.md` |

## Development

```bash
# App starten (in Android Studio emulator)
cd D:\GitHub\JudoScoreBoard
npx expo start --android

# Backend (JudoToernooi)
cd D:\GitHub\JudoToernooi\laravel
php artisan serve --port=8007
```

### Lokale APK build

```bash
# Genereer native Android project
npx expo prebuild --platform android

# Open android/ map in Android Studio
# Build → Build APK(s)
# Output: android/app/build/outputs/apk/release/app-release.apk
```

### Emulator

Android Studio emulator (Pixel 7, API 34). App API gebruikt `10.0.2.2:8007` om lokale backend te bereiken.

---

*Laatste update: 5 april 2026*
