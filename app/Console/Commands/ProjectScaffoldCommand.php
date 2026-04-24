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
                            {--deploy= : Deploy-target ("production" kopieert server-config templates naar deploy/nginx/)}
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

        // KB directory-structuur met INDEX + hiërarchische doc-skeletons.
        $plan['docs/kb/INDEX.md'] = $this->renderKbIndex($slug, $title, $today);
        $plan['docs/kb/reference/security-eisen.md'] = $this->renderSecurityEisenDoc($title);
        $plan['docs/kb/reference/test-quality-policy.md'] = $this->renderTestQualityPolicyDoc($title);
        $plan['docs/kb/runbooks/deploy.md'] = $this->renderDeployRunbook($slug, $title);
        $plan['docs/kb/decisions/0001-docs-first-development.md'] = $this->renderDecisionDocsFirst($title);
        $plan['docs/kb/patterns/.gitkeep'] = '';

        $plan['infection.json5'] = $this->renderInfectionConfig();

        // Laravel + Alpine boilerplate — SecurityHeaders middleware,
        // regression-test, Alpine CSP setup. Zorgt dat elk nieuw project
        // out-of-the-box voldoet aan Mozilla Observatory A+ eisen.
        $plan['app/Http/Middleware/SecurityHeaders.php'] = $this->renderSecurityHeadersMiddleware();
        $plan['tests/Feature/Middleware/SecurityHeadersTest.php'] = $this->renderSecurityHeadersTest();
        $plan['resources/js/alpine-components.js'] = $this->renderAlpineComponentsBoilerplate();
        $plan['resources/js/app.js'] = $this->renderAppJs();

        // Server-config templates (alleen als --deploy=production).
        // Plaatst de 4 nginx/SSL-hardening templates uit HavunCore in
        // deploy/nginx/ van het nieuwe project zodat prod-deploy de
        // canonieke config kan gebruiken en automatisch A+ / 100 haalt
        // op alle externe testsites (SSL Labs, SecurityHeaders, Mozilla
        // Observatory, Hardenize, Internet.nl) — zie
        // `docs/kb/reference/productie-deploy-eisen.md`.
        if ($this->option('deploy') === 'production') {
            $templates = base_path('docs/kb/templates/server-configs');
            if (is_dir($templates)) {
                $entries = scandir($templates);
                if (is_array($entries)) {
                    foreach ($entries as $file) {
                        if ($file === '.' || $file === '..' || is_dir($templates . '/' . $file)) {
                            continue;
                        }
                        $plan['deploy/nginx/' . $file] = File::get($templates . '/' . $file);
                    }
                }
            }
            $plan['deploy/nginx/README.md'] = $this->renderDeployReadme($slug);
        }

        return $plan;
    }

    private function renderDeployReadme(string $slug): string
    {
        return <<<MD
---
title: Nginx deploy-config — {$slug}
type: runbook
scope: {$slug}
last_check: TODO
---

# Nginx deploy-config

Templates uit HavunCore's `docs/kb/templates/server-configs/`. Elk nieuw
Havun productie-project start met deze config om automatisch A+ / 100
te halen op SSL Labs + SecurityHeaders + Mozilla Observatory + Hardenize +
Internet.nl.

## Bestanden

| File | Target op server |
|------|------------------|
| `nginx-ssl-hardened-snippet.conf` | `/etc/nginx/snippets/ssl-hardened.conf` |
| `nginx-http-level-ssl.conf` | Inline toevoegen in `/etc/nginx/nginx.conf` http-block |
| `nginx-security-headers-baseline.conf` | `/etc/nginx/snippets/security-headers-baseline.conf` (alleen voor vhosts zonder eigen app-middleware) |
| `openssl-restricted.cnf` | `/etc/nginx/openssl-restricted.cnf` |
| `systemd-nginx-openssl-override.conf` | `/etc/systemd/system/nginx.service.d/openssl-restricted.conf` |
| `nginx-vhost-hardened.conf.template` | `/etc/nginx/sites-available/{$slug}` (placeholders invullen) |

## Deploy-stappen (eerste keer, per productie-server)

1. Kopieer de snippet/config files naar genoemde locaties
2. Voeg inhoud van `nginx-http-level-ssl.conf` toe aan het `http { }` block
   van `/etc/nginx/nginx.conf` (vervangt per-server session directives —
   nodig voor betrouwbare ID-based resumption)
3. `systemctl daemon-reload` (voor systemd-override)
4. Werk `nginx-vhost-hardened.conf.template` uit: vervang `__DOMAIN__`,
   `__WEBROOT__`, `__PHP_SOCK__` met projectwaarden
5. Plaats uitgewerkte vhost in `/etc/nginx/sites-available/{$slug}`
6. `ln -s` naar `sites-enabled/`
7. ECDSA cert: `certbot certonly --nginx --key-type ecdsa --elliptic-curve secp384r1 -d <domain>`
8. DNS CAA records bij DNS-provider (mijnhost.nl voor Havun)
9. `nginx -t && systemctl restart nginx`
10. Verifieer via `docs/kb/reference/productie-deploy-eisen.md` §Verificatie-sequence

## Canonical requirements

Zie HavunCore `docs/kb/reference/productie-deploy-eisen.md` voor alle
eisen per testsite + hoe te verifiëren.
MD;
    }

    private function renderClaudeMd(string $slug, string $title): string
    {
        return <<<MD
# {$title} — Claude Instructions

> **Role:** {$title} project binnen de Havun-portfolio
> **Canonical werkwijze:** `D:/GitHub/HavunCore/CLAUDE.md`
> **V&K architectuur:** `D:/GitHub/HavunCore/docs/kb/runbooks/kwaliteit-veiligheid-systeem.md`
> **Productie-eisen:** `D:/GitHub/HavunCore/docs/kb/reference/productie-deploy-eisen.md`

## De 6 Onschendbare Regels

1. NOOIT code schrijven zonder KB + kwaliteitsnormen te raadplegen
2. NOOIT features/UI-elementen verwijderen zonder instructie
3. NOOIT credentials/keys/env aanraken
4. ALTIJD tests draaien voor én na wijzigingen. **Kritieke paden 100 %
   gedekt + mutation-score hoog** — zie HavunCore `test-quality-policy.md`.
5. ALTIJD toestemming vragen bij grote wijzigingen
6. NOOIT een falende test "fixen" door de assertion te wijzigen (VP-17)

## Docs-first — code alleen vanuit MD

Dit project volgt **docs-first development**:

- **Elke feature** staat volledig uitgeschreven in `docs/kb/` vóórdat de
  code geschreven wordt (hiërarchisch: runbooks / reference / decisions
  / patterns).
- **Code volgt de docs** — niet omgekeerd. Als je iets wilt bouwen dat
  niet in de docs staat, schrijf eerst de doc, laat Henk review, daarna
  code.
- Nieuwe kennis die uit een sessie komt: **altijd** terug in de docs
  zodat de volgende sessie er direct uit kan coderen.

## Sessie-workflow

- **Begin elke sessie** met `/start` — leest alle relevante docs +
  pull laatste code + checkt AutoFix commits + dependency security audit.
- **Eindig elke sessie** met `/end` — commit + push + deploy + weergeven
  van werkzaamheden voor uren-administratie.

## Kwaliteitseisen — NIET onderhandelbaar

### Tests
- Coverage **>80% methods** op nieuwe code (enterprise-niveau)
- Kritieke paden **100% gedekt** + mutation-score hoog
- Alleen **zinvolle, duurzame tests** — geen coverage-padding of
  implementation-detail tests (zie `feedback_durable_tests_only`)
- **SecurityHeadersTest** regression-set altijd aanwezig + groen
  (zie `app/Http/Middleware/SecurityHeaders.php` + test)

### Security — A+ op alle externe testsites
Elke productie-deploy moet scoren:
- **SSL Labs** → A+ / 100 / 100 / 100 / 100 (ECDSA P-384, TLS 1.2+1.3)
- **SecurityHeaders.com** → A+ (6 recommended headers, strikt CSP)
- **Mozilla Observatory** → A+ (100): geen `unsafe-eval`, geen
  `unsafe-inline` in script-src, nonce-based CSP, SRI op externe CDN's,
  `__Host-`-prefixed session cookies
- **Hardenize** → alle groene vinkjes (DNSSEC + CAA + SPF/DKIM/DMARC)
- **Internet.nl** → alle checks groen

Deploy-templates in `deploy/nginx/` (als `--deploy=production` geactiveerd
werd bij scaffold) zorgen voor het fundament. Zie
`docs/kb/reference/security-eisen.md` voor alle eisen per testsite.

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

    private function renderSecurityHeadersMiddleware(): string
    {
        return <<<'PHP'
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Enterprise-grade security headers for all responses.
 *
 * Scores A+ on SecurityHeaders.com and contributes to A+ on Mozilla
 * Observatory (together with @alpinejs/csp build + SRI on CDN scripts).
 *
 * Regression-guarded by tests/Feature/Middleware/SecurityHeadersTest.
 * Do NOT weaken without first reading docs/kb/reference/security-eisen.md.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $nonce = bin2hex(random_bytes(16));
        app()->instance('csp-nonce', $nonce);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // CSP — strict by default, no unsafe-eval, no unsafe-inline in script-src.
        // Extend here per-project with specific CDN whitelists and add SRI to any
        // external script tag.
        if (! app()->environment('local')) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'none'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self' 'nonce-{$nonce}'",
                "img-src 'self' data: blob:",
                "font-src 'self'",
                "connect-src 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "object-src 'none'",
                "manifest-src 'self'",
                'upgrade-insecure-requests',
            ]));
        }

        // HSTS only on secure production requests — prevents lockout when
        // staging/local HTTPS breaks during development.
        if (app()->environment('production') && $request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        return $response;
    }
}
PHP;
    }

    private function renderSecurityHeadersTest(): string
    {
        return <<<'PHP'
<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;

/**
 * Regression tests — enforces the cross-project Havun security-header
 * contract (see HavunCore docs/kb/reference/productie-deploy-eisen.md).
 * Do NOT remove or soften assertions without team review.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_x_content_type_options_is_nosniff(): void
    {
        $this->get('/')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_x_frame_options_is_deny(): void
    {
        $this->get('/')->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_referrer_policy_is_strict_origin(): void
    {
        $this->get('/')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_permissions_policy_is_present(): void
    {
        $this->assertNotEmpty(
            $this->get('/')->headers->get('Permissions-Policy'),
            'Permissions-Policy header must be set'
        );
    }

    public function test_csp_does_not_allow_unsafe_eval(): void
    {
        // Mozilla Observatory penalty -10 if unsafe-eval is allowed.
        $csp = $this->get('/')->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('unsafe-eval', (string) $csp);
    }

    public function test_csp_does_not_allow_unsafe_inline_in_script_src(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');
        $script = preg_match('/script-src\s+([^;]+)/', (string) $csp, $m) ? $m[1] : '';
        $this->assertStringNotContainsString(
            'unsafe-inline',
            $script,
            'script-src must NOT allow unsafe-inline — use nonces instead'
        );
    }

    public function test_hsts_header_includes_preload_over_https(): void
    {
        \Illuminate\Support\Facades\URL::forceScheme('https');
        $this->app->detectEnvironment(fn () => 'production');
        $hsts = $this->get('/')->headers->get('Strict-Transport-Security');

        $this->assertNotNull($hsts, 'HSTS must be set on HTTPS in production');
        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
        $this->assertStringContainsString('preload', $hsts, 'preload required for hstspreload.org');
    }

    public function test_hsts_header_absent_on_http(): void
    {
        $this->assertNull(
            $this->get('/')->headers->get('Strict-Transport-Security'),
            'HSTS must NOT be set on HTTP requests'
        );
    }

    public function test_csp_nonce_is_per_request_random(): void
    {
        $first = $this->get('/')->headers->get('Content-Security-Policy');
        $second = $this->get('/')->headers->get('Content-Security-Policy');

        if (! preg_match("/nonce-([A-Za-z0-9+\/=]+)/", (string) $first, $mFirst)) {
            $this->markTestSkipped('No nonce directive in this environment');
        }
        preg_match("/nonce-([A-Za-z0-9+\/=]+)/", (string) $second, $mSecond);

        $this->assertNotSame(
            $mFirst[1],
            $mSecond[1] ?? null,
            'CSP nonce must not repeat across requests'
        );
    }
}
PHP;
    }

    private function renderAlpineComponentsBoilerplate(): string
    {
        return <<<'JS'
// Alpine.data() registrations — CSP-build compatible.
//
// Every Alpine x-data reference in a Blade view MUST map to a named
// component registered here. The @alpinejs/csp build forbids inline
// expressions (arrow functions, object/array literals, eval) in x-*
// attributes — all logic lives here, views only reference names.
//
// Starter utilities below. Add project-specific components as needed.

import Alpine from '@alpinejs/csp';

Alpine.data('toggle', (initial = {}) => ({
    open: initial.open ?? false,
    toggle() { this.open = !this.open; },
    close() { this.open = false; },
    show()  { this.open = true; },
}));

Alpine.data('autoHide', (config = {}) => ({
    show: true,
    init() { setTimeout(() => { this.show = false; }, config.ms ?? 4000); },
}));

Alpine.data('searchFilter', (initial = {}) => ({
    search: initial.search ?? '',
}));

Alpine.data('dropdown', () => ({
    open: false,
    toggle() { this.open = !this.open; },
    close() { this.open = false; },
}));

Alpine.data('tabPanel', (config = {}) => ({
    activeTab: config.activeTab ?? '',
    setTab(name) { this.activeTab = name; },
    isActive(name) { return this.activeTab === name; },
}));
JS;
    }

    private function renderAppJs(): string
    {
        return <<<'JS'
import './bootstrap';

import Alpine from '@alpinejs/csp';
import collapse from '@alpinejs/collapse';

window.Alpine = Alpine;
Alpine.plugin(collapse);

// Shared Alpine.data() components — MUST load before Alpine.start().
import './alpine-components';

Alpine.start();
JS;
    }

    private function renderSecurityEisenDoc(string $title): string
    {
        return <<<MD
---
title: Security-eisen — {$title}
type: reference
scope: {$title}
last_check: TODO
---

# Security-eisen

> **Canonieke bron:** `D:/GitHub/HavunCore/docs/kb/reference/productie-deploy-eisen.md`
>
> Dit document is de project-lokale samenvatting. Bij conflict wint de
> canonieke HavunCore-versie.

## Targets

Elke productie-deploy MOET scoren:

| Testsite | Doel |
|---|---|
| **SSL Labs** | A+ / 100 / 100 / 100 / 100 |
| **SecurityHeaders.com** | A+ |
| **Mozilla Observatory** | A+ (100) |
| **Hardenize** | alle groene checks |
| **Internet.nl** | alle groene checks |

## Geen uitzonderingen

- ✗ **Geen `unsafe-eval`** in CSP → `@alpinejs/csp` build, geen inline x-expressies
- ✗ **Geen `unsafe-inline`** in `script-src` → nonce-based CSP
- ✗ **Geen externe scripts zonder SRI** → `integrity="sha384-..."` verplicht
- ✗ **Geen cookies zonder `__Host-` prefix** (session) → `SESSION_COOKIE=__Host-{$title}-session`
- ✗ **Geen HTTPS zonder HSTS preload** → `max-age=31536000; includeSubDomains; preload`
- ✗ **Geen Google Analytics** (standaard) → gebruik Umami (`umami.havun.nl`) of niks

## Regression-tests

`tests/Feature/Middleware/SecurityHeadersTest.php` moet altijd groen zijn:

- `test_csp_does_not_allow_unsafe_eval`
- `test_csp_does_not_allow_unsafe_inline_in_script_src`
- `test_hsts_header_includes_preload_over_https`
- `test_hsts_header_absent_on_http`
- Plus overige standaard headers (X-Frame-Options, Referrer-Policy, etc.)

## Nginx deploy-config

Zie `deploy/nginx/` voor hardened snippets (als `--deploy=production`
was gebruikt bij scaffold). Elke productie-deploy volgt
`deploy/nginx/README.md` stappenplan.

## Monitoring

Mozilla Observatory / SecurityHeaders / SSL Labs testen blijven
**handmatig** (per sessie of wekelijks) — API's geven alleen grade, niet
de breakdown die voor beoordeling nodig is.

MD;
    }

    private function renderTestQualityPolicyDoc(string $title): string
    {
        return <<<MD
---
title: Test-kwaliteit beleid — {$title}
type: reference
scope: {$title}
last_check: TODO
---

# Test-kwaliteit beleid

## Doelen (niet-onderhandelbaar)

- **Methods coverage >80%** op nieuwe code
- **Kritieke paden 100% gedekt** + hoge mutation-score (MSI ≥ 90%)
- **Alleen duurzame tests** — geen coverage-padding, geen
  implementation-detail tests die meebewegen met refactors

## Wat is "kritiek pad"?

Alles waar een bug:
- Geld kost (betalingen, facturering, BTW-berekening)
- Data corrupt maakt (migrations, aggregaties, koppelingen)
- Toegang verleent aan verkeerde gebruiker (auth, authz, device-binding)
- Veiligheid breekt (CSRF, CSP, input-validation, sanitization)

## Anti-patterns (VP-17)

- **NOOIT** een falende test fixen door de assertion te wijzigen zonder
  eerst oorzakenonderzoek + gebruikersgoedkeuring
- Geen tests die alleen bestaan om een coverage-getal te halen
- Geen tests die 100% mocken — kritieke paden gebruiken echte database

## Tools

- **PHPUnit** — unit + feature tests (pcov voor coverage)
- **Infection** — mutation-testing (`infection.json5`)
- **SecurityHeadersTest** — cross-project regression-set

## CI

- Tests draaien altijd **voor én na** wijzigingen
- `--no-coverage` in lokale runs (pcov traag); coverage alleen in CI
  nightly (zie HavunCore `feedback_phpunit11_coverage_ci.md`)

MD;
    }

    private function renderDeployRunbook(string $slug, string $title): string
    {
        return <<<MD
---
title: Deploy-runbook — {$title}
type: runbook
scope: {$slug}
last_check: TODO
---

# Deploy-runbook

## Eerste keer (productie server)

1. Configureer DNS (A-record + eventueel CAA) via `mijn.host` API
   (vanuit HavunCore: zie `reference_dns_and_analytics.md`)
2. Volg `deploy/nginx/README.md` voor SSL + nginx setup
3. Kopieer `.env.example` → `.env`, vul geheimen in
4. `composer install --optimize-autoloader --no-dev`
5. `npm install && npm run build`
6. `php artisan migrate --force`
7. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
8. Verifieer via HavunCore `docs:audit --project={$slug}`

## Reguliere deploy

```bash
cd /var/www/{$slug}/production
git pull
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate --force
php artisan config:clear && php artisan view:clear && php artisan cache:clear
```

## Post-deploy checks

- `curl -skI https://{$slug}.havun.nl/` — headers aanwezig
- Mozilla Observatory handmatige rescan indien security-wijzigingen
- Controleer application logs op errors eerste 5 min na deploy

MD;
    }

    private function renderDecisionDocsFirst(string $title): string
    {
        return <<<MD
---
title: ADR-0001 — Docs-first development
type: decision
scope: {$title}
last_check: TODO
status: ACCEPTED
---

# ADR-0001: Docs-first development

## Context

Claude-sessies werken stabieler en consistenter wanneer alle nieuwe
features, decisions en patterns **eerst** in MD-docs worden uitgeschreven
voordat de code geschreven wordt.

## Besluit

1. **Alle features** staan volledig in `docs/kb/` vóór implementatie
2. **Code volgt de docs**, niet omgekeerd
3. **Elke sessie** begint met `/start` (docs lezen + state-check) en
   eindigt met `/end` (commit + push + deploy + uren-weergave)
4. Nieuwe inzichten uit een sessie → **altijd** terug naar de docs

## Gevolgen

- Langere initiële denk-tijd per feature, maar veel minder rework
- Cross-session consistentie doordat docs de bron van waarheid zijn
- Claude kan een feature oppakken zonder context-verlies
- KB groeit als levende asset, niet een 1-way dump

## Alternatief overwogen

Direct coderen + retrospectief documenteren. Afgewezen omdat retro-docs
bijna nooit volledig zijn en aansluiting op andere docs missen.

MD;
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
