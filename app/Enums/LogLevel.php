<?php

namespace App\Enums;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Runtime log-level for ErrorLog entries — subset of PSR-3 levels we
 * actually persist as DB rows. Distinct from `App\Enums\Severity` (which
 * is for V&K scan findings + doc-issues). See
 * `docs/kb/runbooks/severity-loglevel-priority-taxonomies.md` for the
 * full domain split.
 *
 * Backed values match what's already stored in `error_logs.severity`
 * (kolomnaam misleidend, contenttype = log-level), so no migration needed.
 */
enum LogLevel: string
{
    case Critical = 'critical';
    case Error = 'error';
    case Warning = 'warning';

    /**
     * Map a thrown exception to a log-level. PHP \Error (incl. fatals,
     * type-errors, parse-errors) is always Critical. HttpException 5xx
     * = Error, 4xx = Warning. Anything else falls back to Error.
     */
    public static function fromException(Throwable $e): self
    {
        if ($e instanceof \Error) {
            return self::Critical;
        }

        if ($e instanceof HttpException) {
            return $e->getStatusCode() >= 500 ? self::Error : self::Warning;
        }

        return self::Error;
    }

    /**
     * Sort weight — lower = more severe. Used for ordering ErrorLog
     * lists so critical surfaces first.
     */
    public function sortWeight(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::Error => 1,
            self::Warning => 2,
        };
    }
}
