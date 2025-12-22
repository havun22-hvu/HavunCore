# Project: Studieplanner

**URL:** https://studieplanner.havun.nl
**Repo:** D:\GitHub\Studieplanner (lokaal) | /var/www/studieplanner/production (server)
**Type:** Laravel + React PWA

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
- 4-cijferige pincode per gebruiker
- Pincode reset via email verificatie

### Pincode Reset Flow

Zie pattern: `docs/kb/patterns/email-verification.md`

```
1. Gebruiker klikt "Pincode vergeten"
2. Voert email in → ontvangt 6-cijferige code
3. Voert code in → krijgt reset token
4. Stelt nieuwe pincode in
```

### Benodigde implementatie

- [ ] Migration: `verification_codes` tabel
- [ ] Service: `EmailVerificationService`
- [ ] Mail: `VerificationCodeMail`
- [ ] Controller: `PincodeResetController`
- [ ] Frontend: Reset flow screens

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

## API Endpoints

```
POST /api/auth/login              - Login met email + pincode
POST /api/pincode-reset/request   - Vraag reset code aan
POST /api/pincode-reset/verify    - Valideer code
POST /api/pincode-reset/reset     - Stel nieuwe pincode in

POST /api/session/start           - Start studiesessie
POST /api/session/stop            - Stop sessie + evaluatie
GET  /api/session/active          - Mentor pollt voor updates

POST /api/chat/send               - Verstuur bericht
GET  /api/chat/messages           - Ophalen berichten
```

## Email Configuratie

Gebruikt zelfde SMTP als andere Havun projecten:
- Host: Zie `.env` of HavunCore Vault
- Van: noreply@studieplanner.havun.nl

## Deployment

```bash
# Lokaal
cd D:\GitHub\Studieplanner
git add . && git commit -m "message" && git push

# Server
ssh root@188.245.159.115
cd /var/www/studieplanner/production
git pull origin master
php artisan migrate
php artisan config:clear
```

## Contact

Eigenaar: Henk van Velzen
