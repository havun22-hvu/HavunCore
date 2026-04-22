<?php

namespace Tests\Unit\Enums;

use App\Enums\Priority;
use PHPUnit\Framework\TestCase;

class PriorityTest extends TestCase
{
    public function test_backed_values_match_db_enum_column(): void
    {
        // claude_tasks.priority is an ENUM('low','normal','high','urgent')
        // — these strings are part of the DB contract.
        $this->assertSame('urgent', Priority::Urgent->value);
        $this->assertSame('high', Priority::High->value);
        $this->assertSame('normal', Priority::Normal->value);
        $this->assertSame('low', Priority::Low->value);
    }

    public function test_sort_weight_is_strictly_ascending_by_urgency(): void
    {
        // Urgent runs first (weight 1) → Low runs last (weight 4).
        $this->assertLessThan(Priority::High->sortWeight(), Priority::Urgent->sortWeight());
        $this->assertLessThan(Priority::Normal->sortWeight(), Priority::High->sortWeight());
        $this->assertLessThan(Priority::Low->sortWeight(), Priority::Normal->sortWeight());
    }

    public function test_sort_weights_match_legacy_raw_sql_case_expression(): void
    {
        // Pin the exact integer weights so the new ClaudeTask::orderByPriority
        // CASE expression produces identical SQL output to the pre-refactor
        // raw SQL: WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3
        // WHEN 'low' THEN 4 ELSE 5.
        $this->assertSame(1, Priority::Urgent->sortWeight());
        $this->assertSame(2, Priority::High->sortWeight());
        $this->assertSame(3, Priority::Normal->sortWeight());
        $this->assertSame(4, Priority::Low->sortWeight());
    }
}
