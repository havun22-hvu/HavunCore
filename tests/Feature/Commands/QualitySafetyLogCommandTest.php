<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QualitySafetyLogCommandTest extends TestCase
{
    private string $tempOutput;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->tempOutput = 'tmp-tests/qv-log-' . uniqid() . '.md';
    }

    protected function tearDown(): void
    {
        $absolute = base_path($this->tempOutput);
        if (file_exists($absolute)) {
            unlink($absolute);
        }
        $dir = dirname($absolute);
        if (is_dir($dir) && basename($dir) !== 'HavunCore') {
            @rmdir($dir);
        }
        parent::tearDown();
    }

    public function test_warns_when_no_runs_exist(): void
    {
        $this->artisan('qv:log', ['--output' => $this->tempOutput])
            ->expectsOutputToContain('No qv:scan runs found')
            ->assertExitCode(1);
    }

    public function test_writes_report_from_latest_run(): void
    {
        Storage::disk('local')->put('qv-scans/2026-04-19/run-older.json', json_encode($this->sampleRun()));
        Storage::disk('local')->put('qv-scans/2026-04-19/run-zzzzz.json', json_encode($this->sampleRun([
            'findings' => [[
                'project' => 'havunadmin',
                'check' => 'composer',
                'severity' => 'critical',
                'package' => 'bad/pkg',
                'title' => 'Critical bug',
            ]],
            'totals' => ['critical' => 1, 'high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ])));

        $this->artisan('qv:log', ['--output' => $this->tempOutput])
            ->expectsOutputToContain('Wrote report to')
            ->assertExitCode(0);

        $absolute = base_path($this->tempOutput);
        $this->assertFileExists($absolute);
        $content = file_get_contents($absolute);
        $this->assertStringContainsString('bad/pkg', $content);
        $this->assertStringContainsString('Critical bug', $content);
    }

    public function test_invalid_json_run_returns_failure(): void
    {
        Storage::disk('local')->put('qv-scans/2026-04-19/run-broken.json', 'not json');

        $this->artisan('qv:log', ['--output' => $this->tempOutput])
            ->expectsOutputToContain('not valid JSON')
            ->assertExitCode(1);
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    private function sampleRun(array $overrides = []): array
    {
        return array_merge([
            'started_at' => '2026-04-19T09:00:00+02:00',
            'finished_at' => '2026-04-19T09:00:01+02:00',
            'projects' => ['havunadmin'],
            'checks' => ['composer'],
            'findings' => [],
            'errors' => [],
            'totals' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ], $overrides);
    }
}
