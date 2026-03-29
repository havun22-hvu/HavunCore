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

## `.integrity.json` Schema

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "version": "1.0",
  "project": "herdenkingsportaal",
  "updated": "2026-03-29",
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

## Check Script (Node.js — werkt in elk project)

Plaats als `scripts/check-integrity.js`:

```javascript
#!/usr/bin/env node
const fs = require('fs');
const path = require('path');

const integrityFile = path.join(process.cwd(), '.integrity.json');

if (!fs.existsSync(integrityFile)) {
  console.log('No .integrity.json found — skipping integrity check');
  process.exit(0);
}

const config = JSON.parse(fs.readFileSync(integrityFile, 'utf8'));
let failures = 0;
let passed = 0;

for (const check of config.checks) {
  const filePath = path.join(process.cwd(), check.file);

  if (!fs.existsSync(filePath)) {
    console.error(`FAIL: ${check.file} — FILE NOT FOUND`);
    console.error(`  → ${check.description}`);
    failures++;
    continue;
  }

  const content = fs.readFileSync(filePath, 'utf8');
  const missing = check.must_contain.filter(term => !content.includes(term));

  if (missing.length > 0) {
    console.error(`FAIL: ${check.file}`);
    console.error(`  → ${check.description}`);
    console.error(`  → Missing: ${missing.join(', ')}`);
    failures++;
  } else {
    console.log(`OK: ${check.file} (${check.must_contain.length} checks)`);
    passed++;
  }
}

console.log(`\n${passed} passed, ${failures} failed`);
process.exit(failures > 0 ? 1 : 0);
```

## PHP Artisan versie (Laravel projecten)

Plaats als `app/Console/Commands/IntegrityCheckCommand.php`:

```php
class IntegrityCheckCommand extends Command
{
    protected $signature = 'integrity:check';
    protected $description = 'Validate .integrity.json against codebase';

    public function handle(): int
    {
        $file = base_path('.integrity.json');
        if (!file_exists($file)) {
            $this->info('No .integrity.json found — skipping');
            return 0;
        }

        $config = json_decode(file_get_contents($file), true);
        $failures = 0;

        foreach ($config['checks'] as $check) {
            $path = base_path($check['file']);
            if (!file_exists($path)) {
                $this->error("FAIL: {$check['file']} — FILE NOT FOUND");
                $failures++;
                continue;
            }

            $content = file_get_contents($path);
            $missing = array_filter($check['must_contain'], fn($term) => !str_contains($content, $term));

            if (!empty($missing)) {
                $this->error("FAIL: {$check['file']} → Missing: " . implode(', ', $missing));
                $failures++;
            } else {
                $this->info("OK: {$check['file']}");
            }
        }

        return $failures > 0 ? 1 : 0;
    }
}
```

## Gebruik

```bash
# Node.js (elk project)
node scripts/check-integrity.js

# Laravel
php artisan integrity:check
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

*Laatst bijgewerkt: 29 maart 2026*
