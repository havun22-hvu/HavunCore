# Server Verhuizingen — 18 maart 2026

> **Doel:** Consistente directory structuur op de server. Alle projecten volgen het patroon `/var/www/{project}/{omgeving}`.

## Verhuizing 1: Studieplanner-api

| | Oud | Nieuw |
|---|-----|-------|
| **Pad** | `/var/www/studieplanner-api` | `/var/www/studieplanner/production` |
| **Reden** | Backend stond los in eigen map, niet onder `/var/www/studieplanner/` |

### Wat is aangepast

| Component | Bestand | Wijziging |
|-----------|---------|-----------|
| Nginx (site) | `/etc/nginx/sites-available/studieplanner` | root pad bijgewerkt |
| Nginx (API) | `/etc/nginx/sites-available/studieplanner-api` | root pad bijgewerkt |
| Systemd | `/etc/systemd/system/studieplanner-api.service` | WorkingDirectory bijgewerkt |
| Cron | root crontab | `cd` pad bijgewerkt |
| HavunCore | `DocIndexer.php` serverPaths | `studieplanner-api` pad bijgewerkt |
| Docs | `projects/studieplanner.md` | Server paden bijgewerkt |
| Docs | `projects-index.md` | Server pad bijgewerkt |
| Docs | `runbooks/deploy.md` | Deploy paden bijgewerkt |
| Docs | `reference/server.md` | Directory tree bijgewerkt |

### Niet gewijzigd (bewust)

- `/var/www/studieplanner/` map bestond al (was leeg) — nu bevat deze `production/`
- Nginx `sites-available/studieplanner.havun.nl` (duplicaat config, niet gelinkt in sites-enabled) — ongewijzigd
- Lokale paden (`D:\GitHub\Studieplanner-api`) — ongewijzigd, dat is een aparte repo
- SSL certificaten — ongewijzigd (domein is hetzelfde)
- Database — ongewijzigd

### Troubleshooting

Als de site niet werkt na deze verhuizing:
```bash
# Check of nginx het juiste pad gebruikt
grep -r 'studieplanner' /etc/nginx/sites-enabled/

# Check of systemd het juiste pad gebruikt
grep 'WorkingDirectory' /etc/systemd/system/studieplanner-api.service

# Check of cron het juiste pad gebruikt
crontab -l | grep studieplanner

# Alles moet verwijzen naar /var/www/studieplanner/production
```

---

## Verhuizing 2: JudoToernooi Staging

| | Oud | Nieuw |
|---|-----|-------|
| **Pad** | `/var/www/staging.judotoernooi/laravel` | `/var/www/judotoernooi/staging` |
| **Reden** | Staging stond in eigen top-level map, hoort onder `/var/www/judotoernooi/` |

### Wat is aangepast

| Component | Bestand | Wijziging |
|-----------|---------|-----------|
| Nginx | `/etc/nginx/sites-available/judotoernooi` | root pad staging server block bijgewerkt |
| Systemd | `/etc/systemd/system/judotoernooi-reverb-staging.service` | WorkingDirectory bijgewerkt |
| Supervisor | `/etc/supervisor/conf.d/reverb-staging.conf` | command + logfile pad bijgewerkt |
| HavunCore | `projects/judotoernooi.md` | Staging pad bijgewerkt |
| Docs | `projects-index.md` | Staging pad bijgewerkt |
| Docs | `runbooks/deploy.md` | Deploy pad bijgewerkt |
| Docs | `reference/server.md` | Directory tree bijgewerkt |

### Verwijderd

- `/etc/nginx/sites-available/judotoernooi.bak-20260203` — oude backup config
- `/var/www/staging.judotoernooi/` — lege restanten (CLAUDE.md, images, Scripts etc.) na verplaatsing van laravel/

### Niet gewijzigd (bewust)

- Production (`/var/www/judotoernooi/laravel`) — ongewijzigd
- Cron — er was geen staging cron job
- Lokale paden — ongewijzigd
- SSL certificaten — ongewijzigd
- Database — ongewijzigd

### Troubleshooting

Als staging niet werkt na deze verhuizing:
```bash
# Check nginx
grep 'staging' /etc/nginx/sites-available/judotoernooi

# Check systemd reverb
grep 'WorkingDirectory' /etc/systemd/system/judotoernooi-reverb-staging.service

# Check supervisor reverb
cat /etc/supervisor/conf.d/reverb-staging.conf

# Alles moet verwijzen naar /var/www/judotoernooi/staging
```

---

## Verhuizing 3: HavunCore

| | Oud | Nieuw |
|---|-----|-------|
| **Pad** | `/var/www/development/HavunCore` | `/var/www/havuncore/production` |
| **Reden** | Stond onder `development/` alsof het geen productie was, maar draait gewoon op havuncore.havun.nl |

### Wat is aangepast

