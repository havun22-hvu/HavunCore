<?php

namespace Tests\Unit\QualitySafety;

use App\Services\QualitySafety\QualitySafetyScanner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class QualitySafetyScannerTest extends TestCase
{
    private string $tempProject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempProject = sys_get_temp_dir() . '/qv-scanner-' . uniqid();
        mkdir($this->tempProject, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->cleanUp($this->tempProject);
        parent::tearDown();
    }

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

    /** @return array<string,mixed> */
    private function project(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true,
            'path' => $this->tempProject,
            'url' => 'https://example.test',
        ], $overrides);
    }

    private function withComposerManifest(): void
    {
        file_put_contents($this->tempProject . '/composer.json', '{"name":"test/t"}');
    }

    private function withPackageManifest(): void
    {
        file_put_contents($this->tempProject . '/package.json', '{"name":"test"}');
    }

    public function test_composer_audit_parses_advisories_json(): void
    {
        $this->withComposerManifest();
        Process::fake([
            '*' => Process::result(
                output: json_encode([
                    'advisories' => [
                        'fake/package' => [[
                            'advisoryId' => 'GHSA-xxxx-yyyy-zzzz',
                            'title' => 'Test advisory',
                            'severity' => 'HIGH',
                            'affectedVersions' => '<1.2.3',
                        ]],
                    ],
                ]),
                exitCode: 1,
            ),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['testproject' => $this->project()], ['composer']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('high', $run['findings'][0]['severity']);
        $this->assertSame('fake/package', $run['findings'][0]['package']);
        $this->assertSame(1, $run['totals']['high']);
    }

    public function test_composer_audit_clean_run_has_no_findings(): void
    {
        $this->withComposerManifest();
        Process::fake([
            '*' => Process::result(output: json_encode(['advisories' => []]), exitCode: 0),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['clean' => $this->project()], ['composer']);

        $this->assertEmpty($run['findings']);
        $this->assertSame(0, $run['totals']['high']);
        $this->assertSame(0, $run['totals']['errors']);
    }

    public function test_unparseable_composer_output_registers_error(): void
    {
        $this->withComposerManifest();
        Process::fake([
            '*' => Process::result(output: 'garbage not json', exitCode: 1),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['broken' => $this->project()], ['composer']);

        $this->assertSame(1, $run['totals']['errors']);
    }

    public function test_missing_project_path_registers_error(): void
    {
        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'ghost' => $this->project(['path' => '/nonexistent/path-' . uniqid()]),
        ], ['composer']);

        $this->assertSame(1, $run['totals']['errors']);
        $this->assertStringContainsString('Project path not found', $run['errors'][0]['message']);
    }

    public function test_composer_audit_without_composer_json_is_skipped(): void
    {
        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['nonphp' => $this->project()], ['composer']);

        $this->assertSame(0, $run['totals']['errors']);
        $this->assertEmpty($run['findings']);
    }

    public function test_npm_audit_without_package_json_is_skipped(): void
    {
        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['nonjs' => $this->project()], ['npm']);

        $this->assertSame(0, $run['totals']['errors']);
        $this->assertEmpty($run['findings']);
    }

    public function test_npm_audit_parses_vulnerabilities(): void
    {
        $this->withPackageManifest();
        Process::fake([
            '*' => Process::result(
                output: json_encode([
                    'vulnerabilities' => [
                        'bad-pkg' => [
                            'severity' => 'critical',
                            'range' => '<1.0.0',
                            'via' => [
                                ['title' => 'XSS in bad-pkg'],
                            ],
                        ],
                    ],
                ]),
                exitCode: 1,
            ),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['jsproj' => $this->project()], ['npm']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('critical', $run['findings'][0]['severity']);
        $this->assertSame('bad-pkg', $run['findings'][0]['package']);
    }

    public function test_severity_normalization_maps_moderate_to_medium(): void
    {
        $this->withComposerManifest();
        Process::fake([
            '*' => Process::result(
                output: json_encode([
                    'advisories' => [
                        'pkg' => [[
                            'title' => 'Moderate issue',
                            'severity' => 'moderate',
                            'affectedVersions' => '<1',
                        ]],
                    ],
                ]),
                exitCode: 1,
            ),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['p' => $this->project()], ['composer']);

        $this->assertSame('medium', $run['findings'][0]['severity']);
    }

    public function test_observatory_grade_a_plus_is_clean(): void
    {
        config()->set('quality-safety.observatory.min_grade', 'B');
        Http::fake([
            '*' => Http::response(['grade' => 'A+', 'score' => 115]),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'good' => ['enabled' => true, 'url' => 'https://ok.example'],
        ], ['observatory']);

        $this->assertEmpty($run['findings']);
        $this->assertSame(0, $run['totals']['errors']);
    }

    public function test_observatory_grade_c_triggers_high_finding(): void
    {
        config()->set('quality-safety.observatory.min_grade', 'B');
        Http::fake([
            '*' => Http::response(['grade' => 'C', 'score' => 50]),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'poor' => ['enabled' => true, 'url' => 'https://weak.example'],
        ], ['observatory']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('high', $run['findings'][0]['severity']);
        $this->assertSame('C', $run['findings'][0]['grade']);
    }

    public function test_observatory_grade_f_triggers_critical_finding(): void
    {
        config()->set('quality-safety.observatory.min_grade', 'B');
        Http::fake([
            '*' => Http::response(['grade' => 'F', 'score' => 0]),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'broken' => ['enabled' => true, 'url' => 'https://bad.example'],
        ], ['observatory']);

        $this->assertSame('critical', $run['findings'][0]['severity']);
    }

    public function test_observatory_grade_c_minus_triggers_high_not_critical(): void
    {
        config()->set('quality-safety.observatory.min_grade', 'B');
        Http::fake([
            '*' => Http::response(['grade' => 'C-', 'score' => 40]),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'meh' => ['enabled' => true, 'url' => 'https://meh.example'],
        ], ['observatory']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('high', $run['findings'][0]['severity']);
    }

    public function test_observatory_non_200_response_registers_error(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'internal'], 500),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'bad' => ['enabled' => true, 'url' => 'https://err.example'],
        ], ['observatory']);

        $this->assertSame(1, $run['totals']['errors']);
        $this->assertStringContainsString('HTTP 500', $run['errors'][0]['message']);
    }

    public function test_observatory_response_without_grade_registers_error(): void
    {
        Http::fake([
            '*' => Http::response(['score' => 80]),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'weird' => ['enabled' => true, 'url' => 'https://x.example'],
        ], ['observatory']);

        $this->assertSame(1, $run['totals']['errors']);
        $this->assertStringContainsString('missing grade', $run['errors'][0]['message']);
    }

    public function test_unknown_check_yields_no_findings_or_errors_when_none_run(): void
    {
        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([], ['composer']);

        $this->assertSame([], $run['findings']);
        $this->assertSame([], $run['errors']);
        $this->assertSame([], $run['projects']);
    }
}
