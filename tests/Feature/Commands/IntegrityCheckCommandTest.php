<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class IntegrityCheckCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/integrity-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->cleanUp($this->tempDir);
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

    private function writeIntegrity(array $config): void
    {
        file_put_contents(
            $this->tempDir . '/.integrity.json',
            json_encode($config, JSON_PRETTY_PRINT)
        );
    }

    private function writeFile(string $relativePath, string $content): void
    {
        $fullPath = $this->tempDir . '/' . $relativePath;
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($fullPath, $content);
    }

    // ===================================================================
    // No .integrity.json
    // ===================================================================

    public function test_skips_when_no_integrity_file(): void
    {
        $this->artisan('integrity:check', ['--project' => $this->tempDir])
            ->expectsOutputToContain('No .integrity.json found')
            ->assertExitCode(0);
    }

    // ===================================================================
    // Invalid JSON
    // ===================================================================

    public function test_fails_on_invalid_json(): void
    {
        file_put_contents($this->tempDir . '/.integrity.json', '{invalid}');

        $this->artisan('integrity:check', ['--project' => $this->tempDir])
            ->expectsOutputToContain('Invalid JSON')
            ->assertExitCode(1);
    }

    // ===================================================================
    // must_contain — passing
    // ===================================================================

    public function test_must_contain_passes_when_text_found(): void
    {
        $this->writeFile('app.php', '<?php echo "hello world";');
        $this->writeIntegrity([
            'version' => '2.0',
            'project' => 'test',
            'checks' => [
                [
                    'file' => 'app.php',
                    'description' => 'Must contain hello',
                    'must_contain' => ['hello', 'world'],
                ],
            ],
        ]);

        $this->artisan('integrity:check', ['--project' => $this->tempDir])
            ->expectsOutputToContain('OK: app.php (2 checks)')
            ->assertExitCode(0);
    }

    // ===================================================================
    // must_contain — failing
    // ===================================================================

    public function test_must_contain_fails_when_text_missing(): void
    {
        $this->writeFile('app.php', '<?php echo "hello";');
        $this->writeIntegrity([
            'version' => '2.0',
            'project' => 'test',
            'checks' => [
                [
                    'file' => 'app.php',
                    'description' => 'Must contain goodbye',
                    'must_contain' => ['goodbye'],
                ],
            ],
        ]);

        $this->artisan('integrity:check', ['--project' => $this->tempDir])
            ->expectsOutputToContain('FAIL: app.php')
            ->expectsOutputToContain('text: goodbye')
            ->assertExitCode(1);
    }

    // ===================================================================
    // File not found
    // ===================================================================

    public function test_fails_when_file_not_found(): void
    {
        $this->writeIntegrity([
            'version' => '2.0',
            'project' => 'test',
            'checks' => [
                [
                    'file' => 'nonexistent.php',
                    'description' => 'Missing file',
                    'must_contain' => ['anything'],
                ],
            ],
        ]);

        $this->artisan('integrity:check', ['--project' => $this->tempDir])
            ->expectsOutputToContain('FILE NOT FOUND')
            ->assertExitCode(1);
    }

    // ===================================================================
    // must_contain_selector — #id
    // ===================================================================

    public function test_selector_id_passes(): void
    {
        $this->writeFile('view.html', '<div id="cookie-banner">Cookies</div>');
        $this->writeIntegrity([
            'version' => '2.0',
            'project' => 'test',
            'checks' => [
                [
                    'file' => 'view.html',
                    'description' => 'Cookie banner must exist',
                    'must_contain_selector' => ['#cookie-banner'],
                ],
            ],
        ]);

        $this->artisan('integrity:check', ['--project' => $this->tempDir])
            ->expectsOutputToContain('OK: view.html')
            ->assertExitCode(0);
    }

    public function test_selector_id_fails(): void
    {
        $this->writeFile('view.html', '<div>No banner here</div>');
        $this->writeIntegrity([
            'version' => '2.0',
            'project' => 'test',
            'checks' => [
                [
                    'file' => 'view.html',
                    'description' => 'Cookie banner must exist',
                    'must_contain_selector' => ['#cookie-banner'],
                ],
            ],
        ]);

        $this->artisan('integrity:check', ['--project' => $this->tempDir])
            ->expectsOutputToContain('selector: #cookie-banner')
            ->assertExitCode(1);
    }

    // ===================================================================
    // must_contain_selector — .class
    // ===================================================================

    public function test_selector_class_passes(): void
    {
        $this->writeFile('view.html', '<nav class="main-navigation top-bar">Links</nav>');
        $this->writeIntegrity([
            'version' => '2.0',
            'project' => 'test',
            'checks' => [
                [
                    'file' => 'view.html',
                    'description' => 'Nav must exist',
                    'must_contain_selector' => ['.main-navigation'],
                ],
            ],
        ]);

        $this->artisan('integrity:check', ['--project' => $this->tempDir])
            ->expectsOutputToContain('OK: view.html')
            ->assertExitCode(0);
    }

    // ===================================================================
    // must_contain_selector — tag.class
    // ===================================================================

    public function test_selector_tag_class_passes(): void
    {
        $this->writeFile('view.html', '<footer class="site-footer dark">Content</footer>');
        $this->writeIntegrity([
            'version' => '2.0',
            'project' => 'test',
            'checks' => [
                [
                    'file' => 'view.html',
                    'description' => 'Footer must exist',
                    'must_contain_selector' => ['footer.site-footer'],
                ],
            ],
        ]);

        $this->artisan('integrity:check', ['--project' => $this->tempDir])
            ->expectsOutputToContain('OK: view.html')
            ->assertExitCode(0);
    }

    public function test_selector_tag_class_fails_wrong_tag(): void
    {
        $this->writeFile('view.html', '<div class="site-footer">Not a footer tag</div>');
        $this->writeIntegrity([
            'version' => '2.0',
            'project' => 'test',
            'checks' => [
                [
                    'file' => 'view.html',
                    'description' => 'Footer tag must be footer',
                    'must_contain_selector' => ['footer.site-footer'],
                ],
            ],
        ]);

        $this->artisan('integrity:check', ['--project' => $this->tempDir])
            ->expectsOutputToContain('selector: footer.site-footer')
            ->assertExitCode(1);
    }

    // ===================================================================
    // must_contain_selector — [attribute="value"]
    // ===================================================================

    public function test_selector_attribute_value_passes(): void
    {
        $this->writeFile('view.html', '<img data-testid="logo" src="logo.png" />');
        $this->writeIntegrity([
            'version' => '2.0',
            'project' => 'test',
            'checks' => [
                [
                    'file' => 'view.html',
                    'description' => 'Logo must exist',
                    'must_contain_selector' => ['[data-testid="logo"]'],
                ],
            ],
        ]);

        $this->artisan('integrity:check', ['--project' => $this->tempDir])
            ->expectsOutputToContain('OK: view.html')
            ->assertExitCode(0);
    }

    // ===================================================================
    // must_contain_selector — bare tag
    // ===================================================================

    public function test_selector_bare_tag_passes(): void
    {
        $this->writeFile('view.html', '<nav>Links</nav>');
        $this->writeIntegrity([
            'version' => '2.0',
            'project' => 'test',
            'checks' => [
                [
                    'file' => 'view.html',
                    'description' => 'Nav tag must exist',
                    'must_contain_selector' => ['nav'],
                ],
            ],
        ]);

        $this->artisan('integrity:check', ['--project' => $this->tempDir])
            ->expectsOutputToContain('OK: view.html')
            ->assertExitCode(0);
    }

    // ===================================================================
    // must_contain_route
    // ===================================================================

    public function test_must_contain_route_passes_for_existing_route(): void
    {
        Route::get('/test-integrity-route', fn () => 'ok')->name('test.integrity');
        Route::getRoutes()->refreshNameLookups();

        $this->writeFile('routes.php', 'placeholder');
        $this->writeIntegrity([
            'version' => '2.0',
            'project' => 'test',
            'checks' => [
                [
                    'file' => 'routes.php',
                    'description' => 'Route must exist',
                    'must_contain_route' => ['test.integrity'],
                ],
            ],
        ]);

        $this->artisan('integrity:check', ['--project' => $this->tempDir])
            ->expectsOutputToContain('OK: routes.php')
            ->assertExitCode(0);
    }

    public function test_must_contain_route_fails_for_missing_route(): void
    {
        $this->writeFile('routes.php', 'placeholder');
        $this->writeIntegrity([
            'version' => '2.0',
            'project' => 'test',
            'checks' => [
                [
                    'file' => 'routes.php',
                    'description' => 'Route must exist',
                    'must_contain_route' => ['nonexistent.route.xyz'],
                ],
            ],
        ]);

        $this->artisan('integrity:check', ['--project' => $this->tempDir])
            ->expectsOutputToContain('route: nonexistent.route.xyz')
            ->assertExitCode(1);
    }

    // ===================================================================
    // --json output
    // ===================================================================

    public function test_json_flag_is_accepted(): void
    {
        $this->writeFile('app.php', 'hello');
        $this->writeIntegrity([
            'version' => '2.0',
            'project' => 'jsontest',
            'checks' => [
                [
                    'file' => 'app.php',
                    'description' => 'Test',
                    'must_contain' => ['hello'],
                ],
            ],
        ]);

        $this->artisan('integrity:check', ['--project' => $this->tempDir, '--json' => true])
            ->assertExitCode(0);
    }

    // ===================================================================
    // Mixed pass and fail
    // ===================================================================

    public function test_mixed_pass_and_fail_returns_failure(): void
    {
        $this->writeFile('good.php', 'expected content');
        $this->writeIntegrity([
            'version' => '2.0',
            'project' => 'test',
            'checks' => [
                [
                    'file' => 'good.php',
                    'description' => 'Should pass',
                    'must_contain' => ['expected'],
                ],
                [
                    'file' => 'missing.php',
                    'description' => 'Should fail',
                    'must_contain' => ['anything'],
                ],
            ],
        ]);

        $this->artisan('integrity:check', ['--project' => $this->tempDir])
            ->expectsOutputToContain('1 passed, 1 failed')
            ->assertExitCode(1);
    }

    // ===================================================================
    // Default project (base_path)
    // ===================================================================

    public function test_defaults_to_base_path(): void
    {
        $this->artisan('integrity:check')
            ->expectsOutputToContain('Integrity check: havuncore')
            ->assertExitCode(0);
    }
}
