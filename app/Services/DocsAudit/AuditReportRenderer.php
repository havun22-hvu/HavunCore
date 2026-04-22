<?php

namespace App\Services\DocsAudit;

use App\Enums\Severity;
use Carbon\CarbonImmutable;

/**
 * Rendert een DocsAuditor-resultaat naar Markdown. Output structuur:
 *
 *   # Header + totals
 *   # Findings per severity (grouped)
 *   # Batch-approval block (rm/mv commands met git-status guard)
 */
class AuditReportRenderer
{
    /**
     * @param  array{findings:list<array<string,mixed>>,totals:array<string,int>,scanned:int}  $result
     */
    public function render(array $result, string $project, string $codebaseRoot): string
    {
        $lines = [];
        $lines[] = '---';
        $lines[] = "title: KB audit — {$project}";
        $lines[] = 'type: reference';
        $lines[] = "scope: {$project}";
        $lines[] = 'last_check: ' . CarbonImmutable::now()->toDateString();
        $lines[] = '---';
        $lines[] = '';
        $lines[] = "# KB audit — {$project}";
        $lines[] = '';
        $lines[] = '> Auto-gegenereerd door `php artisan docs:audit`. Overschrijft bij elke run.';
        $lines[] = '';

        $lines[] = '## Samenvatting';
        $lines[] = '';
        $lines[] = sprintf('- Files gescand: **%d**', $result['scanned']);
        foreach (Severity::cases() as $sev) {
            $lines[] = sprintf('- %s %s: **%d**', $sev->icon(), ucfirst($sev->value), $result['totals'][$sev->value] ?? 0);
        }
        $lines[] = '';

        foreach (Severity::cases() as $sev) {
            $group = array_values(array_filter($result['findings'], fn ($f) => ($f['severity'] ?? '') === $sev->value));
            if ($group === []) {
                continue;
            }
            $lines[] = sprintf('## %s %s findings', $sev->icon(), ucfirst($sev->value));
            $lines[] = '';
            foreach ($group as $f) {
                $rel = $this->relativePath((string) $f['file'], $codebaseRoot);
                $detector = (string) ($f['detector'] ?? '?');
                $detail = (string) ($f['detail'] ?? '');
                $action = (string) ($f['action'] ?? '');
                $lines[] = "### `{$rel}` _(detector: {$detector})_";
                $lines[] = '';
                $lines[] = "**Probleem:** {$detail}";
                $lines[] = '';
                $lines[] = "**Voorstel:** {$action}";
                $lines[] = '';
            }
        }

        $lines = array_merge($lines, $this->batchApprovalBlock($result['findings'], $codebaseRoot));

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param  list<array<string,mixed>>  $findings
     * @return list<string>
     */
    private function batchApprovalBlock(array $findings, string $codebaseRoot): array
    {
        $candidates = array_values(array_filter(
            $findings,
            fn ($f) => in_array($f['severity'] ?? '', [Severity::Critical->value, Severity::High->value], true)
                && in_array($f['detector'] ?? '', ['obsolete', 'zombie'], true)
        ));

        if ($candidates === []) {
            return [];
        }

        $lines = [];
        $lines[] = '## Batch-approval commands';
        $lines[] = '';
        $lines[] = '> Deze commands zijn **kandidaten voor verwijdering** (obsolete + zombie).';
        $lines[] = '> Scan de lijst, controleer, en voer uit met **"Uitvoeren"** als akkoord.';
        $lines[] = '>';
        $lines[] = '> **SAFETY-GUARD:** het blok begint met `git status` — als de working';
        $lines[] = '> tree niet clean is, stopt het. Dat voorkomt dat een `rm` onbedoeld';
        $lines[] = '> samen met andere wijzigingen gecommit wordt.';
        $lines[] = '';
        $lines[] = '```bash';
        $lines[] = 'git status --porcelain | grep -q . && { echo "Working tree not clean — abort"; exit 1; }';
        foreach ($candidates as $f) {
            $rel = $this->relativePath((string) $f['file'], $codebaseRoot);
            $comment = str_replace('`', '', (string) ($f['detail'] ?? ''));
            $lines[] = "# {$comment}";
            $lines[] = "rm \"{$rel}\"";
        }
        $lines[] = '```';
        $lines[] = '';

        return $lines;
    }

    private function relativePath(string $absolute, string $root): string
    {
        $root = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, rtrim($root, '/\\'));
        $absolute = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $absolute);
        if (str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', substr($absolute, strlen($root) + 1));
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', $absolute);
    }
}
