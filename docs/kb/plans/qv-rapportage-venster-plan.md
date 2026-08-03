---
title: De V&K-rapportage leest één run per dag — plan
type: plan
scope: havuncore
status: in uitvoering
last_updated: 2026-08-03
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

## Nog niet in deze fix

- **Een check die te lang niet gedraaid heeft, meldt zich niet.** Valt hij helemaal weg uit het
  venster, dan verdwijnt hij stil uit het rapport — dezelfde faalmodus als
  `plans/registry-drift-check-plan.md` beschrijft, nu op de scheduler. Volgende stap: de verwachte
  frequentie per check vastleggen en afwezigheid als finding melden.
- **De code-checks meten op de server niets** (`composer`, `npm`, `cargo`): ze gebruiken `path` uit
  `havun-projects.php`, en dat is Henks Windows-pad. Aparte beslissing — waar horen die te draaien.
- **`serverHealth`** gaat via SSH naar `root@` en valt op de server om, net als de backupcheck deed.
