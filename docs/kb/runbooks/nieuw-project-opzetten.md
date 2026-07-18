---
title: Nieuw Havun-project opzetten (lokaal → GitHub → server)
type: runbook
scope: alle-projecten
last_updated: 2026-07-14
---

# Nieuw Havun-project opzetten

> Volledige keten voor een nieuw Laravel-project: lokale scaffold, werkwijze-infra,
> GitHub, HavunCore-registratie en server (staging + prod). Uitgevoerd voor **Vusista**
> op 14-07-2026; eerder deels voor Agorano. Alles hieronder is idempotent na te lopen.

## 1. Lokaal

```bash
cd D:/GitHub && composer create-project laravel/laravel <Naam> --no-interaction
cd <Naam> && npm install        # lockfile! create-project maakt GEEN package-lock.json
php artisan test --no-coverage  # groen vóór commit
```

Werkwijze-infra: kopieer `.claude/commands/*.md` + `rules.md` van een recent project
(Agorano/Vusista) en maak projectspecifiek (`sed` op naam + poort, daarna handmatig
project-specifieke regels vervangen — check op residu van het bronproject!).
Schrijf: `CLAUDE.md`, `.claude/{context,handover,blueprint}.md` (spec → blueprint met
timestamp-blockquote zodat `/start` hem meldt).

**Lokale poort:** volgende vrije in `config/havun-projects.php` (8000-8008 bezet t/m Vusista).

## 2. GitHub

```bash
git init -b main && git add -A && git commit
gh repo create havun22-hvu/<Naam> --private --source=. --remote=origin --push
git branch staging && git push -u origin staging   # staging = werkbranch
```

Workflows (kopieer van Vusista): `ci.yml` (tests + composer audit),
`deploy-staging.yml` (auto op push naar staging, met secret-guard),
`deploy-production.yml` (workflow_dispatch + migrate-keuze). Beide deployen via
`/root/deploy-havun.sh <dir> <branch> . yes <migrate>`.

## 3. HavunCore-registratie (zelfde commit)

1. `config/havun-projects.php` — key + `path` + `server_path` + `local_url`. **Dit is de
   single source** — `DocIndexer` leest deze config (sinds 15-07, geen aparte lijst meer).
2. `docs/kb/reference/poort-register.md` — php-fpm-socket-tabel + lokale-dev-tabel
3. `php artisan config:clear && php artisan docs:index <naam>`

## 4. Server (188.245.159.115)

**DNS:** `*.havun.nl` is een wildcard naar de server — subdomeinen werken direct.

```bash
# Read-deploykey (clonen) — patroon: key + host-alias + GitHub deploy key
ssh-keygen -t ed25519 -N "" -C "deploy-read-<slug>" -f /root/.ssh/deploy_<slug>
# ~/.ssh/config: Host github-<slug> → User git (VERPLICHT!), HostName github.com,
#   IdentityFile ~/.ssh/deploy_<slug>, IdentitiesOnly yes
#   Zonder "User git" gebruikt ssh de lokale user (root@github.com) → Permission denied (18-07)
gh repo deploy-key add <pub> --repo havun22-hvu/<Naam> --title "server-read"

mkdir /var/www/<slug>
git clone -b main    github-<slug>:havun22-hvu/<Naam>.git /var/www/<slug>/production
git clone -b staging github-<slug>:havun22-hvu/<Naam>.git /var/www/<slug>/staging
```

Per omgeving (production/staging):
1. MySQL: DB `<slug>_production`/`<slug>_staging` + eigen user, wachtwoord server-side
   genereren (`openssl rand`) — komt alleen in de server-`.env`, nooit in een transcript.
2. `.env` uit `.env.example`: APP_NAME/ENV/DEBUG=false/URL + MySQL-blok. `chown root:www-data .env && chmod 640`.
3. `COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction`
4. `npm ci && npm run build` (vereist package-lock.json — zie stap 1)
5. `php artisan key:generate --force && php artisan migrate --force && php artisan storage:link`
6. `chown -R www-data:www-data storage bootstrap/cache` (composer als root maakt anders 500s die zichzelf niet kunnen loggen)
7. `php artisan config:cache route:cache view:cache`

**Nginx:** kopieer een bestaand Laravel-vhost-patroon (bv. Studieplanner/Herdenkingsportaal —
deny-blokken, sw.js no-cache, static immutable), eerst HTTP-only. **Elk server-block MOET
zowel `listen 80;` als `listen [::]:80;` hebben** — de server heeft AAAA-records, dus Let's
Encrypt doet de HTTP-01-challenge over IPv6; zonder IPv6-listen faalt certbot ("failed to
authenticate", 18-07). Dan:
```bash
certbot --nginx -d <slug>.havun.nl -d staging.<slug>.havun.nl --non-interactive --agree-tos -m henkvu@gmail.com --redirect
```
Eén cert dekt beide hosts. (Certbot voegt zelf de `listen [::]:443 ssl` toe.)

**GitHub Actions deploy-toegang:** `scripts/setup-deploy-key.sh <Naam>` (aparte
Actions-key + `SSH_PRIVATE_KEY`-secret; de read-key uit de clone-stap is een andere key).

## 5. Verifiëren (E2E)

1. Push een doc-commit naar `staging` → Action groen → `git log` op server-checkout beweegt.
2. `curl` beide URL's → 200 met geldig cert.
3. Prod-deploy-knop NIET zelf triggeren — eerste klik doet Henk bewust.

## Valkuilen (allemaal echt geraakt)

| Valkuil | Fix |
|---------|-----|
| `composer install` als root faalt stil op verse checkout | `COMPOSER_ALLOW_SUPERUSER=1` |
| `npm ci` faalt: geen package-lock.json | lokaal `npm install` + lockfile committen (create-project maakt hem niet) |
| root-owned `storage/` na composer als root | `chown -R www-data:www-data storage bootstrap/cache` |
| Gekopieerde `.claude`-commands bevatten bronproject-regels | grep op bronproject-termen ná de sed-rename |
| `nginx -t` warnings "conflicting server name havuncore" | pre-existing (`havuncore.havun.nl.bak.2026-07-02` in sites-enabled), niet van jou |

## Zie ook

- `runbooks/deploy-keys-github-actions.md` — Actions-deploytoegang in detail
- `reference/poort-register.md` — poortkeuze
- Vusista (`D:\GitHub\Vusista`) — recentste referentie-implementatie van dit runbook
