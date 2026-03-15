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

*Laatste update: 16 maart 2026*
