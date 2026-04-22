<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Bootstrap een nieuw Havun-project met werkwijze-artefacten.
 *
 * Plant:
 * - CLAUDE.md (6 Onschendbare Regels)
 * - .claude/commands/*.md (alle 11 Claude commands uit HavunCore)
 * - .claude/context.md (template — credentials-velden leeg, handmatig invullen)
 * - .claude/rules.md (verwijst naar HavunCore canonical)
 * - CONTRACTS.md (template)
 * - docs/kb/ directory-structuur + INDEX.md
 * - infection.json5 (als --stack=laravel)
 *
 * Registreert in HavunCore:
 * - config/quality-safety.php entry voor qv:scan / docs:audit inclusion
 *
 * Niet-idempotent-veilig: skipt elke file die al bestaat, vraagt
 * confirmatie bij partial-overwrite.
 */
class ProjectScaffoldCommand extends Command
{
    protected $signature = 'project:scaffold
                            {slug : Project slug (snake-case/kebab-case, bv. havunmusic)}
                            {--path= : Absolute project-pad (default: D:/GitHub/<Slug>)}
                            {--stack=laravel : Project-stack (laravel|node|static). Alleen laravel in MVP.}
                            {--url= : Production URL voor V&K registratie (optioneel)}
                            {--force : Sla confirmatie over (CI-modus)}';

    protected $description = 'Bootstrap een nieuw project met Havun werkwijze, Claude commands, V&K registratie.';

    public function handle(): int
    {
        $slug = (string) $this->argument('slug');
        if (! preg_match('/^[a-z][a-z0-9_-]{1,50}$/', $slug)) {
            $this->error("Ongeldige slug: '{$slug}'. Gebruik kleine letters, cijfers, _ of -.");

            return self::FAILURE;
        }

        $stack = (string) $this->option('stack');
        if ($stack !== 'laravel') {
            $this->warn("Stack '{$stack}' niet in MVP — alleen laravel. Abort.");

            return self::FAILURE;
        }

        $projectPath = (string) ($this->option('path') ?: $this->defaultProjectPath($slug));
        $projectPath = rtrim(str_replace('\\', '/', $projectPath), '/');

        if (! File::exists($projectPath)) {
            if (! $this->option('force') && ! $this->confirm("Pad '{$projectPath}' bestaat niet. Aanmaken?", true)) {
                $this->warn('Abort.');

                return self::FAILURE;
            }
            File::ensureDirectoryExists($projectPath);
        }

        $plan = $this->buildFilePlan($slug, $projectPath);
        $this->renderPlan($plan);

        if (! $this->option('force') && ! $this->confirm('Doorgaan met schrijven?', true)) {
            $this->warn('Abort — geen files gewijzigd.');

            return self::FAILURE;
        }

        $this->writeFiles($plan, $projectPath);
        $this->printQualitySafetyHint($slug, $projectPath, (string) $this->option('url'));
        $this->summary($slug, $projectPath);

        return self::SUCCESS;
    }

    /**
     * @return array<string,string>  relatief-pad → content
     */
    private function buildFilePlan(string $slug, string $projectPath): array
    {
        $today = date('Y-m-d');
        // ucwords ipv ucfirst — voor 'mijn-project' → 'Mijn Project' (title-case)
        // ipv 'Mijn project'.
        $title = ucwords(str_replace(['-', '_'], ' ', $slug));

        $plan = [];

        $plan['CLAUDE.md'] = $this->renderClaudeMd($slug, $title);
        $plan['CONTRACTS.md'] = $this->renderContractsMd($title);
        $plan['.claude/context.md'] = $this->renderContextMd($slug, $title);
        $plan['.claude/rules.md'] = $this->renderRulesMd($slug);

        // Alle Claude commands uit HavunCore naar het nieuwe project.
        $havunCoreCommands = base_path('.claude/commands');
        if (is_dir($havunCoreCommands)) {
            $entries = scandir($havunCoreCommands);
            if (is_array($entries)) {
                foreach ($entries as $file) {
                    if (str_ends_with($file, '.md')) {
                        $plan['.claude/commands/' . $file] = File::get($havunCoreCommands . '/' . $file);
                    }
                }
            } else {
                $this->warn('Kon .claude/commands/ niet lezen — Claude commands worden NIET gekopieerd.');
            }
        }

        // KB directory-structuur met INDEX + placeholders.
        $plan['docs/kb/INDEX.md'] = $this->renderKbIndex($slug, $title, $today);
        foreach (['runbooks', 'reference', 'decisions', 'patterns'] as $sub) {
            $plan["docs/kb/{$sub}/.gitkeep"] = '';
        }

        $plan['infection.json5'] = $this->renderInfectionConfig();

        return $plan;
    }

