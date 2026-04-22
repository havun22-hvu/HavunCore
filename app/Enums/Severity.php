<?php

namespace App\Enums;

/**
 * Cross-cutting severity enum for quality-safety scans, doc-issues and
 * observability findings. Kept backward compatible with existing string
 * values ('critical', 'high', 'medium', 'low', 'info') via the backed
 * enum value, so persisted DB strings and API payloads remain stable.
 *
 * Distinct from `App\Enums\LogLevel` (runtime errors) and
 * `App\Enums\Priority` (task scheduling). See
 * docs/kb/runbooks/severity-loglevel-priority-taxonomies.md.
 */
enum Severity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Info = 'info';

    /**
     * Human-friendly icon for CLI/markdown rendering.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Critical => '🔴',
            self::High => '🟠',
            self::Medium => '🟡',
            self::Low => '🔵',
            self::Info => '⚪',
        };
    }

    /**
     * Weight used to sort findings: lower = more severe.
     * Suitable as stable ordering across renderers and DB-level CASE WHENs.
     */
    public function sortWeight(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::High => 1,
            self::Medium => 2,
            self::Low => 3,
            self::Info => 4,
        };
    }

    /**
     * Tolerant parser for severities coming from external sources
     * (composer audit, Observatory, npm audit, legacy records). Falls
     * back to Medium when the input is unknown so we never lose findings.
     */
    public static function safe(string $raw): self
    {
        return match (strtolower(trim($raw))) {
            'critical', 'severe', 'crit' => self::Critical,
            'high' => self::High,
            'moderate', 'medium', 'med' => self::Medium,
            'low' => self::Low,
            'info', 'informational', 'informational-only' => self::Info,
            default => self::Medium,
        };
    }
}
