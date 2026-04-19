<?php

namespace Tests\Unit\QualitySafety;

use App\Services\QualitySafety\QualitySafetyScanner;
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

    public function test_composer_audit_parses_advisories_json(): void
    {
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
        $run = $scanner->scan([
            'testproject' => [
                'enabled' => true,
                'path' => $this->tempProject,
                'url' => 'https://example.test',
                'has_composer' => true,
                'has_npm' => false,
            ],
        ], ['composer']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('high', $run['findings'][0]['severity']);
        $this->assertSame('fake/package', $run['findings'][0]['package']);
        $this->assertSame(1, $run['totals']['high']);
    }

    public function test_composer_audit_clean_run_has_no_findings(): void
    {
        Process::fake([
            '*' => Process::result(output: json_encode(['advisories' => []]), exitCode: 0),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'clean' => [
                'enabled' => true,
                'path' => $this->tempProject,
                'url' => 'https://example.test',
                'has_composer' => true,
                'has_npm' => false,
            ],
        ], ['composer']);

        $this->assertEmpty($run['findings']);
        $this->assertSame(0, $run['totals']['high']);
        $this->assertSame(0, $run['totals']['errors']);
    }

    public function test_unparseable_composer_output_registers_error(): void
    {
        Process::fake([
            '*' => Process::result(output: 'garbage not json', exitCode: 1),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'broken' => [
                'enabled' => true,
                'path' => $this->tempProject,
                'url' => 'https://example.test',
                'has_composer' => true,
                'has_npm' => false,
            ],
        ], ['composer']);

        $this->assertSame(1, $run['totals']['errors']);
    }

    public function test_missing_project_path_registers_error(): void
    {
        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'ghost' => [
                'enabled' => true,
                'path' => '/nonexistent/path-' . uniqid(),
                'url' => 'https://example.test',
                'has_composer' => true,
                'has_npm' => false,
            ],
        ], ['composer']);

        $this->assertSame(1, $run['totals']['errors']);
        $this->assertStringContainsString('Project path not found', $run['errors'][0]['message']);
    }

    public function test_npm_audit_without_package_json_is_skipped(): void
    {
        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'nonjs' => [
                'enabled' => true,
                'path' => $this->tempProject,
                'url' => 'https://example.test',
                'has_composer' => false,
                'has_npm' => true,
            ],
        ], ['npm']);

        $this->assertSame(0, $run['totals']['errors']);
        $this->assertEmpty($run['findings']);
    }

    public function test_npm_audit_parses_vulnerabilities(): void
    {
        file_put_contents($this->tempProject . '/package.json', '{"name":"test"}');

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
        $run = $scanner->scan([
            'jsproj' => [
                'enabled' => true,
                'path' => $this->tempProject,
                'url' => 'https://example.test',
                'has_composer' => false,
                'has_npm' => true,
            ],
        ], ['npm']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('critical', $run['findings'][0]['severity']);
        $this->assertSame('bad-pkg', $run['findings'][0]['package']);
    }

    public function test_severity_normalization_maps_moderate_to_medium(): void
    {
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
        $run = $scanner->scan([
            'p' => [
                'enabled' => true,
                'path' => $this->tempProject,
                'url' => 'https://example.test',
                'has_composer' => true,
                'has_npm' => false,
            ],
        ], ['composer']);

        $this->assertSame('medium', $run['findings'][0]['severity']);
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