| Component | Bestand | Wijziging |
|-----------|---------|-----------|
| Nginx | `/etc/nginx/sites-available/havuncore.havun.nl` | root pad bijgewerkt |
| Cron | root crontab (`docs:index all` elke 6 uur) | cd pad bijgewerkt |
| Scripts | `scripts/claude-task-poller.sh` | PROJECT_PATH bijgewerkt |
| Scripts | `scripts/setup-task-poller.sh` | cp pad bijgewerkt |
| HavunCore | `CLAUDE.md` | Server pad bijgewerkt |
| HavunCore | `README.md` | 3x pad bijgewerkt |
| HavunCore | `DocIndexer.php`, `StructureIndexer.php`, `DocWatchCommand.php` | serverPaths bijgewerkt |
| Docs | `projects-index.md`, `deploy.md`, `server.md`, `api-kb-search.md` | Paden bijgewerkt |
| Docs | `projects/HAVUNCORE.md` | Server pad bijgewerkt |

### Verwijderd

- `/etc/nginx/sites-available/havuncore.havun.nl.backup` — oude backup config
- `/etc/nginx/sites-available/havuncore.havun.nl.bak` — oude backup config
- `/var/www/development/` — lege map na verplaatsing

### Niet gewijzigd (bewust)

- `CHANGELOG.md` — historische vermeldingen, dat is logboek
- SSL certificaten — ongewijzigd (domein is hetzelfde)
- Database — ongewijzigd
- Systemd — geen HavunCore-specifieke service (draait via nginx+php-fpm)

### Troubleshooting

Als HavunCore niet werkt na deze verhuizing:
```bash
# Check nginx
grep 'root' /etc/nginx/sites-available/havuncore.havun.nl

# Check cron
crontab -l | grep havuncore

# Check task poller script
grep 'PROJECT_PATH' /usr/local/bin/claude-task-poller.sh

# Alles moet verwijzen naar /var/www/havuncore/production
```

---

## Verhuizing 4: HavunCore Webapp

| | Oud | Nieuw |
|---|-----|-------|
| **Pad** | `/var/www/havuncore.havun.nl` | `/var/www/havuncore/webapp` |
| **Reden** | Stond als losstaande map met domeinnaam, hoort onder `/var/www/havuncore/` |

### Wat is aangepast

| Component | Bestand | Wijziging |
|-----------|---------|-----------|
| Nginx | `/etc/nginx/sites-available/havuncore.havun.nl` | root pad bijgewerkt |
| PM2 | `ecosystem.config.cjs` | cwd bijgewerkt |
| .env | `backend/.env` | HAVUNCORE_PATH, DATABASE_PATH, LOG_FILE bijgewerkt |
| .env | `backend/.env.production` | HAVUNCORE_PATH, DATABASE_PATH, LOG_FILE, DOC_INTELLIGENCE_DB bijgewerkt |
| .env | `webapp/.env.production` | HAVUNCORE_PATH bijgewerkt |
| JS code | `backend/src/services/claudeCodeAgent.js` | project path mapping bijgewerkt |
| JS code | `backend/src/services/projectDelegation.js` | project path mapping bijgewerkt |
| JS code | `backend/src/aiOrchestrator.js` | default path + voorbeelden bijgewerkt |
| JS code | `backend/src/services/aiOrchestrator.js` | default path + voorbeelden bijgewerkt |
| JS code | `backend/src/services/claudeWithTools.js` | production path bijgewerkt |
| Docs | `reference/server.md` | Directory tree bijgewerkt |

### Niet gewijzigd (bewust)

- URLs (`https://havuncore.havun.nl`) — dat is het domein, niet het pad
- `WEBAUTHN_RP_ID=havuncore.havun.nl` — dat is een domein identifier
- `CORS_ORIGIN`, `APP_URL`, `WEBAUTHN_ORIGIN` — zijn URLs, geen file paden

### Troubleshooting

Als de webapp niet werkt na deze verhuizing:
```bash
# Check PM2 status + logs
pm2 status
pm2 logs havuncore-backend --lines 20

# Check nginx root
grep 'root' /etc/nginx/sites-available/havuncore.havun.nl

# Check .env paden
grep 'PATH\|DATABASE\|LOG_FILE' /var/www/havuncore/webapp/backend/.env.production

# Veel voorkomende fout: "HavunCore not found at: /var/www/development/HavunCore"
# → HAVUNCORE_PATH in .env wijst naar oud pad, moet /var/www/havuncore/production zijn
```

---

## Resultaat: Nieuwe directory structuur

```
/var/www/
├── havuncore/
│   ├── production/       # HavunCore Laravel API
│   └── webapp/           # React SPA + Node.js backend (PM2)
├── havunadmin/
│   ├── production/       # Volgt al het patroon
│   └── staging/
├── herdenkingsportaal/
│   ├── production/       # Volgt al het patroon
│   └── staging/
├── infosyst/
│   └── production/
├── judotoernooi/
│   ├── laravel/          # Production (judotournament.org)
│   └── staging/          # Staging (staging.judotournament.org)
├── studieplanner/
│   └── production/       # Production (studieplanner.havun.nl)
└── ...
```

Alle projecten volgen nu het patroon `/var/www/{project}/{omgeving}`.
