---
title: AutoFix-errors laten oppakken door Claude CLI op Henks PC
type: plan
scope: havuncore
status: wacht op besluit
last_updated: 2026-08-06
---

# AutoFix-errors laten oppakken door Claude CLI

**Conclusie: het kan, en het meeste ligt er al — maar de wachtrij die we ervoor zouden gebruiken
staat open op internet zonder één regel authenticatie. Dat moet eerst dicht, anders bouwen we een
knop waarmee een willekeurige buitenstaander code laat draaien op de machine van de ontwikkelaar.**

## ⛔ Blokkerend: de taakwachtrij is publiek beschrijfbaar

`POST /api/claude/tasks` heeft geen middleware, geen token, geen rate limit, en staat expliciet in
de nginx-allowlist. **Gemeten 05-08-2026:** een `curl` vanaf een willekeurige machine kreeg
`{"success":true,"message":"Task created successfully"}` terug (probe-taak `id=14`, direct weer
verwijderd; 0 resterend).

Vandaag is dat onschadelijk omdat de pollers stuk zijn — er is niemand die de taken uitvoert. Maar
dit plan zet er juist een uitvoerder op. Dan is de keten:

```
internet  →  POST /api/claude/tasks  →  poller  →  Claude CLI  →  jouw PC, jouw code, jouw vault
```

Dat is remote code execution op de ontwikkelmachine, via een endpoint dat nu al 200 teruggeeft.
Wat er in de wachtrij stond bevestigt de reikwijdte: taak 5 is een instructie in de vorm
*"cd /var/www/… && git pull, fix logout, clear caches"* — shell-werk in projectmappen.

**Voorwaarde vóór alles: authenticatie op deze routes.** Bearer-token zoals de Vault-API die al
heeft (`EnsureAdminToken` bestaat al als middleware-alias), plus rate limiting. Zonder dat gaat de
poller niet aan — ook niet "even, om te testen".

## Wat er al is

| Onderdeel | Staat | Waar |
|---|---|---|
| `ClaudeTask`-model + statusverloop (`pending → running → completed/failed`) | Werkt | `app/Models/ClaudeTask.php` |
| REST-API: aanmaken, ophalen, `start`/`complete`/`fail` | Werkt, **maar zonder auth** | `routes/api.php` §claude/tasks |
| Poller-script dat de wachtrij leegwerkt | Bestaat in de repo | `scripts/claude-task-poller.sh` |
| Drie systemd-units die het script draaien | **Crashloop, 137.630 restarts sinds 3 mei** | `claude-task-poller@{havuncore,havunadmin,herdenkingsportaal}` |
| AutoFix die een taak aanmaakt als hij er zelf niet uitkomt | **Bestaat niet** | — |

De crashloop komt doordat `/usr/local/bin/claude-task-poller.sh` niet meer op de server staat
(`status=200/CHDIR`). De units herstarten elke seconde en doen niets — dat is ook doorlopende
serverbelasting.

## De opzet: jouw PC haalt op, de server duwt niet

Jouw vraag was of AutoFix de error naar je PC kan sturen. Omgekeerd is beter: **de PC pollt.**

- Geen open poort of port-forward in je router nodig; de verbinding gaat naar buiten.
- Staat je PC uit, dan blijft de taak gewoon staan tot je hem weer aanzet.
- Geen extra infrastructuur — het is dezelfde wachtrij die er al is.

```
AutoFix faalt  →  ClaudeTask (pending)  →  lokale poller op jouw PC  →  Claude CLI in de projectmap
                                                    ↓
                                        start / complete / fail terug naar de API
```

De bestaande `claude-task-poller.sh` is bijna wat we nodig hebben, maar hij gaat uit van
Linux-serverpaden (`/var/www/...`). De lokale variant kent `D:\GitHub\<project>` en draait als
gewone taak op Windows, niet als systemd-unit.

## Beslispunten — jouw keuze, hier vastgelegd

**1. Mag Claude CLI autonoom op een taak draaien?**
Aanbeveling: ja, binnen de grenzen die AutoFix al hanteert — werk op een branch, nooit direct naar
productie, en een PR als eindresultaat. De taak beschrijft de fout; de agent stelt een fix voor die
jij beoordeelt. Wat hij níét mag: pushen naar `master`, migraties draaien, `.env` aanraken.

**2. De drie kapotte units: uitzetten of repareren?**
Aanbeveling: **eerst uitzetten** (`systemctl disable --now`), los van dit plan. Ze doen niets, ze
herstarten 137k keer, en repareren heeft pas zin als bekend is of de poller op de server óf op jouw
PC hoort. Systemd raak ik niet aan zonder jouw expliciete go.

**3. Alleen HavunCore, of alle projecten?**
Aanbeveling: begin met één project. AutoFix draait op JudoToernooi en Herdenkingsportaal; die twee
leveren de echte errors, dus daar begint de waarde. Uitbreiden is later een regel config.

## Stappen zodra de besluiten er zijn

| # | Wat | Raakt |
|---|---|---|
| 1 | Bearer-token + rate limiting op `/api/claude/tasks` | `routes/api.php`, nieuwe middleware, token in de Vault |
| 2 | Kapotte units uitzetten | Server (systemd — jouw go) |
| 3 | AutoFix maakt een `ClaudeTask` aan bij een mislukte analyse | `AutoFixService` |
| 4 | Lokale poller voor Windows + Claude CLI | Nieuw script, buiten de webapp |
| 5 | Grenzen vastleggen die de agent niet mag overschrijden | Runbook + de poller zelf |

Stap 1 gaat niet mee in de trein: die staat op zichzelf en hoort sowieso gefixt, of dit plan nu
doorgaat of niet.

## Aanname en omkeerpunt

**Aanname:** de fouten die AutoFix niet zelf oplost zijn de moeite van een agent-run waard.
**Omkeerpunt:** blijkt na een maand dat de aangemaakte taken vooral ruis zijn — dezelfde fout
opnieuw, of iets dat geen codewijziging nodig heeft — dan is een melding aan jou goedkoper dan een
agent, en vervalt de poller.
