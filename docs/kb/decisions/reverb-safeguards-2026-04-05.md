# Decision: Reverb Broadcasting Safeguards

**Datum:** 5 april 2026
**Context:** LCD scoreboard 2-3 dagen down door kettingreactie van 5 bugs
**Post-mortem:** `JudoToernooi/laravel/docs/postmortem/2026-04-05-reverb-broadcasting-failure.md`

## Beslissing

Elke applicatie die Reverb gebruikt MOET de volgende safeguards implementeren:

### 1. Config validatie (boot-time)

`BroadcastConfigValidator` ServiceProvider die bij eerste request na deploy checkt:
- `allowed_origins` is array (niet string)
- `BROADCAST_CONNECTION` is niet `null` op prod/staging
- Reverb key/secret niet leeg
- Port niet 0

Logt `CRITICAL` als iets fout is.

### 2. Health check command

`php artisan reverb:health` met `--fix` flag:
- Config validatie
- Reverb server bereikbaarheid (HTTP naar interne poort)
- Daadwerkelijke broadcast test (event dispatch)
- Circuit breaker status (+ auto-reset met `--fix`)

Draait automatisch na elke deploy.

### 3. Unit tests voor config

`ReverbConfigTest` bewaakt:
- `allowed_origins` type = array
- Key/secret niet leeg
- Broadcasting key matcht Reverb app key
- Port > 0, scheme in [http, https]

### 4. SafelyBroadcasts trait regels

- ALTIJD de error **message** loggen, niet alleen de class name
- Log level = `WARNING`, niet `debug`
- Throttle = 1x per minuut per event type (voorkom spam, maar wees zichtbaar)

### 5. Procesbeheer

- Kies ÉÉN systeem: Supervisor (standaard voor Havun)
- Systemd services voor Reverb: NIET gebruiken naast Supervisor
- Bij JudoToernooi: `reverb` (prod, poort 8080) en `reverb-staging` (poort 8081)

## Waarom

De outage was onzichtbaar door 3 factoren:
1. SafelyBroadcasts at alle errors op zonder ze te loggen
2. Controller retourneerde altijd `{"success": true}`
3. Geen monitoring of health check

Met deze safeguards:
- Config fouten worden direct gezien (boot-time + tests)
- Broadcast failures zijn zichtbaar in logs (WARNING niveau)
- Na deploy wordt automatisch geverifieerd of Reverb werkt

## Geldt voor

- JudoToernooi (geimplementeerd)
- Elk toekomstig project dat Reverb gebruikt

---

*Beslissing vastgelegd: 5 april 2026*
