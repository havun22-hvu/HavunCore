---
title: AutoFix System
type: reference
scope: havuncore
last_check: 2026-07-14
---

# AutoFix System

> Automatische error fixing via Claude AI voor production errors.

## Overzicht

AutoFix analyseert production errors automatisch met Claude AI en past fixes direct toe op de server. Bij zowel succes als falen krijgt de admin een email notificatie. Claude kan ook `NOTIFY_ONLY` antwoorden als een code fix niet mogelijk is (bijv. schema issues) — dan wordt alleen een melding gestuurd.

**Actief in:** JudoToernooi, Herdenkingsportaal
**API:** HavunCore AI Proxy (`/api/ai/chat`)

## Flow

```
Production Error (500-level)
  → Laravel exception handler
  → AutoFixService::handle($e)
    → shouldProcess() check:
      - Excluded exception classes (incl. Tinker/PsySH, artisan commands)
      - Excluded file patterns (/tmp/, vendor/psy/)
      - isProjectFile check op error origin (moet project file in stack trace zijn)
      - Rate limit (1 per uur per uniek error)
      - Recently-fixed check (geen fix-op-fix in 24h)
    → gatherCodeContext() — hele file als < 50KB, anders 100 regels
    → Poging 1:
      → askClaude() — HTTP POST naar HavunCore AI Proxy
      → NOTIFY_ONLY? → createProposal(notify_only) → email → klaar
      → createProposal() → opslaan in database
      → applyFix():
        - Protected files check
        - 24h rollback check (was bestand al gewijzigd?)
        - Parse FILE/OLD/NEW, str_replace, backup
      → Succes? → sendSuccessNotification() → klaar
    → Poging 2 (indien poging 1 faalt):
      → askClaude() — inclusief foutmelding van poging 1
      → applyFix()
      → Succes? → sendSuccessNotification() → klaar
    → Beide pogingen mislukt:
      → sendFailureNotification() — email naar admin
```

## Bestanden (JudoToernooi)

| Bestand | Functie |
|---------|---------|
| `config/autofix.php` | Configuratie (enabled, email, excluded exceptions, file patterns, protected files) |
| `app/Services/AutoFixService.php` | Kernlogica: analyze, apply, notify |
| `app/Models/AutofixProposal.php` | Eloquent model + rate limit check |
| `app/Mail/AutoFixProposalMail.php` | Failure notification email |
| `app/Http/Controllers/AutoFixController.php` | Review/approve/reject web UI |
| `app/Http/Controllers/AdminController.php` | Admin overzicht (`autofix()` method) |
| `resources/views/pages/admin/autofix.blade.php` | Admin overzicht pagina |
| `resources/views/autofix/show.blade.php` | Review pagina |
| `resources/views/emails/autofix-proposal.blade.php` | Email template |
| `bootstrap/app.php` | Exception handler integratie (regel ~132) |

## Bestanden (Herdenkingsportaal)

Identieke structuur als JudoToernooi, maar aangepast voor Herdenkingsportaal context:

| Bestand | Functie |
|---------|---------|
| `config/autofix.php` | Configuratie |
| `app/Services/AutoFixService.php` | Kernlogica (tenant: `herdenkingsportaal`) |
| `app/Models/AutofixProposal.php` | Eloquent model |
| `app/Mail/AutoFixProposalMail.php` | Failure notification email |
| `app/Http/Controllers/AdminController.php` | Admin overzicht (`autofix()` method) |
| `resources/views/admin/autofix.blade.php` | Admin overzicht (x-app-layout, dark mode) |
| `resources/views/emails/autofix-proposal.blade.php` | Email template |
| `bootstrap/app.php` | Exception handler (`$exceptions->report()`) |

**Verschil met JudoToernooi:** Auth guard `web` (User), context: user_name/user_email/memorial i.p.v. organisator/toernooi.

