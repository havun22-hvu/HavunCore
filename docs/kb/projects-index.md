# Projects Index

> Overzicht van alle Havun projecten (bijgewerkt: 3 februari 2026)

| Project | Type | URL | Local | Server |
|---------|------|-----|-------|--------|
| **HavunCore** | Laravel 11 + Node.js | havuncore.havun.nl | D:\GitHub\HavunCore | /var/www/development/HavunCore |
| **HavunAdmin** | Laravel 11 SaaS | havunadmin.havun.nl | D:\GitHub\HavunAdmin | /var/www/havunadmin/production |
| **Herdenkingsportaal** | Laravel 11 | herdenkingsportaal.nl | D:\GitHub\Herdenkingsportaal | /var/www/herdenkingsportaal/production |
| **Havun** | Next.js | havun.nl | D:\GitHub\Havun | /var/www/havun.nl |
| **JudoToernooi** | Laravel 11 SaaS | judotournament.org | D:\GitHub\JudoToernooi | /var/www/judotoernooi/laravel |
| **SafeHavun** | Laravel 12 + React | safehavun.havun.nl | D:\GitHub\SafeHavun | /var/www/safehavun/production |
| **HavunVet** | Laravel 11 + Livewire | staging.havunvet.havun.nl | D:\GitHub\HavunVet | /var/www/havunvet/staging |
| **Studieplanner** | React PWA + Laravel API | studieplanner.havun.nl | D:\GitHub\Studieplanner | /var/www/studieplanner/production |
| **Studieplanner-api** | Laravel 11 | (via /api/) | D:\GitHub\Studieplanner-api | /var/www/studieplanner-api |
| **Infosyst** | Laravel + Ollama | infosyst.havun.nl | D:\GitHub\infosyst | /var/www/infosyst/production |
| **IDSee** | Node.js + React | - | D:\GitHub\IDSee | (in development) |
| **VPDUpdate** | Node.js | - | D:\GitHub\VPDUpdate | (in development) |

## Korte beschrijving

### Production (LIVE)

- **HavunCore** - Centrale orchestrator, Task Queue API, kennisbank
- **Herdenkingsportaal** - Memorial portal (⚠️ LIVE met echte klantdata!)
- **HavunAdmin** - Multi-tenant SaaS boekhouding & facturatie
  - Database per tenant (volledig geïsoleerd)
  - Unified login: PIN, biometric, QR, wachtwoord
- **JudoToernooi** - SaaS judo toernooi management
  - Multi-tenant: organisatoren huren platform
  - Coach Portal: coaches beheren hun judoka's
  - Mollie Connect + Platform mode
  - Staging: `/var/www/staging.judotoernooi/laravel`
- **Havun** - Bedrijfswebsite met portfolio

### In Development

- **SafeHavun** - Smart Money Crypto Tracker (whale alerts, sentiment)
- **HavunVet** - Dierenarts praktijkbeheer (ZZP), integratie met HavunAdmin
- **Studieplanner** - PWA voor leerling-mentor studiesessies
  - Pusher real-time, pincode auth
- **Infosyst** - Wikipedia-achtige kennisbank + eigen AI chat (Ollama)
- **IDSee** - (details volgen)
- **VPDUpdate** - Sync tool voor VPD data

## Voor project-specifieke info

Lees de docs in het project zelf:
```
{project}/CLAUDE.md
{project}/.claude/context.md
```
