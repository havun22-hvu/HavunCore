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
     * Writes a filled-in docs/intake.md first: the intake gates the command
     * by design, and every test but the intake tests themselves is about
     * what comes after that gate.
     *
     * @param  array<string,mixed>  $extraArgs
     */
    private function scaffold(string $slug, array $extraArgs = []): int
    {
        // The intake must conclude the same type the scaffold is asked for —
        // that agreement is the point, so derive it from the args.
        $this->writeIntake($extraArgs['--type'] ?? 'server-webapp');

        return $this->scaffoldWithoutIntake($slug, $extraArgs);
    }

    /**
     * @param  array<string,mixed>  $extraArgs
     */
    private function scaffoldWithoutIntake(string $slug, array $extraArgs = []): int
    {
        return $this->artisan('project:scaffold', array_merge([
            'slug' => $slug,
            '--path' => $this->tmpProject,
            '--type' => 'server-webapp',
            '--force' => true,
        ], $extraArgs))->run();
    }

    /**
     * A completed intake: five answers carried through to a conclusion line.
     */
    private function writeIntake(string $type = 'server-webapp'): void
    {
        File::ensureDirectoryExists($this->tmpProject . '/docs');
        File::put($this->tmpProject . '/docs/intake.md', <<<MD
        # Intake
        1. Draait op: server
        2. Gebruikers tegelijk: meerdere
        3. Data: MySQL op de productieserver
        4. Zwaarste operatie: een overzichtspagina renderen
        5. Vertraging voelbaar bij: paginalaadtijd, doel < 300 ms

        **Type:** {$type}
        MD);
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

    public function test_scaffolds_post_install_runbook_with_middleware_registration(): void
    {
        $this->scaffold('postinstallproj');

        $this->assertFileExists($this->tmpProject . '/docs/kb/runbooks/post-install.md');
        $this->assertFileExists($this->tmpProject . '/docs/kb/reference/kb-audit-latest.md');

        // Runbook moet BEIDE Laravel middleware-registratie stijlen noemen,
        // anders is de scaffold onbruikbaar afhankelijk van Laravel versie.
        $runbook = File::get($this->tmpProject . '/docs/kb/runbooks/post-install.md');
        $this->assertStringContainsString('bootstrap/app.php', $runbook, 'Laravel 11+ pad');
        $this->assertStringContainsString('Kernel.php', $runbook, 'Laravel 10 pad');
        $this->assertStringContainsString('SecurityHeaders', $runbook);
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
        // Tests-regel: zinvolheid primair, coverage-% secundair (Henk 2026-04-20)
        $this->assertStringContainsString('Zinvolheid', $claude);
        $this->assertStringContainsString('Kritieke paden', $claude);
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
            '--type' => 'server-webapp',
            '--force' => true,
        ])->run();

        $this->assertSame(1, $exit);
    }

    public function test_rejects_missing_type_so_the_stack_is_never_inherited(): void
    {
        $this->writeIntake();

        $exit = $this->artisan('project:scaffold', [
            'slug' => 'notypeproj',
            '--path' => $this->tmpProject,
            '--force' => true,
        ])->run();

        $this->assertSame(1, $exit, '--type must be mandatory: an implicit stack is the bug this guards');
        $this->assertFileDoesNotExist($this->tmpProject . '/CLAUDE.md');
    }

    public function test_rejects_unknown_type(): void
    {
        $this->assertSame(1, $this->scaffold('badtypeproj', ['--type' => 'laravel']));
    }

    public function test_refuses_to_scaffold_without_an_intake_and_writes_the_template(): void
    {
        $exit = $this->scaffoldWithoutIntake('nointakeproj');

        $this->assertSame(1, $exit);
        $this->assertFileExists(
            $this->tmpProject . '/docs/intake.md',
            'The template must be written so the five questions can be answered'
        );
        $this->assertFileDoesNotExist(
            $this->tmpProject . '/CLAUDE.md',
            'Nothing may be scaffolded before the intake is answered'
        );

        $intake = File::get($this->tmpProject . '/docs/intake.md');
        $this->assertStringContainsString('Waar draait het?', $intake);
        $this->assertStringContainsString('Hoeveel gebruikers tegelijk?', $intake);
        $this->assertStringContainsString('Waar staat de data', $intake);
        $this->assertStringContainsString('zwaarste operatie', $intake);
        $this->assertStringContainsString('Waar merkt de gebruiker vertraging?', $intake);
    }

    public function test_refuses_the_untouched_template_on_a_second_run(): void
    {
        // The template itself is the unanswered state — scaffolding on top of
        // it would make the intake a ritual instead of a decision.
        $this->scaffoldWithoutIntake('todoproj');

        $this->assertSame(
            1,
            $this->scaffoldWithoutIntake('todoproj'),
            'A template left untouched must keep failing'
        );
        $this->assertFileDoesNotExist($this->tmpProject . '/CLAUDE.md');
    }

    public function test_accepts_an_intake_whose_frontmatter_still_says_todo(): void
    {
        // The scaffold itself writes `last_check: TODO` into nearly every doc
        // it generates. Judging the intake on the word TODO would reject a
        // properly answered one — the conclusion line is the measure.
        File::ensureDirectoryExists($this->tmpProject . '/docs');
        File::put($this->tmpProject . '/docs/intake.md', <<<'MD'
        ---
        title: Intake
        last_check: TODO
        ---
        1. Draait op: server — meerdere gebruikers, data in MySQL

        **Type:** server-webapp
        MD);

        $this->assertSame(0, $this->scaffoldWithoutIntake('frontmatterproj'));
        $this->assertFileExists($this->tmpProject . '/CLAUDE.md');
    }

    public function test_refuses_when_the_intake_concludes_a_different_type(): void
    {
        // Two uncoupled statements of the same decision is how a desktop app
        // ends up scaffolded as a webapp.
        $this->writeIntake('desktop');

        $exit = $this->scaffoldWithoutIntake('mismatchproj', ['--type' => 'server-webapp']);

        $this->assertSame(1, $exit);
        $this->assertFileDoesNotExist($this->tmpProject . '/CLAUDE.md');
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

    public function test_desktop_type_gets_no_web_infrastructure(): void
    {
        $this->assertSame(0, $this->scaffold('desktopproj', ['--type' => 'desktop']));

        // The working method still lands — that part was never the problem.
        $this->assertFileExists($this->tmpProject . '/CLAUDE.md');
        $this->assertFileExists($this->tmpProject . '/docs/kb/INDEX.md');
        $this->assertFileExists($this->tmpProject . '/.claude/commands/start.md');

        // Everything that presumes an HTTP server must stay away: this is the
        // Vusista failure — a desktop app inheriting a web stack it never chose.
        $this->assertFileDoesNotExist($this->tmpProject . '/app/Http/Middleware/SecurityHeaders.php');
        $this->assertFileDoesNotExist($this->tmpProject . '/tests/Feature/Middleware/SecurityHeadersTest.php');
        $this->assertFileDoesNotExist($this->tmpProject . '/resources/js/app.js');
        $this->assertFileDoesNotExist($this->tmpProject . '/resources/js/alpine-components.js');
        $this->assertFileDoesNotExist($this->tmpProject . '/.env.example');
        $this->assertFileDoesNotExist($this->tmpProject . '/.github/workflows/ci.yml');
        $this->assertFileDoesNotExist($this->tmpProject . '/infection.json5');
        $this->assertFileDoesNotExist($this->tmpProject . '/docs/kb/runbooks/deploy.md');
        $this->assertDirectoryDoesNotExist($this->tmpProject . '/deploy');
    }

    public function test_desktop_claude_md_drops_the_five_testsite_targets(): void
    {
        $this->scaffold('desktopclaude', ['--type' => 'desktop']);

        $claude = File::get($this->tmpProject . '/CLAUDE.md');

        // A norm nobody can meet is a norm nobody reads: no public HTTPS
        // endpoint means no A+ grade to chase. The five sites may still be
        // named — but only to rule them out, never as a target.
        $this->assertStringNotContainsString(
            'Elke productie-deploy moet scoren',
            $claude,
            'A desktop app has no production deploy to grade'
        );
        $this->assertStringContainsString(
            'zijn **niet**',
            $claude,
            'The testsites must be explicitly ruled out, not silently dropped'
        );

        // What replaces it must be concrete, not empty.
        $this->assertStringContainsString('Dependency-audit faalt de build', $claude);
        $this->assertStringContainsString('desktop', $claude, 'The type belongs in the header');
        $this->assertStringContainsString(
            'geen argument',
            $claude,
            'Havun-standaard must be explicitly disqualified as a reason'
        );
    }

    /**
     * Guards the trap the earlier structure had: anything appended below the
     * type branch silently vanished for three of the four types (the kb-audit
     * placeholder did exactly that). Asserting per type — rather than once —
     * is what makes a future omission fail here instead of in a scaffolded
     * project.
     */
    public function test_the_common_set_is_identical_for_every_type(): void
    {
        $common = [
            'CLAUDE.md',
            'CONTRACTS.md',
            '.claude/context.md',
            '.claude/rules.md',
            '.claude/commands/start.md',
            '.gitignore',
            'docs/omwegen.md',
            'docs/kb/INDEX.md',
            'docs/kb/reference/test-quality-policy.md',
            'docs/kb/reference/kb-audit-latest.md',
            'docs/kb/decisions/0001-docs-first-development.md',
            'docs/kb/runbooks/post-install.md',
        ];

        foreach (['server-webapp', 'desktop', 'mobile', 'library-cli'] as $type) {
            File::deleteDirectory($this->tmpProject);

            $this->assertSame(0, $this->scaffold('regproj', ['--type' => $type]), "type {$type}");

            foreach ($common as $rel) {
                $this->assertFileExists($this->tmpProject . '/' . $rel, "{$rel} missing for type {$type}");
            }

            $this->assertStringContainsString(
                'tweede regel',
                File::get($this->tmpProject . '/docs/omwegen.md'),
                'The register must state when it turns into an architecture review'
            );
            $this->assertStringContainsString(
                'docs/omwegen.md',
                File::get($this->tmpProject . '/CLAUDE.md'),
                "CLAUDE.md must point at the register for {$type}"
            );
        }
    }

    public function test_the_registry_hint_carries_the_type(): void
    {
        // Without this the choice dies at scaffold time: qv:scan, docs:audit
        // and AutoFix would treat a desktop project as a webapp on day 30.
        $this->writeIntake('desktop');

        $this->artisan('project:scaffold', [
            'slug' => 'hintproj',
            '--path' => $this->tmpProject,
            '--type' => 'desktop',
            '--force' => true,
        ])->expectsOutputToContain("'type' => 'desktop',")->run();
    }

    public function test_production_deploy_is_refused_for_non_server_types(): void
    {
        $exit = $this->scaffold('desktopdeploy', [
            '--type' => 'desktop',
            '--deploy' => 'production',
        ]);

        $this->assertSame(1, $exit, 'A desktop app must not get a staging/production pipeline');
        $this->assertDirectoryDoesNotExist($this->tmpProject . '/deploy');
    }

    public function test_non_web_post_install_points_at_the_intake_instead_of_a_stack(): void
    {
        $this->scaffold('libproj', ['--type' => 'library-cli']);

        $runbook = File::get($this->tmpProject . '/docs/kb/runbooks/post-install.md');

        // It must NOT install a foundation of its own — that would repeat the
        // mistake with a different framework.
        $this->assertStringNotContainsString('composer create-project', $runbook);
        $this->assertStringContainsString('docs/intake.md', $runbook);
        $this->assertStringContainsString('omkeerpunt', $runbook, 'Decisions must name what would reverse them');
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
