---
title: Google Analytics cross-project removal (2026-04-23)
type: runbook
scope: alle-projecten
last_check: 2026-04-23
status: RESOLVED
---

# Google Analytics verwijderd — cross-project

## Aanleiding

Mozilla Observatory meldde -5 penalty op JudoToernooi: externe gtag.js
zonder SRI. Analyse van de afweging (zie `productie-deploy-eisen.md`
§3.2.1) leidde tot beleid: **GA standaard weg**, tenzij strict onderbouwd.

## Beoordeling per project

| Project | GA aanwezig? | Beoordeling | Actie |
|---|---|---|---|
| JudoToernooi | Ja (extern) | Tool, geen marketing-funnel, data minderjarigen | **Weg** |
| Herdenkingsportaal | Ja (self-hosted + SRI) | Gevoelige data overledenen → AVG-risk | **Weg** |
| HavunCore | Nee | — | Clean |
| HavunAdmin | Nee | Intern admin-panel | Clean |
| Infosyst | Nee | Intern tool | Clean |
| Studieplanner | Nee | Persoonlijke tool | Clean |

## JudoToernooi — fix

Verwijderd uit `resources/views/components/seo.blade.php`:
```blade
{{-- Google Analytics (GA4) --}}
@if(app()->environment('production'))
<script async src="https://www.googletagmanager.com/gtag/js?id=..."></script>
...
```

Deploy: `git pull && php artisan view:clear`.

## Herdenkingsportaal — fix (groter, self-hosted infra)

Verwijderd:
- `app/Console/Commands/RefreshGtagScript.php` — artisan command
- `routes/console.php` → `Schedule::command('gtag:refresh')` entry
- `app/Providers/AppServiceProvider.php` → `Blade::directive('gtagSri', ...)`
- `config/services.php` → `google.analytics_id` sleutel
- `app/Http/Middleware/SecurityHeaders.php` → `google-analytics.com` +
  `googletagmanager.com` uit `connect-src` CSP
- `resources/views/layouts/app.blade.php` — GA script block
- `resources/views/memorials/show.blade.php` — GA script block
- `resources/views/public/homepage.blade.php` — GA script block

Server cleanup:
- `rm -f public/js/gtag.js` — self-hosted gtag
- `rm -f storage/app/gtag-sri-hash.txt` — SRI hash

Deploy: `git pull && php artisan view:clear && php artisan config:clear`.

## Verificatie

```bash
curl -sk https://judotournament.org/ | grep -c 'gtag\|googletagmanager'
curl -sk https://herdenkingsportaal.nl/ | grep -c 'gtag\|googletagmanager'
```
→ beide 0.

## Permanent in KB

`productie-deploy-eisen.md` §3.2.1 — nieuwe analytics-policy met
beoordelingscriteria voor toekomstige projecten.
