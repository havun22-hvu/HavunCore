# Knowledge Base Index

> Centrale kennisbank voor alle Havun projecten.
> HavunCore is de hub, andere projecten verwijzen hierheen.

## Structuur

```
docs/kb/
├── INDEX.md          ← Je bent hier
├── projects/         ← Info per project
│   ├── havuncore.md
│   ├── havunadmin.md
│   ├── herdenkingsportaal.md
│   └── vpdupdate.md
├── runbooks/         ← Hoe doe ik X?
│   ├── deploy.md
│   ├── backup.md
│   ├── troubleshoot.md
│   └── new-project.md
├── reference/        ← API's, specs
│   ├── api-taskqueue.md
│   ├── api-vault.md
│   └── server.md
└── decisions/        ← Waarom beslissingen (ADRs)
    ├── 001-havuncore-standalone.md
    └── 002-decentrale-auth.md
```

## Quick Links

### Projects
- [[projects/havuncore]] - Centrale orchestratie platform
- [[projects/havunadmin]] - Boekhouding & facturatie
- [[projects/herdenkingsportaal]] - Memorial portal
- [[projects/vpdupdate]] - Sync tool

### Runbooks
- [[runbooks/deploy]] - Deployen naar production
- [[runbooks/backup]] - Backup systeem
- [[runbooks/troubleshoot]] - Problemen oplossen

### Reference
- [[reference/api-taskqueue]] - Task Queue API
- [[reference/api-vault]] - Vault API
- [[reference/server]] - Server configuratie
