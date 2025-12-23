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

## Credentials Locaties

> **Let op:** Credentials NOOIT in code of publieke docs!

| Wat | Waar te vinden |
|-----|----------------|
| DB wachtwoord | Kopieer van server: `/var/www/herdenkingsportaal/production/.env` (DB_PASSWORD) |
| SendGrid API key | Kopieer van server: `/var/www/herdenkingsportaal/production/.env` (MAIL_PASSWORD) |
| APP_KEY | Genereer nieuw: `php artisan key:generate` |

**Server .env setup:**
```bash
# Op server, kopieer credentials van bestaand project:
ssh root@188.245.159.115
cat /var/www/herdenkingsportaal/production/.env | grep -E "^(DB_PASSWORD|MAIL_)"
# Gebruik deze waarden in /var/www/studieplanner-api/.env
```

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

## Implementatie Roadmap

### Fase 1: Backend Auth ✅ KLAAR (22 dec 2025)
- [x] Laravel 11 + Sanctum project aangemaakt
- [x] User model met pincode + verificatie velden
- [x] AuthController met register/login/reset endpoints
- [x] Email templates (plain text)

### Fase 2: Email Service (TODO - eind week)
- [ ] SendGrid configureren (zelfde account als Herdenkingsportaal)
- [ ] Domain authenticeren: `studieplanner.havun.nl`
- [ ] `.env` configureren met SendGrid API key
- [ ] Test email versturen

**SendGrid config voor `.env`:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.xxxxx  # Uit HavunCore Vault of Herdenkingsportaal
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@studieplanner.havun.nl
MAIL_FROM_NAME="StudiePlanner"
```

### Fase 3: Backend Deploy (TODO)
- [ ] Server folder aanmaken: `/var/www/studieplanner-api`
- [ ] Git clone + composer install
- [ ] Database aanmaken: `studieplanner`
- [ ] `.env` configureren op server
- [ ] Nginx config voor API subdomain
- [ ] SSL certificaat

### Fase 4: Frontend Koppeling (TODO)
- [ ] API base URL configureren in React app
- [ ] Auth flows implementeren (register, login, reset)
- [ ] Token opslaan in localStorage
- [ ] Protected routes

### Fase 5: Mentor Features (TODO)
- [ ] Sessie endpoints (start/stop)
- [ ] Mentor notificaties (polling)
- [ ] Chat endpoints

---

## Contact

Eigenaar: Henk van Unen
