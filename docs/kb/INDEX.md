# Knowledge Base Index

> HavunCore bevat ALLEEN gedeelde resources.
> Project-specifieke info staat in het project zelf.

## Lees eerst

- [OVERZICHT.md](OVERZICHT.md) - Compleet overzicht met uitleg van alle systemen
- [PKM-SYSTEEM.md](PKM-SYSTEEM.md) - Hoe werkt ons kennissysteem

## Structuur

```
docs/kb/
├── INDEX.md              ← Je bent hier
├── PKM-SYSTEEM.md        ← Hoe werkt het kennissysteem
├── projects-index.md     ← Overzicht alle projecten (kort)
│
├── projects/             ← Per-project details
│   ├── studieplanner.md
│   ├── safehavun.md
│   └── doc-intelligence-system.md
│
├── patterns/             ← Herbruikbare code patterns
│   ├── email-verification.md
│   ├── mollie-payments.md
│   └── ...
│
├── contracts/            ← Gedeelde definities tussen projecten
│   └── memorial-reference.md
│
├── templates/            ← Setup templates
│   └── new-laravel-site.md
│
├── runbooks/             ← Algemene procedures
│   ├── claude-werkwijze.md
│   ├── docs-first-workflow.md
│   ├── deploy.md
│   ├── backup.md
│   └── ...
│
├── reference/            ← API's en server
│   ├── server.md
│   ├── api-taskqueue.md
│   ├── api-vault.md
│   └── ...
│
└── decisions/            ← Architectuur beslissingen
    ├── 001-havuncore-standalone.md
    └── ...
```

## Quick Links

### Essentials
- [projects-index.md](projects-index.md) - Overzicht alle projecten
- [PKM-SYSTEEM.md](PKM-SYSTEEM.md) - Hoe werkt dit systeem

### Contracts (gedeelde definities)
- [memorial-reference.md](contracts/memorial-reference.md) - Memorial Reference format

### Templates
- [new-laravel-site.md](templates/new-laravel-site.md) - Nieuwe site opzetten

### Runbooks (hoe doe ik X?)
- [claude-werkwijze.md](runbooks/claude-werkwijze.md) - LEES-DENK-DOE-DOCUMENTEER (kritiek!)
- [docs-first-workflow.md](runbooks/docs-first-workflow.md) - DOCS-FIRST workflow
- [md-file-audit.md](runbooks/md-file-audit.md) - 2-wekelijkse documentatie audit
- [deploy.md](runbooks/deploy.md) - Deployen naar server
- [backup.md](runbooks/backup.md) - Backup systeem
- [troubleshoot.md](runbooks/troubleshoot.md) - Problemen oplossen
- [ssl-monitoring.md](runbooks/ssl-monitoring.md) - SSL certificaat monitoring

### Reference
- [server.md](reference/server.md) - Server configuratie
- [api-taskqueue.md](reference/api-taskqueue.md) - Task Queue API
- [api-vault.md](reference/api-vault.md) - Vault API
- [backup-system.md](reference/backup-system.md) - Backup architectuur & Hetzner
- [external-services.md](reference/external-services.md) - Mollie, Anthropic, GitGuardian dashboards
- [unified-login-system.md](reference/unified-login-system.md) - Passkeys & QR login

### Patterns (herbruikbare code)
- [email-verification.md](patterns/email-verification.md) - Email verificatie met 6-cijferige code
- [pdf-to-image-conversion.md](patterns/pdf-to-image-conversion.md) - PDF naar JPEG (Imagick)
- [pusher-realtime.md](patterns/pusher-realtime.md) - Real-time WebSockets met Pusher
- [mollie-payments.md](patterns/mollie-payments.md) - iDEAL, creditcard via Mollie
- [crypto-payments.md](patterns/crypto-payments.md) - XRP, ADA, SOL betalingen
- [arweave-upload.md](patterns/arweave-upload.md) - Permanente blockchain opslag
- [website-builder.md](patterns/website-builder.md) - Drag-and-drop pagina builder

### Projects (per-project details)
- [studieplanner.md](projects/studieplanner.md) - Studieplanner app info
- [safehavun.md](projects/safehavun.md) - SafeHavun crypto tracker
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
