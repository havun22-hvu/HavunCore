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

    public function test_run_collapses_glob_matches_into_single_artisan_call(): void
    {
        $fixtureDir = base_path('tests/fixtures/critpath-glob');
        File::ensureDirectoryExists($fixtureDir);
        File::put($fixtureDir . '/AlphaTest.php', '<?php');
        File::put($fixtureDir . '/BravoTest.php', '<?php');

        $this->writeFixtureDoc('fixture-glob', <<<MD
## Pad 1 — Glob

**Tests die dit afdekken:**

- `tests/fixtures/critpath-glob/*.php`
MD);

        try {
            $runner = $this->mock(TestRunner::class);
            // Critical: ONE artisan-test boot for the whole glob, not one per match.
            $runner->shouldReceive('run')
                ->once()
                ->withArgs(fn ($filter) => str_contains($filter, 'AlphaTest')
                    && str_contains($filter, 'BravoTest')
                    && str_contains($filter, '|'))
                ->andReturn([
                    'filter' => 'AlphaTest|BravoTest',
                    'exit_code' => 0,
                    'passed' => true,
                    'duration_ms' => 33,
                    'output' => 'ok',
                ]);

            $this->artisan('critical-paths:verify', [
                '--project' => 'fixture-glob',
                '--run' => true,
            ])->assertExitCode(0);
        } finally {
            File::deleteDirectory($fixtureDir);
        }
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

    /**
     * JSON for a missing doc must report the schema with all-zero totals.
     * Without pinning each field, DecrementInteger mutations (`paths` = -1,
     * `ok` = -1, `failed` = -1 etc.) escape.
     */
    public function test_missing_doc_json_reports_full_zero_totals(): void
    {
        Artisan::call('critical-paths:verify', [
            '--project' => 'truly-nonexistent-slug',
            '--json' => true,
        ]);

        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded);
        $this->assertSame('truly-nonexistent-slug', $decoded['project']);
        $this->assertSame('doc missing', $decoded['error']);
        $this->assertSame([], $decoded['paths']);
        $this->assertSame(
            ['paths' => 0, 'references' => 0, 'ok' => 0, 'missing' => 0, 'failed' => 0],
            $decoded['totals']
        );
    }

    /**
     * With `--all`, a mix of (a) doc-missing AND (b) real docs must yield an
     * array of reports — one per project. If `continue` in the doc-missing
     * branch is mutated to `break`, we never reach the second project and
     * only get one report back.
     */
    public function test_all_flag_continues_past_a_missing_doc(): void
    {
        // Ensure at least one real doc is present — we'll inject another
        // slug via glob so the discoverAllProjects() list contains > 1 item.
        $this->writeFixtureDoc('fixture-keepgoing', <<<'MD'
## Pad 1 — Keep Going

**Tests die dit afdekken:**

- `tests/Unit/Services/ObservabilityServiceTest.php`
MD);

        // We can't actually make a missing doc appear in discoverAllProjects,
        // but we CAN verify the foreach reaches every real fixture doc in
        // one pass by adding two separate fixtures.
        $this->writeFixtureDoc('fixture-keepgoing-2', <<<'MD'
## Pad 1 — Also Keep Going

**Tests die dit afdekken:**

- `tests/Unit/Services/ObservabilityServiceTest.php`
MD);

        Artisan::call('critical-paths:verify', ['--all' => true, '--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $projects = array_column($decoded, 'project');
        $this->assertContains('fixture-keepgoing', $projects);
        $this->assertContains('fixture-keepgoing-2', $projects);
    }

    /**
     * Text (non-JSON) output must actually render — if `$this->renderText()`
     * is removed (mutation), the command prints nothing on the text branch.
     */
    public function test_text_output_renders_project_header_and_summary(): void
    {
        $this->writeFixtureDoc('fixture-text', <<<'MD'
## Pad 1 — Text Output

**Tests die dit afdekken:**

- `tests/Unit/Services/ObservabilityServiceTest.php`
MD);

        Artisan::call('critical-paths:verify', ['--project' => 'fixture-text']);
        $output = Artisan::output();

        $this->assertStringContainsString('[fixture-text]', $output);
        $this->assertStringContainsString('Pad: Text Output', $output);
        $this->assertStringContainsString('Summary:', $output);
        $this->assertStringContainsString('1 paths', $output);
        $this->assertStringContainsString('1 refs', $output);
        $this->assertStringContainsString('1 ok', $output);
        $this->assertStringContainsString('0 missing', $output);
        $this->assertStringContainsString('0 failed', $output);
    }

    /**
     * Text output for a missing doc must surface `Error: doc missing`. Mutation
     * removing `$this->error(...)` in renderText escapes silently otherwise.
     */
    public function test_text_output_reports_error_line_for_missing_doc(): void
    {
        Artisan::call('critical-paths:verify', ['--project' => 'definitely-missing-slug']);
        $output = Artisan::output();

        $this->assertStringContainsString('[definitely-missing-slug]', $output);
        $this->assertStringContainsString('Error: doc missing', $output);
    }

    /**
     * The OK mark uses `<info>✓</info>`, the missing mark `<error>✗</error>`.
     * Laravel's console formatter strips the tags and renders just the glyph
     * — so a Ternary mutation swapping the two would print `✗` for existing
     * files. Pin the exact glyph-for-exists.
     */
    public function test_text_output_uses_check_glyph_for_existing_reference(): void
    {
        $this->writeFixtureDoc('fixture-glyph-ok', <<<'MD'
## Pad 1 — Glyph OK

**Tests die dit afdekken:**

- `tests/Unit/Services/ObservabilityServiceTest.php`
MD);

        Artisan::call('critical-paths:verify', ['--project' => 'fixture-glyph-ok']);
        $output = Artisan::output();

        $this->assertStringContainsString('✓', $output);
        $this->assertStringContainsString('tests/Unit/Services/ObservabilityServiceTest.php', $output);
        // Cross glyph must NOT appear when all refs resolve.
        $this->assertStringNotContainsString('✗', $output);
    }

    public function test_text_output_uses_cross_glyph_for_missing_reference(): void
    {
        $this->writeFixtureDoc('fixture-glyph-miss', <<<'MD'
## Pad 1 — Glyph Miss

**Tests die dit afdekken:**

- `tests/Feature/DoesNotExistAtAll.php`
MD);

        Artisan::call('critical-paths:verify', ['--project' => 'fixture-glyph-miss']);
        $output = Artisan::output();

        $this->assertStringContainsString('✗', $output);
        $this->assertStringContainsString('tests/Feature/DoesNotExistAtAll.php', $output);
        $this->assertStringContainsString('file missing', $output);
    }

    /**
     * Path line in text output must render both the glyph AND the actual
     * reference path. A MethodCallRemoval mutation on `$this->line("    {mark} ...")`
     * would suppress the ref-path entirely.
     */
    public function test_text_output_lists_every_reference_under_its_pad(): void
    {
        $this->writeFixtureDoc('fixture-text-multi', <<<'MD'
## Pad 1 — Multi

**Tests die dit afdekken:**

- `tests/Unit/Services/ObservabilityServiceTest.php`
- `tests/Unit/QualitySafety/QualitySafetyScannerTest.php`
MD);

        Artisan::call('critical-paths:verify', ['--project' => 'fixture-text-multi']);
        $output = Artisan::output();

        $this->assertStringContainsString('tests/Unit/Services/ObservabilityServiceTest.php', $output);
        $this->assertStringContainsString('tests/Unit/QualitySafety/QualitySafetyScannerTest.php', $output);
    }

    /**
     * With `--run` and a passing test, the text-suffix includes the duration.
     * Covers the `[ran OK in {ms}ms]` branch of renderText.
     */
    public function test_text_output_reports_run_duration_on_pass(): void
    {
        $this->writeFixtureDoc('fixture-text-run', <<<'MD'
## Pad 1 — Text Run

**Tests die dit afdekken:**

- `tests/Unit/Services/ObservabilityServiceTest.php`
MD);

        $runner = $this->mock(TestRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->andReturn([
                'filter' => 'ObservabilityServiceTest',
                'exit_code' => 0,
                'passed' => true,
                'duration_ms' => 123,
                'output' => 'ok',
            ]);

        Artisan::call('critical-paths:verify', [
            '--project' => 'fixture-text-run',
            '--run' => true,
        ]);
        $output = Artisan::output();

        $this->assertStringContainsString('[ran OK in 123ms]', $output);
    }

    /**
     * With `--run` and a failing test, the text-suffix reads `[RAN FAILED]`.
     * Pinning the exact literal kills the ternary mutation on
     * `$ref['tests_passed'] ? '...OK...' : '...FAILED...'`.
     */
    public function test_text_output_marks_run_failure_explicitly(): void
    {
        $this->writeFixtureDoc('fixture-text-runfail', <<<'MD'
## Pad 1 — Text Run Fail

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
                'duration_ms' => 50,
                'output' => 'bad',
            ]);

        $exit = Artisan::call('critical-paths:verify', [
            '--project' => 'fixture-text-runfail',
            '--run' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('[RAN FAILED]', $output);
    }

    /**
     * A broken reference increments the `missing` total but NOT `ok`.
     * Without a strict JSON assertion, the `continue`-after-missing mutation
     * (continue→break, which would stop iterating siblings) escapes.
     */
    public function test_missing_reference_does_not_abort_sibling_processing(): void
    {
        $this->writeFixtureDoc('fixture-siblings', <<<'MD'
## Pad 1 — Siblings

**Tests die dit afdekken:**

- `tests/Feature/ThisReallyDoesNotExist.php`
- `tests/Unit/Services/ObservabilityServiceTest.php`
- `tests/Feature/AnotherMissing.php`
- `tests/Unit/QualitySafety/QualitySafetyScannerTest.php`
MD);

        Artisan::call('critical-paths:verify', [
            '--project' => 'fixture-siblings',
            '--json' => true,
        ]);

        $decoded = json_decode(Artisan::output(), true);

        // All four references must have been processed (not short-circuited).
        $this->assertCount(4, $decoded['paths'][0]['references']);
        $this->assertSame(4, $decoded['totals']['references']);
        $this->assertSame(2, $decoded['totals']['ok']);
        $this->assertSame(2, $decoded['totals']['missing']);
    }
}
