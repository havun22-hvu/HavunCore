# Handover — 12 april 2026

## Wat is er gedaan

### Niveau 7: Observability (COMPLEET)
- **RequestMetrics middleware** op alle 7 Laravel projecten → centraal in HavunCore DB
- **Error tracking** met fingerprint dedup + infra-error filter
- **Slow query detection** (>100ms), hourly/daily aggregatie (p95/p99)
- **6 API endpoints** + multi-project filtering (?project=judotoernooi)

### Niveau 8: Chaos Engineering (COMPLEET)
- **5 experimenten**: health-deep, endpoint-probe, error-flood, db-slow, api-timeout
- **Circuit breaker** op Claude API, scheduled probes elk uur

### Overige verbeteringen
- **Dashboard** in HavunAdmin (Monitoring + Errors + Slow Queries + AutoFix tab)
- **AutoFix gecentraliseerd** — centraal via HavunCore + lokale fallback
- **Performance baseline** dagelijks 06:00
- **Email verwijderd** — alles via in-app dashboard (email = alleen registratie/login)
- **Log rotatie** — daily channel, logrotate config, ~1GB opgeschoond
- **Reverb fix** — systemd disabled (dubbel met Supervisor)
- **Nginx** — alle Laravel routes correct gerouteerd
- **HavunCore scheduler** in cron

## Open items (bijgewerkt 16-04-2026)

1. ~~Herdenkingsportaal coverage~~ — **GEHAALD 85,75%** (meting 15-04 via CI/PCOV), boven verscherpt 85%-doel
2. **Studieplanner** 82,67% → 90% (React Native/Jest, aparte sessie)
3. **HavunCore CI-drempel** 40% → stapsgewijs naar 80%

Actuele coverage zie `docs/kb/runbooks/test-coverage-normen.md`.

## Server config (niet in git)
- `/etc/logrotate.d/havun-laravel`
- Studieplanner API observability bestanden direct op server
- Nginx: `claude|mcp|version|docs|observability|autofix|health|ai|auth|vault|studieplanner`
- `OBSERVABILITY_ADMIN_TOKEN` in HavunCore + HavunAdmin `.env`

---

YOLO Mode Test Geslaagd.

## Documentatie Bijgewerkt (april 2026)

Enterprise quality standards en refactoring patterns gedocumenteerd. Test portfolio gegroeid naar **9.600+ tests / 17.000+ assertions** over 9 projecten, gemiddeld ~88% coverage.

### Nieuwe KB Documenten

| Document | Type | Beschrijving |
|----------|------|-------------|
| [test-coverage-normen.md](kb/runbooks/test-coverage-normen.md) | Runbook | Verplichte 80% coverage norm, hoe meten, project-specifieke tips |
| [enterprise-quality-standards.md](kb/decisions/enterprise-quality-standards.md) | Decision | ADR: waarom 80%+, code limieten, kwaliteitsregels |
| [controller-splitting.md](kb/patterns/controller-splitting.md) | Pattern | Fat Controller → subdirectory controllers (MemorialController voorbeeld) |
| [service-extraction.md](kb/patterns/service-extraction.md) | Pattern | Fat Service → helper classes (EliminatieService voorbeeld) |
| [model-traits.md](kb/patterns/model-traits.md) | Pattern | Fat Model → Concerns traits (Memorial model voorbeeld) |
