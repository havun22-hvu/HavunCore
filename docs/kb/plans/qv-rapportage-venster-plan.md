---
title: De V&K-rapportage leest één run per dag — plan
type: plan
scope: havuncore
status: af — venster samengevoegd (03-08) en ontbrekende checks gemeld (06-08)
last_updated: 2026-08-06
---

# De rapportage leest één run per dag

**Gevonden 03-08-2026.** `qv:log` en `docs:handover` lezen via `LatestRunFinder::findPath()` het
**nieuwste** run-bestand. Maar elke `qv:scan --only=X` schrijft een **eigen** bestand. Wat niet
toevallig de laatste run vóór 03:27 (`qv:log`) of 04:00 (`docs:handover`) is, komt dus in geen
enkel rapport.

## Wat dat vannacht kostte

| Run | Check | Uitkomst | Gerapporteerd? |
|---|---|---|---|
| 03:24 | deps-coverage | schoon | ✅ (`qv-scan-latest.md` leest deze) |
| 03:57 | debug-mode | schoon | ✅ (`docs/handover.md` leest deze) |
| 04:37 | observatory | **high 1** — safehavun.havun.nl grade C | ❌ |
| 03:07 / 03:17 / 03:22 | composer / npm / cargo | **40 errors** | ❌ |
| 03:47 | server | 1 error | ❌ |
| 05:30 | backup-coverage | 1 error | ❌ |

Beide rapporten meldden `critical 0 | high 0 | medium 0 | low 0`.

**Alle acht wekelijkse checks** (ssl, observatory, forms, ratelimit, secrets, session-cookies,
test-erosion, residu) draaien tussen 04:07 en 05:47 — ná beide rapportagemomenten. Ze hebben dus
**nooit** een finding in een gerenderd document gekregen.

## De fix

`LatestRunFinder` levert de runs uit een **venster van 8 dagen** (dekt de wekelijkse checks).
`MergedRunAssembler` voegt ze samen tot één run-vorm:

- **per check wint de nieuwste run** — oudere runs van dezelfde check tellen niet mee, anders
  verschijnt een opgeloste finding opnieuw
- findings en errors komen uit de winnende runs
- `totals` wordt **herberekend** uit het resultaat, niet opgeteld uit de deelruns
- per check komt de tijd van zijn run mee, zodat een uitslag van zes dagen oud als zodanig leesbaar is

De drie afnemers (`qv:log`, `docs:handover`, `ObservabilityService`) gebruiken dezelfde
samenvoeging, zodat ze niet opnieuw uiteen kunnen lopen.

**Aanname:** een run binnen 8 dagen beschrijft de huidige toestand van die check.
**Omkeerpunt:** komt er een check met een interval langer dan een week, dan is een vast venster te
smal en moet het venster per check uit zijn eigen frequentie volgen.

## Afgerond 06-08 — een check die stopt, meldt zich

`VerwachteChecks` leest de scheduler (16 checks) en vergelijkt die met de runs in het venster.
Ontbreekt er een, of draaide hij te lang geleden, dan staat dat als fout in het rapport met
`type: check-ontbreekt` — onderscheidbaar van een check die wél draaide en faalde.

**Dagelijks krijgt 36 uur, wekelijks anderhalve week**: één overgeslagen run blijft stil, twee niet.
De marge komt uit de cron-expressie, niet uit een aparte lijst — voeg je een check toe aan
`routes/console.php`, dan wordt hij vanzelf verwacht. Een tweede lijst zou uiteenlopen, en dan
bewaakt hij de verkeerde verzameling.

**`assemble()` gaf `null` bij nul runs**, en elke aanroeper maakte daar een leeg rapport van — een
scheduler die helemaal stilstond las als "niets aan de hand". Nu komt er een uitslag terug waarin
alle zestien als ontbrekend staan. `qv:log` waarschuwt nog steeds en eindigt op 1, maar schrijft het
rapport wél: dat is wat iemand leest.

Op productie geforceerd geverifieerd: een weggelaten check meldt *"heeft nooit gedraaid"*, een
check van tien dagen oud meldt *"240 uur; verwacht binnen 36"*, en een lege uitslag meldt er zestien.

## Opgelost op 03/04-08 (stond hier als "nog niet in deze fix")

- ~~**De code-checks meten op de server niets**~~ — `composer`/`npm`/`cargo` gebruikten Henks
  Windows-pad op Linux. Nu wint het pad dat op de draaiende machine bestaat (`server_path` **én**
  `remote_path`). Van 40 errors naar 0.
- ~~**`serverHealth` gaat via SSH naar `root@`**~~ — `runRemote()` draait lokaal als de host deze
  machine is. Geverifieerd met een kunstmatige drempel.

Laatste scan: `critical 0 | errors 0`. De 23 high zijn echte dependency-advisories in andere
projecten, geen meetfouten — die staan in de handover.
