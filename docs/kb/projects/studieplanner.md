# Project: Studieplanner

**URL:** https://studieplanner.havun.nl
**API:** https://studieplanner.havun.nl/api/ (geen apart subdomain)
**Type:** Expo React Native (frontend) + Laravel API (backend)

> **Waarom Expo i.p.v. PWA:** Alarms en timer/stopwatch werken onbetrouwbaar in PWA (achtergrond, notificaties).

## Repositories

| Component | Lokaal | Server |
|-----------|--------|--------|
| Frontend (React) | `D:\GitHub\Studieplanner` | `/var/www/studieplanner/production` |
| Backend (Laravel) | `D:\GitHub\Studieplanner-api` | `/var/www/studieplanner-api` |

## Doel

Mentor-leerling studiesessie tracking app:
- Leerling start/stopt studiesessies
- Mentor krijgt notificaties
- Chat functie
- Evaluaties na sessies

## Architectuur

Zie: `docs/kb/decisions/005-studieplanner-architecture.md`

| Component | Keuze |
|-----------|-------|
| Backend | Laravel 11 (eigen app) |
| Frontend | Expo React Native |
| Real-time | Pusher Channels (WebSockets) |
| Auth | Magic link + pincode + biometrie |
| Push | Web Push (VAPID) |

## Auth Systeem

### Methoden
- **Magic link:** email → link opent app via deep link → token
- **Pincode:** 6-cijferig, fallback login
- **Biometrie:** vingerafdruk/gezicht voor herhaald inloggen (expo-local-authentication)

### Implementatie Status: ✅ KLAAR

**Backend endpoints:**
```
POST /api/register              - Registratie + verificatie email
POST /api/register/mentor       - Mentor registratie
POST /api/verify-email          - Verificatiecode valideren
POST /api/login                 - Login met email + pincode
POST /api/logout                - Uitloggen (token revoke)
POST /api/auth/magic-link       - Magic link aanvragen
POST /api/auth/magic-link/verify - Magic link token valideren
POST /api/forgot-password       - Reset code aanvragen
POST /api/reset-password        - Nieuwe pincode instellen
POST /api/resend-verification   - Nieuwe verificatiecode
GET  /api/user                  - Huidige gebruiker (auth)
```

**Frontend schermen:**
- `AuthScreen.tsx` (290 regels) — email input, magic link flow
- `MagicLinkSentScreen.tsx` (110 regels) — "Check je inbox" wachtscherm

## API Endpoints (Studieplanner-api)

### Auth ✅
Zie hierboven.

### Sessies ✅
```
GET  /api/session/active         - Actieve sessie ophalen
GET  /api/session/history        - Sessie geschiedenis
POST /api/session/start          - Start studiesessie
POST /api/session/stop           - Stop sessie + evaluatie
```

### Mentor ✅
```
GET  /api/mentor/students                    - Leerlingen lijst
POST /api/mentor/accept-student              - Uitnodiging accepteren
GET  /api/mentor/student/{id}                - Leerling data
DELETE /api/mentor/student/{id}              - Leerling verwijderen
GET  /api/mentor/student/{id}/active-session - Actieve sessie van leerling
```

### Magister integratie ✅
```
POST /api/magister/login         - Magister inloggen
GET  /api/magister/status        - Login status
GET  /api/magister/grades        - Cijfers ophalen
GET  /api/magister/homework      - Huiswerk ophalen
GET  /api/magister/schedule      - Rooster ophalen
GET  /api/magister/tests         - Toetsen ophalen
GET  /api/magister/schools       - Scholen zoeken
```

### Premium ✅
```
GET  /api/premium/status         - Premium status
GET  /api/premium/stats          - Statistieken
GET  /api/premium/learning-speed - Leersnelheid analyse
```

### Push Notificaties ✅
```
GET  /api/push/vapid-public-key  - VAPID public key
POST /api/push/subscribe         - Push subscription opslaan
POST /api/push/unsubscribe       - Push subscription verwijderen
```

### Chat (TODO)
```
POST /api/chat/send              - Verstuur bericht
GET  /api/chat/messages          - Ophalen berichten
```

## Database

Migraties op server gedraaid (batch 1-6):
```
users: id, name, email, pincode, role (student/mentor), student_code, premium fields
study_sessions: id, student_id, mentor_id, started_at, stopped_at, status, evaluation, alarm fields
mentor_students: mentor_id, student_id (koppeltabel)
subjects: id, user_id, name, ...
student_invites: id, mentor_id, student_code, ...
user_settings: id, user_id, key, value
push_subscriptions: id, user_id, endpoint, ...
personal_access_tokens: Sanctum tokens
verification_codes: id, email, code, purpose, expires_at, used_at
```

## Frontend Schermen (Expo React Native)

| Scherm | Regels | Status |
|--------|--------|--------|
| AuthScreen.tsx | 290 | Magic link + pincode flow |
| MagicLinkSentScreen.tsx | 110 | Wachtscherm na magic link |
| TimerScreen.tsx | 353 | Studiesessie timer |
| SubjectsScreen.tsx | 110 | Vakken overzicht |
| SubjectDetailScreen.tsx | 374 | Vak detail + sessies |
| StatsScreen.tsx | 412 | Statistieken dashboard |
| AgendaScreen.tsx | 95 | Agenda/planning |
| MentorDashboardScreen.tsx | 235 | Mentor overzicht |
| SettingsScreen.tsx | 254 | Instellingen |

**Services:**
- `src/services/api.ts` — API client
- `src/services/biometrics.ts` — Biometrie wrapper
- `src/store/AuthContext.tsx` — Auth state management

## Email Configuratie

✅ Brevo SMTP geconfigureerd (zelfde account als andere Havun projecten)
- Host: `smtp-relay.brevo.com`
- Van: noreply@studieplanner.havun.nl

## HavunCore Integratie

| Service | Status |
|---------|--------|
| Backup | ✅ Daily 05:00, 1 jaar retention |
| Vault | Beschikbaar voor credentials |
| Scheduler | ✅ Actief (cron) |

## Pusher (Real-time)

**App:** Studieplanner
**Credentials:** Zie `.claude/context.md`
**Pattern:** Zie `docs/kb/patterns/pusher-realtime.md`

## Deployment

### Frontend (Expo)
```bash
cd D:\GitHub\Studieplanner
npm run build
git add . && git commit -m "message" && git push
# Server: git pull + npm ci + npm run build
```

### Backend (Laravel)
```bash
cd D:\GitHub\Studieplanner-api
git add . && git commit -m "message" && git push
# Server: git pull + composer install --no-dev + php artisan migrate + config:clear
```

## Implementatie Roadmap

### Fase 1: Backend Auth ✅ KLAAR (22 dec 2025)
### Fase 2: Email Service ✅ KLAAR (Brevo geconfigureerd)
### Fase 3: Backend Deploy ✅ KLAAR (Hetzner, migraties gedraaid)
### Fase 4: Frontend Koppeling — IN PROGRESS
- Schermen aangemaakt, services bestaan
- Koppeling met backend endpoints moet getest/afgemaakt worden

### Fase 5: Mentor Features — DEELS KLAAR
- [x] Mentor endpoints (students, accept, active session)
- [ ] Chat endpoints
- [ ] Pusher real-time integratie

### Fase 6: Magister + Premium — ✅ BACKEND KLAAR
- [x] Magister integratie (login, grades, homework, schedule, tests)
- [x] Premium features (stats, learning speed)
- [ ] Frontend schermen koppelen aan deze endpoints

---

*Laatste update: 14 maart 2026*
