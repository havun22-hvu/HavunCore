<?php

namespace Tests\Feature\QualitySafety;

use App\Services\QualitySafety\EcosystemDetector;
use App\Services\QualitySafety\QualitySafetyScanner;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The scanner must never report a clean nil for something it did not measure.
 *
 * Measured on 31-07-2026: qv:scan --project=vusista2 returned critical 0,
 * high 0, medium 0 while not a single Rust crate had been looked at, because
 * the dependency checks were composer and npm only. These tests pin the two
 * halves of the fix — detect the ecosystem, and say so when we cannot audit it.
 */
class EcosystemDetectionTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/eco-test-' . uniqid();
        File::ensureDirectoryExists($this->tmp);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    private function manifest(string $relatief, string $inhoud = '{}'): void
    {
        $pad = $this->tmp . '/' . $relatief;
        File::ensureDirectoryExists(dirname($pad));
        File::put($pad, $inhoud);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function findings(string $check): array
    {
        $run = (new QualitySafetyScanner)->scan(
            ['tmpproj' => ['path' => $this->tmp]],
            [$check]
        );

        return $run['findings'];
    }

    public function test_detects_a_manifest_in_the_project_root(): void
    {
        $this->manifest('composer.json');

        $this->assertSame(
            ['php' => ['composer.json']],
            (new EcosystemDetector)->detect($this->tmp)
        );
    }

    public function test_finds_manifests_nested_below_the_root(): void
    {
        // Vusista2's actual shape: no Cargo.toml at the root, four Cargo.lock
        // files in subdirectories. Root-only detection reports "no Rust" there
        // and every crate goes unaudited.
        $this->manifest('proef-index/Cargo.lock');
        $this->manifest('proef-grid/src-tauri/Cargo.lock');

        $rust = (new EcosystemDetector)->detect($this->tmp)['rust'] ?? [];

        $this->assertCount(2, $rust);
        $this->assertContains('proef-index/Cargo.lock', $rust);
        $this->assertContains('proef-grid/src-tauri/Cargo.lock', $rust);
    }

    public function test_ignores_manifests_belonging_to_dependencies(): void
    {
        // These are the manifests of other people's packages. Counting them
        // would report hundreds of ecosystems that are not this project's.
        $this->manifest('target/debug/deps/Cargo.lock');
        $this->manifest('node_modules/left-pad/package.json');
        $this->manifest('vendor/laravel/framework/composer.json');

        $this->assertSame([], (new EcosystemDetector)->detect($this->tmp));
    }

    public function test_reports_several_ecosystems_in_one_repo(): void
    {
        // A Laravel app with a Vite frontend is two, and that is normal.
        $this->manifest('composer.json');
        $this->manifest('package.json');

        $this->assertSame(
            ['js', 'php'],
            array_keys((new EcosystemDetector)->detect($this->tmp))
        );
    }

    public function test_an_unauditable_ecosystem_is_a_finding_not_a_silent_zero(): void
    {
        $this->manifest('go.mod', 'module example.com/x');

        $findings = $this->findings('deps-coverage');

        $this->assertCount(1, $findings, 'Go is detected but never audited — that must surface');
        $this->assertSame('high', $findings[0]['severity']);
        $this->assertSame('go', $findings[0]['ecosystem']);
        $this->assertStringContainsString('niet gekeken', $findings[0]['message']);
    }

    public function test_stays_quiet_when_every_detected_ecosystem_is_audited(): void
    {
        $this->manifest('composer.json');
        $this->manifest('package.json');
        $this->manifest('Cargo.lock');

        $this->assertSame([], $this->findings('deps-coverage'));
    }

    public function test_reports_each_unauditable_ecosystem_separately(): void
    {
        $this->manifest('go.mod');
        $this->manifest('pyproject.toml');

        $ecosystems = array_column($this->findings('deps-coverage'), 'ecosystem');

        sort($ecosystems);
        $this->assertSame(['go', 'python'], $ecosystems);
    }

    public function test_a_project_without_any_manifest_reports_nothing(): void
    {
        // A docs-only or server-only entry has no dependencies to miss.
        $this->manifest('README.md', '# leeg');

        $this->assertSame([], $this->findings('deps-coverage'));
    }

    public function test_cargo_check_skips_a_project_without_rust(): void
    {
        $this->manifest('composer.json');

        $this->assertSame([], $this->findings('cargo'));
    }

    public function test_the_run_records_how_each_project_is_built(): void
    {
        // The report has to carry this: a zero without it cannot be told apart
        // from a zero because nobody looked.
        $this->manifest('composer.json');
        $this->manifest('proef/Cargo.lock');

        $run = (new QualitySafetyScanner)->scan(
            ['tmpproj' => ['path' => $this->tmp]],
            ['deps-coverage']
        );

        $this->assertSame(['php', 'rust'], $run['ecosystems']['tmpproj']);
    }
}
