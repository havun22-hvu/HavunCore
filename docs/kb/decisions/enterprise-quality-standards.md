# Enterprise Quality Standards

> Architecture Decision Record

## Status

**Accepted** — april 2026

## Context

Na meerdere productie-incidenten en de VP-02 deadline bleek dat:
- Refactoring zonder tests te risicovol was (regressies)
- Fat controllers (4000+ regels) ononderhoudbaar werden
- Generic exception handling bugs maskeerde
- Webhook handlers niet idempotent waren → duplicate payments

## Decision

Alle Havun projecten moeten voldoen aan enterprise quality standards:

### Coverage Normen

| Norm | Waarde |
|------|--------|
| Minimum line coverage | **82.5%** |
| Target line coverage | **90%+** |
| Functionele tests prioriteit | Boven cosmetische tests |
| Kritieke paden | Payment webhooks, auth flows, data integrity |

### Code Grootte Limieten

| Type | Maximum regels | Actie bij overschrijding |
|------|---------------|--------------------------|
| Controller | 800 | Split in subdirectory controllers |
| Service | 600 | Extract helper classes |
| Model | 500 | Extract traits naar `Concerns/` |

### Code Kwaliteit Regels

1. **Generic catch vervangen**: `catch(\Exception $e)` → specifieke exception types
   ```php
   // ❌ Fout
   catch (\Exception $e) { log($e); }

   // ✅ Goed
   catch (PaymentException $e) { /* handle payment */ }
   catch (ValidationException $e) { /* handle validation */ }
   catch (\Exception $e) { /* only as last resort with re-throw */ }
   ```

2. **Webhook idempotency verplicht**: elke webhook handler moet duplicate calls afvangen
   ```php
   // Check of payment al verwerkt is
   if ($payment->status === 'paid') {
       return response()->json(['status' => 'already_processed']);
   }
   ```

3. **.env writes alleen via whitelist + backup**: nooit direct `file_put_contents('.env', ...)`
   ```php
   // Gebruik EnvWriter service met whitelist
   $envWriter->set('APP_KEY', $newKey, allowed: ['APP_KEY', 'APP_URL']);
   ```

4. **Functionele tests boven cosmetische**: test eerst payment flows, auth, data integrity — daarna UI/layout

### Refactoring Patterns

| Probleem | Pattern | Documentatie |
|----------|---------|-------------|
| Fat Controller (>800 regels) | Subdirectory controllers | `docs/kb/patterns/controller-splitting.md` |
| Fat Service (>600 regels) | Helper class extraction | `docs/kb/patterns/service-extraction.md` |
| Fat Model (>500 regels) | Concern traits | `docs/kb/patterns/model-traits.md` |

## Consequences

### Positief

- Refactoring mogelijk zonder angst voor regressies
- Nieuwe developers kunnen code sneller begrijpen
- Production bugs drastisch verminderd
- CI/CD pipeline blokkeert bij coverage drop

### Negatief

- Initiële investering: ~2300 tests schrijven over alle projecten
- Elke feature kost ~20% meer tijd door verplichte tests
- Memory/tijd overhead bij grote test suites (Herdenkingsportaal: 2G)

## Zie Ook

- `docs/kb/runbooks/test-coverage-normen.md` — hoe coverage meten
- `docs/kb/patterns/controller-splitting.md` — fat controller oplossen
- `docs/kb/patterns/service-extraction.md` — fat service oplossen
- `docs/kb/patterns/model-traits.md` — fat model oplossen
