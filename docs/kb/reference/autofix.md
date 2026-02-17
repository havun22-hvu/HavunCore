# AutoFix System

> Automatische error fixing via Claude AI voor production errors.

## Overzicht

AutoFix analyseert production errors automatisch met Claude AI en past fixes direct toe op de server. Bij falen (max 2 pogingen) krijgt de admin een email.

**Actief in:** JudoToernooi
**API:** HavunCore AI Proxy (`/api/ai/chat`, tenant: `judotoernooi`)

## Flow

```
Production Error (500-level)
  → Laravel exception handler
  → AutoFixService::handle($e)
    → shouldProcess() check (rate limit + exclusions)
    → gatherCodeContext() — leest bronbestanden uit stack trace
    → Poging 1:
      → askClaude() — HTTP POST naar HavunCore AI Proxy
      → createProposal() — opslaan in database
      → applyFix() — parse FILE/OLD/NEW, str_replace, backup
      → Succes? → klaar, geen email
    → Poging 2 (indien poging 1 faalt):
      → askClaude() — inclusief foutmelding van poging 1
      → applyFix()
      → Succes? → klaar
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
| `resources/views/autofix/show.blade.php` | Review pagina |
| `resources/views/emails/autofix-proposal.blade.php` | Email template |
| `bootstrap/app.php` | Exception handler integratie (regel ~132) |

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
| email_sent_at | timestamp | Wanneer email verstuurd |
| applied_at | timestamp | Wanneer fix toegepast |

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

### .env (JudoToernooi production)

```
AUTOFIX_ENABLED=true
AUTOFIX_EMAIL=havun22@gmail.com
HAVUNCORE_API_URL=https://havuncore.havun.nl
AUTOFIX_RATE_LIMIT=60
```

### config/autofix.php

- `enabled` — aan/uit schakelaar
- `havuncore_url` — HavunCore API base URL
- `email` — admin email voor failure notifications
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

## Veiligheid

- **Rate limiting:** Max 1 analyse per uniek error (class+file+line) per uur
- **Backup:** Origineel bestand wordt gebackupt als `.autofix-backup.{timestamp}`
- **Scope:** Alleen projectbestanden (geen vendor/, node_modules/, storage/)
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

*Aangemaakt: 18 februari 2026*
