# Projects Index

> Overzicht van alle Havun projecten

| Project | Type | URL | Local | Server |
|---------|------|-----|-------|--------|
| **HavunCore** | Laravel + Node.js | havuncore.havun.nl | D:\GitHub\HavunCore | /var/www/development/HavunCore |
| **HavunAdmin** | Laravel | havunadmin.havun.nl | D:\GitHub\HavunAdmin | /var/www/havunadmin/production |
| **Herdenkingsportaal** | Laravel | herdenkingsportaal.nl | D:\GitHub\Herdenkingsportaal | /var/www/herdenkingsportaal/production |
| **Havun** | Next.js | havun.nl | D:\GitHub\Havun | /var/www/havun.nl |
| **Judotoernooi** | Laravel | judotournament.org | - | /var/www/judotoernooi |
| **VPDUpdate** | Node.js | - | D:\GitHub\VPDUpdate | (nog niet deployed) |
| **Infosyst** | Laravel + Ollama | infosyst.havun.nl | D:\GitHub\infosyst | /var/www/infosyst/production |
| **Studieplanner** | Laravel | studieplanner.havun.nl | D:\GitHub\Studieplanner | /var/www/studieplanner/production |

## Korte beschrijving

- **HavunCore** - Centrale orchestrator, Task Queue API, Webapp
- **Havun** - Bedrijfswebsite met portfolio
- **Judotoernooi** - Judo toernooi management systeem
  - `judotournament.org` = publieke introsite
  - Open Wegsrijzen Cees Veen = password protected (voor Cees Veen)
- **HavunAdmin** - Boekhouding en facturatie
- **Herdenkingsportaal** - Memorial portal voor gedenkpagina's (LIVE met klantdata!)
- **VPDUpdate** - Sync tool voor VPD data (in development)
- **Infosyst** - Wikipedia-achtige kennisbank + eigen AI chat (Ollama op PC via tunnel)
- **Studieplanner** - Planning tool voor leerlingen/mentoren met vakken, huiswerk, notificaties
  - PWA met eigen Laravel backend
  - Mentor-leerling koppeling via API
  - Polling voor notificaties (geen Firebase)
  - Zie: [[decisions/005-studieplanner-architecture]]

## Voor project-specifieke info

Lees de docs in het project zelf:
```
{project}/CLAUDE.md
{project}/.claude/context.md
```
