---
title: Kwaliteit & Veiligheid (K&V) systeem
type: runbook
scope: alle-projecten
last_check: 2026-04-19
---

# K&V-systeem (Kwaliteit & Veiligheid)

> **Doel:** centrale, geautomatiseerde borging van kwaliteits- en veiligheidsnormen
> voor **alle Havun-projecten**, zodat regressies én externe kwetsbaarheden niet
> onopgemerkt blijven tussen sessies door.

## Onderdelen (laagsgewijs)

| Laag | Bestand / Systeem | Rol |
|------|-------------------|-----|
| **Normen** | `docs/kb/reference/havun-quality-standards.md` | Enterprise-baseline (coverage >80 %, Form Requests, CSP, etc.) |
| **Policies** | `CLAUDE.md` — 6 Onschendbare Regels | Gedragsregels voor Claude |
| **Findings-log** | `docs/kb/reference/security-findings.md` | Chronologische lijst van CVE's + fixes |
| **Incidenten** | `docs/kb/reference/incidents.md` | Productie-incidenten + post-mortem |
| **Runbooks** | `security-headers-check.md`, `security-findings-logging.md`, deze file | Hoe-te-doen-checks |
| **Geautomatiseerd** | `qv:scan` (artisan) + Laravel scheduler | Dagelijkse/wekelijkse scans |
| **Backups** | `config/havun-backup.php` | Data-veiligheid per project |
| **Sessie-hooks** | `/start` command | Ad-hoc `composer audit` + `docs:issues` |

## Wat scant `qv:scan`?

| Check | Frequentie | Bron | Uitvoer |
|-------|-----------|------|---------|
| `composer audit` | dagelijks | elke project-root in `config/quality-safety.php` | JSON-log + append naar findings-log bij HIGH/CRIT |
| `npm audit --omit=dev` | dagelijks | projects met `package.json` | idem |
| SSL-expiry | wekelijks | prod-URL's | waarschuwing bij <30 dagen tot expiry |
| Mozilla Observatory | wekelijks | prod-URL's | grade < `B` = `high` finding (`D`/`F` = `critical`) via v2 API |
| Server health (SSH) | dagelijks | project-entries met `host` (bv. `server-prod`) | disk ≥ 90 % = `high` (≥ 95 % = `critical`); failed systemd-units = `high` |
| Form-validation coverage | wekelijks (di) | Laravel projects (artisan-detectie) | (FormRequest + inline `::validate`) / write-routes < 60 % = `high`, < 30 % = `critical` (heuristic) |
| Rate-limit coverage | wekelijks (wo) | Laravel projects met write-routes | 0 `throttle:` middleware + 0 `RateLimiter::for(` op write-routes = `high` (boolean — geen rate-limiting actief) |
| Hardcoded secrets | wekelijks (do) | alle code-files (skip vendor/tests/lock) | provider-prefixed credentials (Stripe, AWS, Anthropic, Groq, GitHub PAT, Slack, Mollie, Resend, Google) = `critical`. Output is masked (eerste 8 + laatste 4 chars). |
| Session-cookie flags | wekelijks (vr) | Laravel `config/session.php` | ontbrekende `secure`/`http_only`/`same_site` flag = `high` (XSS / CSRF / cookie hijack risk) |
| Test-erosion | wekelijks (za) | git log + `tests/` walk | test-files deleted in laatste 30d = `high` (VP-17 review); markTestSkipped > 5 = `high` (stille uitschakeling). markTestIncomplete = visible WIP, niet geflagd. |
| Debug-mode default | dagelijks | Laravel `config/app.php` | `'debug' => env('APP_DEBUG', true)` = `critical` (Whoops stack-trace leak in prod als APP_DEBUG mist) |

> De checks zelf zijn **read-only** — geen enkele scan mag code, config of dependencies wijzigen. Fixes gaan via een normale ontwikkel-cyclus (docs-first, /mpc).

## Artisan-interface

```bash
# Alles draaien (alle checks, alle projecten)
php artisan qv:scan

# Specifieke checks
php artisan qv:scan --only=composer
php artisan qv:scan --only=npm
php artisan qv:scan --only=ssl
php artisan qv:scan --only=observatory
php artisan qv:scan --only=server
php artisan qv:scan --only=forms
php artisan qv:scan --only=ratelimit
php artisan qv:scan --only=secrets
php artisan qv:scan --only=session-cookies
php artisan qv:scan --only=test-erosion
php artisan qv:scan --only=debug-mode

# Specifiek project
php artisan qv:scan --project=havunadmin

# JSON-output (voor CI / scheduled runs)
php artisan qv:scan --json

# Laatste run renderen als Markdown-rapport in de KB
php artisan qv:log
php artisan qv:log --output=docs/kb/reference/custom-path.md
```

