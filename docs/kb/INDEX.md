---
title: Knowledge Base Index
type: reference
scope: havuncore
last_check: 2026-04-22
---

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
│   ├── havunclub.md        ← ARCHIVED
│   ├── herdenkingsportaal.md
│   ├── infosyst.md
│   ├── judotoernooi.md
│   ├── judoscoreboard.md   ← Android app (Expo)
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
│   ├── magic-link-auth.md
│   ├── qr-code-url-matching.md
│   ├── regression-guard-tests.md  ← Regression/guard/smoke tests (VERPLICHT)
│   ├── integrity-check.md         ← Shadow file .integrity.json
│   └── website-builder.md
│
├── contracts/            ← Gedeelde definities tussen projecten
│   └── memorial-reference.md
│
├── templates/            ← Setup templates
│   ├── CLAUDE-template.md
│   ├── context-template.md
│   ├── claude-settings.json
│   ├── new-laravel-site.md
│   └── recent-regressions.md  ← 7-dagen rolling regression log
│
├── runbooks/             ← Algemene procedures
│   ├── backup.md
│   ├── chrome-testing.md
│   ├── claude-werkwijze.md       ← HOOFDDOCUMENT: werkwijze + beschermingslagen
│   ├── gemini-claude-workflow.md ← Gemini+Claude hybride pipeline (architectuur via Gemini, uitvoer via Claude)
│   ├── deploy.md
│   ├── deploy-safehavun.md
│   ├── doc-intelligence-setup.md
│   ├── eu-compliance-checklist.md ← Wettelijke verplichtingen webshops
│   ├── expo-android-app-setup.md  ← Android apps bouwen met Expo + Android Studio
│   ├── ggshield-setup.md
│   ├── md-file-audit.md
│   ├── op-reis-workflow.md
│   ├── project-cleanup.md
│   ├── ssl-monitoring.md
│   ├── sync-start-command.md
│   ├── server-verhuizingen-2026-03-18.md
│   ├── troubleshoot.md
│   ├── unified-login-procedure.md
│   ├── unified-login-system.md
│   ├── uptime-monitoring.md       ← Uptime doelen + UptimeRobot (VP-09)
│   ├── emergency-runbook.md       ← Noodprocedure voor backup-persoon (VP-07)
│   └── security-headers-check.md  ← Security headers + OWASP (VP-04)
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
    ├── 008-gemini-claude-hybrid-workflow.md ← Gemini=architect, Claude=executor
    └── auth-same-origin.md

docs/audit/                   ← Kwartaal-audit (extern)
├── werkwijze-beoordeling-derden.md   ← Beoordelingsdocument voor derden
└── verbeterplan-q2-2026.md           ← Verbeterplan 10 actiepunten (VP-01 t/m VP-10)
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
- [claude-werkwijze.md](runbooks/claude-werkwijze.md) - Werkwijze, DOCS-FIRST, beschermingslagen + Linter-Gate, PKM (alles-in-1)
- [eu-compliance-checklist.md](runbooks/eu-compliance-checklist.md) - Wettelijke verplichtingen online verkoop (KVK, herroeping, privacy)
- [expo-android-app-setup.md](runbooks/expo-android-app-setup.md) - Android apps bouwen (Expo + Android Studio)
- [github-actions-ci.md](runbooks/github-actions-ci.md) - GitHub Actions CI: automatische tests bij elke push
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
- [unified-login-procedure.md](runbooks/unified-login-procedure.md) - Stap-voor-stap login implementatie (v4.0)
- [unified-login-system.md](runbooks/unified-login-system.md) - Passkeys, QR, biometrie, magic link (v4.0)
- [uptime-monitoring.md](runbooks/uptime-monitoring.md) - Uptime doelen, SLA, UptimeRobot setup (VP-09)
- [emergency-runbook.md](runbooks/emergency-runbook.md) - Noodprocedure voor backup-persoon (VP-07)
- [security-headers-check.md](runbooks/security-headers-check.md) - Security headers check + OWASP ZAP (VP-04)

### Templates
- [recent-regressions.md](templates/recent-regressions.md) - 7-dagen rolling regression log template

