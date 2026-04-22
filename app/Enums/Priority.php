<?php

namespace App\Enums;

/**
 * Task priority for ClaudeTask scheduling — distinct domain from
 * Severity (V&K findings) and LogLevel (runtime errors). See
 * `docs/kb/runbooks/severity-loglevel-priority-taxonomies.md` for the
 * full domain split.
 *
 * Backed values match the DB ENUM column `claude_tasks.priority`
 * (urgent/high/normal/low). Sort-order: lower weight runs first.
 */
enum Priority: string
{
    case Urgent = 'urgent';
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    /**
     * Sort weight — lower runs first. Used by ClaudeTask::orderByPriority
     * to translate the enum into a SQL CASE expression.
     */
    public function sortWeight(): int
    {
        return match ($this) {
            self::Urgent => 1,
            self::High => 2,
            self::Normal => 3,
            self::Low => 4,
        };
    }
}
