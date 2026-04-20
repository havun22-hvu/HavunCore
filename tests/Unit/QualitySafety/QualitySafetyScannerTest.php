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

    public function test_composer_silently_skips_server_only_entries(): void
    {
        // Server-only entries (no path, only host) must not raise composer
        // errors — they exist for the `server` check, not for code audits.
        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'server-prod' => ['enabled' => true, 'host' => '1.2.3.4', 'user' => 'root'],
        ], ['composer']);

        $this->assertSame(0, $run['totals']['errors']);
        $this->assertEmpty($run['findings']);
    }

    public function test_npm_silently_skips_server_only_entries(): void
    {
        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan([
            'server-prod' => ['enabled' => true, 'host' => '1.2.3.4', 'user' => 'root'],
        ], ['npm']);

        $this->assertSame(0, $run['totals']['errors']);
        $this->assertEmpty($run['findings']);
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

    public function test_observatory_sends_host_as_querystring_not_json_body(): void
    {
        // Mozilla Observatory v2 API requires `host` in the querystring.
        // Sending it as a JSON body triggers HTTP 400 "querystring must have required property 'host'".
        Http::fake([
            '*' => Http::response(['grade' => 'A', 'score' => 100]),
        ]);

        $scanner = new QualitySafetyScanner;
        $scanner->scan([
            'good' => ['enabled' => true, 'url' => 'https://example.havun.nl'],
        ], ['observatory']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'host=example.havun.nl'));
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

    public function test_ratelimit_check_skips_non_laravel_project(): void
    {
        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['nonlaravel' => $this->project()], ['ratelimit']);

        $this->assertEmpty($run['findings']);
        $this->assertSame(0, $run['totals']['errors']);
    }

    public function test_ratelimit_check_skips_when_no_write_routes(): void
    {
        $this->buildLaravelSkeleton(writeRoutes: 0, formRequests: 0, inlineValidates: 0);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['empty' => $this->project()], ['ratelimit']);

        $this->assertEmpty($run['findings']);
    }

    public function test_ratelimit_check_high_when_no_throttle_or_ratelimiter(): void
    {
        $this->buildLaravelSkeleton(writeRoutes: 5, formRequests: 0, inlineValidates: 0);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['unsafe' => $this->project()], ['ratelimit']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('high', $run['findings'][0]['severity']);
        $this->assertSame(5, $run['findings'][0]['write_routes']);
    }

    public function test_ratelimit_check_clean_when_throttle_middleware_present(): void
    {
        $this->buildLaravelSkeleton(writeRoutes: 5, formRequests: 0, inlineValidates: 0);
        // Add a throttled route on top
        file_put_contents(
            $this->tempProject . '/routes/web.php',
            "<?php\nRoute::post('/login', fn() => null)->middleware('throttle:5,1');\n"
        );

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['safe' => $this->project()], ['ratelimit']);

        $this->assertEmpty($run['findings']);
    }

    public function test_ratelimit_check_clean_when_ratelimiter_for_in_provider(): void
    {
        $this->buildLaravelSkeleton(writeRoutes: 5, formRequests: 0, inlineValidates: 0);
        mkdir($this->tempProject . '/app/Providers', 0755, true);
        file_put_contents(
            $this->tempProject . '/app/Providers/RouteServiceProvider.php',
            "<?php\nRateLimiter::for('api', fn() => null);\n"
        );

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['safe2' => $this->project()], ['ratelimit']);

        $this->assertEmpty($run['findings']);
    }

    public function test_secrets_check_returns_no_findings_when_clean(): void
    {
        // Plain code with no secret-shaped strings
        file_put_contents($this->tempProject . '/clean.php', "<?php\n\$x = 'hello world';\n");

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['clean' => $this->project()], ['secrets']);

        $this->assertEmpty($run['findings']);
        $this->assertSame(0, $run['totals']['errors']);
    }

    public function test_secrets_check_detects_anthropic_api_key(): void
    {
        file_put_contents(
            $this->tempProject . '/leak.php',
            "<?php\n\$key = 'sk-ant-" . str_repeat('a1B2c3D4e5', 6) . "';\n"
        );

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['leak' => $this->project()], ['secrets']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('critical', $run['findings'][0]['severity']);
        $this->assertSame('anthropic', $run['findings'][0]['kind']);
    }

    public function test_secrets_check_detects_aws_access_key(): void
    {
        file_put_contents(
            $this->tempProject . '/aws.php',
            "<?php\n\$creds = ['key' => 'AKIAIOSFODNN7EXAMPLE'];\n"
        );

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['aws' => $this->project()], ['secrets']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('aws-access-key', $run['findings'][0]['kind']);
    }

    public function test_secrets_check_skips_tests_directory(): void
    {
        mkdir($this->tempProject . '/tests', 0755, true);
        file_put_contents(
            $this->tempProject . '/tests/FixtureTest.php',
            "<?php\n\$dummy = 'sk-ant-" . str_repeat('xyz12345', 8) . "';\n"
        );

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['fixture' => $this->project()], ['secrets']);

        $this->assertEmpty($run['findings'], 'Test fixtures must be ignored.');
    }

    public function test_secrets_check_skips_env_files(): void
    {
        $key = 'sk-ant-' . str_repeat('a1B2c3D4e5', 6);
        file_put_contents($this->tempProject . '/.env', "ANTHROPIC_API_KEY={$key}\n");
        file_put_contents($this->tempProject . '/.env.production', "ANTHROPIC_API_KEY={$key}\n");
        file_put_contents($this->tempProject . '/.env.example', "ANTHROPIC_API_KEY=placeholder\n");

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['env' => $this->project()], ['secrets']);

        $this->assertEmpty($run['findings'], '.env* files contain real secrets by design — not a code-leak.');
    }

    public function test_secrets_check_masks_credential_in_output(): void
    {
        $secret = 'sk-ant-' . str_repeat('a1B2c3D4e5', 6);
        file_put_contents($this->tempProject . '/leak.php', "<?php\n\$k = '{$secret}';\n");

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['m' => $this->project()], ['secrets']);

        $masked = $run['findings'][0]['masked'];
        $this->assertStringNotContainsString('a1B2c3D4', substr($masked, 8, -4),
            'Middle of the secret must be masked.');
        $this->assertStringStartsWith(substr($secret, 0, 8), $masked);
        $this->assertStringEndsWith(substr($secret, -4), $masked);
    }

    public function test_secrets_check_detects_openai_project_key(): void
    {
        file_put_contents(
            $this->tempProject . '/openai.php',
            "<?php\n\$key = 'sk-proj-" . str_repeat('AbCdEf12_', 5) . "';\n"
        );

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['oa' => $this->project()], ['secrets']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('openai', $run['findings'][0]['kind']);
    }

    public function test_secrets_check_detects_sentry_dsn(): void
    {
        file_put_contents(
            $this->tempProject . '/sentry.php',
            "<?php\n\$dsn = 'https://" . str_repeat('a', 32) . "@o12345.ingest.sentry.io/678';\n"
        );

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['s' => $this->project()], ['secrets']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('sentry-dsn', $run['findings'][0]['kind']);
    }

    public function test_secrets_check_detects_digitalocean_token(): void
    {
        file_put_contents(
            $this->tempProject . '/do.php',
            "<?php\n\$tok = 'dop_v1_" . str_repeat('a', 64) . "';\n"
        );

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['do' => $this->project()], ['secrets']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('digitalocean', $run['findings'][0]['kind']);
    }

    public function test_secrets_check_skips_non_text_extensions(): void
    {
        file_put_contents(
            $this->tempProject . '/binary.png',
            "AKIAIOSFODNN7EXAMPLE"
        );

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['bin' => $this->project()], ['secrets']);

        $this->assertEmpty($run['findings']);
    }

    public function test_session_cookies_check_skips_non_laravel_project(): void
    {
        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['nonlaravel' => $this->project()], ['session-cookies']);

        $this->assertEmpty($run['findings']);
        $this->assertSame(0, $run['totals']['errors']);
    }

    public function test_session_cookies_check_skips_when_no_session_config(): void
    {
        // Laravel skeleton without config/session.php
        file_put_contents($this->tempProject . '/artisan', "#!/usr/bin/env php\n");
        mkdir($this->tempProject . '/routes', 0755, true);
        file_put_contents($this->tempProject . '/routes/web.php', '<?php');

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['no-session' => $this->project()], ['session-cookies']);

        $this->assertEmpty($run['findings']);
    }

    public function test_session_cookies_check_clean_when_all_flags_secure(): void
    {
        $this->buildSessionConfig(secure: true, httpOnly: true, sameSite: 'lax');

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['secure' => $this->project()], ['session-cookies']);

        $this->assertEmpty($run['findings']);
    }

    public function test_session_cookies_check_high_when_secure_default_is_null(): void
    {
        $this->buildSessionConfig(secureRaw: "env('SESSION_SECURE_COOKIE', null)", httpOnly: true, sameSite: 'lax');

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['unsafe' => $this->project()], ['session-cookies']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('high', $run['findings'][0]['severity']);
        $this->assertContains('secure cookie flag default is not true (env fallback null/false)', $run['findings'][0]['issues']);
    }

    public function test_session_cookies_check_high_when_same_site_is_none(): void
    {
        $this->buildSessionConfig(secure: true, httpOnly: true, sameSite: 'none');

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['none' => $this->project()], ['session-cookies']);

        $this->assertCount(1, $run['findings']);
        $this->assertContains('same_site is not strict/lax (CSRF risk)', $run['findings'][0]['issues']);
    }

    public function test_session_cookies_check_lists_multiple_issues(): void
    {
        $this->buildSessionConfig(secureRaw: "false", httpOnlyRaw: "false", sameSite: 'none');

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['triple' => $this->project()], ['session-cookies']);

        $this->assertCount(1, $run['findings']);
        $this->assertCount(3, $run['findings'][0]['issues']);
    }

    public function test_debug_mode_check_skips_non_laravel(): void
    {
        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['nonlaravel' => $this->project()], ['debug-mode']);

        $this->assertEmpty($run['findings']);
    }

    public function test_debug_mode_check_skips_when_no_app_config(): void
    {
        file_put_contents($this->tempProject . '/artisan', "#!/usr/bin/env php\n");
        mkdir($this->tempProject . '/routes', 0755, true);
        file_put_contents($this->tempProject . '/routes/web.php', '<?php');

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['noconfig' => $this->project()], ['debug-mode']);

        $this->assertEmpty($run['findings']);
    }

    public function test_debug_mode_check_clean_when_default_is_false(): void
    {
        $this->buildAppConfig("'debug' => env('APP_DEBUG', false)");

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['safe' => $this->project()], ['debug-mode']);

        $this->assertEmpty($run['findings']);
    }

    public function test_debug_mode_check_critical_when_default_is_true(): void
    {
        $this->buildAppConfig("'debug' => env('APP_DEBUG', true)");

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['unsafe' => $this->project()], ['debug-mode']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('critical', $run['findings'][0]['severity']);
    }

    public function test_debug_mode_check_clean_with_bool_cast(): void
    {
        $this->buildAppConfig("'debug' => (bool) env('APP_DEBUG', false)");

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['cast' => $this->project()], ['debug-mode']);

        $this->assertEmpty($run['findings']);
    }

    private function buildAppConfig(string $debugLine): void
    {
        file_put_contents($this->tempProject . '/artisan', "#!/usr/bin/env php\n");
        mkdir($this->tempProject . '/routes', 0755, true);
        file_put_contents($this->tempProject . '/routes/web.php', '<?php');
        mkdir($this->tempProject . '/config', 0755, true);
        file_put_contents(
            $this->tempProject . '/config/app.php',
            "<?php\nreturn [\n    'name' => 'Test',\n    {$debugLine},\n];\n"
        );
    }

    public function test_test_erosion_check_skips_when_no_tests_dir(): void
    {
        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['no-tests' => $this->project()], ['test-erosion']);

        $this->assertEmpty($run['findings']);
    }

    public function test_test_erosion_check_clean_when_no_skipped_tests(): void
    {
        config()->set('quality-safety.thresholds.test_skip_max', 5);
        mkdir($this->tempProject . '/tests', 0755, true);
        file_put_contents($this->tempProject . '/tests/Foo.php', "<?php\nclass Foo {}\n");

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['clean' => $this->project()], ['test-erosion']);

        $this->assertEmpty($run['findings']);
    }

    public function test_test_erosion_check_high_when_many_unconditional_skipped(): void
    {
        config()->set('quality-safety.thresholds.test_skip_max', 2);
        mkdir($this->tempProject . '/tests', 0755, true);
        $body = "<?php\n";
        for ($i = 0; $i < 5; $i++) {
            $body .= "function t{$i}() { \$this->markTestSkipped('reason'); }\n";
        }
        file_put_contents($this->tempProject . '/tests/SkipTest.php', $body);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['skipped' => $this->project()], ['test-erosion']);

        $this->assertCount(1, $run['findings']);
        $this->assertSame('high', $run['findings'][0]['severity']);
        $this->assertSame(5, $run['findings'][0]['unconditional_skips']);
        $this->assertSame(0, $run['findings'][0]['defensive_skips']);
    }

    public function test_test_erosion_check_separates_defensive_from_unconditional_skips(): void
    {
        config()->set('quality-safety.thresholds.test_skip_max', 1);
        mkdir($this->tempProject . '/tests', 0755, true);

        // 1 unconditional + 2 defensive (else-branch is unreachable when file
        // exists, so they're cosmetic noise, not silent disabling).
        $body = <<<'PHP'
<?php
class FooTest {
    public function test_skip_unconditional() {
        $this->markTestSkipped('really skipped');
    }
    public function test_defensive_one() {
        if (extension_loaded('imagick')) {
            $this->assertTrue(true);
        } else {
            $this->markTestSkipped('imagick missing');
        }
    }
    public function test_defensive_two() {
        if (file_exists('/tmp/x')) {
            $this->assertTrue(true);
        } else {
            $this->markTestSkipped('file missing');
        }
    }
}
PHP;
        file_put_contents($this->tempProject . '/tests/MixedTest.php', $body);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['mixed' => $this->project()], ['test-erosion']);

        // Only the unconditional one counts toward the threshold (1 > 1 is false).
        $this->assertEmpty($run['findings'], 'Defensive skips behind else-branches must not push us over threshold.');
    }

    public function test_test_erosion_check_does_not_flag_incomplete_below_threshold(): void
    {
        config()->set('quality-safety.thresholds.test_skip_max', 5);
        mkdir($this->tempProject . '/tests', 0755, true);
        $body = "<?php\n";
        for ($i = 0; $i < 3; $i++) {
            $body .= "function t{$i}() { \$this->markTestIncomplete('TODO'); }\n";
        }
        file_put_contents($this->tempProject . '/tests/IncompleteTest.php', $body);

        $scanner = new QualitySafetyScanner;
        $run = $scanner->scan(['wip' => $this->project()], ['test-erosion']);

        $this->assertEmpty($run['findings'], 'markTestIncomplete is visible WIP, not erosion.');
    }

    private function buildSessionConfig(
        bool|string|null $secure = null,
        bool|string|null $httpOnly = null,
        string $sameSite = 'lax',
        ?string $secureRaw = null,
        ?string $httpOnlyRaw = null,
    ): void {
        file_put_contents($this->tempProject . '/artisan', "#!/usr/bin/env php\n");
        mkdir($this->tempProject . '/routes', 0755, true);
        file_put_contents($this->tempProject . '/routes/web.php', '<?php');
        mkdir($this->tempProject . '/config', 0755, true);

        $secureValue = $secureRaw ?? ($secure ? 'true' : 'false');
        $httpOnlyValue = $httpOnlyRaw ?? ($httpOnly ? 'true' : 'false');

        $php = <<<PHP
<?php
return [
    'driver' => 'file',
    'lifetime' => 120,
    'secure' => {$secureValue},
    'http_only' => {$httpOnlyValue},
    'same_site' => '{$sameSite}',
];
PHP;
        file_put_contents($this->tempProject . '/config/session.php', $php);
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
