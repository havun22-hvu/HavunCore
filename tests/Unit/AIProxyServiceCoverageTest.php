<?php

namespace Tests\Unit;

use App\Models\AIUsageLog;
use App\Services\AIProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AIProxyServiceCoverageTest extends TestCase
{
    use RefreshDatabase;

    private AIProxyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.claude.api_key' => 'test-key',
            'services.claude.model' => 'claude-3-haiku-20240307',
        ]);

        $this->service = new AIProxyService();
    }

    // ===================================================================
    // getDefaultSystemPrompt — all tenant branches
    // ===================================================================

    public function test_default_system_prompt_infosyst(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'OK']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->service->chat('infosyst', 'Test');

        Http::assertSent(function ($request) {
            $system = $request->data()['system'] ?? '';
            return str_contains($system, 'Infosyst') && str_contains($system, 'objectief');
        });
    }

    public function test_default_system_prompt_herdenkingsportaal(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'OK']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->service->chat('herdenkingsportaal', 'Test');

        Http::assertSent(function ($request) {
            $system = $request->data()['system'] ?? '';
            return str_contains($system, 'Herdenkingsportaal') && str_contains($system, 'empathisch');
        });
    }

    public function test_default_system_prompt_havunadmin(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'OK']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->service->chat('havunadmin', 'Test');

        Http::assertSent(function ($request) {
            $system = $request->data()['system'] ?? '';
            return str_contains($system, 'HavunAdmin') && str_contains($system, 'facturatie');
        });
    }

    public function test_default_system_prompt_havuncore(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'OK']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->service->chat('havuncore', 'Test');

        Http::assertSent(function ($request) {
            $system = $request->data()['system'] ?? '';
            return str_contains($system, 'HavunCore') && str_contains($system, 'Task Queue');
        });
    }

    public function test_default_system_prompt_unknown_tenant(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'OK']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->service->chat('unknown_tenant', 'Test');

        Http::assertSent(function ($request) {
            $system = $request->data()['system'] ?? '';
            return str_contains($system, 'Nederlands');
        });
    }

    // ===================================================================
    // logUsage — error handling branch
    // ===================================================================

    public function test_log_usage_handles_database_error_gracefully(): void
    {
        // Use a config that causes DB write to fail
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'OK']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'Failed to log usage');
            });

        // Temporarily break the DB table to trigger the catch block
        // Drop the ai_usage_logs table
        \Illuminate\Support\Facades\Schema::dropIfExists('ai_usage_logs');

        // This should still succeed (not throw), logging error gracefully
        $result = $this->service->chat('test', 'Test question');

        $this->assertEquals('OK', $result['response']);
    }

    // ===================================================================
    // getUsageStats — all period branches
    // ===================================================================

    public function test_get_usage_stats_hour_period(): void
    {
        AIUsageLog::create([
            'tenant' => 'test',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
            'execution_time_ms' => 100,
            'model' => 'test',
        ]);

        $stats = $this->service->getUsageStats('test', 'hour');
        $this->assertEquals(1, $stats['total_requests']);
    }

    public function test_get_usage_stats_week_period(): void
    {
        AIUsageLog::create([
            'tenant' => 'test',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
            'execution_time_ms' => 100,
            'model' => 'test',
        ]);

        $stats = $this->service->getUsageStats('test', 'week');
        $this->assertEquals(1, $stats['total_requests']);
    }

    public function test_get_usage_stats_month_period(): void
    {
        AIUsageLog::create([
            'tenant' => 'test',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
            'execution_time_ms' => 100,
            'model' => 'test',
        ]);

        $stats = $this->service->getUsageStats('test', 'month');
        $this->assertEquals(1, $stats['total_requests']);
    }

    public function test_get_usage_stats_unknown_period_defaults(): void
    {
        AIUsageLog::create([
            'tenant' => 'test',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
            'execution_time_ms' => 100,
            'model' => 'test',
        ]);

        $stats = $this->service->getUsageStats('test', 'unknown');
        $this->assertEquals(1, $stats['total_requests']);
    }
}
