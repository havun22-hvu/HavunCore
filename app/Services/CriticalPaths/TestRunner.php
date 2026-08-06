<?php

namespace App\Services\CriticalPaths;

use App\Support\Timing\Stopwatch;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs `php artisan test --filter=<name>` for a critical-paths reference,
 * captures pass/fail/duration. Separated from the command so it can be
 * faked in tests.
 */
class TestRunner
{
    public function __construct(private Stopwatch $stopwatch)
    {
    }

    /**
     * Filter is typically the class name derived from the test file path,
     * e.g. `tests/Feature/VaultControllerTest.php` → `VaultControllerTest`.
     *
     * @return array{filter: string, exit_code: int, passed: bool, duration_ms: int, output: string}
     */
    public function run(string $filter): array
    {
        $measurement = $this->stopwatch->start();
        $exitCode = Artisan::call('test', [
            '--filter' => $filter,
            '--no-coverage' => true,
        ]);
        $durationMs = $measurement->elapsedMs();

        return [
            'filter' => $filter,
            'exit_code' => $exitCode,
            'passed' => $exitCode === 0,
            'duration_ms' => $durationMs,
            'output' => (string) Artisan::output(),
        ];
    }

    /**
     * Convert a test-file path into a PHPUnit filter (class-name).
     *
     * `tests/Feature/VaultControllerTest.php` → `VaultControllerTest`.
     */
    public static function filterFromPath(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }
}
