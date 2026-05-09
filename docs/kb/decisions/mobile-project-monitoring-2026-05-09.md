---
title: Mobile project monitoring in PWA
type: decision
date: 2026-05-09
status: accepted
project: havuncore-webapp (cross-project)
---

# ADR — Mobile project monitoring in PWA

## Status

Voorgesteld 2026-05-09. Aanleiding: tijdens [repo-hygiene rollout](repo-hygiene-2026-05-09.md) bleek dat de PWA Project Status `getProjectStatus()` op `/var/www/{project}` doet, wat voor mobile-only projecten geen zinvol resultaat geeft. JudoScoreBoard is een React Native Expo app — er is geen server-checkout om te `git status`-en. Studieplanner heeft naast de Laravel-API ook een Expo-app, die nu niet in de PWA zichtbaar is.

## Context

Het Havun-portfolio heeft drie soorten projecten:

| Soort | Voorbeeld | Wat draait waar? |
|-------|-----------|------------------|
| Server (Laravel/Node) | HavunAdmin, Herdenkingsportaal, Infosyst | Op Hetzner met git-checkout |
| Mobile (Expo) | JudoScoreBoard | Op telefoon, geen server-deploy |
| Both | Studieplanner | Laravel API op Hetzner + Expo app op telefoon |

De huidige PWA monitort **alleen** server-checkouts via `git status --porcelain`. Voor mobile-only projecten:

- `/var/www/judoscoreboard/` is een 1KB Coming-Soon HTML — geen `.git`, geen project
- Met de [bonus-fix uit repo-hygiene-ADR](repo-hygiene-2026-05-09.md) zou dit nu rood worden — terwijl er feitelijk niks rood is, alleen de verkeerde data-source

De Expo-app van Studieplanner is **helemaal niet** in de PWA zichtbaar omdat de "Studieplanner"-key naar de Laravel-API verwijst.

## Beslissing

Mobile projecten krijgen een **eigen monitoring-pad** via GitHub API. De PWA wordt twee-soortig: server-projecten houden hun bestaande check, mobile-projecten krijgen een aparte data-flow.

### Categorisering

Project-config krijgt een `category`-veld:

```js
'studieplanner': { category: 'server', remotePath: '/var/www/studieplanner/production', ... },
'studieplanner-app': { category: 'mobile', github: 'havun22-hvu/Studieplanner', ... },
'judoscoreboard': { category: 'mobile', github: 'havun22-hvu/judoscoreboard', ... },
```

`category` mag zijn:
- `server` — bestaande gedrag, `git status` op `remotePath`
- `mobile` — GitHub API check, geen server-checkout
- `both` — twee aparte entries (server- en mobile-key) ipv één hybride entry. Helder + uitbreidbaar.

Voor Studieplanner ontstaan dus **twee project-keys**: `studieplanner` (Laravel API, bestaand) en `studieplanner-app` (Expo app, nieuw).

### GitHub API check

Per mobile project, één HTTP-call naar `https://api.github.com/repos/{github}` + `/commits` + `/pulls`:

| Veld | Bron | Display |
|------|------|---------|
| `last_commit.date` | `/commits?per_page=1` | "5u geleden" |
| `last_commit.message` | idem | korte titel |
| `default_branch` | `/repos/{x}` | branch-badge |
| `open_prs` | `/pulls?state=open&per_page=1` (lengtegebruik) | "3 open PRs" als > 0 |

**Geen** server-side state, geen disk-usage, geen git-pull. Alleen activiteit op GitHub.

### Auth — via HavunCore Vault

PAT wordt **niet** in `.env.production` gezet. In plaats daarvan:

1. Vault-project `havuncore-webapp` krijgt eigen Bearer-token (eenmalig via `VaultController::adminCreateProject`)
2. Secret `github_pat_ro` (read-only PAT, scopes `public_repo` + `repo:status`) staat in Vault
3. Project `havuncore-webapp` heeft access tot die secret
4. PWA backend bij startup: `GET https://havuncore.havun.nl/api/vault/secrets/github_pat_ro` met Bearer-token uit `VAULT_PROJECT_TOKEN` env-var
5. PAT in-memory gecached voor de PM2-uptime — restart = re-fetch

Voordeel: rotatie is een DB-update (`VaultSecret`) + PM2 restart. PAT zit niet in deploy-bundles, niet in `.env.production`, niet in git-history. Alleen het Vault-token zelf staat in `.env.production` (kleine sleutel, niet de waardevolle PAT).

