# Project: Studieplanner

**URL:** https://studieplanner.havun.nl
**Type:** React PWA (frontend) + Laravel API (backend)

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
| Backend | Laravel (eigen app) |
| Frontend | React PWA |
| Notificaties | Database + polling (MVP) |
| Chat | Polling (5 sec) |
| Auth | Pincode-based (geen wachtwoord) |

## Auth Systeem

**Speciaal:** Geen traditionele username/password, maar:
- 6-cijferige pincode per gebruiker
- Email verificatie bij registratie
- Pincode reset via email code

### Implementatie Status: ✅ KLAAR

Gebaseerd op pattern: `docs/kb/patterns/email-verification.md`

**Backend (Studieplanner-api):**
- [x] Laravel 11 + Sanctum
- [x] User model met pincode + verificatie velden
- [x] AuthController met alle endpoints
- [x] Email verzending (verificatie + reset)

**Flows:**

1. **Registratie:** email + pincode → verificatiecode per email → verify → token
2. **Login:** email + pincode → token
3. **Reset:** email → code per email → nieuwe pincode

## Database

```
users: id, name, email, pincode, role (student/mentor), mentor_id
study_sessions: id, student_id, mentor_id, started_at, stopped_at, status, evaluation
messages: id, session_id, sender_id, message, created_at
verification_codes: id, email, code, purpose, expires_at, used_at
```

## HavunCore Integratie

| Service | Status |
|---------|--------|
| Backup | Daily 05:00, 1 jaar retention |
| Vault | Beschikbaar voor credentials |
| Task Queue | Optioneel |

## API Endpoints (Studieplanner-api)

### Auth (geïmplementeerd ✅)
```
POST /api/register              - Registratie + verificatie email
POST /api/verify-email          - Verificatiecode valideren
POST /api/login                 - Login met email + pincode
POST /api/logout                - Uitloggen (token revoke)
POST /api/forgot-password       - Reset code aanvragen
POST /api/reset-password        - Nieuwe pincode instellen
POST /api/resend-verification   - Nieuwe verificatiecode
GET  /api/user                  - Huidige gebruiker (auth)
```

### Sessies (TODO)
```
POST /api/session/start           - Start studiesessie
POST /api/session/stop            - Stop sessie + evaluatie
GET  /api/session/active          - Mentor pollt voor updates
```

### Chat (TODO)
```
POST /api/chat/send               - Verstuur bericht
GET  /api/chat/messages           - Ophalen berichten
```

## Email Configuratie

Gebruikt zelfde SMTP als andere Havun projecten:
- Host: Zie `.env` of HavunCore Vault
- Van: noreply@studieplanner.havun.nl

## Deployment

### Frontend (React)
```bash
# Lokaal
cd D:\GitHub\Studieplanner
npm run build
git add . && git commit -m "message" && git push

# Server
ssh root@188.245.159.115
cd /var/www/studieplanner/production
git pull origin master
npm ci && npm run build
```

### Backend (Laravel)
```bash
# Lokaal
cd D:\GitHub\Studieplanner-api
git add . && git commit -m "message" && git push

# Server
ssh root@188.245.159.115
cd /var/www/studieplanner-api
git pull origin master
composer install --no-dev
php artisan migrate
php artisan config:clear
```

## Contact

Eigenaar: Henk van Velzen