## Database: autofix_proposals

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| exception_class | string | Bijv. `ErrorException` |
| exception_message | text | Foutmelding (max 1000 chars) |
| file | string | Relatief pad |
| line | int | Regelnummer |
| stack_trace | text | Top 10 frames |
| code_context | longText | Broncode rond error |
| claude_analysis | longText | Claude's volledige response |
| proposed_diff | longText | Kopie van claude_analysis |
| approval_token | string(64) | Uniek token voor review URL |
| status | enum | pending, approved, rejected, applied, failed, **notify_only** |
| apply_error | text | Foutmelding bij mislukt toepassen |
| url | string | Request URL |
| *context kolommen* | | *Verschilt per project (zie onder)* |
| http_method | string | GET, POST, etc. |
| route_name | string | Laravel route naam |
| email_sent_at | timestamp | Wanneer email verstuurd |
| applied_at | timestamp | Wanneer fix toegepast |

### Context kolommen per project

| Project | Kolommen |
|---------|---------|
| JudoToernooi | organisator_id, organisator_naam, toernooi_id, toernooi_naam, http_method, route_name |
| Herdenkingsportaal | user_id, user_name, user_email, memorial_id, memorial_naam, http_method, route_name |

## Claude Prompt Format

AutoFixService stuurt een system prompt die Claude instrueert om te antwoorden in dit format:

```
ACTION: FIX | NOTIFY_ONLY
ANALYSIS: [1-2 zinnen over de oorzaak]

FILE: [relatief pad, bijv. app/Services/MyService.php]    (alleen bij ACTION: FIX)
OLD:
```php
[exacte code om te vervangen]
```
NEW:
```php
[vervangende code]
```

RISK: [low/medium/high]
```

Bij `ACTION: NOTIFY_ONLY` worden de FILE/OLD/NEW blokken niet verwacht. AutoFix slaat de proposal op met status `notify_only` en stuurt alleen een email met Claude's analyse.

De `applyFix()` methode parsed het FIX-format met regex en voert `str_replace` uit.

## Claude Fix Strategie (Prompt Rules)

De system prompt bevat een gerangschikte fix strategie:

1. **NULL SAFETY** — Als error "on null" bevat: gebruik `?->` of null checks
2. **COLUMN/SCHEMA** — Als kolom niet bestaat: `ACTION: NOTIFY_ONLY` (migration nodig)
3. **MISSING RESOURCE** — Als command/class/file niet bestaat: `ACTION: NOTIFY_ONLY` (handmatige actie nodig)
4. **LOGIC FIX** — Bij logische fouten: fix de logica minimaal
5. **TRY/CATCH** — Alleen als **laatste redmiddel**, NOOIT rond entrypoints of hele method bodies

**Verboden:**
- Nooit het `artisan` bestand wijzigen
- Nooit try/catch rond hele method bodies
- Nooit code verzinnen die niet in de context staat

**Waarom deze volgorde:** Analyse van 11 AutoFix pogingen (feb 2026) toonde dat try/catch als default meer kapot maakte dan het repareerde. De nieuwe strategie lost problemen bij de bron op i.p.v. ze te maskeren.

**Actief in:** JudoToernooi + Herdenkingsportaal (sinds 25 feb 2026)

## Configuratie

### .env (identiek voor alle projecten)

```
AUTOFIX_ENABLED=true
AUTOFIX_EMAIL=havun22@gmail.com
HAVUNCORE_API_URL=https://havuncore.havun.nl
AUTOFIX_RATE_LIMIT=60
```

**Geconfigureerd op:** JudoToernooi production, Herdenkingsportaal production

### config/autofix.php

- `enabled` — aan/uit schakelaar
- `havuncore_url` — HavunCore API base URL
- `email` — admin email voor success + failure + notify_only notifications
- `rate_limit_minutes` — cooldown per uniek error (default: 60 min)
- `max_context_files` — max bestanden in context (default: 5)
- `max_file_size` — max context grootte in bytes (default: 50000)
- `excluded_exceptions` — lijst van exception classes die geskipt worden
- `excluded_file_patterns` — regex patterns voor file paths die genegeerd worden
- `protected_files` — bestanden die nooit gewijzigd mogen worden door AutoFix

### Excluded Exceptions

**Standaard (Laravel):**
- ValidationException, AuthenticationException, TokenMismatchException
- NotFoundHttpException, ModelNotFoundException
- MethodNotAllowedHttpException, TooManyRequestsHttpException

