<?php

namespace Tests\Unit\CriticalPaths;

use App\Services\CriticalPaths\TestRunner;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TestRunnerTest extends TestCase
{
    public function test_passing_test_returns_success_result(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('test', ['--filter' => 'SomeTest', '--no-coverage' => true])
            ->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn("OK (1 test)\n");

        $result = (new TestRunner)->run('SomeTest');

        $this->assertSame('SomeTest', $result['filter']);
        $this->assertSame(0, $result['exit_code']);
        $this->assertTrue($result['passed']);
        $this->assertIsInt($result['duration_ms']);
        $this->assertStringContainsString('OK', $result['output']);
    }

    public function test_failing_test_returns_failure_result(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->andReturn(1);
        Artisan::shouldReceive('output')->once()->andReturn("Failed: boom\n");

        $result = (new TestRunner)->run('BrokenTest');

        $this->assertSame(1, $result['exit_code']);
        $this->assertFalse($result['passed']);
        $this->assertStringContainsString('Failed', $result['output']);
    }

    public function test_filter_from_path_extracts_class_name(): void
    {
        $this->assertSame(
            'VaultControllerTest',
            TestRunner::filterFromPath('tests/Feature/VaultControllerTest.php')
        );
        $this->assertSame(
            'EncryptionTest',
            TestRunner::filterFromPath('tests/Unit/Vault/EncryptionTest.php')
        );
    }
}
