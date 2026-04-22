<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocsHandoverCommandTest extends TestCase
{
    private string $tmpOutput;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpOutput = tempnam(sys_get_temp_dir(), 'handover-') . '.md';
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tmpOutput)) {
            File::delete($this->tmpOutput);
        }
        parent::tearDown();
    }

    public function test_writes_a_handover_file_with_required_sections(): void
    {
        $exitCode = $this->runCommand();

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->tmpOutput);

        $body = File::get($this->tmpOutput);
        $this->assertStringContainsString('# Handover (auto-generated)', $body);
        $this->assertStringContainsString('## Recente activiteit', $body);
        $this->assertStringContainsString('## V&K status', $body);
        $this->assertStringContainsString('## Verdiepende bronnen', $body);
        $this->assertStringContainsString('Bewerk dit bestand niet handmatig', $body);
    }

    public function test_renders_qv_totals_and_high_findings_from_json_snapshot(): void
    {
        $disk = (string) config('quality-safety.storage.disk', 'local');
        Storage::fake($disk);
        Storage::disk($disk)->put('qv-scans/' . now()->toDateString() . '/run-handover.json', json_encode([
            'started_at' => '2026-04-22T12:00:00+02:00',
            'totals' => ['critical' => 0, 'high' => 2, 'medium' => 1, 'low' => 0, 'informational' => 0, 'errors' => 0],
            'findings' => [
                ['severity' => 'high', 'project' => 'judotoernooi', 'check' => 'forms', 'message' => 'Form coverage 53%'],
                ['severity' => 'high', 'project' => 'herdenkingsportaal', 'check' => 'test-erosion', 'message' => 'Test deleted'],
                ['severity' => 'medium', 'project' => 'havuncore', 'check' => 'ssl', 'message' => 'Should not appear'],
            ],
        ]));

        $this->runCommand();
        $body = File::get($this->tmpOutput);

        $this->assertStringContainsString('critical 0', $body);
        $this->assertStringContainsString('high 2', $body);
        $this->assertStringContainsString('judotoernooi/forms', $body);
        $this->assertStringContainsString('Form coverage 53%', $body);
        $this->assertStringContainsString('herdenkingsportaal/test-erosion', $body);
        $this->assertStringNotContainsString('Should not appear', $body);
    }

    public function test_truncates_with_visible_overflow_indicator_above_max(): void
    {
        $disk = (string) config('quality-safety.storage.disk', 'local');
        Storage::fake($disk);
        $findings = [];
        for ($i = 1; $i <= 13; $i++) {
            $findings[] = ['severity' => 'high', 'project' => 'p', 'check' => 'c', 'message' => "f{$i}"];
        }
        Storage::disk($disk)->put('qv-scans/' . now()->toDateString() . '/run-overflow.json', json_encode([
            'started_at' => '2026-04-22T12:00:00+02:00',
            'totals' => ['critical' => 0, 'high' => 13, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
            'findings' => $findings,
        ]));

        $this->runCommand();
        $body = File::get($this->tmpOutput);

        $this->assertStringContainsString('+3 meer', $body);
        $this->assertStringContainsString('f1', $body);
        $this->assertStringContainsString('f10', $body);
        $this->assertStringNotContainsString(' f11', $body);
    }

    private function runCommand(): int
    {
        return $this->artisan('docs:handover', [
            '--days' => 1,
            '--output' => $this->tmpOutput,
        ])->run();
    }
}
