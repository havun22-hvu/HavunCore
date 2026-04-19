<?php

namespace Tests\Feature\Commands;

use App\Services\QualitySafety\QualitySafetyScanner;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class QualitySafetyScanCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tempProject = sys_get_temp_dir() . '/qv-test-' . uniqid();
        mkdir($this->tempProject, 0755, true);

        config()->set('quality-safety.projects', [
            'testproject' => [
                'enabled' => true,
                'path' => $this->tempProject,
                'url' => 'https://example.test',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanUp($this->tempProject);
        Mockery::close();
        parent::tearDown();
    }

    private string $tempProject;

    private function cleanUp(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->cleanUp($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_unknown_check_returns_exit_code_2(): void
    {
        $this->artisan('qv:scan', ['--only' => 'nonsense'])
            ->expectsOutputToContain('Unknown check: nonsense')
            ->assertExitCode(2);
    }

    public function test_unknown_project_filter_returns_exit_code_2(): void
    {
        $this->artisan('qv:scan', ['--project' => 'ghost-project'])
            ->expectsOutputToContain('No matching projects')
            ->assertExitCode(2);
    }

    public function test_clean_composer_run_returns_exit_code_0(): void
    {
        $this->mockScanner([
            'findings' => [],
            'errors' => [],
            'totals' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ]);

        $this->artisan('qv:scan', ['--only' => 'composer'])
            ->expectsOutputToContain('Quality & Safety scan')
            ->assertExitCode(0);
    }

    public function test_high_finding_returns_exit_code_1(): void
    {
        $this->mockScanner([
            'findings' => [[
                'project' => 'testproject',
                'check' => 'composer',
                'severity' => 'high',
                'message' => 'fake/package — test advisory',
            ]],
            'errors' => [],
            'totals' => ['critical' => 0, 'high' => 1, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ]);

        $this->artisan('qv:scan', ['--only' => 'composer'])
            ->expectsOutputToContain('[high] testproject/composer')
            ->assertExitCode(1);
    }

    public function test_scanner_error_returns_exit_code_2(): void
    {
        $this->mockScanner([
            'findings' => [],
            'errors' => [['project' => 'testproject', 'check' => 'composer', 'message' => 'binary missing']],
            'totals' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 1],
        ]);

        $this->artisan('qv:scan', ['--only' => 'composer'])
            ->assertExitCode(2);
    }

    public function test_json_flag_emits_json(): void
    {
        $this->mockScanner([
            'findings' => [],
            'errors' => [],
            'totals' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ]);

        $this->artisan('qv:scan', ['--only' => 'composer', '--json' => true])
            ->assertExitCode(0);
    }

    public function test_run_is_persisted_to_disk(): void
    {
        $this->mockScanner([
            'findings' => [],
            'errors' => [],
            'totals' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ]);

        $this->artisan('qv:scan', ['--only' => 'composer'])->assertExitCode(0);

        $files = Storage::disk('local')->allFiles('qv-scans');
        $this->assertNotEmpty($files, 'Expected at least one persisted run file');
        $this->assertTrue(
            str_ends_with($files[0], '.json'),
            'Persisted run file should be JSON'
        );
    }

    public function test_test_erosion_check_is_an_accepted_only_value(): void
    {
        $this->mockScanner([
            'findings' => [],
            'errors' => [],
            'totals' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ]);

        $this->artisan('qv:scan', ['--only' => 'test-erosion'])
            ->expectsOutputToContain('Quality & Safety scan')
            ->assertExitCode(0);
    }

    public function test_session_cookies_check_is_an_accepted_only_value(): void
    {
        $this->mockScanner([
            'findings' => [],
            'errors' => [],
            'totals' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ]);

        $this->artisan('qv:scan', ['--only' => 'session-cookies'])
            ->expectsOutputToContain('Quality & Safety scan')
            ->assertExitCode(0);
    }

    public function test_secrets_check_is_an_accepted_only_value(): void
    {
        $this->mockScanner([
            'findings' => [],
            'errors' => [],
            'totals' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ]);

        $this->artisan('qv:scan', ['--only' => 'secrets'])
            ->expectsOutputToContain('Quality & Safety scan')
            ->assertExitCode(0);
    }

    public function test_ratelimit_check_is_an_accepted_only_value(): void
    {
        $this->mockScanner([
            'findings' => [],
            'errors' => [],
            'totals' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ]);

        $this->artisan('qv:scan', ['--only' => 'ratelimit'])
            ->expectsOutputToContain('Quality & Safety scan')
            ->assertExitCode(0);
    }

    public function test_forms_check_is_an_accepted_only_value(): void
    {
        $this->mockScanner([
            'findings' => [],
            'errors' => [],
            'totals' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ]);

        $this->artisan('qv:scan', ['--only' => 'forms'])
            ->expectsOutputToContain('Quality & Safety scan')
            ->assertExitCode(0);
    }

    public function test_server_check_is_an_accepted_only_value(): void
    {
        $this->mockScanner([
            'findings' => [],
            'errors' => [],
            'totals' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ]);

        $this->artisan('qv:scan', ['--only' => 'server'])
            ->expectsOutputToContain('Quality & Safety scan')
            ->assertExitCode(0);
    }

    public function test_disabled_project_is_filtered_out(): void
    {
        config()->set('quality-safety.projects', [
            'disabled' => [
                'enabled' => false,
                'path' => $this->tempProject,
                'url' => 'https://example.test',
            ],
        ]);

        $this->artisan('qv:scan', ['--only' => 'composer'])
            ->expectsOutputToContain('No matching projects')
            ->assertExitCode(2);
    }

    /**
     * @param  array<string,mixed>  $stub
     */
    private function mockScanner(array $stub): void
    {
        $stub += [
            'started_at' => '2026-04-19T10:00:00+00:00',
            'finished_at' => '2026-04-19T10:00:01+00:00',
            'projects' => ['testproject'],
            'checks' => ['composer'],
        ];

        $mock = Mockery::mock(QualitySafetyScanner::class);
        $mock->shouldReceive('scan')->once()->andReturn($stub);

        $this->app->instance(QualitySafetyScanner::class, $mock);
    }
}
