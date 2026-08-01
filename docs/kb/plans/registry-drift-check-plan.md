---
title: Registry-drift check — plan
type: plan
scope: havuncore
status: af (backup-arm open)
last_updated: 2026-08-01
---

# Registry-drift check (`qv:scan --only=registries`)

**Probleem:** HavunCore houdt registers bij die bepalen wat er bewaakt wordt, en ze lopen uit de
pas. Drie keer in twee dagen (29–31 juli) stond een project wél in `havun-projects.php` en níét in
`quality-safety.php`: `vusista`, `vusista2` en Veen. Gevolg bij Veen: **nooit een `composer audit`
of secrets-scan** — de eerste scan na toevoegen leverde meteen een high op.

**Kern van de fout:** afwezigheid is stil. Elke andere V&K-check meldt iets; deze categorie meldde
per definitie niets.

## Wat de check meldt

| Severity | Regel |
|---|---|
| 🟠 high | project met `server_path` staat niet in `quality-safety.php` — draait live, wordt nooit gescand |
| 🟡 medium | sleutel in `quality-safety.php` bestaat niet in `havun-projects.php` — wees of typefout |
| 🟡 medium | zelfde sleutel, ander pad in de twee registers — de scan meet een ander project dan de naam zegt |
| ⚪ info | uitgezonderd met reden, of een uitzondering die overbodig is geworden |

Uitzonderen kan via `'registry_exempt' => ['qv' => 'reden']` in `havun-projects.php`. Een lege
reden telt niet — anders verdwijnt een bevinding achter een leeg stringetje.

Geen bevinding voor: een project zonder `server_path` (mobiele apps), een scanlijst-entry zonder
enig pad (`server-prod` stuurt de server-health aan via host+user), een subpad
(`JudoToernooi/laravel` binnen `JudoToernooi`), of een afwijkende sleutel op hetzelfde pad
(`studieplanner-mobile`).

## Eerste run — wat het opleverde (01-08-2026)

**3 high:** `havun` (`/var/www/havun.nl`), `vpdupdate` (`/var/www/vpdupdate`) en `havuncore-webapp`
(`/var/www/havuncore/webapp`) draaiden live en waren **nooit** gescand. Alle drie toegevoegd.

**Sleutelkruising opgelost:** `studieplanner` was in `havun-projects.php` de Expo-app mét het
server-pad van de API, en in `quality-safety.php` de API onder de naam van de app. De scan
"studieplanner" mat dus de API. Nu: `studieplanner` = app zonder server-pad, `studieplanner-api` =
de API in beide registers.

**Dode server-paden weg:** `infosyst` en `havunclub` gingen 18-07 van de server (geverifieerd
01-08: de paden bestaan niet meer) maar hadden nog `server_path`, `remote_path` en `url`. Lokaal
scannen blijft aan.

Regressietest `test_de_echte_configs_kennen_geen_high_drift` draait mee met de suite: zakt er weer
een live project uit de scanlijst, dan faalt de build vóór de nachtelijke scan het zou vinden.
Rood gezien tegen de configs van vóór de fix.

## ⚠️ Backup zit er bewust NIET in

Het eerste ontwerp vergeleek óók met `config/havun-backup.php`. Dat gaf vijf medium's — en die
waren **vals**: gemeten 01-08-2026 leest niets die config. Geen command, geen scheduler-regel,
alleen de check zelf. De echte backups draaien uit `/usr/local/bin/havun-backup.sh` (cron 03:00,
8 prod-databases) en `havun-hotbackup.sh` (elke 5 min, 3 kritieke databases). JudoToernooi en
SafeHavun wórden dus geback-upt — het register wist het alleen niet.

Een melding die altijd afgaat en niets betekent is erger dan geen melding, dus de backup-arm is
eruit.

**Wat daardoor open blijft staan** (echte bevindingen, met de hand gemeten):

- `config/havun-backup.php` is een register zonder uitvoerder — 4 projecten, nul effect.
  Weggooien of alsnog laten uitvoeren; nu suggereert het dekking die er niet is.
- Het shellscript backupt `infosyst` en `havunclub_production` — beide 18-07 van de server af.
- **`vpdupdate` zit in geen enkele backup.** `users.json` staat alleen op de server (handover
  25-07) en is de enige plek waar die gebruikers bestaan.
- `havun.nl` en `havuncore-webapp` zitten ook nergens in; mogelijk terecht (statisch / build-output),
  maar dat staat nu nergens vastgelegd.

**Aanname:** de twee configs blijven de bron van waarheid voor wat er gescand wordt.
**Omkeerpunt:** komt er een register bij dat niet uit een config te lezen is — zoals de backup, die
in een shellscript op de server staat — dan is een config-vergelijking niet genoeg en moet de
check de server bevragen.

## Status

- [x] Plan
- [x] Detector + 13 tests (rood gezien tegen de oude configs)
- [x] Globale check in de scanner (`GLOBAL_CHECKS` — draait één keer, buiten de projectloop)
- [x] Scheduler (dagelijks 03:02, vóór `qv:log`) + `--only=registries`
- [x] Gevonden drift opgelost — 3 high en 1 sleutelkruising weg
- [ ] Backupdekking meten tegen het shellscript in plaats van tegen dode config
