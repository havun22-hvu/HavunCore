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

## Vervolg: backupdekking meten aan de uitkomst (01-08)

De backup-arm kwam terug, maar anders. Niet "staat het project in een lijst" — dat was juist de
fout — maar **bestaat er vanochtend een verse backup van wat dit project nodig heeft.**

Gemeten op de server (01-08, 03:00-run): acht databases, allemaal vers. Waaronder
`judo_toernooi` en `safehavun`, die het dode register níét kende. En óók `infosyst` (368 bytes) en
`havunclub_production` (378 bytes) — twee databases van apps die 18-07 van de server af gingen.
Het script dumpt ze nog steeds; leeg, elke nacht.

`config/havun-backup.php` wordt daarmee de **verwachting** (wat hoort er geback-upt te zijn, en
hoe vers) en het serverscript blijft de uitvoerder. De check toetst de een aan de ander. Dat is
niet ideaal — twee plekken — maar het alternatief (backups door Laravel laten draaien) raakt cron
en systemd, en dat is niet iets om ongevraagd om te bouwen.

**Aanname:** een backup die vanochtend bestaat en niet leeg is, is een bruikbare backup.
**Omkeerpunt:** blijkt een dump wel vers maar niet herstelbaar, dan is bestaan+omvang niet genoeg
en moet de check een restore-proef doen.

## De backup die er vier maanden was, en niets bewaarde (gevonden 01-08)

Henk vroeg of er élke nacht een backup naar de Hetzner Storage Box gaat, "zeker nodig voor
JudoToernooi en Herdenkingsportaal". Het antwoord op de vraag zoals gesteld is ja — 31 van de 31
dagen in juli, geen enkel gat. Maar de controle daarachter leverde iets anders op.

**Van 15 maart tot 27 juli 2026 werd elke nacht `herdenkingsportaal_production` gedumpt: een dood
restant van 22 tabellen en 47 rijen. De app draait op `herdenkingsportaal_prod` — 52 tabellen,
50.520 rijen.** Er stonden ruim vier maanden lang keurige bestanden van 5,1 KB op de Storage Box,
elke nacht om 03:00, met een plausibele naam in een nette mapstructuur. Sinds 28 juli staat de
goede dump erin (1,4 MB).

De bestandenbackup (`herdenkingsportaal_storage.tar.gz`, 172 MB) was al die tijd wél goed. De
monumenten en foto's waren dus veilig; de teksten, gebruikers en relaties niet.

**JudoToernooi is de hele periode in orde**, gecontroleerd op 1, 10, 20 en 31 juli en vandaag:
53 van 53 tabellen in de dump. Idem voor HavunAdmin, SafeHavun, HavunCore en Studieplanner —
alle zes tellen exact gelijk aan hun live database.

### Waarom geen enkele bestaande controle dit zag

Naam plausibel, bestand vers, omvang constant, upload geslaagd, map compleet. Elke check die naar
de *backup* kijkt, ziet een gezonde backup. Alleen de `.env` van de app weet welke database de
echte is.

