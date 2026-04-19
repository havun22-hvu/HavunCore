---
title: Poort-register (productie + lokale dev)
type: reference
scope: alle-projecten
last_check: 2026-04-19
---

# Poort-register

> **Single source of truth** voor TCP-poorten op de Havun-productieserver
> én lokale dev-machines. Voorkomt conflicten binnen één app en tussen apps.
>
> **Spelregel:** voor je een nieuwe service / port-binding toevoegt — eerst
> dit document raadplegen, daarna direct hier bijwerken in dezelfde commit.

## Range-conventie (afspraken)

| Range | Doel | Voorbeelden |
|-------|------|-------------|
| 22, 53, 80, 443, 3306, 6379, 33060 | OS / infra (vastgelegd) | SSH, DNS, HTTP(S), MySQL, Redis |
| **30xx** | Node.js / Next.js productie services | havuncore-backend (3001), vpdupdate (3002), havun-website (3003) |
| **40xx** | Gereserveerd — volgende Node services | _vrij_ |
| **80xx** | Laravel `artisan serve` + Reverb (WebSocket) | Studieplanner-API (8001), Reverb prod (8080), Reverb staging (8081) |
| **83xx-84xx** | Tools (Syncthing GUI, etc.) | Syncthing GUI (8384) |
| **22xxx** | P2P / sync | Syncthing sync (22000) |
| **50xx (lokaal)** | Vite dev-server | Vite (5173) |
| **80xx (lokaal)** | Laravel `artisan serve` | havuncore Laravel dev (8000) |
| **10xx + 80xx (lokaal)** | Mailpit / MailHog | SMTP 1025, web 8025 |

> Als een nieuwe service binnen één range past, gebruik het volgende vrije nummer.
> Als de range vol is, open een nieuwe range hier voordat je iets bindt.

## Productie (188.245.159.115)

| Poort | Service | Project | Bind | Beheerder |
|-------|---------|---------|------|-----------|
| 22 | SSH | infra | `0.0.0.0` | system |
| 53 | DNS resolver | infra | `127.0.0.53` | systemd-resolved |
| 80 | HTTP | infra | `0.0.0.0` | nginx (→ 443 redirect) |
| 443 | HTTPS | infra | `0.0.0.0` | nginx |
| 3001 | **havuncore-backend** (Node) | havuncore-webapp | `0.0.0.0` | PM2 (`pm2-www-data.service`) |
| 3002 | **vpdupdate** (Node) | vpdupdate | `*` | PM2 (`pm2-www-data.service`) |
| 3003 | **havun-website** (Next.js) | havun-nl | `*` | PM2 (`pm2-www-data.service`) |
| 3306 | MySQL | infra | `127.0.0.1` | mysqld |
| 6379 | Redis | infra | `127.0.0.1` | redis-server |
| 8001 | **Studieplanner-API** | studieplanner | `127.0.0.1` | `php artisan serve` (systemd) |
| 8080 | **Reverb** (WebSocket) | judotoernooi-prod | `0.0.0.0` | Supervisor (`reverb`) |
| 8081 | **Reverb** (WebSocket) | judotoernooi-staging | `0.0.0.0` | Supervisor (`reverb-staging`) |
| 8384 | Syncthing GUI | infra | `127.0.0.1` | syncthing |
| 22000 | Syncthing sync | infra | `*` | syncthing |
| 33060 | MySQL X-protocol | infra | `127.0.0.1` | mysqld |

### Nginx → upstream mapping

| Public host | Upstream |
|-------------|----------|
| `havuncore.havun.nl` | `127.0.0.1:3001` (havuncore-backend) |
| `havun.nl` / `www.havun.nl` | `localhost:3003` (havun-website) |
| `judotournament.org` (WS) | `127.0.0.1:8080` (Reverb prod) |
| `staging.judotournament.org` (WS) | `127.0.0.1:8081` (Reverb staging) |
| (overige Laravel projecten) | php-fpm via Unix socket (geen TCP-poort) |

> vpdupdate (3002) en Studieplanner (8001) hebben geen publieke nginx-ingang — alleen interne calls via `localhost:`.

## Lokale dev (per ontwikkelaar)

> **Conventie:** hou lokale poorten gelijk aan productie waar mogelijk, om
> verwarring te voorkomen. Specifieke dev-tools (Vite, Mailpit) krijgen
> hun standaard-poort.

### Per project

| Project | Service | Poort lokaal | Notes |
|---------|---------|--------------|-------|
| HavunCore (Laravel) | `php artisan serve` | 8000 | default |
| HavunCore (Laravel) | Vite | 5173 | default |
| havuncore-webapp | Backend (Node) | 3001 | gelijk aan prod |
| havuncore-webapp | Frontend | _TODO bevestigen_ | mogelijke conflict met Laravel 8000 — anders 8002 |
| HavunAdmin | `php artisan serve` | 8000 | botst met HavunCore Laravel — niet beide tegelijk draaien, of override naar 8003 |
| Herdenkingsportaal | `php artisan serve` | 8000 | idem — gebruik `--port=8004` om parallel te draaien |
| JudoToernooi | `php artisan serve` | 8000 | idem |
| JudoToernooi | Reverb | 8080 | gelijk aan prod |
| Studieplanner-API | `php artisan serve` | 8001 | gelijk aan prod |
| Mailpit | SMTP | 1025 | gedeeld voor alle projecten |
| Mailpit | web | 8025 | gedeeld |

> **TODO:** Henk bevestigt of de lokale Node-frontend van havuncore-webapp op 8000 of 8002 draait, dan dit register bijwerken.

## Procedure: nieuwe service toevoegen

1. **Lees deze tabel** — vind een vrije poort in de juiste range.
2. **Update dit register** in dezelfde commit als de service-config.
3. **Gebruik bind-address bewust:**
   - `127.0.0.1` voor interne services (DB, Redis, dev-server, intern API)
   - `0.0.0.0` of `*` alleen als de service publiek of via nginx benaderd moet worden
4. **Bij PM2:** zet `PORT` in de `env` van de ecosystem-entry, niet hardcoded in code.
5. **Bij Laravel:** zet de poort in `.env` (`APP_PORT=8001`, `REVERB_PORT=8080`) of via systemd `--port=` flag.

## Bekende valkuilen

- **Memory zei `havuncore-backend = 8009`** (foutief, was 3001 al sinds .env.production v1). Gecorrigeerd 2026-04-19. Kijk altijd naar `.env.production` voor de echte port.
- **Reverb-poorten 8080/8081 zijn publiek** (`0.0.0.0`) — nodig voor WebSocket-verkeer, maar zorg dat nginx ze ook proxy't met SSL.
- **JudoToernooi heeft 2 Reverb-instances** (prod 8080 + staging 8081) op één host — niet samenvoegen.

## Zie ook

- `docs/kb/runbooks/pm2-as-www-data.md` — PM2-managed Node services (3001-3003)
- `docs/kb/runbooks/server-verhuizingen-2026-03-18.md` — pad-conventies waar deze poorten draaien
- `docs/kb/runbooks/kwaliteit-veiligheid-systeem.md` — `qv:scan` checkt SSL-certs op de publieke poorten
