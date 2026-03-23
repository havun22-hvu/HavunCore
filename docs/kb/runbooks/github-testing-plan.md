# GitHub Testing Plan

> **Status:** Fase 1 actief, overige geparkeerd
> **Aangemaakt:** 2026-03-24

## Fase 1: Basis (ACTIEF - HavunCore gereed)

Per project implementeren:
- **Route smoke tests** — alle routes laden zonder 500
- **`composer audit`** — security check op dependencies
- **`php artisan route:list`** — controllers/middleware valid
- **GitHub Actions workflow** — automatisch bij push/PR

### Status per project

| Project | Tests | Workflow | Status |
|---------|-------|----------|--------|
| HavunCore | tests/ (20 tests) | .github/workflows/tests.yml | Done |
| HavunAdmin | - | - | TODO |
| Herdenkingsportaal | - | - | TODO |
| Infosyst | - | - | TODO |
| JudoToernooi | - | - | TODO |
| SafeHavun | - | - | TODO |
| Studieplanner | - | - | TODO |

## Fase 2: Beschermingslaag-tests (TOEKOMST)

- Tests voor kritieke UI/features die eerder per ongeluk zijn verwijderd
- Per project: identificeer features die beschermd moeten worden
- Gebruik als laag 3 van het 5-lagen beschermingssysteem

## Fase 3: API endpoint tests (TOEKOMST)

- HavunCore API responses correct testen (AutoFix, AI proxy, Vault)
- Contract tests: response structuur matcht wat andere projecten verwachten
- Kan uitgebreid worden met database seeding voor realistische tests

## Fase 4: Mail/notification tests (TOEKOMST)

- Herdenkingsportaal: email templates renderen zonder errors
- Notificatie systeem: juiste events worden verstuurd

## Niet doen

- Unit tests op Eloquent models (te veel overhead)
- Browser/Dusk tests (te fragiel)
- 100% code coverage (zinloos bij deze schaal)