**Nieuw toegevoegd (25 feb 2026):**
- `Psy\Exception\ParseErrorException` — Tinker syntax errors
- `Psy\Exception\ErrorException` — Tinker runtime errors
- `Symfony\Component\Console\Exception\NamespaceNotFoundException` — missing artisan commands
- `Symfony\Component\Console\Exception\CommandNotFoundException` — missing artisan commands

### Excluded File Patterns

Regex patterns — errors uit deze paden worden genegeerd:
- `#/tmp/#` — tijdelijke bestanden
- `#vendor/psy/#` — PsySH/Tinker
- `#vendor/laravel/tinker/#` — Laravel Tinker

### Protected Files

Bestanden die nooit gewijzigd mogen worden:
- `artisan` — entrypoint, te risicovol
- `public/index.php` — entrypoint
- `bootstrap/app.php` — app bootstrap
- `composer.json`, `composer.lock` — dependencies

## Vendor Stack Trace Following

Wanneer een error in vendor code ontstaat (bijv. `BcryptHasher.php`, `QueryBuilder.php`), volgt AutoFix de stack trace naar het eerste **project** bestand:

1. Vendor bestand wordt meegestuurd als referentie met label `(VENDOR - error origin, NOT editable)`
2. Stack trace wordt doorlopen tot het eerste project bestand
3. Dat bestand wordt volledig meegestuurd (als < 50KB) met label `(FULL FILE - FIX TARGET)`
4. System prompt instrueert Claude: "fix the PROJECT file, not vendor"

**Actief in:** JudoToernooi + Herdenkingsportaal (sinds 22 feb 2026)

## Context Gathering

Verbeterd (25 feb 2026) om meer context mee te sturen:

- **Bestanden < 50KB:** Hele file wordt meegestuurd met label `(FULL FILE - ...)`
- **Bestanden > 50KB:** 100 regels rond de error (was: 20 regels)
- **FIX TARGET (vendor errors):** Hele file meegestuurd (was: 20 regels)

**Waarom:** Met slechts 20 regels context kon Claude de code niet begrijpen en stelde verkeerde fixes voor.

## Veiligheid

- **Rate limiting:** Max 1 analyse per uniek error (class+file+line) per uur
- **Backup:** Origineel bestand wordt gebackupt in `storage/app/autofix-backups/`
- **Syntax check:** Na elke fix wordt `php -l` uitgevoerd. Bij syntax error → automatische rollback vanuit backup (sinds 15 maart 2026)
- **Scope:** Alleen projectbestanden — `isProjectFile()` check in zowel `gatherCodeContext()` als `applyFix()` (vendor/, node_modules/, storage/ geblokkeerd)
- **No-project-file check:** Als er geen enkel project bestand in de stack trace zit, wordt de error geskipt
- **Vendor errors:** Vendor bestanden worden NOOIT gewijzigd — alleen meegestuurd als context voor de fix in project code
- **Protected files:** Bestanden in `config('autofix.protected_files')` kunnen niet gewijzigd worden
- **Rollback-bewustzijn:** Als een bestand al in de afgelopen 24 uur door AutoFix is gewijzigd, wordt het overgeslagen (voorkomt cascade-fixes). Geldt voor zowel Service als Controller.
- **NOTIFY_ONLY:** Claude kan aangeven dat een code fix niet mogelijk is — dan wordt alleen een melding gestuurd, geen code gewijzigd
- **Beperkingen Claude:** Geen .env, config, database schema, of dependency wijzigingen
- **Failsafe:** AutoFixService in try/catch — breekt nooit de error handling
- **Review URL:** `/autofix/{token}` voor handmatige inspectie achteraf

## Git Sync (Kennis-drift preventie)

Sinds 15 maart 2026 commit en pusht AutoFix automatisch na een succesvolle fix:

```
Fix toegepast → php -l syntax check → OK → git add + commit + push
                                        ↓
                                      FAIL → rollback vanuit backup, geen git
```

**Commit format (gestructureerd voor DocIndexer):**

```
autofix(FileName): Claude's analysis summary (max 72 chars)

File: app/Services/MyService.php
Exception: ErrorException
Risk: low
Proposal: #42
```

- Titel: `autofix(scope): wat er gefixt is` — leesbaar in git log
- Body: metadata voor DocIndexer (file, exception, risk, proposal ID)

