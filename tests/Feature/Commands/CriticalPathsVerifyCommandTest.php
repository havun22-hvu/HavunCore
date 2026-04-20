<?php

namespace Tests\Feature\Commands;

use App\Services\CriticalPaths\TestRunner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CriticalPathsVerifyCommandTest extends TestCase
{
    private string $docDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->docDir = base_path('docs/kb/reference');
        File::ensureDirectoryExists($this->docDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->docDir . '/critical-paths-fixture-*.md') ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function writeFixtureDoc(string $slug, string $content): string
    {
        $path = $this->docDir . "/critical-paths-{$slug}.md";
        File::put($path, $content);

        return $path;
    }

    public function test_exit_0_when_all_references_exist(): void
    {
        $this->writeFixtureDoc('fixture-ok', <<<'MD'
## Pad 1 — Observability (stable)

**Tests die dit afdekken:**

- `tests/Unit/Services/ObservabilityServiceTest.php`
- `tests/Unit/QualitySafety/QualitySafetyScannerTest.php`
MD);

        $exit = $this->artisan('critical-paths:verify', ['--project' => 'fixture-ok']);
        $exit->assertExitCode(0);
    }

    public function test_exit_1_when_reference_broken(): void
    {
        $this->writeFixtureDoc('fixture-broken', <<<'MD'
## Pad 1 — Broken

**Tests die dit afdekken:**

- `tests/Unit/Services/ObservabilityServiceTest.php`
- `tests/Feature/ThisReallyDoesNotExist.php`
MD);

        $this->artisan('critical-paths:verify', ['--project' => 'fixture-broken'])
            ->assertExitCode(1);
    }

    public function test_exit_2_when_doc_missing(): void
    {
        $this->artisan('critical-paths:verify', ['--project' => 'nonexistent-project'])
            ->assertExitCode(2);
    }

    public function test_project_and_all_are_mutually_exclusive(): void
    {
        $this->artisan('critical-paths:verify', ['--project' => 'havuncore', '--all' => true])
            ->assertExitCode(2);
    }

    public function test_json_output_schema(): void
    {
        $this->writeFixtureDoc('fixture-json', <<<'MD'
## Pad 1 — JSON

**Tests die dit afdekken:**

- `tests/Unit/Services/ObservabilityServiceTest.php`
- `tests/Unit/NopeTest.php`
MD);

        Artisan::call('critical-paths:verify', [
            '--project' => 'fixture-json',
            '--json' => true,
        ]);

        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded);
        $this->assertSame('fixture-json', $decoded['project']);
        $this->assertCount(1, $decoded['paths']);
        $this->assertSame('JSON', $decoded['paths'][0]['name']);
        $this->assertCount(2, $decoded['paths'][0]['references']);
        $this->assertSame(2, $decoded['totals']['references']);
        $this->assertSame(1, $decoded['totals']['ok']);
        $this->assertSame(1, $decoded['totals']['missing']);
    }

    public function test_run_flag_triggers_test_execution(): void
    {
        $this->writeFixtureDoc('fixture-run', <<<'MD'
## Pad 1 — Run

**Tests die dit afdekken:**

- `tests/Unit/Services/ObservabilityServiceTest.php`
MD);

        $runner = $this->mock(TestRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->with('ObservabilityServiceTest')
            ->andReturn([
                'filter' => 'ObservabilityServiceTest',
                'exit_code' => 0,
                'passed' => true,
                'duration_ms' => 42,
                'output' => 'ok',
            ]);

        $this->artisan('critical-paths:verify', [
            '--project' => 'fixture-run',
            '--run' => true,
        ])->assertExitCode(0);
    }

    public function test_run_flag_reports_failed_tests_as_exit_1(): void
    {
        $this->writeFixtureDoc('fixture-runfail', <<<'MD'
## Pad 1 — Run fail

**Tests die dit afdekken:**

- `tests/Unit/Services/ObservabilityServiceTest.php`
MD);

        $runner = $this->mock(TestRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->andReturn([
                'filter' => 'ObservabilityServiceTest',
                'exit_code' => 1,
                'passed' => false,
                'duration_ms' => 17,
                'output' => 'fail',
            ]);

        $this->artisan('critical-paths:verify', [
            '--project' => 'fixture-runfail',
            '--run' => true,
        ])->assertExitCode(1);
    }

    public function test_all_flag_scans_every_critical_paths_doc(): void
    {
        $this->writeFixtureDoc('fixture-all-a', <<<'MD'
## Pad 1 — A

**Tests die dit afdekken:**

- `tests/Unit/Services/ObservabilityServiceTest.php`
MD);
        $this->writeFixtureDoc('fixture-all-b', <<<'MD'
## Pad 1 — B

**Tests die dit afdekken:**

- `tests/Unit/NopeTest.php`
MD);

        Artisan::call('critical-paths:verify', ['--all' => true, '--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded);
        $projects = array_column($decoded, 'project');
        $this->assertContains('fixture-all-a', $projects);
        $this->assertContains('fixture-all-b', $projects);
    }
}