    private function renderClaudeMd(string $slug, string $title): string
    {
        return <<<MD
# {$title} — Claude Instructions

> **Role:** {$title} project binnen de Havun-portfolio
> **Canonical werkwijze:** `D:/GitHub/HavunCore/CLAUDE.md`
> **V&K architectuur:** `D:/GitHub/HavunCore/docs/kb/runbooks/kwaliteit-veiligheid-systeem.md`

## De 6 Onschendbare Regels

1. NOOIT code schrijven zonder KB + kwaliteitsnormen te raadplegen
2. NOOIT features/UI-elementen verwijderen zonder instructie
3. NOOIT credentials/keys/env aanraken
4. ALTIJD tests draaien voor én na wijzigingen. **Kritieke paden 100 %
   gedekt + mutation-score hoog** — zie HavunCore `test-quality-policy.md`.
5. ALTIJD toestemming vragen bij grote wijzigingen
6. NOOIT een falende test "fixen" door de assertion te wijzigen (VP-17)

## Werkwijze per taak

1. **LEES** — `docs:search "[onderwerp]"` voordat je code leest of schrijft
2. **DENK** — vraag bij twijfel en wacht op antwoord
3. **DOE** — pas dan uitvoeren; kwaliteit boven snelheid
4. **DOCUMENTEER** — nieuwe kennis in `docs/kb/` of HavunCore centrale KB

## Verboden zonder overleg

SSH keys, credentials, `.env`, composer/npm installs, prod migrations,
systemd/cron wijzigingen.

## V&K registratie

Dit project is geregistreerd in HavunCore's `config/quality-safety.php`.
Maandelijkse audit: zie `docs/kb/reference/kb-audit-latest.md` (auto-gegen).

MD;
    }

    private function renderContractsMd(string $title): string
    {
        return <<<MD
# {$title} — Contracts

> Onveranderlijke regels van dit project. NIEMAND mag deze overtreden —
> ook AI niet. Bij elke wijziging eerst raadplegen. Wijzigen mag alleen
> na schriftelijk akkoord van eigenaar.

## Wanneer toepassen

Bij elke nieuwe feature, refactor of AI-suggestie eerst dit document
raadplegen. Pas dan aan de code.

## Contracten

> Vul hier project-specifieke onveranderlijke regels in. Voorbeelden:
> - "API endpoint X mag nooit een 500 teruggeven bij ontbrekende input — altijd 422"
> - "Audit-logs worden nooit verwijderd, alleen gemarkeerd"

## Voorbeeld-template

Zie `D:/GitHub/HavunCore/docs/kb/patterns/contracts-md-template.md`.

MD;
    }

