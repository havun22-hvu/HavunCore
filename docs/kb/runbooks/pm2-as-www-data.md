---
title: PM2 op productie als www-data (least-privilege Node.js services)
type: runbook
scope: havuncore-webapp, vpdupdate, havun-website
last_check: 2026-04-19
---

# PM2 als www-data — runbook

> **Doel:** Node-services op productie draaien als `www-data` (least-privilege), niet als root. Eén systemd-unit (`pm2-www-data.service`), één centrale ecosystem-config.

## Architectuur

| Onderdeel | Waarde |
|-----------|--------|
| systemd unit | `pm2-www-data.service` (`/etc/systemd/system/`) |
| User | `www-data` |
| `PM2_HOME` | `/var/www/.pm2` |
| `WorkingDirectory` | `/var/www/.pm2` |
| Ecosystem | `/var/www/.pm2/ecosystem.config.js` |
| Dump | `/var/www/.pm2/dump.pm2` |
| Logs | `/var/www/.pm2/logs/{name}-{out\|error}.log` |
| PID | `/var/www/.pm2/pm2.pid` |

## Apps

| Naam | Cwd | Poort | Start |
|------|-----|-------|-------|
| `havun-website` | `/var/www/havun.nl` | 3003 | `npm start -- -p 3003` (Next.js) |
| `havuncore-backend` | `/var/www/havuncore/webapp` | **3001** | `node --env-file=backend/.env.production backend/src/server.js` |
| `vpdupdate` | `/var/www/vpdupdate` | 3002 | `node server-v2.js` |

> **Let op:** `havuncore-backend` draait op poort **3001**, niet 8009. Nginx proxypass zit op 3001.

## Veelgebruikte commando's

> Voer deze uit op de server (`ssh root@188.245.159.115`). Begin altijd met `cd /var/www/.pm2` — anders erft `sudo -u www-data` een onleesbare cwd (`/root`) en faalt PM2 met `EACCES`.

```bash
cd /var/www/.pm2

# Status
sudo -u www-data HOME=/var/www/.pm2 PM2_HOME=/var/www/.pm2 pm2 list

# Restart één app
sudo -u www-data HOME=/var/www/.pm2 PM2_HOME=/var/www/.pm2 pm2 restart havuncore-backend

# Logs
sudo -u www-data HOME=/var/www/.pm2 PM2_HOME=/var/www/.pm2 pm2 logs havuncore-backend --lines 100

# Na ecosystem-wijziging — herlaad config + persisteer dump
sudo -u www-data HOME=/var/www/.pm2 PM2_HOME=/var/www/.pm2 pm2 reload /var/www/.pm2/ecosystem.config.js
sudo -u www-data HOME=/var/www/.pm2 PM2_HOME=/var/www/.pm2 pm2 save

# Service-niveau (root)
systemctl status pm2-www-data.service
systemctl restart pm2-www-data.service
```

## Volledige ecosystem.config.js (live op `/var/www/.pm2/`)

```js
module.exports = {
  apps: [
    {
      name: 'vpdupdate',
      cwd: '/var/www/vpdupdate',
      script: 'server-v2.js',
      interpreter: 'node',
      env: { NODE_ENV: 'production', PORT: 3002 },
      autorestart: true,
      max_memory_restart: '500M',
      out_file: '/var/www/.pm2/logs/vpdupdate-out.log',
      error_file: '/var/www/.pm2/logs/vpdupdate-error.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
    },
    {
      name: 'havun-website',
      cwd: '/var/www/havun.nl',
      script: 'npm',
      args: 'start -- -p 3003',
      autorestart: true,
      max_memory_restart: '500M',
      out_file: '/var/www/.pm2/logs/havun-website-out.log',
      error_file: '/var/www/.pm2/logs/havun-website-error.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
    },
    {
      name: 'havuncore-backend',
      cwd: '/var/www/havuncore/webapp',
      script: 'backend/src/server.js',
      interpreter: 'node',
      node_args: '--env-file=backend/.env.production',
      env: { NODE_ENV: 'production' },
      autorestart: true,
      max_memory_restart: '500M',
      out_file: '/var/www/.pm2/logs/havuncore-backend-out.log',
      error_file: '/var/www/.pm2/logs/havuncore-backend-error.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
    },
  ],
};
```

## Volledige systemd unit (`/etc/systemd/system/pm2-www-data.service`)

