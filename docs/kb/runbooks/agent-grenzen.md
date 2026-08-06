---
title: Wat een agent uit de taakwachtrij wel en niet mag
type: runbook
scope: alle-projecten
last_check: 2026-08-06
---

# Wat een agent uit de taakwachtrij wel en niet mag

**Een taak uit de wachtrij wordt onbewaakt uitgevoerd. Henk kijkt niet mee terwijl het gebeurt —
hij ziet het resultaat. Daarom is de uitkomst altijd een voorstel dat hij kan afwijzen, nooit een
voldongen feit.**

Besluit Henk 06-08-2026. Plan: [`plans/autofix-naar-claude-cli-plan.md`](../plans/autofix-naar-claude-cli-plan.md).

## De grenzen

| Wel | Niet |
|---|---|
| Werken op een branch (`hotfix/autofix-<project>-<datum>`) | Pushen naar `master` of `main` |
| Een PR openen en daar stoppen | Een PR mergen |
| Code lezen, wijzigen, tests draaien | Migraties draaien — ook niet lokaal |
| Een test toevoegen die de fout aantoont | `.env` lezen, schrijven of kopiëren |
| `composer install` / `npm ci` (lockfile volgen) | Dependencies toevoegen of upgraden |
| Melden dat het niet lukt | Iets anders "erbij" oplossen dan de taak vraagt |

**Bij twijfel: stoppen en melden.** Een taak die eindigt met "ik kom er niet uit, dit heb ik
geprobeerd" is bruikbaar. Een taak die eindigt met een halve wijziging op een branch is dat niet.

## Waarom deze en geen andere

Ze zijn niet nieuw bedacht: het zijn de grenzen die AutoFix al hanteert
(`config/autofix.php` → `branch_model`, `auto_pr`, `dry_run_on_risk`). Een agent die een taak
oppakt hoort niet meer te mogen dan de automatiek die de taak aanmaakte.

De drie harde verboden — `master`, migraties, `.env` — hebben elk dezelfde reden: **ze zijn niet
terug te draaien met een `git revert`.** Een branch gooi je weg. Een gedropte kolom, een gedraaide
migratie op productie of een gelekt secret niet.

## Wat de poller afdwingt, en wat niet

De poller kan een deel hiervan hard maken; de rest staat in de taakinstructie en leunt op het
model. Dat onderscheid is belangrijk — noem het geen beveiliging als het een verzoek is.

| Grens | Hoe geborgd |
|---|---|
| Nooit naar `master` pushen | **Hard**: de poller weigert een taak te starten als de checkout op `master`/`main` staat, en checkt na afloop of de branch nog klopt |
| Geen `.env` | **Hard**: de poller vergelijkt vooraf/achteraf de hash van `.env` en faalt de taak als die wijzigde |
| Geen migraties, geen nieuwe dependencies | **Zacht**: staat in de instructie. Een lockfile-wijziging valt wel op in de PR-diff |
| Scope niet oprekken | **Zacht**: de reviewer (Henk) ziet het in de PR |

## De poller starten

Draait op Henks PC, in Git Bash. De PC haalt op; er hoeft niets open te staan in de router.

```bash
# Token eenmalig uit de Vault halen (staat niet in dit doc, en hoort er ook niet in):
#   ssh root@188.245.159.115
#   cd /var/www/havuncore/production && sudo -u www-data php artisan tinker
#   >>> App\Models\VaultSecret::where('key','havuncore_tasks_token')->first()->getDecryptedValue()

export HAVUNCORE_TASKS_TOKEN='<token>'
cd /d/GitHub/HavunCore

./scripts/local-task-poller.sh --self-test   # controleert de guards, voert niets uit
./scripts/local-task-poller.sh --once        # één ronde
./scripts/local-task-poller.sh               # blijven pollen (elke 60s)
```

**Begin met `--self-test`.** Die controleert of de guards echt werken en of Claude CLI en het token
aanwezig zijn. Meldt hij dat een guard stuk is: niet gebruiken.

Instelbaar via omgevingsvariabelen: `PROJECTS` (standaard `judotoernooi herdenkingsportaal`),
`POLL_INTERVAL` (60s), `GITHUB_ROOT` (`/d/GitHub`).

Het script heeft `php` nodig (staat er al voor dit project) en bewust **niet** `jq` — die ontbreekt
op deze machine en zou een extra installatie zijn.

## Als het misgaat

1. **Branch weggooien** — `git branch -D <branch>`; er is niets naar `master` gegaan.
2. **Taak op `failed` zetten** met de reden, zodat de wachtrij niet opnieuw hetzelfde probeert.
3. **Herhaalt het zich?** Dan is de taakomschrijving het probleem, niet de agent. Pas
   `AutoFixService::escalationInstruction()` aan.

## Zie ook

- [`../reference/api-taskqueue.md`](../reference/api-taskqueue.md) — de wachtrij zelf (auth verplicht)
- [`../reference/security-findings.md`](../reference/security-findings.md) — waarom die auth er is
