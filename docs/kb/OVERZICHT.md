# HavunCore Bibliotheek - Compleet Overzicht

> Laatst bijgewerkt: 2026-03-11
> Laatste full index (alle projecten): 2026-03-10 — `php artisan docs:index all --force`

Dit document geeft een compleet overzicht van alle systemen, methodes en kennis in de HavunCore bibliotheek.  
**Werkzaamheden (wat is gedaan, waar we staan):** [werkzaamheden-overzicht.md](werkzaamheden-overzicht.md)

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

### 4. Overige patterns
- **invoice-numbering.md** - Factuurnummering
- **csrf-token-refresh.md** - CSRF token refresh
- **password-hashing.md** - Wachtwoord hashing
- **qr-code-url-matching.md** - QR code URL matching
- **mollie-payments.md** - Mollie betalingen
- **crypto-payments.md** - Crypto betalingen
- **arweave-upload.md** - Arweave blockchain opslag
- **website-builder.md** - Drag-and-drop pagina builder

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

### Doc Intelligence Setup (`runbooks/doc-intelligence-setup.md`)
Indexering en issue-detectie voor MD-bestanden in projecten.

### Project Cleanup (`runbooks/project-cleanup.md`)
Project opschonen (dode code, missende indexes, etc.).

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
- Resend (email Herdenkingsportaal)
- Brevo (email JudoToernooi)

### AI Proxy (`reference/ai-proxy.md`)
AI proxy service voor Claude API calls.

### Postcode Service (`reference/postcode-service.md`)
Nederlandse postcode lookup service.

### Security (`reference/security.md`)
Security richtlijnen en best practices.

### Autofix (`reference/autofix.md`)
Autofix referentie.

### Urenregistratie 2026 (`reference/urenregistratie-2026.md`)
Urenregistratie overzicht.

### Design Inspiration Session (`reference/design-inspiration-session.md`)
Design inspiration sessie referentie.

### HavunAIBridge & Hybrid Flow
- **HavunAIBridge (PHP):** `scripts/HavunAIBridge.php` — vraag → PDO + cosine similarity op `doc_embeddings` → Ollama (Command-R). Zie `reference/havun-ai-bridge.md`.
- **Hybrid Flow (Node backend):** `backend/src/routes/orchestrate.js` — `POST /api/intelligent`: top 15 uit SQLite → Command-R filtert context → Claude (Sonnet) antwoordt. Zelfde SQLite in `backend/src/app.js` (HAVUNCORE_DB_PATH). Zie `docs/internal/context-filter-flow.md` en `docs/internal/architecture.md`.

---

## Projects

### HavunAdmin (`projects/havunadmin.md`)
Multi-tenant SaaS boekhouding & facturatie:
- Database-per-tenant met centrale user management
- Bunq import, Mollie sync, PDF parsing met Claude AI
- 97% belastingcompliant, audit trail, 7 jaar snapshots

### HavunClub (`projects/havunclub.md`)
Multi-club SaaS ledenadministratie (judo/sport):
- Leden, gezinnen, abonnementen, bandexamens
- Mollie recurring, QR check-in
- Opgezet feb 2026, in development

### Herdenkingsportaal (`projects/herdenkingsportaal.md`)
Memorial portaal (v3.0.80):
- Digitale herdenkingspagina's met blockchain (Arweave)
- Mollie betalingen, AI chatbot, PDF export
- AutoFix systeem actief

### Infosyst (`projects/infosyst.md`)
Gedistribueerde kennisbank (Henkiepedia):
- Lokaal invoeren (SQLite + PWA), sync via Git JSON
- Server read-only wiki + AI chat (Ollama)

### JudoToernooi (`projects/judotoernooi.md`)
SaaS judo toernooi management:
- Freemium model, Reverb WebSockets, double elimination
- Danpunten (JBN), offline server pakket, unified login
- AutoFix systeem actief

### Studieplanner (`projects/studieplanner.md`)
Expo React Native Android app (v1.0.4) voor leerling-mentor studiesessies:
- Magic link auth + biometrie, eigen APK distributie (geen Play Store)
- Laravel 12 API backend, bunq.me + XRP betalingen (€0 kosten)
- OTA updates via expo-updates, in-app APK download

### JudoScoreBoard (`projects/judoscoreboard.md`)
Expo React Native Android scorebord app voor judo wedstrijden:
- Bediening (tablet/smartphone) + Display (Blade/TV via Reverb)
- Gekoppeld aan JudoToernooi API, event-based sync, IJF scoring regels
- Expo SDK 55, landscape, foreground service timer, offline resilient

### SafeHavun (`projects/safehavun.md`)
Smart Money Crypto Tracker:
- Whale alerts, sentiment analyse
- Laravel 12 + React

### Doc Intelligence System (`projects/doc-intelligence-system.md`)
Doc indexering en semantic search voor alle projecten.

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

## Doc Intelligence (doorzoek alle projecten)

**Kennisbank bijwerken:** Indexeer en doorzoek alle projecten vanuit HavunCore:

```bash
cd D:\GitHub\HavunCore
php artisan docs:index all --force   # Indexeer alle MD-bestanden
php artisan docs:detect              # Detecteer broken links, etc.
php artisan docs:search "ZOEKTERM"   # Zoek in geïndexeerde docs
```

**Laatste index (2026-03-10):** havuncore 87, havunadmin 87, herdenkingsportaal 63, judotoernooi 86, infosyst 31, studieplanner 26, studieplanner-api 3, safehavun 23, havun 8, vpdupdate 23, idsee 21, havunvet 11, havuncore-webapp 28. HavunClub: pad niet gevonden lokaal.

---

## Structuur

```
docs/kb/
├── OVERZICHT.md      ← Dit bestand
├── INDEX.md          ← Quick links + volledige structuur
├── projects-index.md
├── audit-rapport-2026-01-20.md
├── claude-workflow-enforcement.md
│
├── patterns/         ← Herbruikbare code (11 bestanden)
├── runbooks/         ← How-to guides (15 bestanden)
├── reference/        ← Specificaties (12 bestanden)
├── projects/         ← Project details (8: havunadmin, havunclub, herdenkingsportaal, infosyst, judotoernooi, safehavun, studieplanner, doc-intelligence)
├── decisions/       ← Waarom zo? (6 bestanden)
├── templates/        ← new-laravel-site, context-template, CLAUDE-template, claude-settings.json
└── contracts/        ← memorial-reference.md
```

---

*Dit overzicht wordt automatisch bijgewerkt wanneer nieuwe systemen worden toegevoegd.*
