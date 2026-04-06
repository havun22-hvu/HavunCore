<?php

namespace Tests\Unit;

use App\Models\AIUsageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIUsageLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_stats_aggregates_correctly(): void
    {
        AIUsageLog::create([
            'tenant' => 'infosyst',
            'input_tokens' => 100,
            'output_tokens' => 200,
            'total_tokens' => 300,
            'execution_time_ms' => 400,
            'model' => 'claude-3-haiku',
        ]);

        AIUsageLog::create([
            'tenant' => 'infosyst',
            'input_tokens' => 50,
            'output_tokens' => 100,
            'total_tokens' => 150,
            'execution_time_ms' => 600,
            'model' => 'claude-3-haiku',
        ]);

        // Different tenant - should not be included
        AIUsageLog::create([
            'tenant' => 'havunadmin',
            'input_tokens' => 999,
            'output_tokens' => 999,
            'total_tokens' => 1998,
            'execution_time_ms' => 100,
            'model' => 'claude-3-haiku',
        ]);

        $stats = AIUsageLog::dailyStats('infosyst');

        $this->assertEquals(2, $stats['requests']);
        $this->assertEquals(450, $stats['tokens']);
        $this->assertEquals(500, $stats['avg_time_ms']);
    }

    public function test_daily_stats_returns_zeros_for_no_data(): void
    {
        $stats = AIUsageLog::dailyStats('nonexistent');

        $this->assertEquals(0, $stats['requests']);
        $this->assertEquals(0, $stats['tokens']);
        $this->assertEquals(0, $stats['avg_time_ms']);
    }

    public function test_all_tenants_stats(): void
    {
        AIUsageLog::create([
            'tenant' => 'infosyst',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
            'execution_time_ms' => 100,
            'model' => 'test',
        ]);

        AIUsageLog::create([
            'tenant' => 'havunadmin',
            'input_tokens' => 5,
            'output_tokens' => 10,
            'total_tokens' => 15,
            'execution_time_ms' => 50,
            'model' => 'test',
        ]);

        $stats = AIUsageLog::allTenantsStats('day');

        $this->assertArrayHasKey('infosyst', $stats);
        $this->assertArrayHasKey('havunadmin', $stats);
        $this->assertEquals(1, $stats['infosyst']['requests']);
        $this->assertEquals(1, $stats['havunadmin']['requests']);
    }

    public function test_for_tenant_scope(): void
    {
        AIUsageLog::create([
            'tenant' => 'infosyst',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
            'execution_time_ms' => 100,
            'model' => 'test',
        ]);

        AIUsageLog::create([
            'tenant' => 'havunadmin',
            'input_tokens' => 5,
            'output_tokens' => 10,
            'total_tokens' => 15,
            'execution_time_ms' => 50,
            'model' => 'test',
        ]);

        $infosystLogs = AIUsageLog::forTenant('infosyst')->get();
        $this->assertCount(1, $infosystLogs);
        $this->assertEquals('infosyst', $infosystLogs->first()->tenant);
    }

    public function test_recent_scope(): void
    {
        // Current log
        AIUsageLog::create([
            'tenant' => 'test',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
            'execution_time_ms' => 100,
            'model' => 'test',
        ]);

        $recent = AIUsageLog::recent(1)->get();
        $this->assertCount(1, $recent);
    }
}
