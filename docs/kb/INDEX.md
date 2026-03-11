# Knowledge Base Index

> HavunCore bevat ALLEEN gedeelde resources.
> Project-specifieke info staat in het project zelf.

## Lees eerst

- [OVERZICHT.md](OVERZICHT.md) - Compleet overzicht met uitleg van alle systemen
- [werkzaamheden-overzicht.md](werkzaamheden-overzicht.md) - Wat is gedaan, waar we staan, koppeling uren
- **Kennisbank doorzoeken/updaten:** `cd D:\GitHub\HavunCore && php artisan docs:index all --force` daarna `php artisan docs:detect`

## Structuur

```
docs/kb/
├── INDEX.md              ← Je bent hier
├── OVERZICHT.md          ← Compleet overzicht alle systemen
├── projects-index.md     ← Overzicht alle projecten (kort)
├── audit-rapport-2026-01-20.md
├── claude-workflow-enforcement.md
│
├── projects/             ← Per-project details
│   ├── doc-intelligence-system.md
│   ├── havunadmin.md
│   ├── havunclub.md
│   ├── herdenkingsportaal.md
│   ├── infosyst.md
│   ├── judotoernooi.md
│   ├── safehavun.md
│   └── studieplanner.md
│
├── patterns/             ← Herbruikbare code patterns
│   ├── arweave-upload.md
│   ├── crypto-payments.md
│   ├── csrf-token-refresh.md
│   ├── email-verification.md
│   ├── invoice-numbering.md
│   ├── mollie-payments.md
│   ├── password-hashing.md
│   ├── pdf-to-image-conversion.md
│   ├── pusher-realtime.md
│   ├── qr-code-url-matching.md
│   └── website-builder.md
│
├── contracts/            ← Gedeelde definities tussen projecten
│   └── memorial-reference.md
│
├── templates/            ← Setup templates
│   ├── CLAUDE-template.md
│   ├── context-template.md
│   ├── claude-settings.json
│   └── new-laravel-site.md
│
├── runbooks/             ← Algemene procedures
│   ├── backup.md
│   ├── chrome-testing.md
│   ├── claude-werkwijze.md
│   ├── deploy.md
│   ├── deploy-safehavun.md
│   ├── doc-intelligence-setup.md
│   ├── fix-qr-login-csrf.md
│   ├── ggshield-setup.md
│   ├── md-file-audit.md
│   ├── op-reis-workflow.md
│   ├── passkey-mobile-fix.md
│   ├── project-cleanup.md
│   ├── ssl-monitoring.md
│   ├── sync-start-command.md
│   ├── token-based-login.md
│   ├── troubleshoot.md
│   └── unified-login-system.md
│
├── reference/            ← API's en server
│   ├── ai-proxy.md
│   ├── api-taskqueue.md
│   ├── api-vault.md
│   ├── autofix.md
│   ├── backup-system.md
│   ├── design-inspiration-session.md
│   ├── external-services.md
│   ├── postcode-service.md
│   ├── security.md
│   ├── server.md
│   ├── urenregistratie-2026.md
│   └── unified-login-system.md
│
└── decisions/            ← Architectuur beslissingen
    ├── 001-havuncore-standalone.md
    ├── 002-decentrale-auth.md
    ├── 003-security-incident-ssh-key.md
    ├── 004-vision-orchestration.md
    ├── 005-studieplanner-architecture.md
    └── auth-same-origin.md
```

## Quick Links

### Essentials
- [projects-index.md](projects-index.md) - Overzicht alle projecten

### Internal (architectuur & hybrid flow)
- [architecture.md](../internal/architecture.md) - Database & metadata (doc_embeddings, uitbreiden bestandstypen, Node backend)
- [context-filter-flow.md](../internal/context-filter-flow.md) - Tap 1: Command-R filtert context → Claude (orchestrate.js, POST /api/intelligent)

### Contracts (gedeelde definities)
- [memorial-reference.md](contracts/memorial-reference.md) - Memorial Reference format

### Templates
- [new-laravel-site.md](templates/new-laravel-site.md) - Nieuwe site opzetten
- [context-template.md](templates/context-template.md) - Context.md template
- [CLAUDE-template.md](templates/CLAUDE-template.md) - CLAUDE.md template

