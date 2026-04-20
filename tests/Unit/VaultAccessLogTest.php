<?php

namespace Tests\Unit;

use App\Models\VaultAccessLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage voor VaultAccessLog static log() helper + scopes.
 */
class VaultAccessLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_creates_row_with_all_fields(): void
    {
        $entry = VaultAccessLog::log(
            project: 'havunadmin',
            action: 'read',
            resourceType: 'secret',
            resourceKey: 'STRIPE_KEY',
            ipAddress: '127.0.0.1',
        );

        $this->assertSame('havunadmin', $entry->project);
        $this->assertSame('read', $entry->action);
        $this->assertSame('STRIPE_KEY', $entry->resource_key);
        $this->assertNotNull($entry->created_at);
    }

    public function test_for_project_scope_filters_correctly(): void
    {
        VaultAccessLog::log('a', 'read', 'secret', 'X');
        VaultAccessLog::log('a', 'read', 'secret', 'X');
        VaultAccessLog::log('b', 'read', 'secret', 'X');

        $this->assertSame(2, VaultAccessLog::forProject('a')->count());
        $this->assertSame(1, VaultAccessLog::forProject('b')->count());
    }

    public function test_recent_scope_excludes_old_entries(): void
    {
        $entry = VaultAccessLog::log('a', 'read', 'secret', 'X');
        \DB::table('vault_access_logs')
            ->where('id', $entry->id)
            ->update(['created_at' => now()->subDays(60)]);

        $this->assertSame(0, VaultAccessLog::recent(30)->count());
        $this->assertSame(1, VaultAccessLog::recent(90)->count(),
            '90-day window includes the 60-day-old entry.');
    }
}
