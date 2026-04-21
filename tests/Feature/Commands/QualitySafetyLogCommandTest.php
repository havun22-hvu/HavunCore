<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QualitySafetyLogCommandTest extends TestCase
{
    private string $tempOutput;

    private string $tempAppendLog;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $uniq = uniqid();
        $this->tempOutput = 'tmp-tests/qv-log-' . $uniq . '.md';
        $this->tempAppendLog = 'tmp-tests/qv-findings-log-' . $uniq . '.md';
    }

    protected function tearDown(): void
    {
        foreach ([$this->tempOutput, $this->tempAppendLog] as $rel) {
            $absolute = base_path($rel);
            if (file_exists($absolute)) {
                unlink($absolute);
            }
        }

        $safeRoot = base_path('tmp-tests');
        if (is_dir($safeRoot)) {
            @rmdir($safeRoot);
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

        $this->artisan('qv:log', [
            '--output' => $this->tempOutput,
            '--append-log' => $this->tempAppendLog,
        ])
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

    public function test_high_finding_is_appended_to_security_log(): void
    {
        Storage::disk('local')->put('qv-scans/2026-04-20/run-aaaa.json', json_encode($this->sampleRun([
            'started_at' => '2026-04-20T14:30:00+02:00',
            'findings' => [[
                'project' => 'judotoernooi',
                'check' => 'composer',
                'severity' => 'high',
                'package' => 'session/cookies',
                'advisory_id' => 'GHSA-aaaa',
                'title' => 'Insecure cookie flag',
            ]],
            'totals' => ['critical' => 0, 'high' => 1, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ])));

        $this->artisan('qv:log', [
            '--output' => $this->tempOutput,
            '--append-log' => $this->tempAppendLog,
        ])->assertExitCode(0);

        $log = file_get_contents(base_path($this->tempAppendLog));
        $this->assertStringContainsString('auto-generated, append-only', $log);
        $this->assertStringContainsString('## 2026-04-20 14:30', $log);
        $this->assertStringContainsString('[HIGH]', $log);
        $this->assertStringContainsString('judotoernooi/session/cookies', $log);
        $this->assertStringContainsString('GHSA-aaaa', $log);
    }

    public function test_scan_without_high_or_critical_leaves_log_untouched(): void
    {
        Storage::disk('local')->put('qv-scans/2026-04-20/run-bbbb.json', json_encode($this->sampleRun([
            'findings' => [[
                'project' => 'x',
                'check' => 'composer',
                'severity' => 'medium',
                'package' => 'not/important',
                'title' => 'meh',
            ]],
            'totals' => ['critical' => 0, 'high' => 0, 'medium' => 1, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ])));

        // Seed existing log content to assert it stays intact.
        $absoluteLog = base_path($this->tempAppendLog);
        @mkdir(dirname($absoluteLog), 0777, true);
        file_put_contents($absoluteLog, "pre-existing content\n");

        $this->artisan('qv:log', [
            '--output' => $this->tempOutput,
            '--append-log' => $this->tempAppendLog,
        ])
            ->expectsOutputToContain('No HIGH/CRITICAL findings to append')
            ->assertExitCode(0);

        $this->assertSame("pre-existing content\n", file_get_contents($absoluteLog));
    }

    public function test_append_preserves_historical_entries(): void
    {
        Storage::disk('local')->put('qv-scans/2026-04-20/run-cccc.json', json_encode($this->sampleRun([
            'started_at' => '2026-04-20T10:00:00+02:00',
            'findings' => [[
                'project' => 'p1',
                'check' => 'composer',
                'severity' => 'critical',
                'package' => 'first/pkg',
                'title' => 'First critical',
            ]],
        ])));

        $this->artisan('qv:log', [
            '--output' => $this->tempOutput,
            '--append-log' => $this->tempAppendLog,
        ])->assertExitCode(0);

        // Second run with a different finding — simulate next scan.
        Storage::disk('local')->delete('qv-scans/2026-04-20/run-cccc.json');
        Storage::disk('local')->put('qv-scans/2026-04-21/run-dddd.json', json_encode($this->sampleRun([
            'started_at' => '2026-04-21T11:15:00+02:00',
            'findings' => [[
                'project' => 'p2',
                'check' => 'npm',
                'severity' => 'high',
                'package' => 'second/pkg',
                'title' => 'Second high',
            ]],
        ])));

        $this->artisan('qv:log', [
            '--output' => $this->tempOutput,
            '--append-log' => $this->tempAppendLog,
        ])->assertExitCode(0);

        $log = file_get_contents(base_path($this->tempAppendLog));
        $this->assertStringContainsString('first/pkg', $log);
        $this->assertStringContainsString('First critical', $log);
        $this->assertStringContainsString('second/pkg', $log);
        $this->assertStringContainsString('Second high', $log);
        $this->assertStringContainsString('## 2026-04-20 10:00', $log);
        $this->assertStringContainsString('## 2026-04-21 11:15', $log);
        // Header should only appear once.
        $this->assertSame(1, substr_count($log, 'auto-generated, append-only'));
    }

    public function test_no_append_flag_skips_the_log(): void
    {
        Storage::disk('local')->put('qv-scans/2026-04-20/run-eeee.json', json_encode($this->sampleRun([
            'findings' => [[
                'project' => 'p',
                'check' => 'composer',
                'severity' => 'critical',
                'package' => 'skip/me',
                'title' => 'should not appear',
            ]],
        ])));

        $this->artisan('qv:log', [
            '--output' => $this->tempOutput,
            '--append-log' => $this->tempAppendLog,
            '--no-append' => true,
        ])->assertExitCode(0);

        $this->assertFileDoesNotExist(base_path($this->tempAppendLog));
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