### Runbooks (hoe doe ik X?)
- [claude-werkwijze.md](runbooks/claude-werkwijze.md) - Werkwijze, DOCS-FIRST, beschermingslagen, PKM (alles-in-1)
- [op-reis-workflow.md](runbooks/op-reis-workflow.md) - USB / op reis: credentials alleen, code via git
- [sync-start-command.md](runbooks/sync-start-command.md) - Start-command sync naar alle projecten
- [md-file-audit.md](runbooks/md-file-audit.md) - 2-wekelijkse documentatie audit
- [chrome-testing.md](runbooks/chrome-testing.md) - Browser testing met Claude for Chrome
- [deploy.md](runbooks/deploy.md) - Deployen naar server
- [deploy-safehavun.md](runbooks/deploy-safehavun.md) - Deploy SafeHavun
- [backup.md](runbooks/backup.md) - Backup systeem
- [troubleshoot.md](runbooks/troubleshoot.md) - Problemen oplossen
- [ssl-monitoring.md](runbooks/ssl-monitoring.md) - SSL certificaat monitoring
- [doc-intelligence-setup.md](runbooks/doc-intelligence-setup.md) - Doc Intelligence indexering
- [project-cleanup.md](runbooks/project-cleanup.md) - Project opschonen
- [ggshield-setup.md](runbooks/ggshield-setup.md) - GitGuardian pre-commit
- [token-based-login.md](runbooks/token-based-login.md) - Token-gebaseerde login
- [unified-login-system.md](runbooks/unified-login-system.md) - Passkeys, QR, PIN
- [fix-qr-login-csrf.md](runbooks/fix-qr-login-csrf.md) - QR login CSRF fix
- [passkey-mobile-fix.md](runbooks/passkey-mobile-fix.md) - Passkey op mobiel

### Reference
- [server.md](reference/server.md) - Server configuratie
- [api-taskqueue.md](reference/api-taskqueue.md) - Task Queue API
- [api-vault.md](reference/api-vault.md) - Vault API
- [backup-system.md](reference/backup-system.md) - Backup architectuur & Hetzner
- [external-services.md](reference/external-services.md) - Mollie, Anthropic, GitGuardian dashboards
- [unified-login-system.md](reference/unified-login-system.md) - Passkeys & QR login
- [ai-proxy.md](reference/ai-proxy.md) - AI proxy voor Claude API
- [postcode-service.md](reference/postcode-service.md) - Postcode lookup
- [security.md](reference/security.md) - Security richtlijnen
- [havun-ai-bridge.md](reference/havun-ai-bridge.md) - HavunAIBridge (vraag → KB → Ollama)
- [autofix.md](reference/autofix.md) - Autofix referentie
- [urenregistratie-2026.md](reference/urenregistratie-2026.md) - Urenregistratie
- [design-inspiration-session.md](reference/design-inspiration-session.md) - Design inspiration

### Patterns (herbruikbare code)
- [email-verification.md](patterns/email-verification.md) - Email verificatie met 6-cijferige code
- [pdf-to-image-conversion.md](patterns/pdf-to-image-conversion.md) - PDF naar JPEG (Imagick)
- [pusher-realtime.md](patterns/pusher-realtime.md) - Real-time WebSockets met Pusher
- [mollie-payments.md](patterns/mollie-payments.md) - iDEAL, creditcard via Mollie
- [crypto-payments.md](patterns/crypto-payments.md) - XRP, ADA, SOL betalingen
- [arweave-upload.md](patterns/arweave-upload.md) - Permanente blockchain opslag
- [website-builder.md](patterns/website-builder.md) - Drag-and-drop pagina builder
- [invoice-numbering.md](patterns/invoice-numbering.md) - Factuurnummering
- [csrf-token-refresh.md](patterns/csrf-token-refresh.md) - CSRF token refresh
- [password-hashing.md](patterns/password-hashing.md) - Wachtwoord hashing
- [qr-code-url-matching.md](patterns/qr-code-url-matching.md) - QR code URL matching

### Projects (per-project details)
- [havunadmin.md](projects/havunadmin.md) - HavunAdmin boekhouding SaaS
- [havunclub.md](projects/havunclub.md) - HavunClub ledenadministratie
- [herdenkingsportaal.md](projects/herdenkingsportaal.md) - Herdenkingsportaal
- [infosyst.md](projects/infosyst.md) - Infosyst kennisbank + AI
- [judotoernooi.md](projects/judotoernooi.md) - JudoToernooi
- [safehavun.md](projects/safehavun.md) - SafeHavun crypto tracker
- [studieplanner.md](projects/studieplanner.md) - Studieplanner app info
- [doc-intelligence-system.md](projects/doc-intelligence-system.md) - Doc Intelligence systeem

### Decisions (waarom zo?)
- [001-havuncore-standalone.md](decisions/001-havuncore-standalone.md) - HavunCore als standalone app
- [002-decentrale-auth.md](decisions/002-decentrale-auth.md) - Elke app eigen auth
- [003-security-incident-ssh-key.md](decisions/003-security-incident-ssh-key.md) - SSH key incident
- [004-vision-orchestration.md](decisions/004-vision-orchestration.md) - Multi-Claude Orchestration
- [005-studieplanner-architecture.md](decisions/005-studieplanner-architecture.md) - Studieplanner architecture

## Credentials

Credentials staan in: `HavunCore/.claude/context.md`

## Project-specifieke info

Voor info over een specifiek project, lees de docs IN dat project:

```
{project}/CLAUDE.md
{project}/.claude/context.md
```
