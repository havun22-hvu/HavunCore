# HavunCore - Claude Instructions

> **Role:** Centrale hub voor alle Havun projecten
> **Type:** Standalone Laravel 11 app + Task Queue API
> **URL:** https://havuncore.havun.nl

## Rules (ALWAYS follow)

### Forbidden without permission
- SSH keys, credentials, .env files wijzigen
- Database migrations op production
- Composer/npm packages installeren
- Systemd services, cron jobs aanpassen

### Communication
- Antwoord max 20-30 regels
- Bullet points, direct to the point
- Lange uitleg? Eerst samenvatting, details op vraag

### Workflow
- HavunCore ALLEEN lokaal bewerken (te kritiek voor Task Queue)
- Na wijzigingen: git push naar server
- Test lokaal eerst, dan deploy

## Quick Reference

| Project | Local | Server |
|---------|-------|--------|
| HavunCore | D:\GitHub\HavunCore | /var/www/development/HavunCore |
| HavunAdmin | D:\GitHub\HavunAdmin | /var/www/havunadmin/production |
| Herdenkingsportaal | D:\GitHub\Herdenkingsportaal | /var/www/herdenkingsportaal/production |

**Server:** 188.245.159.115 (root, SSH key)

## Knowledge Base

Zoek info in deze folders:

| Onderwerp | Locatie |
|-----------|---------|
| Server, credentials, API's | `.claude/context.md` |
| Per-project details | `docs/kb/projects/` |
| Hoe doe ik X? | `docs/kb/runbooks/` |
| API specs, referenties | `docs/kb/reference/` |
| Waarom beslissingen | `docs/kb/decisions/` |

## MCP Tools

Gebruik `mcp__havun__*` tools voor:
- `getMessages(project)` - Berichten voor project
- `readFile(project, path)` - Bestand lezen
- `scanMarkdownFiles()` - Alle docs scannen
