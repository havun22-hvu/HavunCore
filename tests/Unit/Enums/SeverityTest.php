<?php

namespace Tests\Unit\Enums;

use App\Enums\Severity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SeverityTest extends TestCase
{
    public function test_backed_values_are_stable(): void
    {
        // These string values are part of the public contract (DB rows,
        // scan JSON, API payloads). Changing them would break consumers.
        $this->assertSame('critical', Severity::Critical->value);
        $this->assertSame('high', Severity::High->value);
        $this->assertSame('medium', Severity::Medium->value);
        $this->assertSame('low', Severity::Low->value);
        $this->assertSame('info', Severity::Info->value);
    }

    public function test_sort_weight_is_strictly_ascending_by_severity(): void
    {
        $this->assertLessThan(Severity::High->sortWeight(), Severity::Critical->sortWeight());
        $this->assertLessThan(Severity::Medium->sortWeight(), Severity::High->sortWeight());
        $this->assertLessThan(Severity::Low->sortWeight(), Severity::Medium->sortWeight());
        $this->assertLessThan(Severity::Info->sortWeight(), Severity::Low->sortWeight());
    }

    public function test_icon_returns_distinct_value_per_case(): void
    {
        // Distinct icons per severity — kills MatchArmRemoval mutations
        // and prevents accidental collapse of two cases to the same icon.
        $this->assertSame('🔴', Severity::Critical->icon());
        $this->assertSame('🟠', Severity::High->icon());
        $this->assertSame('🟡', Severity::Medium->icon());
        $this->assertSame('🔵', Severity::Low->icon());
        $this->assertSame('⚪', Severity::Info->icon());

        $icons = array_map(fn (Severity $s) => $s->icon(), Severity::cases());
        $this->assertCount(count($icons), array_unique($icons), 'Each severity must have a unique icon');
    }

    #[DataProvider('safeProvider')]
    public function test_safe_parses_external_strings_tolerantly(string $raw, Severity $expected): void
    {
        $this->assertSame($expected, Severity::safe($raw));
    }

    public static function safeProvider(): array
    {
        return [
            'critical lowercase' => ['critical', Severity::Critical],
            'critical uppercase' => ['CRITICAL', Severity::Critical],
            'severe alias' => ['severe', Severity::Critical],
            'crit alias' => ['crit', Severity::Critical],
            'high' => ['high', Severity::High],
            'medium' => ['medium', Severity::Medium],
            'moderate alias' => ['moderate', Severity::Medium],
            'low' => ['low', Severity::Low],
            'info' => ['info', Severity::Info],
            'informational alias' => ['informational', Severity::Info],
            'unknown falls back to medium' => ['bogus', Severity::Medium],
            'whitespace is trimmed' => ['  high  ', Severity::High],
        ];
    }
}