**Waarom:** Zonder git sync weet de lokale ontwikkelomgeving niet dat er code gewijzigd is op de server. Dit veroorzaakte "kennis-drift" — de KB en lokale code liepen uit sync met productie.

**Lokale sync:** Het `/start` command in Claude doet automatisch `git pull` bij sessie start om de lokale code te synchroniseren.

**Faalveilig:** Git operations zitten in try/catch. Als push faalt (bijv. merge conflict), wordt dit gelogd maar de fix blijft actief op de server.

## Troubleshooting

```bash
# Logs bekijken
tail -f /var/www/judotoernooi/laravel/storage/logs/laravel.log | grep AutoFix

# Database proposals bekijken
cd /var/www/judotoernooi/laravel
php artisan tinker
>>> AutofixProposal::latest()->take(5)->get(['id','status','exception_class','created_at'])

# Backup bestanden zoeken
find /var/www/judotoernooi -name "*.autofix-backup.*"
find /var/www/judotoernooi/laravel/storage/app/autofix-backups -type f

# AutoFix uitschakelen
# In .env: AUTOFIX_ENABLED=false
# Dan: php artisan config:clear
```

### Achterhaalde "AutoFix failed" — schema/DDL-fout blijft als open `system_alert` hangen

**Symptoom:** Je ziet bij sessiestart of in monitoring een `AutoFix failed: ... SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'jobs' already exists` (of een andere DDL-fout), terwijl prod-code én database aantoonbaar in orde zijn.

**Oorzaak:** AutoFix kan schema-/DDL-fouten **niet** repareren (fix-strategie regel COLUMN/SCHEMA → `NOTIFY_ONLY`, geen code-fix). Bij zo'n fout schrijft het een `system_alert` (`type=autofix`). Die alert wordt **niet automatisch gesloten** wanneer het onderliggende probleem later via een deploy/migratie wél is opgelost. De alert blijft dus `open` (`resolved_at IS NULL`) en wordt bij elke `/start` opnieuw als "AutoFix failed" gezien — ook al is de fout maanden oud en al verholpen.

Typisch scenario: een `create_*_table`-migratie stond op prod als *Pending* terwijl de tabel al bestond (migratie-rij ooit verloren) → `migrate --force` gaf 1050. Opgelost met een **idempotente guarded migratie** (`if (Schema::hasTable('x')) return;`) + deploy. De code is daarna goed, maar de historische alert blijft open.

**Diagnose (read-only, bewijs verzamelen vóór je iets doet):**
```bash
cd /var/www/<project>/repo-prod/laravel   # let op: 'laravel' is vaak een symlink → repo-prod/laravel
git rev-parse --abbrev-ref HEAD && git fetch -q && git rev-list --left-right --count HEAD...@{u}  # 0  0 = up-to-date
sudo -u www-data php artisan migrate:status | grep -i pending    # leeg = geen pending
curl -s -o /dev/null -w "%{http_code}\n" https://<domein>/       # 200 = live
grep -rl "1050\|already exists" storage/logs/ | head             # datum van de fout → historisch?
sudo -u www-data php artisan tinker --execute="echo App\Models\SystemAlert::whereNull('resolved_at')->count();"
```

**Fix (alléén als prod aantoonbaar consistent is — NIET blind deployen/migreren):** sluit de achterhaalde alert netjes via Eloquent, met een guard op de message zodat je zeker de juiste sluit. `system_alerts` heeft géén `status`-kolom; "resolved" = `resolved_at` vullen (+ `is_read`).
```bash
# 1. ALTIJD eerst backup (DB-write op prod):
MYSQL_PWD="$(grep ^DB_PASSWORD= .env|cut -d= -f2-|tr -d '"')" mysqldump --single-transaction --quick --no-tablespaces \
  -h 127.0.0.1 -u "$(grep ^DB_USERNAME= .env|cut -d= -f2-)" "$(grep ^DB_DATABASE= .env|cut -d= -f2-)" \
  | gzip > /var/backups/havun-db/<project>/db_$(date +%Y%m%d-%H%M%S).sql.gz
# 2. Alert sluiten (guarded):
sudo -u www-data php artisan tinker --execute="
\$a = App\Models\SystemAlert::find(<id>);
if (str_contains(\$a->message, '1050')) { \$a->resolved_at = now(); \$a->is_read = true; \$a->save(); echo 'resolved'; }
else { echo 'ABORT: verkeerde alert'; }"
```

