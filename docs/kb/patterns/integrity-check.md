# Pattern: Shadow File Integrity Check

> Bewaakt kritieke UI-componenten en code-elementen via een `.integrity.json` bestand,
> zonder de productiecode te vervuilen met `DO NOT REMOVE` comments.

## Wat is het?

Een `.integrity.json` in de project root beschrijft welke kritieke elementen in welke bestanden MOETEN staan. Een check-script valideert dit automatisch bij `/end` of in CI.

## Wanneer gebruiken?

- Als alternatief voor of aanvulling op `DO NOT REMOVE` comments
- Bij views/templates met veel kritieke elementen
- Bij API responses die altijd bepaalde keys moeten bevatten
- Bij config bestanden die niet per ongeluk leeggehaald mogen worden

## `.integrity.json` Schema (v2.0)

Ondersteunt drie typen checks:
- **`must_contain`** — plain text zoeken (string moet voorkomen in het bestand)
- **`must_contain_selector`** — CSS selector zoeken in HTML/Blade bestanden (v2.0)
- **`must_contain_route`** — Laravel route-naam moet geregistreerd zijn (v2.0)

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "version": "2.0",
  "project": "herdenkingsportaal",
  "updated": "2026-04-15",
  "checks": [
    {
      "file": "resources/views/layouts/app.blade.php",
      "description": "Main layout moet legal footer links bevatten",
      "must_contain": [
        "legal.terms",
        "legal.privacy",
        "legal.cookies"
      ]
    },
    {
      "file": "resources/views/layouts/app.blade.php",
      "description": "Kritieke UI-elementen moeten aanwezig zijn",
      "must_contain_selector": [
        "footer.site-footer",
        "#cookie-banner",
        "nav.main-navigation",
        "[data-testid=\"logo\"]"
      ]
    },
    {
      "file": "resources/views/layouts/app.blade.php",
      "description": "Favicon references moeten aanwezig zijn",
      "must_contain": [
        "favicon.ico",
        "apple-touch-icon.png"
      ]
    },
    {
      "file": "app/Http/Controllers/PaymentController.php",
      "description": "Payment controller moet Mollie webhook handler hebben",
      "must_contain": [
        "handleWebhook",
        "PaymentServiceFactory"
      ]
    },
    {
      "file": "routes/web.php",
      "description": "Legal routes moeten bestaan",
      "must_contain": [
        "legal.terms",
        "legal.privacy",
        "legal.cookies",
        "legal.disclaimer"
      ],
      "must_contain_route": [
        "legal.terms",
        "legal.privacy"
      ]
    },
    {
      "file": "config/company.php",
      "description": "Bedrijfsgegevens moeten compleet zijn",
      "must_contain": [
        "name",
        "kvk",
        "btw",
        "address",
        "email"
      ]
    }
  ]
}
```

## PHP Artisan Command (Laravel projecten)

Beschikbaar in HavunCore als `app/Console/Commands/IntegrityCheckCommand.php`.

Ondersteunt:
- `must_contain` — plain text checks
- `must_contain_selector` — CSS selector checks (#id, .class, tag.class, [attr]) via regex
- `must_contain_route` — Laravel route-naam checks
- `--project=` optie om externe projecten te checken
- `--json` optie voor machine-readable output

## Gebruik

```bash
# HavunCore zelf checken
php artisan integrity:check

# Ander project checken (als dat project een .integrity.json heeft)
php artisan integrity:check --project=/var/www/herdenkingsportaal/production

# JSON output (voor CI)
php artisan integrity:check --json
```

## Relatie met 5 Beschermingslagen

`.integrity.json` vervangt NIET de andere lagen, maar vult ze aan:

| Laag | Wat | Shadow File rol |
|------|-----|-----------------|
| 1. MD docs | Waarom iets bestaat | `.integrity.json` beschrijft WAT er moet zijn |
| 2. DO NOT REMOVE | In-code marker | Shadow file = externe marker (schonere code) |
| 3. Tests | Runtime check | Integrity check = statische check (sneller) |
| 4. CLAUDE.md | Regels | CLAUDE.md verwijst naar `.integrity.json` |
| 5. Memory | Context | Niet van toepassing |

---

*Laatst bijgewerkt: 15 april 2026 — v2.0 schema (selector + route checks)*
