# HavunCore Documentation Index

> Centrale kennisbank & orchestrator voor alle Havun projecten

## Start Here

- [README.md](README.md) - Project overview
- [CLAUDE.md](CLAUDE.md) - Instructies voor Claude AI
- [ARCHITECTURE.md](ARCHITECTURE.md) - Systeem architectuur
- [CHANGELOG.md](CHANGELOG.md) - Versie geschiedenis

## Knowledge Base

De volledige kennisbank staat in `docs/kb/`:

- [docs/kb/INDEX.md](docs/kb/INDEX.md) - Kennisbank navigatie
- [docs/kb/PKM-SYSTEEM.md](docs/kb/PKM-SYSTEEM.md) - Hoe werkt het kennissysteem
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
| [docs/setup/](docs/setup/) | Setup en MCP configuratie |

## Credentials

Credentials staan in: `.claude/context.md`

## Project Structuur

```
HavunCore/
├── CLAUDE.md              # Instructies voor Claude
├── .claude/
│   ├── context.md         # Credentials, server info
│   └── commands/          # Slash commands (/start, /kb, etc.)
├── docs/
│   ├── kb/                # Knowledge Base (hoofd-documentatie)
│   ├── projects/          # Per-project info
│   └── setup/             # Setup guides
├── app/                   # Laravel applicatie
├── database/              # Migrations, SQLite databases
└── src/                   # HavunCore package source
```

---

**Laatste update:** 2026-01-04
