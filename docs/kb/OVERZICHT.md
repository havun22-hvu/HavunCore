# HavunCore Bibliotheek - Compleet Overzicht

> Laatst bijgewerkt: 2025-12-30

Dit document geeft een compleet overzicht van alle systemen, methodes en kennis in de HavunCore bibliotheek.

---

## Herbruikbare Patterns

### 1. Email Verificatie (`patterns/email-verification.md`)
**Wat:** 6-cijferige verificatiecode systeem voor email bevestiging en password reset.

**Gebruik:**
- Registratie flow met email verificatie
- Password/pincode reset via email
- Tijdelijke codes met expiratie

**Implementatie:**
- Laravel backend met `verification_codes` tabel
- Codes verlopen na 15 minuten
- Rate limiting tegen misbruik

---

### 2. PDF naar Image Conversie (`patterns/pdf-to-image-conversion.md`)
**Wat:** PDF pagina's converteren naar JPEG afbeeldingen met ImageMagick.

**Gebruik:**
- Preview genereren van PDF documenten
- Thumbnails voor document overzichten
- Print-ready afbeeldingen maken

**Implementatie:**
- PHP Imagick extension
- Configurable DPI en kwaliteit
- Batch processing voor meerdere pagina's

---

### 3. Pusher Real-time WebSockets (`patterns/pusher-realtime.md`)
**Wat:** Real-time communicatie tussen users via WebSockets.

**Gebruik:**
- Live chat
- Instant notificaties
- Online status (wie is online)
- Mentor-leerling synchronisatie

**Implementatie:**
- Backend: Laravel + `pusher/pusher-php-server`
- Frontend: React + `laravel-echo` + `pusher-js`
- Channels: public, private (auth), presence (online status)

**Account:** Pusher.com via GitHub havun22-hvu

---

## Runbooks (How-To Guides)

### Deploy (`runbooks/deploy.md`)
Standaard deployment procedure voor alle Laravel projecten:
- Git pull
- Composer install
- Migrations
- Cache clear
- Queue restart

### Deploy SafeHavun (`runbooks/deploy-safehavun.md`)
Specifieke deployment voor SafeHavun met extra stappen.

### Backup (`runbooks/backup.md`)
Backup procedures:
- Automatische dagelijkse backups
- Hetzner Storage Box als bestemming
- Encryptie met GPG
- Restore procedures

### Troubleshoot (`runbooks/troubleshoot.md`)
Veelvoorkomende problemen en oplossingen:
- 500 errors debuggen
- Permission issues
- Queue failures
- Database connectie problemen

### GGShield Setup (`runbooks/ggshield-setup.md`)
GitGuardian pre-commit hook installeren:
- Voorkomt dat secrets in git komen
- Automatische scanning bij elke commit

### SSL Monitoring (`runbooks/ssl-monitoring.md`)
SSL certificaat monitoring en vernieuwing:
- Let's Encrypt auto-renewal
- Handmatige renewal procedure
- Monitoring alerts

### Token Based Login (`runbooks/token-based-login.md`)
Token-gebaseerde authenticatie implementatie.

### Unified Login System (`runbooks/unified-login-system.md`)
Centrale login implementatie met:
- Passkeys (biometrie)
- QR code login
- PIN code

### Fix QR Login CSRF (`runbooks/fix-qr-login-csrf.md`)
Oplossing voor CSRF issues bij QR code login.

### Passkey Mobile Fix (`runbooks/passkey-mobile-fix.md`)
Fixes voor passkey authenticatie op mobiele devices.

---

## Reference (Specificaties)

### Task Queue API (`reference/api-taskqueue.md`)
**Endpoint:** `https://havuncore.havun.nl/api/claude/tasks`

Centrale taak orchestratie voor Claude instances:
- Tasks aanmaken en ophalen
- Status updates
- Cross-project communicatie

### Vault API (`reference/api-vault.md`)
**Endpoint:** `https://havuncore.havun.nl/api/vault/`

Secrets management:
- API keys opslaan/ophalen
- Credentials centraal beheren
- Encryptie at rest

### Unified Login System (`reference/unified-login-system.md`)
Specificaties voor het centrale login systeem:
- Passkey/WebAuthn flow
- QR code authenticatie
- PIN code met numpad
- Session management

### Backup System (`reference/backup-system.md`)
Backup architectuur:
- Hetzner Storage Box configuratie
- Retention policies
- Encryptie details

### Server (`reference/server.md`)
Server configuratie:
- IP: 188.245.159.115
- Nginx configs
- PHP-FPM settings
- Project paths

### Security (`reference/security.md`)
Security richtlijnen en best practices.

### External Services (`reference/external-services.md`)
Externe API's en dashboards:
- Mollie (betalingen)
- Anthropic (Claude AI)
- GitGuardian (secret scanning)
- SendGrid (email)

### AI Proxy (`reference/ai-proxy.md`)
AI proxy service voor Claude API calls.

### Postcode Service (`reference/postcode-service.md`)
Nederlandse postcode lookup service.

---

## Projects

### Studieplanner (`projects/studieplanner.md`)
Mentor-leerling studiesessie tracking app:
- React PWA frontend
- Laravel API backend
- Pincode authenticatie
- Pusher real-time sync

### SafeHavun (`projects/safehavun.md`)
Wachtwoord manager applicatie.

---

## Architecture Decisions

### ADR-001: HavunCore Standalone
HavunCore als standalone Laravel app ipv package.

### ADR-002: Decentrale Auth
Elke app beheert eigen authenticatie (geen centrale SSO).

### ADR-003: Security Incident SSH Key
Lessons learned van SSH key incident.

### ADR-004: Vision Orchestration
Multi-Claude orchestratie visie.

### ADR-005: Studieplanner Architecture
Architectuur keuzes voor Studieplanner.

### Auth Same Origin
Same-origin authenticatie beslissing.

---

## Templates

### New Laravel Site (`templates/new-laravel-site.md`)
Template voor nieuwe Laravel site opzetten:
- Nginx config
- SSL setup
- Database aanmaken
- .env configuratie

---

## Contracts

### Memorial Reference (`contracts/memorial-reference.md`)
Gedeelde definitie voor memorial/herdenkings referenties tussen projecten.

---

## Credentials & Config

**Locatie:** `.claude/context.md` (niet in git!)

Bevat:
- Server credentials
- Database wachtwoorden
- API keys (Mollie, Anthropic, GitGuardian)
- Pusher credentials
- Hetzner backup credentials

---

## Structuur

```
docs/kb/
├── OVERZICHT.md      ← Dit bestand
├── INDEX.md          ← Quick links
├── PKM-SYSTEEM.md    ← Hoe werkt het kennissysteem
│
├── patterns/         ← Herbruikbare code
│   ├── email-verification.md
│   ├── pdf-to-image-conversion.md
│   └── pusher-realtime.md
│
├── runbooks/         ← How-to guides
│   ├── deploy.md
│   ├── backup.md
│   ├── troubleshoot.md
│   └── ...
│
├── reference/        ← Specificaties
│   ├── api-taskqueue.md
│   ├── api-vault.md
│   └── ...
│
├── projects/         ← Project details
│   ├── studieplanner.md
│   └── safehavun.md
│
├── decisions/        ← Waarom zo?
│   └── 001-*.md
│
├── templates/        ← Setup templates
│   └── new-laravel-site.md
│
└── contracts/        ← Gedeelde definities
    └── memorial-reference.md
```

---

*Dit overzicht wordt automatisch bijgewerkt wanneer nieuwe systemen worden toegevoegd.*
