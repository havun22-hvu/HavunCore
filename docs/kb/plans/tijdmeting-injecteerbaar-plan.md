---
title: Tijdmeting injecteerbaar maken (AIProxyService)
type: plan
scope: havuncore
status: in uitvoering
last_updated: 2026-08-05
---

# Tijdmeting injecteerbaar maken

**Conclusie:** de duurmeting in `AIProxyService::chat()` wordt achter een `Stopwatch`-interface
gezet, met een monotone implementatie (`hrtime`) in productie en een gestuurde in tests. De drie
timing-tests verliezen hun `usleep()` en meten dan wat ze beweren te meten.

## Waarom — twee aanleidingen

1. **Flaky (03-08).** `test_chat_logs_execution_time_in_milliseconds_not_seconds` viel één keer om
   in een volle run. De test leunt op wall-clock jitter rond een `usleep(50_000)` met ondergrens
   40 ms. Oorzaak nooit vastgesteld — en dat kán ook niet zolang de meting niet stuurbaar is.
2. **De mutatiedekking bestond niet.** De comments claimen dat `*999`/`*1001` (Increment/Decrement
   Integer) gedood worden. Met een sleep van 50 ms levert `*999` → 49,95 en `*1001` → 50,05; beide
   ronden naar 50 en vallen ruim binnen de band 40–500. Diezelfde band laat ook `floor`/`ceil`
   leven. Marge oprekken maakt dat erger; een gestuurde klok maakt het meetbaar.

Bijvangst: `microtime(true)` is wall-clock en kan door een NTP-correctie terugspringen — dan meet
een duur negatief. `hrtime(true)` is monotoon en kent dat probleem niet.

## Wat er komt

| Onderdeel | Keuze |
|---|---|
| `App\Support\Timing\Stopwatch` | Interface: `seconds(): float` (de tijdbron) + `start(): Measurement` |
| `Measurement` | Hier woont de rékenkunde — aftrekking, `*1000`, `round`. Eén plek, één keer getest |
| `SystemStopwatch` | `hrtime(true) / 1e9` — monotoon, productie-implementatie, gebonden als singleton |
| `FakeStopwatch` (tests) | `advance(float $seconds)` stuurt de klok; geen `sleep` meer in de suite |
| `AIProxyService` | Constructor-injectie (alle app-code resolvet al via de container) |

**Waarom een eigen interface en niet `symfony/clock`.** Die component heeft met `MonotonicClock`
precies wat we nodig hebben (PSR-20 op `hrtime`) — het is dus géén gat in het ecosysteem. Twee
redenen om het toch zelf te doen: hij staat alleen *transitief* in `composer.lock`, dus direct
gebruiken maakt er een directe dependency van (overleg-plichtig), en `now()` bouwt per meting een
`DateTimeImmutable` die een duur weer moet uitpakken via `format('U.u')`. Een `float` volstaat.
Wordt de component ooit een directe dependency om andere redenen, dan is dit besluit terug te
draaien — dat is het omkeerpunt.

## Tests — wat ze na afloop bewijzen

Met een gestuurde klok kan de duur zó groot zijn dat de rondingsmutanten niet meer verstoppen:

- `advance(10.0)` → verwacht exact `10000`. `*999` geeft 9990, `*1001` geeft 10010, weggevallen
  `*1000` geeft 10. Alle drie dood op een `assertSame`.
- `advance(10.0006)` → `round` = 10001, `floor` = 10000 → doodt `floor`.
- `advance(10.0004)` → `round` = 10000, `ceil` = 10001 → doodt `ceil`.

**Rood zien:** elke mutant wordt handmatig in `AIProxyService` aangebracht en de test moet erop
falen; de oude tests bleven bij dezelfde mutant groen. Dat verschil is het bewijs.
Regel: `patterns/test-rood-gezien.md`.

## Afgerond 06-08: alle duurmetingen op één idioom

De vijf resterende plekken zijn gemigreerd — `RequestMetricsMiddleware`, `Chaos\ChaosExperiment`
(2×, waaronder `measure()` dat ~15 experimentklassen bedienen), `CriticalPaths\TestRunner` en
`src/Services/BackupOrchestrator`. **`microtime(true)` komt in `app/` en `src/` niet meer voor als
duurmeting.**

Twee dingen die daarbij bleken:

- **`measure()` rondde af op 2 decimalen.** Dat stilzwijgend naar hele milliseconden brengen zou
  elk chaos-rapport veranderen, dus `Measurement` kreeg `elapsedMsPrecise()` erbij. De *default*
  van die methode is getest, want `measure()` roept hem zonder argument aan — een mutant die de
  default van 2 naar 3 zette overleefde eerst.
- **De experimenten komen allemaal uit de container en geen enkele subklasse heeft een eigen
  constructor**, dus injectie op de abstracte klasse bereikte alle vijftien zonder ze aan te raken.
  Drie tests die `new $class()` deden gebruiken nu `app($class)` — die testen daarmee ook het pad
  dat productie echt loopt.

Vijf mutanten opnieuw geverifieerd na de migratie (`*999`, `floor`, `ceil`, `- → +`, en de default
van `elapsedMsPrecise`): alle vijf dood.

## Nog open — zelfde soort schuld

`AIProxyService` construeert zijn `CircuitBreaker` nog steeds zelf (`new CircuitBreaker('claude_api')`,
één regel boven de geïnjecteerde stopwatch). Daardoor moeten twee tests via
`Cache::get('circuit_breaker:…')` in de interne staat graaien — dezelfde omweg als de `usleep()`
die hier verdween.
