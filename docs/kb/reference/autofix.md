# AutoFix System

> Automatische error fixing via Claude AI voor production errors.

## Overzicht

AutoFix analyseert production errors automatisch met Claude AI en past fixes direct toe op de server. Bij zowel succes als falen krijgt de admin een email notificatie.

**Actief in:** JudoToernooi, Herdenkingsportaal
**API:** HavunCore AI Proxy (`/api/ai/chat`)

## Flow

```
Production Error (500-level)
  → Laravel exception handler
  → AutoFixService::handle($e)
    → shouldProcess() check (rate limit + exclusions)
    → gatherCodeContext() — leest bronbestanden uit stack trace
    → Poging 1:
      → askClaude() — HTTP POST naar HavunCore AI Proxy
      → createProposal() — opslaan in database (incl. user/toernooi context)
      → applyFix() — parse FILE/OLD/NEW, str_replace, backup
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
| `config/autofix.php` | Configuratie (enabled, email, excluded exceptions) |
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
| status | enum | pending, approved, rejected, applied, failed |
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

AutoFixService stuurt een system prompt die Claude instrueert om te antwoorden in dit exacte format:

```
ANALYSIS: [1-2 zinnen over de oorzaak]

FILE: [relatief pad, bijv. app/Services/MyService.php]
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

De `applyFix()` methode parsed dit met regex en voert `str_replace` uit.

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
- `email` — admin email voor success + failure notifications
- `rate_limit_minutes` — cooldown per uniek error (default: 60 min)
- `max_context_files` — max bestanden in context (default: 5)
- `max_file_size` — max context grootte in bytes (default: 50000)
- `excluded_exceptions` — lijst van exception classes die geskipt worden

### Excluded Exceptions

- ValidationException
- AuthenticationException
- TokenMismatchException
- NotFoundHttpException
- ModelNotFoundException
- MethodNotAllowedHttpException
- TooManyRequestsHttpException

## Vendor Stack Trace Following

Wanneer een error in vendor code ontstaat (bijv. `BcryptHasher.php`, `QueryBuilder.php`), volgt AutoFix de stack trace naar het eerste **project** bestand:

1. Vendor bestand wordt meegestuurd als referentie met label `(VENDOR - error origin, NOT editable)`
2. Stack trace wordt doorlopen tot het eerste project bestand
3. Dat bestand krijgt label `(FIX TARGET - called vendor code at line X)` + 20 regels context (i.p.v. 10)
4. System prompt instrueert Claude: "fix the PROJECT file, not vendor"

**Zonder dit:** Claude kreeg lege context bij vendor errors en kon geen fix voorstellen.

**Actief in:** JudoToernooi + Herdenkingsportaal (sinds 22 feb 2026)

## Claude Fix Strategie (Prompt Rules)

De system prompt bevat expliciete regels voor hoe Claude fixes moet voorstellen:

1. **Try/catch als primaire strategie** — Wrap de falende call in een try/catch block dat de specifieke exception vangt. Log de error en return een veilige fallback.
2. **Geen argumenten/logica wijzigen** — De aanroepende code is correct; het probleem zit in de data/input.
3. **Vendor exceptions** — Bij RuntimeException, TypeError etc. uit vendor: de vendor code is correct, de input/data is fout. Vang de exception in de aanroepende project code.

**Waarom:** Zonder deze regels stelde Claude vaak verkeerde fixes voor (bijv. methode-argumenten wijzigen i.p.v. try/catch toevoegen). Try/catch is de veiligste auto-fix pattern omdat het bestaande logica niet breekt.

**Actief in:** JudoToernooi + Herdenkingsportaal (sinds 22 feb 2026)

## Veiligheid

- **Rate limiting:** Max 1 analyse per uniek error (class+file+line) per uur
- **Backup:** Origineel bestand wordt gebackupt als `.autofix-backup.{timestamp}`
- **Scope:** Alleen projectbestanden — `isProjectFile()` check in zowel `gatherCodeContext()` als `applyFix()` (vendor/, node_modules/, storage/ geblokkeerd)
- **Vendor errors:** Vendor bestanden worden NOOIT gewijzigd — alleen meegestuurd als context voor de fix in project code
- **Beperkingen Claude:** Geen .env, config, database schema, of dependency wijzigingen
- **Failsafe:** AutoFixService in try/catch — breekt nooit de error handling
- **Review URL:** `/autofix/{token}` voor handmatige inspectie achteraf

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
- Stats: totaal, toegepast, mislukt, in behandeling
- Tabel met alle proposals incl. gebruiker/toernooi context
- Detail panel met URL, HTTP method, route, Claude analyse
- Knop in admin dashboard met badge voor proposals van afgelopen 24 uur

---

*Laatste update: 22 februari 2026*