### Reference
- [server.md](reference/server.md) - Server configuratie
- [api-taskqueue.md](reference/api-taskqueue.md) - Task Queue API
- [api-vault.md](reference/api-vault.md) - Vault API
- [backup-system.md](reference/backup-system.md) - Backup architectuur & Hetzner
- [external-services.md](reference/external-services.md) - Mollie, Anthropic, GitGuardian dashboards
- [unified-login-system.md](reference/unified-login-system.md) - Unified Login System v4.0 (wachtwoord, QR, biometrie, magic link)
- [ai-proxy.md](reference/ai-proxy.md) - AI proxy voor Claude API
- [postcode-service.md](reference/postcode-service.md) - Postcode lookup
- [security.md](reference/security.md) - Security richtlijnen
- [havun-ai-bridge.md](reference/havun-ai-bridge.md) - HavunAIBridge (vraag → KB → Ollama)
- [autofix.md](reference/autofix.md) - Autofix referentie
- [urenregistratie-2026.md](reference/urenregistratie-2026.md) - Urenregistratie
- [design-inspiration-session.md](reference/design-inspiration-session.md) - Design inspiration

### Patterns (herbruikbare code)
- [zinvolle-tests.md](patterns/zinvolle-tests.md) - **VERPLICHT:** Wat wel/niet testen (geen padding, alleen contract/invariant/regressie/domein)
- [regression-guard-tests.md](patterns/regression-guard-tests.md) - **VERPLICHT:** Regression/guard/smoke tests + coverage targets
- [integrity-check.md](patterns/integrity-check.md) - Shadow file `.integrity.json` validatie
- [email-verification.md](patterns/email-verification.md) - Email verificatie met 6-cijferige code
- [pdf-to-image-conversion.md](patterns/pdf-to-image-conversion.md) - PDF naar JPEG (Imagick)
- [pusher-realtime.md](patterns/pusher-realtime.md) - Real-time WebSockets met Pusher
- [mollie-payments.md](patterns/mollie-payments.md) - iDEAL | Wero, creditcard via Mollie
- [crypto-payments.md](patterns/crypto-payments.md) - XRP, ADA, SOL betalingen
- [arweave-upload.md](patterns/arweave-upload.md) - Permanente blockchain opslag
- [website-builder.md](patterns/website-builder.md) - Drag-and-drop pagina builder
- [invoice-numbering.md](patterns/invoice-numbering.md) - Factuurnummering
- [csrf-token-refresh.md](patterns/csrf-token-refresh.md) - CSRF token refresh
- [password-hashing.md](patterns/password-hashing.md) - Wachtwoord hashing
- [magic-link-auth.md](patterns/magic-link-auth.md) - Magic link registratie + wachtwoord vergeten (Laravel)
- [qr-code-url-matching.md](patterns/qr-code-url-matching.md) - QR code URL matching

### Projects (per-project details)
- [havunadmin.md](projects/havunadmin.md) - HavunAdmin boekhouding SaaS
- ~~havunclub.md~~ - HavunClub ledenadministratie (ARCHIVED)
- [herdenkingsportaal.md](projects/herdenkingsportaal.md) - Herdenkingsportaal memorial platform
- [infosyst.md](projects/infosyst.md) - Infosyst kennisbank + AI
- [judotoernooi.md](projects/judotoernooi.md) - JudoToernooi toernooi management
- [judoscoreboard.md](projects/judoscoreboard.md) - JudoScoreBoard Android app (Expo)
- [safehavun.md](projects/safehavun.md) - SafeHavun crypto tracker
- [studieplanner.md](projects/studieplanner.md) - Studieplanner Android app (Expo)
- [doc-intelligence-system.md](projects/doc-intelligence-system.md) - Doc Intelligence systeem

### Audit (kwartaallijks)
- [werkwijze-beoordeling-derden.md](../audit/werkwijze-beoordeling-derden.md) - Volledig werkwijze-overzicht ter beoordeling door derden
- [verbeterplan-q2-2026.md](../audit/verbeterplan-q2-2026.md) - Verbeterplan 10 actiepunten (Gemini + Claude Sonnet reviews)

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
