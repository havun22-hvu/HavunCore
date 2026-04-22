<?php

namespace Tests\Unit\Enums;

use App\Enums\LogLevel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LogLevelTest extends TestCase
{
    public function test_backed_values_are_stable(): void
    {
        // Match the strings already stored in error_logs.severity.
        $this->assertSame('critical', LogLevel::Critical->value);
        $this->assertSame('error', LogLevel::Error->value);
        $this->assertSame('warning', LogLevel::Warning->value);
    }

    public function test_php_error_maps_to_critical(): void
    {
        $this->assertSame(LogLevel::Critical, LogLevel::fromException(new \TypeError('boom')));
        $this->assertSame(LogLevel::Critical, LogLevel::fromException(new \ParseError('bad syntax')));
    }

    public function test_http_5xx_maps_to_error(): void
    {
        $this->assertSame(LogLevel::Error, LogLevel::fromException(new HttpException(500)));
        $this->assertSame(LogLevel::Error, LogLevel::fromException(new HttpException(503)));
    }

    public function test_http_4xx_maps_to_warning(): void
    {
        $this->assertSame(LogLevel::Warning, LogLevel::fromException(new HttpException(404)));
        $this->assertSame(LogLevel::Warning, LogLevel::fromException(new HttpException(422)));
    }

    public function test_http_boundary_500_is_error_not_warning(): void
    {
        // Hard-pin the >= 500 boundary so an off-by-one mutation
        // (e.g. > 500 instead of >= 500) on the source line is killable.
        $this->assertSame(LogLevel::Error, LogLevel::fromException(new HttpException(500)));
        $this->assertSame(LogLevel::Warning, LogLevel::fromException(new HttpException(499)));
    }

    public function test_generic_exception_falls_back_to_error(): void
    {
        $this->assertSame(LogLevel::Error, LogLevel::fromException(new \RuntimeException('oops')));
        $this->assertSame(LogLevel::Error, LogLevel::fromException(new \Exception('plain')));
    }

    public function test_sort_weight_is_strictly_ascending_by_severity(): void
    {
        $this->assertLessThan(LogLevel::Error->sortWeight(), LogLevel::Critical->sortWeight());
        $this->assertLessThan(LogLevel::Warning->sortWeight(), LogLevel::Error->sortWeight());
    }
}
