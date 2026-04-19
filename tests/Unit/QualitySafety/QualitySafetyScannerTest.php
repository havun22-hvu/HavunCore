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

    public function test_server_check_skips_projects_without_host(): void
    {
        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['nohost' => $this->project()], ['server']);

        $this->assertSame(0, $run['totals']['errors']);
        $this->assertEmpty($run['findings']);
    }

    public function test_server_check_reports_critical_disk_above_critical_threshold(): void
    {
        config()->set('quality-safety.thresholds.disk_warning_pct', 90);
        config()->set('quality-safety.thresholds.disk_critical_pct', 95);
        config()->set('quality-safety.server.disk_ignore_mounts', ['/dev', '/proc', '/sys', '/run']);

        Process::fake([
            '*' => Process::result(
                output: $this->fakeServerOutput(
                    df: <<<'DF'
Filesystem     1024-blocks      Used Available Capacity Mounted on
/dev/sda1       100000000  96000000   4000000      96% /
tmpfs            8000000          0   8000000       0% /run
DF,
                    systemd: '',
                ),
                exitCode: 0,
            ),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'srv' => ['enabled' => true, 'host' => '1.2.3.4', 'user' => 'root'],
        ], ['server']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('critical', $run['findings'][0]['severity']);
        $this->assertSame('/', $run['findings'][0]['mount']);
        $this->assertSame(96, $run['findings'][0]['usage_pct']);
    }

    public function test_server_check_reports_high_disk_between_warn_and_critical(): void
    {
        config()->set('quality-safety.thresholds.disk_warning_pct', 90);
        config()->set('quality-safety.thresholds.disk_critical_pct', 95);
        config()->set('quality-safety.server.disk_ignore_mounts', []);

        Process::fake([
            '*' => Process::result(
                output: $this->fakeServerOutput(
                    df: <<<'DF'
Filesystem     1024-blocks      Used Available Capacity Mounted on
/dev/sda1       100000000  91000000   9000000      91% /
DF,
                    systemd: '',
                ),
                exitCode: 0,
            ),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'srv' => ['enabled' => true, 'host' => '1.2.3.4'],
        ], ['server']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('high', $run['findings'][0]['severity']);
    }

    public function test_server_check_ignores_disks_below_warning_threshold(): void
    {
        config()->set('quality-safety.thresholds.disk_warning_pct', 90);
        config()->set('quality-safety.thresholds.disk_critical_pct', 95);
        config()->set('quality-safety.server.disk_ignore_mounts', []);

        Process::fake([
            '*' => Process::result(
                output: $this->fakeServerOutput(
                    df: <<<'DF'
Filesystem     1024-blocks      Used Available Capacity Mounted on
/dev/sda1       100000000  50000000  50000000      50% /
DF,
                    systemd: '',
                ),
                exitCode: 0,
            ),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'srv' => ['enabled' => true, 'host' => '1.2.3.4'],
        ], ['server']);

        $this->assertEmpty($run['findings']);
    }

    public function test_server_check_skips_ignored_mount_prefixes(): void
    {
        config()->set('quality-safety.thresholds.disk_warning_pct', 90);
        config()->set('quality-safety.thresholds.disk_critical_pct', 95);
        config()->set('quality-safety.server.disk_ignore_mounts', ['/snap', '/run']);

        Process::fake([
            '*' => Process::result(
                output: $this->fakeServerOutput(
                    df: <<<'DF'
Filesystem     1024-blocks      Used Available Capacity Mounted on
/dev/loop0       50000000  49000000   1000000      99% /snap/core/123
tmpfs            8000000   7900000     100000      99% /run/lock
DF,
                    systemd: '',
                ),
                exitCode: 0,
            ),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'srv' => ['enabled' => true, 'host' => '1.2.3.4'],
        ], ['server']);

        $this->assertEmpty($run['findings'], 'Snap loops and tmpfs should be ignored even at 99%.');
    }

    public function test_server_check_reports_failed_systemd_units(): void
    {
        Process::fake([
            '*' => Process::result(
                output: $this->fakeServerOutput(
                    df: <<<'DF'
Filesystem     1024-blocks      Used Available Capacity Mounted on
/dev/sda1       100000000  10000000  90000000      10% /
DF,
                    systemd: <<<'UNITS'
nginx.service                 loaded failed failed A high performance web server
worker.service                loaded failed failed App worker
UNITS,
                ),
                exitCode: 0,
            ),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'srv' => ['enabled' => true, 'host' => '1.2.3.4'],
        ], ['server']);

        $units = array_column($run['findings'], 'unit');
        $this->assertContains('nginx.service', $units);
        $this->assertContains('worker.service', $units);
        $this->assertSame(2, $run['totals']['high']);
    }

    public function test_server_check_records_error_when_ssh_fails(): void
    {
        Process::fake([
            '*' => Process::result(
                output: '',
                errorOutput: 'Permission denied (publickey).',
                exitCode: 255,
            ),
        ]);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'srv' => ['enabled' => true, 'host' => '1.2.3.4'],
        ], ['server']);

        $this->assertSame(1, $run['totals']['errors']);
        $this->assertStringContainsString('SSH to 1.2.3.4 failed', $run['errors'][0]['message']);
        $this->assertStringContainsString('Permission denied', $run['errors'][0]['message']);
    }

    private function fakeServerOutput(string $df, string $systemd): string
    {
        return $df . "\n---SYSTEMD---\n" . $systemd . "\n";
    }

    public function test_forms_check_skips_non_laravel_project(): void
    {
        // No artisan file → not Laravel → skip
        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['nonlaravel' => $this->project()], ['forms']);

        $this->assertEmpty($run['findings']);
        $this->assertSame(0, $run['totals']['errors']);
    }

    public function test_forms_check_skips_when_no_write_routes(): void
    {
        $this->buildLaravelSkeleton(writeRoutes: 0, formRequests: 0, inlineValidates: 0);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['empty' => $this->project()], ['forms']);

        $this->assertEmpty($run['findings']);
    }

    public function test_forms_check_clean_when_above_warning(): void
    {
        config()->set('quality-safety.thresholds.forms_warning_pct', 60);
        config()->set('quality-safety.thresholds.forms_critical_pct', 30);

        // 10 routes, 8 FormRequests = 80%
        $this->buildLaravelSkeleton(writeRoutes: 10, formRequests: 8, inlineValidates: 0);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['good' => $this->project()], ['forms']);

        $this->assertEmpty($run['findings']);
    }

    public function test_forms_check_high_when_below_warning(): void
    {
        config()->set('quality-safety.thresholds.forms_warning_pct', 60);
        config()->set('quality-safety.thresholds.forms_critical_pct', 30);

        // 10 routes, 4 FormRequests = 40% → high
        $this->buildLaravelSkeleton(writeRoutes: 10, formRequests: 4, inlineValidates: 0);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['weak' => $this->project()], ['forms']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('high', $run['findings'][0]['severity']);
        $this->assertSame(40, $run['findings'][0]['coverage_pct']);
    }

    public function test_forms_check_critical_when_below_critical_threshold(): void
    {
        config()->set('quality-safety.thresholds.forms_warning_pct', 60);
        config()->set('quality-safety.thresholds.forms_critical_pct', 30);

        // 10 routes, 2 FormRequests = 20% → critical
        $this->buildLaravelSkeleton(writeRoutes: 10, formRequests: 2, inlineValidates: 0);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['bad' => $this->project()], ['forms']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('critical', $run['findings'][0]['severity']);
    }

    public function test_forms_check_counts_inline_validates_as_coverage(): void
    {
        config()->set('quality-safety.thresholds.forms_warning_pct', 60);
        config()->set('quality-safety.thresholds.forms_critical_pct', 30);

        // 10 routes, 0 FormRequests + 7 inline ::validate = 70% → clean
        $this->buildLaravelSkeleton(writeRoutes: 10, formRequests: 0, inlineValidates: 7);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['inline' => $this->project()], ['forms']);

        $this->assertEmpty($run['findings']);
    }

    public function test_forms_check_skips_vendor_directory(): void
    {
        config()->set('quality-safety.thresholds.forms_warning_pct', 60);
        config()->set('quality-safety.thresholds.forms_critical_pct', 30);

        $this->buildLaravelSkeleton(writeRoutes: 10, formRequests: 1, inlineValidates: 0);

        // Plant 100 fake FormRequest classes inside vendor/ — must be ignored.
        mkdir($this->tempProject . '/app/vendor/fake', 0755, true);
        for ($i = 0; $i < 100; $i++) {
            file_put_contents(
                $this->tempProject . "/app/vendor/fake/Req{$i}.php",
                "<?php\nclass R{$i} extends FormRequest {}\n"
            );
        }

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['vendortest' => $this->project()], ['forms']);

        // Only 1 real FormRequest → 10% coverage → critical
        $this->assertCount(1, $run['findings']);
        $this->assertSame('critical', $run['findings'][0]['severity']);
        $this->assertSame(10, $run['findings'][0]['coverage_pct']);
    }

    private function buildLaravelSkeleton(int $writeRoutes, int $formRequests, int $inlineValidates): void
    {
        // Marker
        file_put_contents($this->tempProject . '/artisan', "#!/usr/bin/env php\n");

        // Routes file with N write-routes
        mkdir($this->tempProject . '/routes', 0755, true);
        $routesPhp = "<?php\n";
        for ($i = 0; $i < $writeRoutes; $i++) {
            $verb = ['post', 'put', 'patch', 'delete'][$i % 4];
            $routesPhp .= "Route::{$verb}('/r{$i}', fn () => null);\n";
        }
        file_put_contents($this->tempProject . '/routes/web.php', $routesPhp);

        // FormRequests
        mkdir($this->tempProject . '/app/Http/Requests', 0755, true);
        for ($i = 0; $i < $formRequests; $i++) {
            file_put_contents(
                $this->tempProject . "/app/Http/Requests/Req{$i}.php",
                "<?php\nclass Req{$i} extends FormRequest {}\n"
            );
        }

        // Inline validates
        mkdir($this->tempProject . '/app/Http/Controllers', 0755, true);
        if ($inlineValidates > 0) {
            $controller = "<?php\nclass C {\n";
            for ($i = 0; $i < $inlineValidates; $i++) {
                $controller .= "    public function m{$i}(\$r) { \$r->validate([]); }\n";
            }
            $controller .= "}\n";
            file_put_contents($this->tempProject . '/app/Http/Controllers/C.php', $controller);
        }
    }
}
