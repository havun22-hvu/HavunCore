<?php

namespace App\Services\DocsAudit;

use App\Enums\Severity;
use Carbon\CarbonImmutable;

/**
 * Flags docs waar `last_check:` te ver in het verleden ligt, of waar
 * `status: DEPRECATED` zonder recente activiteit staat.
 *
 * Drempels (afgesproken met Henk 22-04):
 * - 0-6 mnd  = geen finding
 * - 6-12 mnd = Medium
 * - 12-24 mnd = High
 * - > 24 mnd OF status: DEPRECATED = Critical
 */
class ObsoleteChecker
{
    private const STALE_MEDIUM_MONTHS = 6;

    private const STALE_HIGH_MONTHS = 12;

    private const STALE_CRITICAL_MONTHS = 24;

    /**
     * @return list<array<string,mixed>>
     */
    public function check(string $absolutePath): array
    {
        $content = @file_get_contents($absolutePath);
        if ($content === false) {
            return [];
        }

        [$lastCheck, $status] = $this->parseFrontmatter($content);
        $findings = [];

        if ($status === 'DEPRECATED') {
            $findings[] = $this->finding($absolutePath, Severity::Critical, 'status: DEPRECATED', 'Deprecated doc — overweeg verwijdering');
        }

        if ($lastCheck === null) {
            return $findings;
        }

        $monthsOld = CarbonImmutable::now()->diffInMonths($lastCheck, true);
        $detail = sprintf('last_check %s (%.0f mnd oud)', $lastCheck->toDateString(), $monthsOld);

        if ($monthsOld > self::STALE_CRITICAL_MONTHS) {
            $findings[] = $this->finding($absolutePath, Severity::Critical, $detail, 'Handmatige review of verwijdering');
        } elseif ($monthsOld > self::STALE_HIGH_MONTHS) {
            $findings[] = $this->finding($absolutePath, Severity::High, $detail, 'Update last_check of review inhoud');
        } elseif ($monthsOld > self::STALE_MEDIUM_MONTHS) {
            $findings[] = $this->finding($absolutePath, Severity::Medium, $detail, 'Binnenkort reviewen');
        }

        return $findings;
    }

    /**
     * @return array{0:?CarbonImmutable,1:?string}
     */
    private function parseFrontmatter(string $content): array
    {
        if (! preg_match('/^---\n(.*?)\n---/s', $content, $m)) {
            return [null, null];
        }
        $block = $m[1];

        $lastCheck = null;
        if (preg_match('/^last_check:\s*(\S+)/m', $block, $lm)) {
            try {
                $lastCheck = CarbonImmutable::parse($lm[1]);
            } catch (\Throwable) {
                // Invalid date → no finding, laat aan StructureChecker.
            }
        }

        $status = null;
        if (preg_match('/^status:\s*(\S+)/m', $block, $sm)) {
            $status = trim($sm[1]);
        }

        return [$lastCheck, $status];
    }

    private function finding(string $path, Severity $severity, string $detail, string $action): array
    {
        return [
            'severity' => $severity->value,
            'detector' => 'obsolete',
            'file' => $path,
            'detail' => $detail,
            'action' => $action,
        ];
    }
}
