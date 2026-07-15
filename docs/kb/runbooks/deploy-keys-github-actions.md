---
title: GitHub Actions deploy-keys — één commando per project
type: runbook
scope: havuncore
last_check: 2026-07-01
---

# GitHub Actions deploy-keys

Elke Havun-repo die via GitHub Actions naar de server (188.245.159.115) deployt,
gebruikt het secret `SSH_PRIVATE_KEY`. Dit runbook beschrijft hoe je dat in één
commando regelt en hoe het deploy-patroon in elkaar zit.

## Één commando: key + secret

```bash
cd D:\GitHub\HavunCore
bash scripts/setup-deploy-key.sh <RepoNaam>
```

Doet idempotent:
1. **Dedicated** ed25519 deploy-key per repo op de server: `/root/.ssh/github_deploy_<slug>`.
   Nooit hergebruik van een andere key → bij een lek roteer je maar één repo.
2. Public key in root's `authorized_keys`.
3. Private key **rechtstreeks doorgepijpt** naar GitHub-secret `SSH_PRIVATE_KEY`
   (nooit geprint, nooit lokaal opgeslagen).

Vereist: SSH `root@188.245.159.115` + `gh auth` met scopes `repo` + `workflow`.

> De private key hoort **alleen** in GitHub Secrets, nooit in git of een bestand.
> Gebruik NOOIT je persoonlijke `~/.ssh/id_ed25519` als CI-secret — dat is je
> volledige server-toegang. Zie `runbooks/geen-hardcoded-secrets-in-tests.md` voor het principe.

## De workflow (handmatige knop)

Prod = bewuste keuze → **geen auto-deploy op push**. Elke repo krijgt
`.github/workflows/deploy-production.yml` met `workflow_dispatch` (knop in
GitHub → Actions → Deploy to Production → Run workflow). Input `migrate`
(default `no`) bepaalt of er gemigreerd wordt — **auto-migrate op prod mag niet**.

De workflow roept het centrale server-script aan:

```
bash /root/deploy-havun.sh <app-dir> <branch> [subdir=.] [build=yes] [migrate=no]
```

`/root/deploy-havun.sh` doet: `git pull --ff-only` (conventie: altijd git pull,
nooit scp/rsync), `composer install --no-dev`, `npm ci && npm run build` (indien
`package.json`), en bij Laravel `artisan config/route/view:cache` (+ migrate alleen
bij `migrate=yes`). Faalt luid bij lokale wijzigingen op de server (ff-only).

## Stand per project (2 juli 2026)

| Project | Server-pad | Branch | Deploybaar |
|---|---|---|---|
| HavunClub | `/var/www/havunclub/staging` | staging | ✅ |
| HavunAdmin | `/var/www/havunadmin/{production,staging}` | main/staging | ✅ |
| Herdenkingsportaal | `/var/www/herdenkingsportaal/production` | main | ✅ |
| infosyst | `/var/www/infosyst/production` | master | ✅ |
| SafeHavun | `/var/www/safehavun/production` | master | ✅ |
| Judotoernooi | `/var/www/judotoernooi` (laravel/) | main | ✅ |
| VPDUpdate | `/var/www/vpdupdate` | main | ✅ (read-deploykey `deploy_vpdupdate` op repo gezet + remote → alias `github-vpdupdate`; non-Laravel, deploy = git pull + `npm run build`) |
| Studieplanner-api | `/var/www/studieplanner/production` | master | ✅ |
| havuncore-webapp | `/var/www/havuncore/webapp` | main | ⚠️ draait (Node/PWA, geen artisan/composer) — centrale Laravel-`deploy-havun.sh` past NIET; vereist Node-variant (npm install + build + `pm2 restart`). Enige openstaande deploy-taak. |
| Vusista | `/var/www/vusista/{production,staging}` | main/staging | ✅ — staging auto op push, prod = `workflow_dispatch`. Deploy-key `server-read (188.245.159.115)` SHA256:Tdjl6DmzEjWr3kW/lhWlNNWoXcABUc6HE4JTUPLmsqY toegevoegd 14 jul 2026. |
| HavunVet | — | — | ⛔ **OBSOLEET — geparkeerd** (Henk, 2 juli). Niet aanwerken; vhost `staging.havunvet.havun.nl` + leeg pad mag ooit opgeruimd (server-config → Henks go). |
| IDSee, Agorano | — | — | ⛔ **geparkeerd** (Henk, 2 juli): "nog lang niet aan de beurt". Server-setup pas als ze aan de beurt zijn. |

Native apps (judoscoreboard, LastMatch, Studieplanner, Aeterna) + geparkeerd
Munus: geen server-deploy.

> **Read-deploykey vs CI-key.** Twee verschillende keys per repo:
> `deploy_<slug>` (host-alias `github-<slug>` in `/root/.ssh/config`) = server pullt
> de repo. `github_deploy_<slug>` = GitHub Action SSH't als root de server in.
> `git@github.com:` in een server-remote gebruikt de default `id_ed25519` en faalt
> voor private repos → altijd de host-alias gebruiken (zie VPDUpdate-fix).

## Nieuw project toevoegen

1. Server-setup (eenmalig, raakt nginx/DNS/DB → Henks go): dir clonen onder
   `/var/www/<slug>/{production|staging}`, vhost, DB, `.env`.
2. `bash scripts/setup-deploy-key.sh <Repo>`.
3. `deploy-production.yml` (workflow_dispatch) in de repo, met de juiste
   `deploy-havun.sh`-args voor pad/branch/subdir.
