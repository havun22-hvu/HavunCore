---
title: GitHub deploy keys — centraal overzicht
type: reference
scope: alle-projecten
last_audit: 2026-04-18
next_review: 2026-05-18
---

# Deploy keys inventaris

> **Single source of truth** voor alle GitHub deploy keys onder `havun22-hvu`.
> **Elke nieuwe of verwijderde key eerst hier bijwerken, dan in GitHub.**
> **Beheer:** Claude (zie `runbooks/deploy-key-management.md`).

## Huidige stand (audit 2026-04-18)

| Repo | Key titel | RO/RW | Doel | Toegevoegd | Laatst gebruikt | id |
|------|-----------|:---:|------|------------|-----------------|-----|
| HavunAdmin | `Havun Server` | RO | prod deploy | 2026-01-09 | 2026-04-17 | 139997431 |
| HavunClub | `server-deploy` | RO | prod deploy (geparkeerd — Cees mogelijk re-activeert) | 2026-02-14 | 2026-02-14 | 143001316 |
| havuncore-webapp | `Hetzner Production Server` | RO | Hetzner prod deploy | 2026-04-13 | 2026-04-13 | 148467139 |
| Herdenkingsportaal | `Staging Server Deploy Key` | **RW** | AutoFix hotfix-branch push (VP-01) | 2025-11-24 | 2026-04-15 | 136782596 |
| infosyst | `HavunCore Server` | RO | prod deploy | 2026-01-01 | 2026-04-12 | 139427685 |
| Judotoernooi | `server-deploy-judotoernooi` | **RW** | AutoFix hotfix-branch push (VP-01) | 2026-02-22 | 2026-04-17 | 143647875 |
| Studieplanner-api | `studieplanner-api-deploy-prod` | RO | prod deploy | 2026-04-18 | 2026-04-18 | 148992748 |

## Per-project verwachting (uit runbook)

| Project | Verwacht | Huidig | OK? |
|---------|----------|--------|-----|
| HavunAdmin | RO deploy | RO ✅ | ✅ |
| HavunClub | RO (geparkeerd, mogelijk re-activatie door Cees) | RO ✅ | ✅ bewaren |
| havuncore-webapp | RO deploy | RO ✅ | ✅ |
| Herdenkingsportaal | RW (AutoFix) | RW ✅ | ✅ motivatie: AutoFix |
| infosyst | RO deploy | RO ✅ | ✅ |
| Judotoernooi | RW (AutoFix) | RW ✅ | ✅ motivatie: AutoFix |
| Studieplanner-api | RO (geen AutoFix) | RO ✅ | ✅ (geroteerd 2026-04-18) |
| HavunVet | geen (geen prod) | geen | ✅ |
| SafeHavun | geen (geen prod) | geen | ✅ |
| JudoScoreBoard | geen (Expo) | geen | ✅ |
| Studieplanner | geen (Expo) | geen | ✅ |
| IDSee | geen | geen | ✅ |
| HavunCore | geen (lokaal) | geen | ✅ |
| Munus | geen (tbd) | geen | ✅ |
| VPDUpdate | geen | geen | ✅ |

## Openstaande acties (Claude)

### ✅ Studieplanner-api key geroteerd RW→RO (2026-04-18)

Oude `server-deploy` (RW, id 148702447) verwijderd van GitHub en server.
Nieuwe `studieplanner-api-deploy-prod` (RO, id 148992748) actief.
`~/.ssh/config` op 188.245.159.115 bijgewerkt. `git ls-remote` geverifieerd.

### HavunClub — bewaren (geparkeerd)

Project is **niet archived** maar geparkeerd met mogelijke re-activatie
(Cees heeft belangstelling — bevestigd 2026-04-18). Deploy-key blijft
actief zodat we bij re-activatie direct kunnen deployen. Geen actie.

## Naming-migratie (toekomst)

Huidige titels volgen geen conventie. Bij elke rotation → nieuwe titel volgens `<project>-<rol>-<env>`:

| Nu | Nieuwe conventie |
|-----|------------------|
| `Havun Server` | `havunadmin-deploy-prod` |
| `Hetzner Production Server` | `havuncore-webapp-deploy-prod` |
| `Staging Server Deploy Key` | `herdenkingsportaal-autofix-prod` |
| `HavunCore Server` | `infosyst-deploy-prod` |
| `server-deploy-judotoernooi` | `judotoernooi-autofix-prod` |
| `server-deploy` (HavunClub) | `havunclub-deploy-prod` (bij volgende rotation) |
| `server-deploy` (Studieplanner-api) | ✅ al geroteerd 2026-04-18 |

## Audit-script (maandelijks)

```bash
# Draait Claude bij volgende /start of maandelijkse /audit
for r in $(gh api user/repos --paginate --jq '.[].name'); do
  gh api "repos/havun22-hvu/$r/keys" --jq \
    '.[] | "\(.title)|ro=\(.read_only)|\(.created_at[:10])|last=\((.last_used // "never")[:10])|id=\(.id)"' \
    2>/dev/null | sed "s|^|$r|"
done
```

Vergelijk output met bovenstaande tabel. Elke afwijking = nieuw key zonder doc → onmiddellijk documenteren of verwijderen.

## Log van wijzigingen aan deze inventory

| Datum | Wijziging | Door |
|-------|-----------|------|
| 2026-04-18 | Eerste centrale audit + inventory aangemaakt | Claude |
| 2026-04-18 | Studieplanner-api: RW-key 148702447 → RO-key 148992748 (nieuwe naam `studieplanner-api-deploy-prod`). Oude key verwijderd van GitHub + server. | Claude |
| 2026-04-18 | HavunClub status gecorrigeerd: niet archived, geparkeerd (mogelijk Cees re-activeert). Key blijft. | Claude |