    private function renderContextMd(string $slug, string $title): string
    {
        return <<<MD
---
title: {$title} — project context
type: claude
scope: {$slug}
last_check: TODO
---

# {$title} Context

> **VUL DIT HANDMATIG IN.** Credentials/server-info worden bewust niet
> auto-gekopieerd.

## Server

- Production host: TODO
- Production path: TODO
- Deploy-commando: TODO

## GitHub

- Repository: TODO (bv. https://github.com/havun22-hvu/{$title})
- Main branch: TODO (master of main)
- Deploy-key: TODO

## Belangrijke paden

| Omgeving | Pad |
|----------|-----|
| Productie | TODO |
| Staging | TODO |
| Lokaal | D:/GitHub/{$title} |

## Env vars (production)

> Zie HavunCore's `.claude/context.md` voor canonical credentials-index.
> HIER alleen de SPECIFIEKE env-var-namen voor dit project (geen waardes).

MD;
    }

    private function renderRulesMd(string $slug): string
    {
        return <<<MD
---
title: Rules — {$slug}
type: claude
scope: {$slug}
last_check: TODO
---

# Rules

De 6 Onschendbare Regels staan in `CLAUDE.md` (dit project) en zijn
canonical in `D:/GitHub/HavunCore/CLAUDE.md`. Eventuele project-
specifieke afwijkingen (toevoegingen, geen overschrijvingen) hieronder.

## Project-specifieke regels

> Vul in of laat leeg. Bij toevoegingen: reden expliciet maken.

MD;
    }

    private function renderKbIndex(string $slug, string $title, string $today): string
    {
        return <<<MD
---
title: KB INDEX — {$slug}
type: reference
scope: {$slug}
last_check: {$today}
---

# KB — {$title}

> Project-lokale kennisbank. Canonical cross-project KB: `D:/GitHub/HavunCore/docs/kb/`.

## Structuur

- **runbooks/** — hoe-te-doen-procedures (deploy, troubleshoot)
- **reference/** — feit-lookups (API's, configs, paden)
- **decisions/** — ADR's (waarom-keuzes zijn gemaakt)
- **patterns/** — herbruikbare patterns/solutions

## Automatische audits

- Wekelijks: `php artisan docs:audit --project={$slug}` (vanuit HavunCore)
- Rapport: `docs/kb/reference/kb-audit-latest.md`

## Zoeken

```bash
cd D:/GitHub/HavunCore && php artisan docs:search "term" --project={$slug}
```

MD;
    }

    private function renderInfectionConfig(): string
    {
        return <<<'JSON'
{
    "$schema": "vendor/infection/infection/resources/schema.json",
    "source": {
        "directories": ["app/Services"],
        "excludes": []
    },
    "timeout": 30,
    "logs": {
        "text": "storage/logs/infection.log",
        "summary": "storage/logs/infection-summary.log",
        "perMutator": "storage/logs/infection-per-mutator.md"
    },
    "tmpDir": "storage/framework/cache/infection",
    "phpUnit": {
        "configDir": ".",
        "customPath": "vendor/bin/phpunit"
    },
    "minMsi": 50,
    "minCoveredMsi": 50,
    "mutators": {
        "@default": true,
        "global-ignoreSourceCodeByRegex": [
            "Log::.*",
            "report\\(.*\\)"
        ]
    },
    "testFramework": "phpunit",
    "testFrameworkOptions": "--testsuite=Feature,Unit --no-coverage"
}
JSON;
    }

    /**
     * @param  array<string,string>  $plan
     */
    private function renderPlan(array $plan): void
    {
        $this->info('Plan — files die geschreven worden:');
        foreach (array_keys($plan) as $rel) {
            $this->line("  + {$rel}");
        }
        $this->newLine();
    }

    /**
     * @param  array<string,string>  $plan
     */
    private function writeFiles(array $plan, string $projectPath): void
    {
        $written = 0;
        $skipped = 0;
        foreach ($plan as $rel => $content) {
            $full = $projectPath . '/' . $rel;
            if (File::exists($full)) {
                $this->warn("  skip (bestaat al): {$rel}");
                $skipped++;
                continue;
            }
            File::ensureDirectoryExists(dirname($full));
            File::put($full, $content);
            $written++;
        }
        $this->info("Geschreven: {$written} | Geskipt (bestonden): {$skipped}");
    }

    /**
     * Print een kopieer-en-plak hint voor handmatige V&K-registratie in
     * config/quality-safety.php. Auto-edit is bewust NIET geïmplementeerd:
     * PHP-array edits zonder AST-parser zijn fragiel bij formatting-verschillen.
     */
    private function printQualitySafetyHint(string $slug, string $projectPath, string $url): void
    {
        $cfgPath = base_path('config/quality-safety.php');
        if (! File::exists($cfgPath)) {
            $this->warn('config/quality-safety.php bestaat niet — sla V&K-hint over.');

            return;
        }

        if (str_contains(File::get($cfgPath), "'{$slug}' =>")) {
            $this->info("V&K: project '{$slug}' al geregistreerd, skip.");

            return;
        }

        $envPrefix = strtoupper(str_replace('-', '_', $slug));

        $this->warn('');
        $this->warn('V&K: voeg handmatig toe aan config/quality-safety.php:');
        $this->line("    '{$slug}' => [");
        $this->line("        'enabled' => env('QV_{$envPrefix}_ENABLED', true),");
        $this->line("        'path' => env('{$envPrefix}_LOCAL_PATH', '{$projectPath}'),");
        if ($url !== '') {
            $this->line("        'url' => '{$url}',");
        }
        $this->line("    ],");
        $this->warn('');
    }

    private function summary(string $slug, string $projectPath): void
    {
        $this->newLine();
        $this->info("[OK] Project '{$slug}' scaffolded in: {$projectPath}");
        $this->newLine();
        $this->line('Volgende stappen:');
        $this->line("  1. Vul .claude/context.md in (credentials/server-info)");
        $this->line("  2. Registreer in HavunCore config/quality-safety.php (zie hint hierboven)");
        $this->line("  3. Run vanuit HavunCore: php artisan docs:audit --project={$slug}");
        $this->line("  4. Run vanuit HavunCore: php artisan qv:scan --project={$slug}");
        $this->newLine();
    }

    private function defaultProjectPath(string $slug): string
    {
        $dirname = ucfirst(str_replace(['-', '_'], '', $slug));

        return 'D:/GitHub/' . $dirname;
    }
}
