# Project: Studieplanner

**URL:** https://studieplanner.havun.nl
**API:** https://api.studieplanner.havun.nl
**Type:** Expo React Native (frontend) + Laravel 12 API (backend)
**Business model:** Freemium (gratis planning, premium stats/analytics €1/jaar via Mollie iDEAL)

> **Waarom Expo i.p.v. PWA:** Alarms en timer/stopwatch werken onbetrouwbaar in PWA (achtergrond, notificaties).
> **Waarom geen Play Store:** 100% eigen distributie via APK downloads op eigen server. 0% commissie.

## Repositories

| Component | Lokaal | Server |
|-----------|--------|--------|
| Frontend (React Native) | `D:\GitHub\Studieplanner` | `/var/www/studieplanner/production` |
| Backend (Laravel 12) | `D:\GitHub\Studieplanner-api` | `/var/www/studieplanner/production` |

## Doel

Studieplanningsapp voor leerlingen en mentors:
- Vakken & taken beheren met toetsdatums
- Automatische planning: taken verdelen tot toetsdatum
- Timer met StudyLog tracking (loopt door op achtergrond)
- Weekagenda met drag & drop
- Mentor dashboard met real-time status
- Premium statistieken (leersnelheid, streaks, nauwkeurigheid)

## Tech Stack

| Component | Keuze |
|-----------|-------|
| Frontend | React Native 0.81 + Expo SDK 54 |
| Backend | Laravel 12 (PHP 8.2+) |
| Database | MySQL (prod) / SQLite (dev) |
| Real-time | Laravel Reverb (WebSocket via HavunCore proxy) |
| Auth | Magic link (email) + biometrie (expo-local-authentication) |
| Push | expo-notifications (native OS) |
| Payments | Mollie iDEAL (€1/jaar) |
| Email | Brevo SMTP (noreply@studieplanner.havun.nl) |
| State | React Context (geen Redux) |
| i18n | i18next (Nederlands default, Engels) |
| Navigation | React Navigation 7 (stack + bottom tabs) |

## Auth Systeem

- **Magic link:** email → link opent app via deep link `studieplanner://verify?token=XXX` → token opgeslagen in AsyncStorage
- **Biometrie:** vingerafdruk/gezicht voor herhaald inloggen (optioneel na eerste login)
- **Dev mode:** Automatisch inloggen als `dev@test.nl` (premium student) in `__DEV__`
- **Token TTL:** 15 minuten (MagicLinkToken model)
- **Geen pincode meer** — verwijderd in v4.0

### Auth endpoints
```
POST /api/auth/magic-link        - Magic link aanvragen
POST /api/auth/magic-link/verify - Token verifiëren → user + Sanctum token
GET  /api/auth/user              - Huidige gebruiker
POST /api/auth/logout            - Uitloggen (token revoke)
```

## API Endpoints (Studieplanner-api)

### Subjects & Tasks
```
GET    /api/subjects              - Alle vakken
POST   /api/subjects              - Nieuw vak
PUT    /api/subjects/{id}         - Vak bijwerken
DELETE /api/subjects/{id}         - Vak verwijderen
POST   /api/subjects/{id}/tasks   - Taak aanmaken
PUT    /api/tasks/{id}            - Taak bijwerken
DELETE /api/tasks/{id}            - Taak verwijderen
```

### Sessions
```
GET  /api/sessions                - Sessies (query: from, to)
POST /api/sessions                - Sessie aanmaken
PUT  /api/sessions/{id}           - Sessie verplaatsen
DELETE /api/sessions/{id}         - Sessie verwijderen
POST /api/student/subjects/sync   - Bulk sync subjects + tasks
POST /api/student/sessions/sync   - Bulk sync sessions
```

### Timer
```
POST /api/session/start           - Sessie starten
POST /api/session/stop            - Sessie stoppen + StudyLog opslaan
GET  /api/session/active          - Actieve sessies (mentor)
GET  /api/session/history         - Sessie geschiedenis
```

### Mentor
```
GET    /api/mentor/students       - Gekoppelde leerlingen
GET    /api/mentor/student/{id}   - Leerling detail
POST   /api/mentor/accept-student - Uitnodiging accepteren
DELETE /api/mentor/student/{id}   - Ontkoppelen
```

### Student
```
POST   /api/student/invite        - Invite code genereren (6 char, 24h)
GET    /api/student/mentors       - Mentoren
DELETE /api/student/mentor/{id}   - Mentor ontkoppelen
```

### Premium
```
GET /api/premium/status           - Premium status
GET /api/premium/stats            - Statistieken (premium only)
GET /api/premium/learning-speed   - Leersnelheid per vak
POST /api/premium/pay             - Mollie betaling starten
GET /api/premium/payment/{id}     - Betaling status
```

