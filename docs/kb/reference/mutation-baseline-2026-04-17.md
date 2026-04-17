# Mutation Testing Baseline — 17-04-2026

> **Bron:** VP-16 — eerste lokale baseline-meting met Infection PHP 0.32.6
> **Scope:** `app/Services` (excl. Chaos, DocIntelligence)
> **Duur:** 7 min 17 s, threads=4

## Resultaten

| Metric | Waarde |
|--------|--------|
| Totaal mutaties gegenereerd | 679 |
| Killed (gedood door tests) | 363 |
| Escaped (overleven) | 312 |
| Errored / Syntax / Timed Out / Skipped | 0 |
| Ignored | 4 |
| Mutation Code Coverage | 100% |
| **Mutation Score Indicator (MSI)** | **53,78%** |
| **Covered Code MSI** | **53%** |
| Min MSI drempel (config) | 60% — **niet gehaald** |

## Interpretatie

53% Covered MSI betekent dat ~bijna de helft van de mutaties onopgemerkt blijft.
Dit is een normale eerste baseline; de coverage zelf (87,4%) zegt alleen dat code
*aangeroepen* wordt — niet dat de assertions gedrag écht vastleggen.

## Zwakke plekken (escaped mutations)

Hot-spots uit de log:
- `ObservabilityService::getSystemMetrics()` — disk-bytes berekening volledig
  ongetest (DecrementInteger, IncrementInteger, Division, RoundingFamily mutaties
  ontsnappen op regels 178-179).
- `QrAuthService` (regels 309-419) — array-keys, methodCalls, ProtectedVisibility
  blijven onopgemerkt → tests checken HAPPY PATH maar niet de losse velden.
- `AutoFixService::resolveDeliveryMode()` — 2 escaped (CastArray + ArrayItemRemoval)
  op de `dry_run_on_risk` defaults.

## Vervolgactie

1. **Drempel aanpassen** — `infection.json5`: `minMsi=50` (huidige baseline + 5pp marge).
   Bij elke kwartaalrun MSI met 3-5pp omhoog tot 75%+ gehaald wordt.
2. **Quick wins** — extra assertions toevoegen voor:
   - ObservabilityService array-keys + numerieke waarden
   - QrAuthService device-update payload (alle keys verifiëren)
   - AutoFixService default-config gedrag (lege array vs. `(array)` cast)
3. **Volgende meting** — automatisch via cron op 1 juli 2026, of handmatig
   via `gh workflow run mutation-test.yml`.

## Logs

Volledige output: `storage/logs/infection-baseline-2026-04-17.log`
HTML rapport: `storage/logs/infection.html`
JSON: `storage/logs/infection.json`
