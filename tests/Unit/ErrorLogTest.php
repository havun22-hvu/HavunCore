<?php

namespace Tests\Unit;

use App\Models\ErrorLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_creates_new_error_log(): void
    {
        $exception = new \RuntimeException('Test error', 0);

        ErrorLog::capture($exception);

        $this->assertDatabaseCount('error_logs', 1);
        $this->assertDatabaseHas('error_logs', [
            'exception_class' => 'RuntimeException',
            'message' => 'Test error',
            'severity' => 'error',
            'occurrence_count' => 1,
        ]);
    }

    public function test_capture_deduplicates_same_error_within_hour(): void
    {
        // Same exception from same file/line will have same fingerprint
        $exception = new \RuntimeException('Test error');

        ErrorLog::capture($exception);
        ErrorLog::capture($exception);
        ErrorLog::capture($exception);

        $this->assertDatabaseCount('error_logs', 1);
        $this->assertDatabaseHas('error_logs', [
            'occurrence_count' => 3,
        ]);
    }

    public function test_capture_creates_new_entry_for_different_errors(): void
    {
        ErrorLog::capture(new \RuntimeException('Error 1'));
        ErrorLog::capture(new \InvalidArgumentException('Error 2'));

        $this->assertDatabaseCount('error_logs', 2);
    }

    public function test_severity_critical_for_php_errors(): void
    {
        $error = new \Error('Fatal error');

        ErrorLog::capture($error);

        $this->assertDatabaseHas('error_logs', [
            'severity' => 'critical',
        ]);
    }

    public function test_severity_warning_for_4xx_http_exceptions(): void
    {
        $exception = new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Not found');

        ErrorLog::capture($exception);

        $this->assertDatabaseHas('error_logs', [
            'severity' => 'warning',
        ]);
    }

    public function test_severity_error_for_5xx_http_exceptions(): void
    {
        $exception = new \Symfony\Component\HttpKernel\Exception\HttpException(500, 'Server error');

        ErrorLog::capture($exception);

        $this->assertDatabaseHas('error_logs', [
            'severity' => 'error',
        ]);
    }

    public function test_capture_truncates_long_traces(): void
    {
        config(['observability.error_trace_max_length' => 100]);

        $exception = new \RuntimeException('Test');

        ErrorLog::capture($exception);

        $log = ErrorLog::first();
        $this->assertLessThanOrEqual(100, strlen($log->trace));
    }

    public function test_capture_strips_sensitive_input_keys(): void
    {
        $request = \Illuminate\Http\Request::create('/test', 'POST', [
            'name' => 'testuser',
            'password' => 'test-value-not-real',
            'token' => 'test-token-value',
            'data' => 'safe',
        ]);

        ErrorLog::capture(new \RuntimeException('test'), $request);

        $log = ErrorLog::first();
        $inputKeys = $log->context['input_keys'] ?? [];

        $this->assertContains('name', $inputKeys);
        $this->assertContains('data', $inputKeys);
        $this->assertNotContains('password', $inputKeys);
        $this->assertNotContains('token', $inputKeys);
    }
}
