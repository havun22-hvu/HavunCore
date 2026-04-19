<?php

namespace App\Services\QualitySafety;

class ScanReportRenderer
{
    /**
     * @param  array<string,mixed>  $run
     */
    public function render(array $run, string $sourceFile): string
    {
        $startedAt = $run['started_at'] ?? 'unknown';
        $projects = $run['projects'] ?? [];
        $checks = $run['checks'] ?? [];
        $findings = $run['findings'] ?? [];
        $errors = $run['errors'] ?? [];
        $totals = $run['totals'] ?? [];

        $critHigh = array_filter(
            $findings,
            fn ($f) => in_array($f['severity'] ?? '', ['critical', 'high'], true)
        );

        $lines = [];
        $lines[] = '---';
        $lines[] = 'title: qv:scan latest report (auto-generated)';
        $lines[] = 'type: reference';
        $lines[] = 'scope: alle-projecten';
        $lines[] = "generated_from: {$sourceFile}";
        $lines[] = "generated_at: {$startedAt}";
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '# qv:scan — laatste run (auto-generated)';
        $lines[] = '';
        $lines[] = '> Dit bestand wordt overschreven door `php artisan qv:log` na elke scan.';
        $lines[] = '> Voor **post-mortem, prose en fix-statussen** zie `security-findings.md` (handmatig).';
        $lines[] = '';
        $lines[] = "**Started:** {$startedAt}  ";
        $lines[] = '**Projects:** ' . (empty($projects) ? '—' : implode(', ', $projects)) . '  ';
        $lines[] = '**Checks:** ' . (empty($checks) ? '—' : implode(', ', $checks));
        $lines[] = '';
        $lines[] = '## Totals';
        $lines[] = '';
        $lines[] = '| Severity | Count |';
        $lines[] = '|----------|-------|';
        foreach (['critical', 'high', 'medium', 'low', 'informational', 'errors'] as $key) {
            $lines[] = "| {$key} | " . ($totals[$key] ?? 0) . ' |';
        }
        $lines[] = '';

        $lines[] = '## HIGH / CRITICAL findings';
        $lines[] = '';
        if (empty($critHigh)) {
            $lines[] = '_Geen HIGH/CRITICAL findings in laatste run._';
        } else {
            $lines[] = '| Project | Check | Severity | Package / Host | Advisory / Title |';
            $lines[] = '|---------|-------|----------|----------------|-------------------|';
            foreach ($critHigh as $f) {
                $subject = $f['package'] ?? $f['host'] ?? '—';
                $title = $f['title'] ?? $f['message'] ?? '—';
                $advisory = $f['advisory_id'] ?? '';
                $titleCell = $advisory ? "{$advisory} — {$title}" : $title;
                $lines[] = sprintf(
                    '| %s | %s | %s | %s | %s |',
                    $this->cell($f['project'] ?? '—'),
                    $this->cell($f['check'] ?? '—'),
                    $this->cell($f['severity'] ?? '—'),
                    $this->cell($subject),
                    $this->cell($titleCell),
                );
            }
        }
        $lines[] = '';

        if (! empty($errors)) {
            $lines[] = '## Scanner errors';
            $lines[] = '';
            $lines[] = '| Project | Check | Message |';
            $lines[] = '|---------|-------|---------|';
            foreach ($errors as $err) {
                $lines[] = sprintf(
                    '| %s | %s | %s |',
                    $this->cell($err['project'] ?? '—'),
                    $this->cell($err['check'] ?? '—'),
                    $this->cell($err['message'] ?? '—'),
                );
            }
            $lines[] = '';
        }

        $lines[] = '## Next actions';
        $lines[] = '';
        $lines[] = '- HIGH/CRITICAL in de tabel hierboven → onderzoek, fix, en documenteer in `security-findings.md`.';
        $lines[] = '- Na een fix: laat deze file automatisch worden overschreven door de volgende `qv:scan` + `qv:log`.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function cell(string $value): string
    {
        return str_replace(['|', "\n"], ['\|', ' '], $value);
    }
}
