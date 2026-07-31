---
title: Projects Index
type: reference
scope: havuncore
last_check: 2026-04-22
---

# Projects Index

> Overzicht van alle Havun projecten (bijgewerkt: 11 maart 2026)
> **Doc Intelligence:** Index van alle projecten bijgewerkt op 2026-03-10 (`docs:index all`).
> **Projectnamen:** Altijd met hoofdletter (JudoToernooi, Infosyst, HavunClub, etc.)

> ⛔ **De `Type`-kolom hieronder is een beschrijving, geen norm.** Dat de meeste projecten
> Laravel draaien, is geen reden dat het volgende dat ook doet. Een nieuw project begint met de
> vijf intake-vragen — [`standards/stack-keuze.md`](standards/stack-keuze.md) — en `project:scaffold`
> weigert te draaien zonder ingevulde `docs/intake.md`. Wat er gebeurt als die vraag uitblijft:
> [`patterns/fundament-versus-omweg.md`](patterns/fundament-versus-omweg.md).

| Project | Type | URL | Local | Server |
|---------|------|-----|-------|--------|
| **HavunCore** | Laravel 11 + Node.js | havuncore.havun.nl | D:\GitHub\HavunCore | /var/www/havuncore/production |
| **HavunAdmin** | Laravel 11 SaaS | havunadmin.havun.nl | D:\GitHub\HavunAdmin | /var/www/havunadmin/production |
| **Herdenkingsportaal** | Laravel 11 | herdenkingsportaal.nl | D:\GitHub\Herdenkingsportaal | /var/www/herdenkingsportaal/production |
| **Havun** | Next.js | havun.nl | D:\GitHub\Havun | /var/www/havun.nl |
| **JudoToernooi** | Laravel 11 SaaS | judotournament.org | D:\GitHub\JudoToernooi | /var/www/judotoernooi/laravel |
| **SafeHavun** | Laravel 12 + React | safehavun.havun.nl | D:\GitHub\SafeHavun | /var/www/safehavun/production |
| **Studieplanner** | Expo React Native + Laravel API | studieplanner.havun.nl | D:\GitHub\Studieplanner | /var/www/studieplanner/production |
| **Studieplanner-api** | Laravel 12 | (via /api/) | D:\GitHub\Studieplanner-api | /var/www/studieplanner/production |
| **Infosyst** | Laravel + Ollama | infosyst.havun.nl | D:\GitHub\infosyst | /var/www/infosyst/production |
| **IDSee** | Node.js + React | - | D:\GitHub\IDSee | (in development) |
| **JudoScoreBoard** | Expo React Native | (via JudoToernooi) | D:\GitHub\JudoScoreBoard | /var/www/judoscoreboard/ |
| **HavunClub** | Laravel 11 SaaS | havunclub.havun.nl | D:\GitHub\HavunClub | /var/www/havunclub/production |
| **VPDUpdate** | Node.js | - | D:\GitHub\VPDUpdate | (in development) |
| **Aeterna** | Tauri 2.0 + Rust + React | github.com/havun22-hvu/Aeterna | D:\GitHub\Aeterna | (geen server — multi-mirror distributie) |
| **VeenLedenadministratie** | Laravel 12 SaaS | — (server opgeruimd 31-07) | D:\GitHub\VeenLedenadministratie | ⛔ **geparkeerd** — lokaal blijft; oude app bij Cees op TransIP |
| **Vusista** | Laravel 12 + NativePHP (desktop-app) | **blijft staan** — bron voor Vusista2; weg mag pas als die áf is | D:\GitHub\Vusista | — (server opgeruimd 31-07) |
| **Vusista2** | Rust + Tauri v2 (desktop-app) | github.com/havun22-hvu/Vusista2 | D:\GitHub\Vusista2 | (geen server — release = installer) |

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
  - Staging: `/var/www/judotoernooi/staging`
- **Havun** - Bedrijfswebsite met portfolio

### In Development

- **HavunClub** - Multi-club SaaS ledenadministratie (judo/sport) — opgezet feb 2026
  - Gezinnen, abonnementen, Mollie recurring, bandexamens, QR check-in
  - Server: `/var/www/havunclub/production`, lokaal: `D:\GitHub\HavunClub`
- **SafeHavun** - Smart Money Crypto Tracker (whale alerts, sentiment)
- **Studieplanner** - Expo React Native Android app (v1.0.4) voor leerling-mentor studiesessies
  - Magic link + biometrie auth, eigen APK distributie, bunq.me + XRP betalingen, OTA updates
- **JudoScoreBoard** - Expo React Native scorebord app voor judo wedstrijden
  - Bediening (tablet/smartphone) + Display (Blade/TV), gekoppeld aan JudoToernooi API
- **Infosyst** - Gedistribueerde kennisbank (Henkiepedia) + eigen AI chat (Ollama)
  - Lokaal invoeren (SQLite + PWA), sync via Git JSON, server read-only wiki
- **IDSee** - (details volgen)
- **VPDUpdate** - Sync tool voor VPD data
- **Aeterna** - Soeverein desktop/mobile instrument voor ADA-transacties op Cardano. Tauri 2.0 + Rust, censuur-resistant by design. Brainstorm-fase mei 2026, geen code nog.
- **Vusista** - Picasa-opvolger op Laravel+NativePHP; dat fundament paste niet bij het werk (`patterns/fundament-versus-omweg.md`). **Blijft staan als bron voor Vusista2 — verwijderen pas als die áf is.** Niets meer repareren: V&K-scan staat bewust uit, KB-index blijft draaien.
- **Vusista2** - De herbouw in **Rust + Tauri v2**. Zelfde product, ander fundament: memory-mapped index, canvas-grid, gezichtsdetectie in-process (de C++-sidecar is weg). Proefmetingen: 66.844 thumbnails in vijf minuten, 60 fps grid. Release = installer via GitHub Releases, geen server.

## Voor project-specifieke info

Lees de docs in het project zelf:
```
{project}/CLAUDE.md
{project}/.claude/context.md
```