```ini
[Unit]
Description=PM2 process manager
Documentation=https://pm2.keymetrics.io/
After=network.target

[Service]
Type=forking
User=www-data
LimitNOFILE=infinity
LimitNPROC=infinity
LimitCORE=infinity
Environment=PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
Environment=PM2_HOME=/var/www/.pm2
WorkingDirectory=/var/www/.pm2
PIDFile=/var/www/.pm2/pm2.pid
Restart=on-failure
RestartSec=5

ExecStart=/usr/lib/node_modules/pm2/bin/pm2 resurrect
ExecReload=/usr/lib/node_modules/pm2/bin/pm2 reload all
ExecStop=/usr/lib/node_modules/pm2/bin/pm2 kill

[Install]
WantedBy=multi-user.target
```

## Toevoegen van een nieuwe Node-service

1. Zorg dat de app-directory en alle write-paden (logs, cache, sqlite) eigendom zijn van `www-data`:
   ```bash
   chown -R www-data:www-data /var/www/<app>/{logs,data,.next/cache}
   ```
2. Voeg een entry toe aan `/var/www/.pm2/ecosystem.config.js`:
   ```js
   {
     name: 'mijn-app',
     cwd: '/var/www/mijn-app',
     script: 'index.js',
     interpreter: 'node',
     env: { NODE_ENV: 'production', PORT: 3010 },
     autorestart: true,
     max_memory_restart: '500M',
     out_file: '/var/www/.pm2/logs/mijn-app-out.log',
     error_file: '/var/www/.pm2/logs/mijn-app-error.log',
   }
   ```
3. Reload + save:
   ```bash
   cd /var/www/.pm2
   sudo -u www-data HOME=/var/www/.pm2 PM2_HOME=/var/www/.pm2 pm2 start /var/www/.pm2/ecosystem.config.js
   sudo -u www-data HOME=/var/www/.pm2 PM2_HOME=/var/www/.pm2 pm2 save
   ```

## Bekende valkuilen

1. **`spawn /usr/bin/node EACCES`** — `sudo -u www-data` erft cwd; als die in `/root` of een andere onleesbare dir staat faalt fork(). Fix: altijd `cd /var/www/.pm2` eerst.
2. **`SqliteError: attempt to write a readonly database`** — DB-file of parent dir is nog root-owned. Chown het hele `data/`-pad naar www-data, niet alleen de file (WAL-mode wil schrijven naast de db).
3. **`pm2 startup` zet `PM2_HOME=/var/www/.pm2/.pm2`** (dubbel) als je `--hp /var/www/.pm2` opgeeft. Schrijf de unit liever handmatig — zie boven.
4. **`Can't open PID file ... Operation not permitted`** — een handmatig gestarte daemon schrijft géén `pm2.pid`. Alleen een via systemd-gestarte daemon doet dat. Bij twijfel: `pm2 kill` + `systemctl start pm2-www-data`.
5. **www-data home is `/var/www`** (uit `/etc/passwd`) maar die is niet writable voor www-data. Daarom expliciet `HOME=/var/www/.pm2` meegeven aan elk pm2-commando.

## Rollback (als de migratie ooit terug moet)

Backup van de oude root-dump staat op `/root/.pm2/dump.pm2.pre-www-data-20260419-105250` (bewaren tot 2026-05-19).

```bash
# Stop www-data PM2
systemctl stop pm2-www-data.service

# Herstel oude root-PM2 unit (handmatig opnieuw schrijven of pm2 startup als root)
pm2 startup systemd  # als root
cp /root/.pm2/dump.pm2.pre-www-data-20260419-105250 /root/.pm2/dump.pm2
systemctl start pm2-root.service
```

## Geschiedenis

- **2026-02-24:** PM2 als root gestart om een acute permissions-issue te omzeilen; `pm2-www-data.service` bleef failed staan.
- **2026-04-19:** K&V server-health-scan signaleerde de stale failed unit. Migratie uitgevoerd: chown van schrijf-paden, centrale ecosystem geschreven, `pm2-root.service` verwijderd, `pm2-www-data.service` herschreven en geactiveerd. Eindstaat: alle 3 apps draaien als www-data, geen failed units, K&V-scan clean.

## Zie ook

- `docs/kb/runbooks/kwaliteit-veiligheid-systeem.md` — server-health-check die deze regressie betrapte
- `docs/kb/runbooks/server-verhuizingen-2026-03-18.md` — pad-conventies op productie
