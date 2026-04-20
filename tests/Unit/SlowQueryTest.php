<?php

namespace Tests\Unit;

use App\Models\SlowQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage voor SlowQuery scopes (recent, slowerThan).
 */
class SlowQueryTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuery(array $overrides = []): SlowQuery
    {
        return SlowQuery::create(array_merge([
            'project' => 'havuncore',
            'query' => 'SELECT * FROM x',
            'time_ms' => 500,
            'connection' => 'mysql',
            'created_at' => now(),
        ], $overrides));
    }

    public function test_recent_scope_includes_queries_in_window(): void
    {
        $this->makeQuery(['created_at' => now()->subHours(2)]);
        $this->makeQuery(['created_at' => now()->subHours(50)]); // outside default 24h

        $this->assertSame(1, SlowQuery::recent()->count());
        $this->assertSame(2, SlowQuery::recent(72)->count(), '72h includes both.');
    }

    public function test_slower_than_scope_filters_by_threshold(): void
    {
        $this->makeQuery(['time_ms' => 100]);
        $this->makeQuery(['time_ms' => 500]);
        $this->makeQuery(['time_ms' => 1500]);

        $this->assertSame(2, SlowQuery::slowerThan(400)->count());
        $this->assertSame(1, SlowQuery::slowerThan(1000)->count());
    }

    public function test_time_ms_is_cast_to_decimal(): void
    {
        $q = $this->makeQuery(['time_ms' => 1234.56]);

        $this->assertEquals(1234.56, (float) $q->fresh()->time_ms);
    }
}
