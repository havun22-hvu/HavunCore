---
title: Deploy key management — centrale werkwijze
type: runbook
scope: alle-projecten
owner: HavunCore
last_check: 2026-04-18
---

# Deploy key management

> **Doel:** centraal overzicht + controle over GitHub deploy keys zodat
> elk project precies de permissies heeft die nodig zijn — niet meer, niet minder.
> **Inventaris:** `docs/kb/reference/deploy-keys-inventory.md`
> **Uitvoerder:** **Claude**. Henk voert zelf nooit commando's uit of maakt keys —
> dit is volledig Claude's afdeling (zie `memory/feedback_claude_owns_infra.md`).

## Waarom centraal via HavunCore

- Ad-hoc key-aanmaak per project → geen overzicht → security blind spot
- Elke deploy key = extra aanvalsoppervlak (server compromise = repo compromise)
- Naming-chaos: 6 verschillende titels voor hetzelfde doel
- Geen rotation-schema → oude keys blijven eeuwig geldig
- Geen review: wie heeft wanneer welke key voor welke reden toegevoegd

## Principes

### 1. Read-only tenzij AutoFix

| Situatie | Permissie |
|----------|-----------|
| Server doet alleen `git pull` / deploy | **read_only = true** |
| Server pusht hotfix-branches (AutoFix) | **read_only = false** — met notie in inventory |
| Alles anders (CI, staging-tests die committen) | read_only = true, gebruik ipv GitHub Actions met `GITHUB_TOKEN` |

Write-access betekent: bij server-compromise kan aanvaller naar main pushen → AutoFix runt die meteen in productie. Zware impact.

### 2. Naming-conventie

`<project>-<rol>-<env>`

Voorbeelden:
- `herdenkingsportaal-autofix-prod`
- `judotoernooi-autofix-prod`
- `infosyst-deploy-prod`
- `havuncore-webapp-deploy-prod`
- `havunadmin-deploy-prod`

Titel bevat altijd de scope (repo → rol → env) zodat in GitHub's key-lijst onmiddellijk duidelijk is.

### 3. Registratie vóór installatie

**Elke nieuwe deploy key eerst in `deploy-keys-inventory.md` documenteren, dan pas aanmaken op GitHub.** Dit voorkomt dat Claude of een andere sessie ad-hoc keys toevoegt zonder centraal zicht.

### 4. Rotation

- **Minimaal jaarlijks** (januari) alle deploy keys draaien
- Bij elke server-migratie (zoals de 2026-03-18 verhuizingen)
- Bij verdenking of lek
- Bij einde van een AutoFix-schemaperiode

### 5. Stale-detectie

Keys die >90 dagen niet gebruikt zijn staan in inventory als candidate-for-removal. Elke `/start` die deze runbook aanraakt checkt `last_used`.

## Werkwijze: nieuwe deploy key toevoegen

1. **Bepaal scope** — welke repo, welke server, welke permissie (RO of RW + reden).
2. **Documenteer** in `deploy-keys-inventory.md` met: repo, titel (volgens conventie), doel (`deploy` / `autofix` / `ci`), RO/RW, motivatie.
3. **Genereer key op server:**
   ```bash
   ssh-keygen -t ed25519 -C "<titel-volgens-conventie>" -f ~/.ssh/<titel> -N ""
   ```
4. **Voeg `<titel>.pub` toe** aan repo via GitHub UI (`Settings → Deploy keys → Add`) **of** via API:
   ```bash
   gh api repos/havun22-hvu/<REPO>/keys \
     -f title="<titel>" \
     -f key="$(cat ~/.ssh/<titel>.pub)" \
     -F read_only=true
   ```
5. **Test toegang** vanaf server — `ssh -T git@github.com-<titel>` of directe `git pull`.
6. **Update inventory** met `created_at` (vandaag) + id (uit GitHub response).

## Werkwijze: deploy key verwijderen

1. **Verifieer geen actief gebruik** — check server-crontab, deploy-scripts, AutoFix-config.
2. **Verwijder via GitHub UI** of API:
   ```bash
   gh api -X DELETE repos/havun22-hvu/<REPO>/keys/<ID>
   ```
3. **Verwijder van server** — `rm ~/.ssh/<titel>*` + eventuele `~/.ssh/config` entry.
4. **Markeer in inventory** als `REMOVED (datum, reden)`.

## Werkwijze: key rotation (RO / RW)

GitHub deploy-keys zijn **immutable** — je kunt `read_only` NIET wijzigen via PATCH. Rotation bestaat dus uit:

1. Nieuwe key aanmaken met gewenste permissie (volgens "toevoegen" hierboven)
2. Server overschakelen naar nieuwe key
3. Oude key verwijderen (volgens "verwijderen" hierboven)

Geplande windows: weekend + na deploy-hours.

## Per-project benodigdheden

Elk project krijgt precies de deploy-key(s) die het nodig heeft om zijn taken te doen — niet meer:

| Project | Doel | Benodigde permissie |
|---------|------|---------------------|
| HavunAdmin | productie deploy | RO |
| havuncore-webapp | Hetzner productie deploy | RO |
| Herdenkingsportaal | AutoFix hotfix-branch push | **RW** (gemotiveerd) |
| Judotoernooi | AutoFix hotfix-branch push | **RW** (gemotiveerd) |
| infosyst | productie deploy | RO |
| Studieplanner-api | productie deploy (geen AutoFix) | RO |
| HavunClub | (archived — geen key nodig) | — |
| HavunVet | (nog geen prod) | — |
| SafeHavun | (nog geen prod) | — |
| JudoScoreBoard | (Expo build-only) | — |
| Studieplanner | (Expo build-only) | — |
| IDSee | (Node.js app) | — |
| HavunCore | (geen remote deploy, lokale dev) | — |
| Munus | (tbd) | — |

Afwijking van deze tabel → eerst hier bijwerken + motiveren, dan pas nieuwe key installeren.

## Audit

Eens per maand: `gh api repos/havun22-hvu/<REPO>/keys --jq '.[]'` over alle repos en vergelijk met inventory. Nieuwe keys die niet in inventory staan → melden.

Audit-oneliner (draait al in `reference/deploy-keys-inventory.md` refresh):

```bash
for r in $(gh api user/repos --paginate --jq '.[].name'); do
  count=$(gh api "repos/havun22-hvu/$r/keys" --jq 'length' 2>/dev/null)
  [ -n "$count" ] && [ "$count" != "0" ] && echo "$r: $count key(s)"
done
```

## Verband met andere KB

- `runbooks/autofix-branch-model.md` — waarom AutoFix-repos RW nodig hebben
- `runbooks/server-verhuizingen-2026-03-18.md` — bij migratie: keys mee laten verhuizen
- `runbooks/emergency-runbook.md` — bij compromise: alle keys direct rotate
- `reference/security-findings.md` — log eventuele deploy-key incidenten hier
