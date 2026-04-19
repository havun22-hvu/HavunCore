<?php

namespace Tests\Unit\QualitySafety;

use App\Services\QualitySafety\ScanReportRenderer;
use Tests\TestCase;

class ScanReportRendererTest extends TestCase
{
    public function test_empty_run_renders_no_findings_line(): void
    {
        $renderer = new ScanReportRenderer;

        $md = $renderer->render($this->makeRun(['_source_file' => 'qv-scans/2026-04-19/empty.json']));

        $this->assertStringContainsString('Geen HIGH/CRITICAL findings', $md);
        $this->assertStringContainsString('generated_from: qv-scans/2026-04-19/empty.json', $md);
    }

    public function test_high_findings_appear_in_table(): void
    {
        $renderer = new ScanReportRenderer;

        $md = $renderer->render($this->makeRun([
            'findings' => [
                [
                    'project' => 'havunadmin',
                    'check' => 'composer',
                    'severity' => 'high',
                    'package' => 'phpseclib/phpseclib',
                    'advisory_id' => 'GHSA-xxxx',
                    'title' => 'Signature verification bypass',
                ],
            ],
            'totals' => ['critical' => 0, 'high' => 1, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ]));

        $this->assertStringContainsString('phpseclib/phpseclib', $md);
        $this->assertStringContainsString('GHSA-xxxx', $md);
        $this->assertStringContainsString('Signature verification bypass', $md);
        $this->assertStringContainsString('| havunadmin | composer | high |', $md);
    }

    public function test_medium_and_low_findings_are_filtered_out_of_findings_table(): void
    {
        $renderer = new ScanReportRenderer;

        $md = $renderer->render($this->makeRun([
            'findings' => [
                ['project' => 'p', 'check' => 'composer', 'severity' => 'medium', 'package' => 'noise/medium', 'title' => 'medium'],
                ['project' => 'p', 'check' => 'composer', 'severity' => 'low', 'package' => 'noise/low', 'title' => 'low'],
            ],
            'totals' => ['critical' => 0, 'high' => 0, 'medium' => 1, 'low' => 1, 'informational' => 0, 'errors' => 0],
        ]));

        $this->assertStringNotContainsString('noise/medium', $md);
        $this->assertStringNotContainsString('noise/low', $md);
        $this->assertStringContainsString('Geen HIGH/CRITICAL findings', $md);
    }

    public function test_errors_are_rendered_in_separate_table(): void
    {
        $renderer = new ScanReportRenderer;

        $md = $renderer->render($this->makeRun([
            'errors' => [
                ['project' => 'bad', 'check' => 'composer', 'message' => 'Project path not found: /nope'],
            ],
        ]));

        $this->assertStringContainsString('## Scanner errors', $md);
        $this->assertStringContainsString('Project path not found', $md);
    }

    public function test_pipe_characters_in_title_are_escaped(): void
    {
        $renderer = new ScanReportRenderer;

        $md = $renderer->render($this->makeRun([
            'findings' => [
                ['project' => 'p', 'check' => 'composer', 'severity' => 'high', 'package' => 'p|k', 'title' => 'bad|title'],
            ],
        ]));

        $this->assertStringContainsString('bad\|title', $md);
        $this->assertStringNotContainsString('| bad|title |', $md);
    }

    public function test_totals_table_includes_all_severities(): void
    {
        $renderer = new ScanReportRenderer;

        $md = $renderer->render($this->makeRun([
            'totals' => ['critical' => 2, 'high' => 3, 'medium' => 4, 'low' => 5, 'informational' => 6, 'errors' => 7],
        ]));

        $this->assertStringContainsString('| critical | 2 |', $md);
        $this->assertStringContainsString('| high | 3 |', $md);
        $this->assertStringContainsString('| medium | 4 |', $md);
        $this->assertStringContainsString('| low | 5 |', $md);
        $this->assertStringContainsString('| informational | 6 |', $md);
        $this->assertStringContainsString('| errors | 7 |', $md);
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    private function makeRun(array $overrides = []): array
    {
        return array_merge([
            'started_at' => '2026-04-19T09:00:00+02:00',
            'finished_at' => '2026-04-19T09:00:01+02:00',
            'projects' => ['p'],
            'checks' => ['composer'],
            'findings' => [],
            'errors' => [],
            'totals' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => 0],
        ], $overrides);
    }
}
