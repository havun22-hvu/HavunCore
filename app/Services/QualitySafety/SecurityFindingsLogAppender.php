<?php

namespace App\Services\QualitySafety;

use App\Enums\Severity;
use Illuminate\Support\Facades\File;

/**
 * Appends HIGH/CRITICAL findings from a qv:scan run to an audit log file.
 *
 * The append-only log is auto-generated and MUST NOT be confused with the
 * human-written `security-findings.md` (post-mortem, prose, fix-statussen).
 */
class SecurityFindingsLogAppender
{
    private const HEADER = <<<'MD'
---
title: qv:scan security findings log (auto-generated, append-only)
type: reference
scope: alle-projecten
---

# qv:scan security findings log

> **Auto-generated.** Elke `php artisan qv:log` voegt HIGH/CRITICAL findings
> toe aan dit bestand. Runs zonder HIGH/CRITICAL worden overgeslagen.
>
> Voor **post-mortem, prose en fix-statussen** zie `security-findings.md`
> (handmatig onderhouden — NIET automatisch bijgewerkt).

MD;

    /**
     * Append HIGH/CRITICAL findings from a run to the log file.
     *
     * @param  array<string,mixed>  $run
     * @return int  Number of findings appended (0 if none).
     */
    public function append(array $run, string $absolutePath): int
    {
        $findings = $run['findings'] ?? [];

        $critHigh = array_values(array_filter(
            $findings,
            fn ($f) => in_array($f['severity'] ?? '', [Severity::Critical->value, Severity::High->value], true)
        ));

        if (empty($critHigh)) {
            return 0;
        }

        File::ensureDirectoryExists(dirname($absolutePath));

        if (! File::exists($absolutePath)) {
            File::put($absolutePath, self::HEADER);
        }

        $block = $this->renderEntry($run, $critHigh);

        File::append($absolutePath, $block);

        return count($critHigh);
    }

    /**
     * @param  array<string,mixed>           $run
     * @param  array<int,array<string,mixed>> $critHigh
     */
    private function renderEntry(array $run, array $critHigh): string
    {
        $timestamp = $this->formatTimestamp((string) ($run['started_at'] ?? ''));

        $lines = [];
        $lines[] = '';
        $lines[] = '## ' . $timestamp;
        $lines[] = '';

        foreach ($critHigh as $f) {
            $lines[] = $this->renderFindingLine($f);
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string,mixed>  $f
     */
    private function renderFindingLine(array $f): string
    {
        $severity = (string) ($f['severity'] ?? 'unknown');
        $icon = $severity === Severity::Critical->value ? '[CRIT]' : '[HIGH]';

        $project = (string) ($f['project'] ?? '—');
        $subject = (string) ($f['package'] ?? $f['host'] ?? '—');
        $title = (string) ($f['title'] ?? $f['message'] ?? '—');
        $advisory = (string) ($f['advisory_id'] ?? '');

        $detail = $advisory !== '' ? "{$advisory} — {$title}" : $title;

        return sprintf(
            '- %s **[%s]** %s/%s: %s',
            $icon,
            $severity,
            $project,
            $subject,
            $detail
        );
    }

    private function formatTimestamp(string $startedAt): string
    {
        if ($startedAt === '') {
            return date('Y-m-d H:i');
        }

        $ts = strtotime($startedAt);

        return $ts === false ? $startedAt : date('Y-m-d H:i', $ts);
    }
}
