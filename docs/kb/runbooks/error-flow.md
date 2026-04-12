# Error Flow — Van fout tot oplossing

> Hoe errors worden opgevangen, doorgestuurd, opgelost en gerapporteerd.

---

## Overzicht

```
Error optreedt in app
    │
    ├─── 1. Laravel Exception Handler
    │        └── Logt naar lokaal: storage/logs/laravel.log
    │
    ├─── 2. Observability (NIEUW — april 2026)
    │        └── Stuurt naar HavunCore DB: error_logs tabel
    │        └── Deduplicatie via fingerprint (class+file+line)
    │        └── Infra-errors gefilterd (EADDRINUSE, ECONNREFUSED, etc.)
    │
    ├─── 3. AutoFix (alleen JudoToernooi + Herdenkingsportaal)
    │        └── AI analyseert error + past automatisch code fix toe
    │        └── Max 2 pogingen per error, rate limit 60 min
    │        └── Git commit + push bij succesvolle fix
    │
    └─── 4. Health Check (elke 5 min)
             └── Bash script checkt of apps bereikbaar zijn
             └── Email alert bij downtime
```

---

## Per laag in detail

### 1. Lokale Laravel Log

| Wat | Detail |
|-----|--------|
| Waar | `storage/logs/laravel.log` per project |
| Retentie | Onbeperkt (handmatig opschonen) |
| Toegang | SSH → `tail -f storage/logs/laravel.log` |
| Nadeel | Alleen lokaal, niet doorzoekbaar, geen alerts |

### 2. Observability → HavunCore

| Wat | Detail |
|-----|--------|
| Waar | HavunCore database, tabel `error_logs` |
| Hoe | `bootstrap/app.php` → `reportable()` → DB insert via `havuncore` connection |
| Dedup | Zelfde error (class+file+line) binnen 1 uur → `occurrence_count++` |
| Filter | EADDRINUSE, ECONNREFUSED, disk full, sock permission worden geskipt |
| API | `GET /api/observability/errors?project=judotoernooi` |
| Dashboard | HavunAdmin → Monitoring → Errors tab |
| Retentie | 30 dagen (automatische cleanup) |

**Actieve projecten:** HavunCore, HavunAdmin, Herdenkingsportaal, Infosyst, SafeHavun, JudoToernooi, Studieplanner API

### 3. AutoFix — Automatische reparatie

| Wat | Detail |
|-----|--------|
| Actief op | JudoToernooi + Herdenkingsportaal |
| Hoe | Error → HavunCore AI Proxy → Claude analyseert → code fix → `php -l` check → git commit |
| Limieten | Max 2 pogingen per error, 60 min cooldown per uniek error |
| Veiligheid | Alleen project-bestanden, `isProjectFile()` check |
| Notificatie | Email bij success + failure |
| Excluded | Infra-errors (EADDRINUSE, ECONNREFUSED, etc.) |

### 4. Server Health Check

| Wat | Detail |
|-----|--------|
| Script | `/usr/local/bin/havun-health-check.sh` |
| Frequentie | Elke 5 minuten (cron) |
| Checkt | Alle 6 publieke apps via `curl -sk` |
| Alert | Email bij downtime, recovery email bij herstel |
| Rate limit | Max 1 alert per uur per app |
| Log | `/var/log/havun-health.log` |

### 5. Chaos Probes (elk uur)

| Wat | Detail |
|-----|--------|
| health-deep | DB, disk, memory, API keys, observability tables |
| endpoint-probe | Alle 6 project endpoints, response time |
| Alert | Email bij FAIL status |
| Resultaten | `chaos_results` tabel in HavunCore |

### 6. Performance Baseline (dagelijks)

| Wat | Detail |
|-----|--------|
| Wanneer | Elke dag 06:00 |
| Wat | p95, avg response time, error rate per project |
| Vergelijking | Met vorige dag |
| Alert | Email als p95 meer dan 2x verslechtert |
| API | `GET /api/observability/baseline?date=2026-04-12` |

---

## Waar kijk ik?

| Vraag | Waar kijken |
|-------|-------------|
| "Is alles online?" | `php artisan chaos:run endpoint-probe` |
| "Zijn er errors?" | HavunAdmin → Monitoring, of API: `/api/observability/errors` |
| "Wat is de performance?" | HavunAdmin → Monitoring dashboard |
| "Wat gebeurde er gisteren?" | `/api/observability/baseline?date=YYYY-MM-DD` |
| "Is de server gezond?" | `php artisan chaos:run health-deep` |
| "Specifieke error details?" | API: `/api/observability/errors?project=X&severity=critical` |
| "Trage queries?" | HavunAdmin → Monitoring → Slow Queries |

---

## Scheduler Overzicht (cron)

| Frequentie | Command | Doel |
|------------|---------|------|
| Elke minuut | `schedule:run` (alle projecten) | Laravel scheduler |
| Elke 5 min | `havun-health-check.sh` | Uptime check + email alert |
| Elk uur | `observability:aggregate --period=hourly` | Metrics samenvatten |
| Elk uur | `chaos:run health-deep` | Systeem health probe |
| Elk uur | `chaos:run endpoint-probe` | Endpoint bereikbaarheid |
| Dagelijks 00:15 | `observability:aggregate --period=daily` | Dagelijkse metrics |
| Dagelijks 03:00 | `observability:cleanup` | Oude data opruimen |
| Dagelijks 06:00 | `observability:baseline` | Performance vergelijking |

---

*Aangemaakt: 12 april 2026*