### SOMtoday & Magister
```
POST /api/magister/login          - Inloggen
GET  /api/magister/status         - Login status
GET  /api/magister/grades         - Cijfers
GET  /api/magister/homework       - Huiswerk
GET  /api/magister/schedule       - Rooster
GET  /api/magister/tests          - Toetsen
GET  /api/magister/schools        - Scholen zoeken
```

### Push & Versioning
```
GET  /api/push/vapid-public-key   - VAPID public key
POST /api/push/subscribe          - Push subscription
POST /api/push/unsubscribe        - Unsubscribe
GET  /api/app/version             - Version info + download URL
```

### Webhooks
```
POST /webhooks/mollie             - Mollie payment webhook
```

## Database

| Tabel | Beschrijving |
|-------|-------------|
| users | id, name, email, role (student/mentor), student_code, is_premium, premium_until |
| subjects | id, user_id, name, color, exam_date, exam_time, exam_duration |
| tasks | id, subject_id, description, estimated_minutes, planned_amount, unit, completed |
| planned_sessions | id, date, hour, minute, subject_id, task_id, minutes_planned, amount_planned, unit, completed, logs (StudyLog[]) |
| study_sessions | id, student_id, mentor_id, started_at, stopped_at, status, evaluation |
| magic_link_tokens | id, email, token, expires_at (15 min TTL) |
| mentor_students | mentor_id, student_id, status, invite_code |
| student_invites | id, mentor_id, student_code |
| premium_payments | id, user_id, amount, period, status, mollie_id |
| newsletter_subscribers | id, email, is_active, unsubscribed_at |
| newsletter_campaigns | id, subject, content, sent_at |
| push_subscriptions | id, user_id, endpoint |

## Frontend Structuur

```
src/
├── navigation/         # RootNavigator, StudentNavigator, MentorNavigator
├── screens/            # AuthScreen, SubjectsScreen, AgendaScreen, TimerScreen, etc.
├── components/         # SubjectCard, TaskItem, SessionBlock, WeekView, TimerDisplay
├── store/              # AuthContext, SubjectsContext, SessionsContext, TimerContext, SettingsContext
├── services/           # api.ts, storage.ts, biometrics.ts, alarmService.ts, backgroundTimer.ts
├── types/              # TypeScript interfaces
├── utils/              # planning.ts (auto-verdeling), formatters.ts, colors.ts
├── constants/          # theme.ts, config.ts
└── i18n/locales/       # nl.json, en.json
```

## APK Distributie

- Geen Google Play Store — eigen server
- APK locatie: `/var/www/studieplanner/production/public/downloads/studieplanner-latest.apk`
- Versie check: `GET /api/app/version` → `{ version, downloadUrl, forceUpdate }`
- OTA updates via expo-updates + Expo Services
- Build via EAS Build (Expo Application Services)

## HavunCore Integratie

| Service | Status |
|---------|--------|
| Backup | Daily 05:00, 1 jaar retention |
| Vault | Beschikbaar voor credentials |
| WebSocket | Reverb proxy voor mentor real-time updates |
| Scheduler | Actief (cron) |

## Email

Brevo SMTP (zelfde account als andere Havun projecten)
- Host: `smtp-relay.brevo.com:587`
- Van: `noreply@studieplanner.havun.nl`

## Development

```bash
# Frontend
cd D:\GitHub\Studieplanner
npm install
npx expo start --android     # Expo Go of dev client

# Backend
cd D:\GitHub\Studieplanner-api
composer install
php artisan serve --port=8003
```

## Server Configuratie

| Component | Details |
|-----------|---------|
| Nginx (site) | `/etc/nginx/sites-enabled/studieplanner` → `/var/www/studieplanner/production/public` |
| Nginx (API) | `/etc/nginx/sites-enabled/studieplanner-api` → `api.studieplanner.havun.nl` → `/var/www/studieplanner/production/public` |
| Systemd | `studieplanner-api.service` (php artisan serve :8001) |
| Cron | `* * * * * cd /var/www/studieplanner/production && php artisan schedule:run` |
| SSL | Let's Encrypt voor beide domeinen |
| PHP | 8.2-fpm |

## Bekende Issues

- Background timer + biometrie niet testbaar in Expo Go (vereist dev APK)
- Code wijkt deels af van docs (oude timer flow nog in code, nieuw StudyLog model moet verwerkt worden)
- Oude web-routes in studieplanner-api/routes/web.php serveren nog een verouderde website

---

*Laatste update: 18 maart 2026*