**Kernles:** een openstaande `system_alert` bewijst niet dat prod stuk is — het kan een niet-gesloten historische melding zijn. Verifieer code + migrate:status + live-status; forceer geen deploy/migrate "voor de zekerheid" (dat is juist onnodig prod-risico). Casus 14-07-2026: JudoToernooi alert id=3 (1050 `create_jobs_table`, 26 juni) — opgelost door guarded migratie `50bda4c9` + deploy 4 juli, alert bleef 18 dagen open.

## Afhankelijkheden

- **HavunCore AI Proxy** — tenant `judotoernooi` moet toegevoegd zijn
- **Email** — Laravel mail configuratie moet werken
- **Disk schrijfrechten** — www-data moet kunnen schrijven in app/ directories

---

## Admin Overzicht

Toegankelijk via `/admin/autofix` (alleen sitebeheerders). Toont:
- Stats: totaal, toegepast, mislukt, in behandeling, notify_only
- Tabel met alle proposals incl. gebruiker/toernooi context
- Detail panel met URL, HTTP method, route, Claude analyse
- Knop in admin dashboard met badge voor proposals van afgelopen 24 uur

---

## Branch-Model (VP-01 — gepland Q2 2026)

> **Bron:** Externe audit Q1 2026 — AutoFix pusht niet langer direct naar main.
> **Status:** SPECIFICATIE — implementatie gepland april 2026

### Waarom?

Beide externe reviewers (Gemini + Claude Sonnet) adviseren om AutoFix niet direct naar de hoofdbranch te pushen. Hoewel er 8 safety-checks zijn, blijft directe productiewijziging een risico dat in de professionele industrie vrijwel universeel wordt afgeraden.

### Nieuwe flow

```
Production Error (500)
  → AutoFixService::handle($e)
  → shouldProcess() checks (ongewijzigd)
  → askClaude() (ongewijzigd)
  → RISK: medium/high → DRY-RUN: alleen notificatie, geen fix
  → RISK: low → applyFix() + branch-model:
      1. git checkout -b hotfix/autofix-{timestamp}
      2. Apply fix to file
      3. php -l syntax check (auto-rollback bij fout)
      4. git add + commit + push hotfix branch
      5. Create PR via GitHub API (base: main/master)
      6. Email notificatie met PR-link
      7. Eigenaar reviewed en mergt met één klik
```

### Configuratie (GEÏMPLEMENTEERD 29-03-2026)

```php
// config/autofix.php
'branch_model' => env('AUTOFIX_BRANCH_MODEL', true),
'branch_prefix' => 'hotfix/autofix-',
'auto_pr' => env('AUTOFIX_AUTO_PR', true),
'dry_run_on_risk' => ['medium', 'high'],
'github_token' => env('GITHUB_TOKEN'),     // Personal Access Token, repo scope
```

### .env vereist op server:
```
GITHUB_TOKEN=ghp_...   # Personal Access Token met 'repo' scope
```

### Wat verandert er NIET?

- Rate limiting, syntax check, backup, protected files — alles blijft
- Claude prompt format en fix-strategie — ongewijzigd
- Email notificaties — ongewijzigd (PR-link wordt toegevoegd)
- Review URL `/autofix/{token}` — blijft bestaan

### Implementatie (AFGEROND 29-03-2026)

Geïmplementeerd via GitHub REST API (Laravel `Http` facade), geen `gh` CLI nodig.

**Nieuwe methodes in AutoFixService:**
- `isDryRunRisk()` — checkt of RISK level in dry_run_on_risk zit
- `sendDryRunNotification()` — stuurt e-mail bij dry-run
- `gitBranchAndPR()` — maakt branch, commit, push, PR via API
- `gitDirectPush()` — fallback (legacy direct push)
- `createGitHubPR()` — GitHub REST API call voor PR aanmaak
- `extractRisk()` — parsed RISK level uit Claude's analyse

**Projecten:**
- [x] JudoToernooi — commit `6d0faa3e`
- [x] Herdenkingsportaal — commit `8ac8c01`

---

*Laatste update: 29 maart 2026*