Daarom vraagt de check het sinds 01-08 aan de app: elke `DB_DATABASE` uit
`/var/www/*/production/.env` (en JudoToernooi's `repo-prod/laravel/.env`) moet terugkomen als
`<naam>.sql.gz` in de verwachting. Zo niet → **high**. Alleen die ene regel wordt gelezen;
wachtwoorden blijven staan. Rood gezien door `herdenkingsportaal_prod` tijdelijk uit de
verwachting te halen.

**De valstrik zelf staat er nog:** `herdenkingsportaal_production` bestaat nog als database. Dump
veiliggesteld in `/root/backups/hp-dode-db-2026-08-01`; verwijderen is een prod-database en dus
Henks beslissing.

## Status

- [x] Plan
- [x] Detector + 13 tests (rood gezien tegen de oude configs)
- [x] Globale check in de scanner (`GLOBAL_CHECKS` — draait één keer, buiten de projectloop)
- [x] Scheduler (dagelijks 03:02, vóór `qv:log`) + `--only=registries`
- [x] Gevonden drift opgelost — 3 high en 1 sleutelkruising weg
- [x] Backupdekking meten aan de uitkomst — `qv:scan --only=backup-coverage`, dagelijks 05:30
      (ná de backup-cron van 03:00), 12 tests
- [x] **De 05:30-cron meet niets** (gevonden 02-08, gefixt 03-08) — manifest-route, zie hieronder

## De fix: het backupscript schrijft zijn eigen uitkomst op (03-08-2026)

`/usr/local/bin/havun-backup-manifest.sh` draait als root aan het eind van de backuprun en legt
`/var/lib/havun/backup-manifest.json` neer (644): per bestand naam/bytes/mtime, plus de
`DB_DATABASE` van elke app. Geen wachtwoorden, dus wereldleesbaar mag.

De check leest dat manifest als het er is, en valt alleen daarbuiten (Henks machine) terug op SSH.
Geen SSH-sessie naar de machine waar je al op staat.

**Correctie op de diagnose van 02-08** (geverifieerd 03-08, tijdens de deploy): de scan draait
*niet* als `www-data`. Alle `schedule:run`-crons staan in **roots** crontab — de qv-scan-bestanden
in `storage/app/qv-scans/` zijn `root:root`. De echte oorzaak is dus niet een ontbrekende
www-data-sleutel maar dat **de server geen SSH-key naar zichzelf heeft** (`/root/.ssh/` bevat
alleen `deploy_havunadmin`). Dat de fix goed is verandert niet; de reden wel. Bijvangst van
diezelfde meting: die root-cron maakt `storage/**` root-owned, waardoor `cache:clear` als
`www-data` faalt — 03-08 rechtgezet met `chown -R www-data:www-data storage bootstrap/cache`.

**Drie regels erbij, alle drie over de meting in plaats van over de backups:**

| Severity | Regel |
|---|---|
| 🔴 critical | er is niets gemeten — geen manifest én geen bestandslijst. De detector geeft dan *alleen* deze finding terug: de rest zou verzonnen zijn |
| 🟠 high | het manifest is ouder dan 26 uur (`monitoring.max_meting_age_hours`) — de meetketen staat stil, ongeacht hoe gezond de inhoud eruitziet |
| — | een SSH-fout is geen stille `error` meer maar diezelfde critical-finding |

**Tweede regel, doorgevoerd:** `docs:handover` toont nu `errors N` in de V&K-regel plus een
kopje *"Checks die niets gemeten hebben"*. Dat was de plek waar het wegviel — `qv-scan-latest.md`
toonde errors al wél.

**Aanname:** een manifest dat vers is, beschrijft de run van vannacht.
**Omkeerpunt:** schrijft het script het manifest ook als de dump zelf faalde, dan meet dit de
verkeerde stap en moet de exitstatus van de backuprun erin.

## ⛔ De nachtelijke backupcheck was blind (02-08-2026, opgelost 03-08)

De check werkt **alleen lokaal**. Op de server, waar de cron hem draait, meet hij niets:

```
lokaal (Henks machine, root-SSH-key):   errors=0  high=0  medium=0
server (cron, als root):                errors=1  <- "Backupmap niet op te vragen:
                                                     root@188.245.159.115: Permission denied (publickey)"
na de fix (server, 03-08):              errors=0  high=0  info=2
```

**Oorzaak:** `QualitySafetyScanner::runRemote()` gaat via SSH naar `root@<server>` — geschreven
vanuit "Claude draait dit lokaal". Draait de scan op de server zelf, dan is dat een SSH-verbinding
naar zichzelf, en daar is geen sleutel voor. Er hoort er ook geen te zijn: een bestand dat lokaal
op schijf staat via het netwerk bij jezelf opvragen is de omweg, niet de oplossing.

**Waarom dit erger is dan een gewone bug:** dit is exact de faalmodus die de check moest afvangen.
Elke nacht draait er iets dat eruitziet als bewaking, `high=0` rapporteert, en niet gekeken heeft.
Vier maanden lang keek iedereen naar een gezonde backup van de verkeerde database; nu keek er drie
dagen lang niemand naar de bewaking zelf. De check meldt zijn eigen falen wel netjes als
`errors: 1` — maar niets leest dat veld, en `qv:log` toont alleen high/medium.

**Afgevallen alternatieven** (blijven staan zodat niemand ze opnieuw voorstelt): de cron als root
draaien maakt `storage/**` root-owned → de bekende 500's; `www-data` een root-key geven is
privilege-escalatie.

**Afgevallen alternatief dat vaak terugkomt:** de cron als root draaien. Dat *doet* hij al (dat
was de verkeerde aanname), en precies daarom staat `storage/**` periodiek op root-owned.

**Ook opgelost, generiek (03-08):** `runRemote()` kijkt eerst of `$host` deze machine ís — IP van
de host tegen de adressen van de eigen interfaces — en draait het commando dan gewoon lokaal.
Daarmee werken `serverHealth` en `residueCheck` op de server zonder sleutel naar zichzelf, en hoeft
een volgende check daar niets van te weten. Het backupmanifest blijft nuttig: dat lost óók op dat
de scan de backupmap niet hoeft te kunnen lezen.

## Wat de backupcheck vond op zijn eerste run (01-08)

| | |
|---|---|
| 🟠 high | **`vpdupdate` heeft geen backup van `users.json`** — de enige plek waar die gebruikers bestaan. Laatste kopie is met de hand gemaakt bij de deploy van 28-07 (`/var/backups/havun-vpd-users/`) |
| 🟡 medium | `infosyst.sql.gz` (368 B) en `havunclub_production.sql.gz` (378 B) — elke nacht een lege dump van apps die 18-07 van de server af gingen |
| 🟡 medium | `havunvet_staging.sql.gz` — HavunVet is 24-07 gearchiveerd, de database staat er nog |
| ⚪ info | `havun.nl` en `havuncore-webapp` bewust uitgezonderd, met reden |

Wat er **wél** goed bleek: `judo_toernooi` en `safehavun` worden gewoon geback-upt, verse dumps van
03:00. Het dode register kende ze niet — dat was de valse melding, niet het gat.

Drie staging-omgevingen draaien echt (`havunadmin`, `herdenkingsportaal`, `judotoernooi`,
geverifieerd via `/var/www/*/staging` + de nginx-vhosts), dus hun dumps horen in de verwachting.
Zonder die controle had de check ze als restant gemeld — een verwachting die te smal staat,
produceert net zo goed onzin als een register dat niemand leest.
