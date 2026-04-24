<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProjectScaffoldCommandTest extends TestCase
{
    private string $tmpProject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpProject = sys_get_temp_dir() . '/scaffold-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tmpProject)) {
            File::deleteDirectory($this->tmpProject);
        }
        parent::tearDown();
    }

    /**
     * Run the scaffold command against the temp path with --force.
     * Centralised so individual tests stay focused on assertions.
     *
     * @param  array<string,mixed>  $extraArgs
     */
    private function scaffold(string $slug, array $extraArgs = []): int
    {
        return $this->artisan('project:scaffold', array_merge([
            'slug' => $slug,
            '--path' => $this->tmpProject,
            '--force' => true,
        ], $extraArgs))->run();
    }

    public function test_scaffolds_required_artefacts_for_valid_slug(): void
    {
        $this->assertSame(0, $this->scaffold('testproject'));

        // Kern-artefacten moeten bestaan:
        $this->assertFileExists($this->tmpProject . '/CLAUDE.md');
        $this->assertFileExists($this->tmpProject . '/CONTRACTS.md');
        $this->assertFileExists($this->tmpProject . '/.claude/context.md');
        $this->assertFileExists($this->tmpProject . '/.claude/rules.md');
        $this->assertFileExists($this->tmpProject . '/docs/kb/INDEX.md');
        $this->assertFileExists($this->tmpProject . '/infection.json5');

        // KB-structuur met 4 subdirs:
        foreach (['runbooks', 'reference', 'decisions', 'patterns'] as $sub) {
            $this->assertDirectoryExists($this->tmpProject . '/docs/kb/' . $sub);
        }
    }

    public function test_scaffolds_laravel_security_boilerplate(): void
    {
        $this->scaffold('secproj');

        // SecurityHeaders middleware + regression-test altijd aanwezig
        $this->assertFileExists($this->tmpProject . '/app/Http/Middleware/SecurityHeaders.php');
        $this->assertFileExists($this->tmpProject . '/tests/Feature/Middleware/SecurityHeadersTest.php');

        // Middleware heeft de verplichte CSP clauses
        $mw = File::get($this->tmpProject . '/app/Http/Middleware/SecurityHeaders.php');
        $this->assertStringContainsString('X-Content-Type-Options', $mw);
        $this->assertStringContainsString("'nonce-{", $mw, 'Nonce-based CSP required');
        $this->assertStringNotContainsString("'unsafe-eval'", $mw, 'No unsafe-eval in scaffold default CSP');
        $this->assertStringContainsString('includeSubDomains; preload', $mw);

        // Test heeft de kern-asserties
        $test = File::get($this->tmpProject . '/tests/Feature/Middleware/SecurityHeadersTest.php');
        $this->assertStringContainsString('test_csp_does_not_allow_unsafe_eval', $test);
        $this->assertStringContainsString('test_hsts_header_includes_preload_over_https', $test);
    }

    public function test_scaffolds_alpine_csp_setup(): void
    {
        $this->scaffold('alpineproj');

        $this->assertFileExists($this->tmpProject . '/resources/js/app.js');
        $this->assertFileExists($this->tmpProject . '/resources/js/alpine-components.js');

        $app = File::get($this->tmpProject . '/resources/js/app.js');
        $this->assertStringContainsString("import Alpine from '@alpinejs/csp'", $app);
        $this->assertStringContainsString("import './alpine-components'", $app);

        $components = File::get($this->tmpProject . '/resources/js/alpine-components.js');
        $this->assertStringContainsString("Alpine.data('toggle'", $components);
        $this->assertStringContainsString("Alpine.data('dropdown'", $components);
    }

    public function test_scaffolds_hierarchical_kb_docs(): void
    {
        $this->scaffold('kbproj');

        // Project-lokale referentie-docs als entry-points
        $this->assertFileExists($this->tmpProject . '/docs/kb/reference/security-eisen.md');
        $this->assertFileExists($this->tmpProject . '/docs/kb/reference/test-quality-policy.md');
        $this->assertFileExists($this->tmpProject . '/docs/kb/runbooks/deploy.md');
        $this->assertFileExists($this->tmpProject . '/docs/kb/decisions/0001-docs-first-development.md');

        // Security-eisen doc bevat de 5 testsite-targets
        $sec = File::get($this->tmpProject . '/docs/kb/reference/security-eisen.md');
        $this->assertStringContainsString('SSL Labs', $sec);
        $this->assertStringContainsString('SecurityHeaders.com', $sec);
        $this->assertStringContainsString('Mozilla Observatory', $sec);
        $this->assertStringContainsString('Hardenize', $sec);
        $this->assertStringContainsString('Internet.nl', $sec);
    }

    public function test_scaffolds_env_example_with_secure_defaults(): void
    {
        $this->scaffold('envproj');

        $this->assertFileExists($this->tmpProject . '/.env.example');
        $env = File::get($this->tmpProject . '/.env.example');

        $this->assertStringContainsString('SESSION_COOKIE=__Host-envproj-session', $env);
        $this->assertStringContainsString('SESSION_DOMAIN=', $env);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $env);
        $this->assertStringContainsString('APP_TIMEZONE=Europe/Amsterdam', $env);
    }

    public function test_scaffolds_gitignore_with_env_protection(): void
    {
        $this->scaffold('gitproj');

        $this->assertFileExists($this->tmpProject . '/.gitignore');
        $gi = File::get($this->tmpProject . '/.gitignore');

        // .env protection (incl. backups from rotation)
        $this->assertStringContainsString('.env', $gi);
        $this->assertStringContainsString('.env.*', $gi);
        $this->assertStringContainsString('!.env.example', $gi, 'Example must be tracked');
        // Vendor + node_modules + build artifacts
        $this->assertStringContainsString('/vendor', $gi);
        $this->assertStringContainsString('/node_modules', $gi);
        $this->assertStringContainsString('/public/build', $gi);
    }

    public function test_scaffolds_ci_workflow(): void
    {
        $this->scaffold('ciproj');

        $this->assertFileExists($this->tmpProject . '/.github/workflows/ci.yml');
        $ci = File::get($this->tmpProject . '/.github/workflows/ci.yml');

        // CI must run composer audit, npm audit, test suite and security regression-set.
        $this->assertStringContainsString('composer audit', $ci);
        $this->assertStringContainsString('npm audit', $ci);
        $this->assertStringContainsString('php artisan test', $ci);
        $this->assertStringContainsString('SecurityHeadersTest', $ci);
    }

    public function test_kb_index_links_to_skeleton_docs(): void
    {
        $this->scaffold('indexproj');

        $index = File::get($this->tmpProject . '/docs/kb/INDEX.md');

        $this->assertStringContainsString('security-eisen.md', $index);
        $this->assertStringContainsString('test-quality-policy.md', $index);
        $this->assertStringContainsString('0001-docs-first-development.md', $index);
        $this->assertStringContainsString('runbooks/deploy.md', $index);
    }

    public function test_claude_md_documents_docs_first_principles(): void
    {
        $this->scaffold('docsfirstproj');

        $claude = File::get($this->tmpProject . '/CLAUDE.md');
        $this->assertStringContainsString('Docs-first', $claude);
        $this->assertStringContainsString('/start', $claude);
        $this->assertStringContainsString('/end', $claude);
        $this->assertStringContainsString('A+', $claude, 'Security target must be explicit');
        $this->assertStringContainsString('SSL Labs', $claude);
        $this->assertStringContainsString('Coverage', $claude);
    }

    public function test_copies_claude_commands_from_havuncore(): void
    {
        $this->scaffold('testproj2');

        // Kern Claude commands die in HavunCore bestaan en gekopieerd zijn:
        $this->assertFileExists($this->tmpProject . '/.claude/commands/start.md');
        $this->assertFileExists($this->tmpProject . '/.claude/commands/end.md');
        $this->assertFileExists($this->tmpProject . '/.claude/commands/kb.md');
        $this->assertFileExists($this->tmpProject . '/.claude/commands/kb-audit.md');
        $this->assertFileExists($this->tmpProject . '/.claude/commands/mpc.md');
    }

    public function test_rejects_invalid_slug(): void
    {
        // --path omitted so the scaffold bails before touching the tmp dir.
        $exit = $this->artisan('project:scaffold', [
            'slug' => 'UPPERCASE', // invalid: uppercase
            '--force' => true,
        ])->run();

        $this->assertSame(1, $exit);
    }

    public function test_rejects_non_laravel_stack_in_mvp(): void
    {
        $this->assertSame(1, $this->scaffold('nodetestproj', ['--stack' => 'node']));
    }

    public function test_deploy_production_generates_nginx_server_configs(): void
    {
        $this->scaffold('deployproj', ['--deploy' => 'production']);

        foreach ([
            'nginx-ssl-hardened-snippet.conf',
            'nginx-http-level-ssl.conf',
            'nginx-security-headers-baseline.conf',
            'openssl-restricted.cnf',
            'systemd-nginx-openssl-override.conf',
            'nginx-vhost-hardened.conf.template',
            'README.md',
        ] as $f) {
            $this->assertFileExists($this->tmpProject . '/deploy/nginx/' . $f);
        }

        // README moet naar canonical requirements wijzen
        $readme = File::get($this->tmpProject . '/deploy/nginx/README.md');
        $this->assertStringContainsString('productie-deploy-eisen.md', $readme);
    }

    public function test_default_deploy_does_not_generate_server_configs(): void
    {
        $this->scaffold('nodeployproj');

        $this->assertDirectoryDoesNotExist($this->tmpProject . '/deploy');
    }

    public function test_skips_existing_files_idempotent_run(): void
    {
        $this->assertSame(0, $this->scaffold('idempotent'));

        // Wijzig een bestand om te verifieren dat run #2 het niet overschrijft.
        File::put($this->tmpProject . '/CLAUDE.md', '# Custom content — must not be overwritten');

        $this->assertSame(0, $this->scaffold('idempotent'));
        $this->assertSame(
            '# Custom content — must not be overwritten',
            File::get($this->tmpProject . '/CLAUDE.md')
        );
    }
}