Exit-codes:
- `0` — alle checks clean
- `1` — één of meer HIGH/CRITICAL findings
- `2` — één of meer checks konden niet draaien (bijv. binary ontbreekt)

## Scheduler-integratie

Geregistreerd in `routes/console.php`:

```php
Schedule::command('qv:scan --only=composer --json')->dailyAt('03:07');
Schedule::command('qv:scan --only=npm --json')->dailyAt('03:17');
Schedule::command('qv:scan --only=ssl --json')->weeklyOn(1, '04:07');          // ma 04:07
Schedule::command('qv:scan --only=observatory --json')->weeklyOn(1, '04:37');  // ma 04:37
Schedule::command('qv:scan --only=server --json')->dailyAt('03:47');           // server health (SSH)
Schedule::command('qv:scan --only=forms --json')->weeklyOn(2, '04:57');         // form-validation coverage (di)
Schedule::command('qv:scan --only=ratelimit --json')->weeklyOn(3, '05:07');     // rate-limit coverage (wo)
Schedule::command('qv:scan --only=secrets --json')->weeklyOn(4, '05:17');       // hardcoded credentials (do)
Schedule::command('qv:scan --only=session-cookies --json')->weeklyOn(5, '05:27'); // session-cookie flags (vr)
Schedule::command('qv:scan --only=test-erosion --json')->weeklyOn(6, '05:37'); // test deletions + skips (za)
Schedule::command('qv:scan --only=debug-mode --json')->dailyAt('03:57');         // APP_DEBUG default check
Schedule::command('qv:log')->dailyAt('03:27');                                  // render latest → KB
```

Off-minuten (`:07`, `:17`, `:27`, `:37`, `:47`) voorkomen dat Havun-cron samenvalt met het wereldwijde `:00`-boeket.

## Output & logging

- **JSON-log per run**: `storage/app/qv-scans/{YYYY-MM-DD}/run-{Hisv}-{pid}.json`
- **Markdown-rapport**: `docs/kb/reference/qv-scan-latest.md` (overschreven door `qv:log` na elke scheduled scan — bevat HIGH/CRIT findings + totals + errors)
- **Curated post-mortem**: `docs/kb/reference/security-findings.md` — handmatig onderhouden met prose, lessen en fix-statussen. Auto-rapport is **alleen** raw data, de human log is de single source of truth voor post-mortem.
- **Geen e-mail** (zie `feedback_no_email_notifications.md`) — in-app notificaties/observability zijn de norm.

## Wat scant `qv:scan` (nog) niet?

- OWASP ZAP / Burp automated DAST — ad-hoc via runbook
- PHPUnit mutation-score — apart traject (`mutation-baseline-2026-04-17.md`)
- Memory / CPU baselines — al gedekt door `observability:baseline`

## Server health — vereisten

De `server`-check gebruikt SSH (publickey-only, `BatchMode=yes`). Vereist op de host die de scheduler draait:

- `ssh` binary in PATH (override via `QV_SSH_BIN` env-var indien nodig)
- SSH-key zonder passphrase (of agent-forwarding) met `root@188.245.159.115` toegang
- `df`, `systemctl` aanwezig op de remote (Linux servers — standaard)

Drempelwaardes en mount-filters (`/snap`, `/dev`, `/proc`, …) staan in `config/quality-safety.php` onder `thresholds.disk_*` en `server.disk_ignore_mounts`.

## Onderhoud

- Nieuw project toevoegen → `config/quality-safety.php` → `projects[]` array
- Pad op prod aanpassen → idem (werkt via env-var met default)
- Check uitzetten voor één project → `'enabled' => false` in config

## Zie ook

- `docs/kb/reference/havun-quality-standards.md` — waarom deze normen
- `docs/kb/runbooks/security-findings-logging.md` — wat loggen, hoe structureren
- `docs/kb/runbooks/security-headers-check.md` — CSP / Mozilla Observatory-detail
- `config/havun-backup.php` — data-veiligheid (complement)
