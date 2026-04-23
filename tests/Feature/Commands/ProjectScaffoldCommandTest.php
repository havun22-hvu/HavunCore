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

    public function test_scaffolds_required_artefacts_for_valid_slug(): void
    {
        $exit = $this->artisan('project:scaffold', [
            'slug' => 'testproject',
            '--path' => $this->tmpProject,
            '--force' => true,
        ])->run();

        $this->assertSame(0, $exit);

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

    public function test_copies_claude_commands_from_havuncore(): void
    {
        $this->artisan('project:scaffold', [
            'slug' => 'testproj2',
            '--path' => $this->tmpProject,
            '--force' => true,
        ])->run();

        // Kern Claude commands die in HavunCore bestaan en gekopieerd zijn:
        $this->assertFileExists($this->tmpProject . '/.claude/commands/start.md');
        $this->assertFileExists($this->tmpProject . '/.claude/commands/end.md');
        $this->assertFileExists($this->tmpProject . '/.claude/commands/kb.md');
        $this->assertFileExists($this->tmpProject . '/.claude/commands/kb-audit.md');
        $this->assertFileExists($this->tmpProject . '/.claude/commands/mpc.md');
    }

    public function test_rejects_invalid_slug(): void
    {
        $exit = $this->artisan('project:scaffold', [
            'slug' => 'UPPERCASE', // invalid: uppercase
            '--force' => true,
        ])->run();

        $this->assertSame(1, $exit);
    }

    public function test_rejects_non_laravel_stack_in_mvp(): void
    {
        $exit = $this->artisan('project:scaffold', [
            'slug' => 'nodetestproj',
            '--path' => $this->tmpProject,
            '--stack' => 'node',
            '--force' => true,
        ])->run();

        $this->assertSame(1, $exit);
    }

    public function test_deploy_production_generates_nginx_server_configs(): void
    {
        $this->artisan('project:scaffold', [
            'slug' => 'deployproj',
            '--path' => $this->tmpProject,
            '--deploy' => 'production',
            '--force' => true,
        ])->run();

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
        $this->artisan('project:scaffold', [
            'slug' => 'nodeployproj',
            '--path' => $this->tmpProject,
            '--force' => true,
        ])->run();

        $this->assertDirectoryDoesNotExist($this->tmpProject . '/deploy');
    }

    public function test_skips_existing_files_idempotent_run(): void
    {
        $firstRun = $this->artisan('project:scaffold', [
            'slug' => 'idempotent',
            '--path' => $this->tmpProject,
            '--force' => true,
        ])->run();
        $this->assertSame(0, $firstRun);

        // Wijzig een bestand om te verifieren dat run #2 het niet overschrijft.
        File::put($this->tmpProject . '/CLAUDE.md', '# Custom content — must not be overwritten');

        $secondRun = $this->artisan('project:scaffold', [
            'slug' => 'idempotent',
            '--path' => $this->tmpProject,
            '--force' => true,
        ])->run();

        $this->assertSame(0, $secondRun);
        $this->assertSame(
            '# Custom content — must not be overwritten',
            File::get($this->tmpProject . '/CLAUDE.md')
        );
    }
}
