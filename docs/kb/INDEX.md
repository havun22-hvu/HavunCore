# Knowledge Base Index

> HavunCore bevat ALLEEN gedeelde resources.
> Project-specifieke info staat in het project zelf.

## Lees eerst

- **[[PKM-SYSTEEM]]** - Hoe werkt ons kennissysteem

## Structuur

```
docs/kb/
├── INDEX.md              ← Je bent hier
├── PKM-SYSTEEM.md        ← Hoe werkt het kennissysteem
├── credentials.md        ← Alle wachtwoorden en keys
├── projects-index.md     ← Overzicht alle projecten (kort)
│
├── contracts/            ← Gedeelde definities tussen projecten
│   └── memorial-reference.md
│
├── templates/            ← Setup templates
│   └── new-laravel-site.md
│
├── runbooks/             ← Algemene procedures
│   ├── deploy.md
│   ├── backup.md
│   └── troubleshoot.md
│
├── reference/            ← API's en server
│   ├── api-taskqueue.md
│   ├── api-vault.md
│   └── server.md
│
└── decisions/            ← Architectuur beslissingen
    ├── 001-havuncore-standalone.md
    ├── 002-decentrale-auth.md
    ├── 003-security-incident-ssh-key.md
    └── 004-vision-orchestration.md
```

## Quick Links

### Essentials
- [[credentials]] - Wachtwoorden, API keys, SSH
- [[projects-index]] - Overzicht alle projecten
- [[PKM-SYSTEEM]] - Hoe werkt dit systeem

### Contracts (gedeelde definities)
- [[contracts/memorial-reference]] - Memorial Reference format

### Templates
- [[templates/new-laravel-site]] - Nieuwe site opzetten

### Runbooks (hoe doe ik X?)
- [[runbooks/deploy]] - Deployen naar server
- [[runbooks/backup]] - Backup systeem
- [[runbooks/troubleshoot]] - Problemen oplossen

### Reference
- [[reference/api-taskqueue]] - Task Queue API
- [[reference/api-vault]] - Vault API
- [[reference/server]] - Server configuratie

### Decisions (waarom zo?)
- [[decisions/001-havuncore-standalone]] - HavunCore als standalone app
- [[decisions/002-decentrale-auth]] - Elke app eigen auth
- [[decisions/003-security-incident-ssh-key]] - SSH key incident (lessons learned)
- [[decisions/004-vision-orchestration]] - Multi-Claude Orchestration visie

## Project-specifieke info

Voor info over een specifiek project, lees de docs IN dat project:

```
{project}/CLAUDE.md
{project}/.claude/context.md
```