5000/h GitHub API rate-limit is meer dan genoeg (~14 calls per scan × 60s = max 840/h).

### UI

Aparte `MobileProjectCard` component naast bestaande `ProjectCard` in `frontend/src/components/ProjectsView.jsx`:

```
┌─ JudoScoreBoard ──────────── 5u geleden ──┐
│ ● MOBILE  main  v1.0.2 (102)              │
│ Laatste commit: a3f1c "fix: ..."           │
│ 0 open PRs                                 │
└────────────────────────────────────────────┘
```

Status-kleur:
- 🟢 `green` — last commit < 30 dagen, geen open PRs > 14d
- 🟡 `orange` — last commit > 30 dagen of >2 open PRs > 14d (stale)
- 🔴 `red` — GitHub API error of repo gone

### Backend service

Nieuwe `mobileProjectService.js` (parallel aan `projectStatusService.js`):

```js
async function getMobileProjectStatus(key, config) { ... }
async function getAllMobileProjectStatuses() { ... }
```

Bestaande `/api/projects/status` route wordt categorize-aware: per project welk service-pad afhankelijk van `category`.

### Caching

GitHub API responses worden in-memory gecached voor 5 minuten (per (repo, query) tuple). Vermindert rate-limit risico bij snelle achter-elkaar refreshes. SQLite-cache als follow-up als de webapp meerdere PWA-instances krijgt.

## Niet-doen (out of scope, vervolg-ADRs)

- **Sentry-integratie** — crash-rate per app. Aparte ADR, vereist Sentry-account + DSN-management.
- **EAS Update / OTA tracking** — welke OTA-bundle is live op welke release-channel. Vereist EAS API-key.
- **APK-versie ophalen uit GitHub Releases** — kan in een vervolg-versie van de mobile check.
- **Play Store metrics** — DAU, ratings. Vereist Google Play Console API + scope-creep.
- **GitHub App auth** — start met PAT, upgrade pas als rate-limit wringt.

## Implementatie-volgorde

1. **Config schema**: voeg `category` + `github` toe aan PROJECTS in `webapp/backend/src/config/projects.js`. Categorize bestaande entries: alle huidige zijn `server`. Nieuwe: `studieplanner-app` + `judoscoreboard` als `mobile`.
2. **Backend**: `mobileProjectService.js` met GitHub API client + 5-min cache. `projects.js` route categorize-aware.
3. **Frontend**: `MobileProjectCard` component in `ProjectsView.jsx`. Render conditioneel op `project.category`.
4. **Env**: `GITHUB_PAT_RO` in webapp `.env.production` (read-only token, scopes `repo:status, public_repo` voor private repos).
5. **Test**: handmatig via `/api/projects/status` met PWA UI. Geen unit-test setup in webapp ("Tests coming soon").
6. **Verwijder JudoScoreBoard server-entry** zodra mobile-entry werkt — anders dubbel.
7. **KB-policy**: kleine sectie in `docs/kb/reference/repo-hygiene-policy.md` of nieuwe `mobile-monitoring.md` met de category-regels.

## Risico's

| Risico | Impact | Mitigatie |
|--------|--------|-----------|
| GitHub API rate-limit | PWA toont "rood" voor alle mobile bij 5000/h overschrijding | 5-min cache + auth via PAT (5000/h ipv 60/h) |
| PAT expires / leakt | Mobile-checks falen of token gestolen | Rotate-procedure in [repo-hygiene-policy.md](../reference/repo-hygiene-policy.md) format. Read-only scope. Naar Vault later. |
| Stale-criteria te strikt → false orange | Project zonder activiteit lijkt fout | Threshold tunabel in config (mobile_stale_after_days, default 30) |
| Beide categories door elkaar verwarrend in UI | User-experience | Visuele scheiding: "Server" + "Mobile" sectie in PWA, of badges per kaart |

## Beslissingen (vastgelegd 2026-05-09)

| # | Onderwerp | Keuze |
|---|-----------|-------|
| 1 | Naam Studieplanner Expo-key | `studieplanner-app` |
| 2 | PAT-opslag | Via HavunCore Vault — alleen Vault-bearer-token in `.env.production`, PAT zelf nooit op disk |
| 3 | Stale-threshold | Configurable via `MOBILE_STALE_AFTER_DAYS` env-var (default 30) |
| 4 | UI-layout | Aparte secties "Server" / "Mobile" headers in ProjectsView — schaalbaar voor toekomstige categorieën |
