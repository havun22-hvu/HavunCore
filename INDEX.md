# HavunCore Documentation Index

> Centrale kennisbank & orchestrator voor alle Havun projecten

## Start Here

- [README.md](README.md) - Project overview
- [CLAUDE.md](CLAUDE.md) - Instructies voor Claude AI
- [CHANGELOG.md](CHANGELOG.md) - Versie geschiedenis

## Knowledge Base

De volledige kennisbank staat in `docs/kb/`:

- [docs/kb/INDEX.md](docs/kb/INDEX.md) - Kennisbank navigatie
- [docs/kb/runbooks/claude-werkwijze.md](docs/kb/runbooks/claude-werkwijze.md) - Werkwijze, DOCS-FIRST, PKM (alles-in-1)
- [docs/kb/projects-index.md](docs/kb/projects-index.md) - Overzicht alle projecten

### Belangrijke secties

| Sectie | Inhoud |
|--------|--------|
| [runbooks/](docs/kb/runbooks/) | Hoe doe ik X? (deploy, backup, troubleshoot) |
| [reference/](docs/kb/reference/) | Server, API's, externe services |
| [patterns/](docs/kb/patterns/) | Herbruikbare code patterns |
| [decisions/](docs/kb/decisions/) | Architectuur beslissingen |

## Project Docs

| Folder | Inhoud |
|--------|--------|
| [docs/projects/](docs/projects/) | Per-project overzichten |

## Credentials

Credentials staan in: `.claude/context.md`

## Project Structuur

```
HavunCore/
├── CLAUDE.md              # Instructies voor Claude
├── INDEX.md               # Dit bestand
├── README.md
├── CHANGELOG.md
├── .claude/
│   ├── context.md         # Credentials, server info (niet in git)
│   ├── handover.md
│   ├── working-in-projects.md
│   └── commands/          # Slash commands: start, end, kb, audit, lint, md, test, update, errors
├── .github/
│   └── workflows/
│       └── api-contract-check.yml
├── app/                   # Laravel applicatie
│   ├── Console/Commands/  # DocIndex, DocSearch, DocDetectIssues, DocIssues
│   ├── Events/
│   ├── Http/Controllers/  # Api/, Web/
│   ├── Models/            # Auth*, Vault*, DocIntelligence/, MCPMessage, ClaudeTask, etc.
│   └── Services/          # QrAuth, AIProxy, DocIntelligence/, Postcode, DeviceTrust
├── config/                # app, database, cors, havun-backup, reverb, services, etc.
├── database/
│   └── migrations/       # auth, vault, claude_tasks, backup, doc_*, webauthn, mcp, ai_usage
├── docs/
│   ├── handover.md
│   ├── kb/                # Knowledge Base: INDEX.md, OVERZICHT.md, projects-index, patterns, runbooks, reference, decisions, contracts, templates
│   └── projects/         # HAVUNCORE, HAVUNADMIN, HERDENKINGSPORTAAL, VPDUPDATE, INDEX
├── public/
│   └── index.php
├── routes/               # api.php, web.php, console.php, channels.php
├── scripts/              # claude-task-poller.service, setup-task-poller.sh
├── src/                   # HavunCore package (Commands, Services, Models, Events, Listeners, Strategies, Traits, Testing)
├── stubs/                # Havun auth: views, HavunAuthController, config, routes
├── temp/                 # Tijdelijke blade/scripts
├── tests/
│   └── Pact/             # InvoiceSyncPactTest.php.example
├── tools/                # local-backup.ps1, usb-fix/
└── urenregistratie-2026.csv
```

---

**Laatste update:** 2026-03-10
