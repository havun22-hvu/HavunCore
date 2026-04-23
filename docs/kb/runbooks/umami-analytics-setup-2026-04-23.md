---
title: Umami self-host analytics — setup (2026-04-23)
type: runbook
scope: havun.nl + toekomstige marketing-sites
last_check: 2026-04-23
status: LIVE
---

# Umami self-host analytics

## Doel

Cookieless, privacy-first analytics voor havun.nl (marketing/landing).
Geen Google Analytics (AVG-risk + SRI-issue, zie
`google-analytics-removal-2026-04-23.md`).

## Waar draait het

- **URL**: https://umami.havun.nl
- **Server**: 188.245.159.115 (Hetzner, 4 GB RAM)
- **Install-dir**: `/var/www/umami/production`
- **Repo**: v2 branch van github.com/umami-software/umami
- **Database**: MySQL, DB `umami`, user `umami@localhost` (creds in `.env`)
- **Process**: PM2 app `umami`, Node.js native, poort 3004
- **Nginx**: `/etc/nginx/sites-enabled/umami.havun.nl` (proxy → 127.0.0.1:3004)
- **Cert**: ECDSA P-384 via certbot-webroot, auto-renew

## Admin credentials

Opgeslagen in HavunCore `.env`:
```
UMAMI_URL=https://umami.havun.nl
UMAMI_ADMIN_USER=admin
UMAMI_ADMIN_PASSWORD=<random-24>
```
Standaard `admin/umami` direct geroteerd na install.

## Geregistreerde websites

| Site | website-id |
|---|---|
| havun.nl | `0007503a-1988-4295-9eb3-ee0e898349f1` |

## Tracking script integratie

Voor Next.js (`src/app/layout.tsx` of vergelijkbaar):
```tsx
{process.env.NODE_ENV === "production" && (
  <script
    defer
    src="https://umami.havun.nl/script.js"
    data-website-id="<WEBSITE-ID>"
    integrity="sha384-<HASH>"
    crossOrigin="anonymous"
  />
)}
```
SRI-hash komt van:
```bash
curl -sS https://umami.havun.nl/script.js | openssl dgst -sha384 -binary | openssl base64 -A
```
Hash wijzigt NIET bij Umami-upgrades binnen dezelfde major versie (maar
wel bij major upgrade — check + update).

## Server-setup stappen (referentie)

1. `fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile
   && swapon /swapfile`; add to `/etc/fstab`.
   (Next.js build vraagt ~2GB RAM — OOM zonder swap op 4GB-VPS.)
2. MySQL DB + user aanmaken met random password.
3. `git clone --depth 1 --branch v2 https://github.com/umami-software/umami.git`
4. `.env` met `DATABASE_URL`, `DATABASE_TYPE=mysql`, `APP_SECRET`, `PORT=3004`.
5. `CYPRESS_INSTALL_BINARY=0 PUPPETEER_SKIP_DOWNLOAD=true npm install
   --include=dev --legacy-peer-deps --ignore-scripts` — Cypress skipt.
6. `NODE_OPTIONS=--max-old-space-size=1536 npm run build`.
7. PM2 entry in `/var/www/.pm2/ecosystem.config.js` → `pm2 save`.
8. Nginx vhost (HTTP-only eerst voor ACME challenge).
9. `certbot certonly --webroot --webroot-path /var/www/html --key-type
   ecdsa --elliptic-curve secp384r1 -d umami.havun.nl`.
10. Nginx vhost vervangen door HTTPS-config met `ssl-hardened.conf` include.
11. Default admin wachtwoord roteren via `POST /api/me/password`.

## Toevoegen van een nieuwe tracked website

Via API:
```bash
TOKEN=$(curl -sS -X POST https://umami.havun.nl/api/auth/login \
  -H 'Content-Type: application/json' \
  -d "{\"username\":\"admin\",\"password\":\"$UMAMI_ADMIN_PASSWORD\"}" \
  | jq -r .token)

curl -sS -X POST https://umami.havun.nl/api/websites \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"name":"<NAAM>","domain":"<DOMEIN>"}'
```
Response bevat `id` = website-id voor het tracking-script.

## Upgraden

```bash
cd /var/www/umami/production
sudo -u www-data git pull
sudo -u www-data CYPRESS_INSTALL_BINARY=0 PUPPETEER_SKIP_DOWNLOAD=true \
  npm install --include=dev --legacy-peer-deps --ignore-scripts
sudo -u www-data NODE_OPTIONS=--max-old-space-size=1536 npm run build
sudo -u www-data pm2 restart umami
# SRI-hash opnieuw berekenen + overal updaten!
curl -sS https://umami.havun.nl/script.js | openssl dgst -sha384 -binary | openssl base64 -A
```

## Beleid

Umami is de **default analytics-keuze** voor nieuwe Havun
marketing/content-sites die tracking willen. Beoordelingscriteria in
`productie-deploy-eisen.md` §3.2.1 blijven leidend — tools zonder
marketing-functie krijgen ook geen Umami.
