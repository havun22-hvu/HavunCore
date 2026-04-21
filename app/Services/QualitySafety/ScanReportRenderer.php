<?php

namespace App\Services\QualitySafety;

use App\Enums\Severity;

class ScanReportRenderer
{
    /**
     * @param  array<string,mixed>  $run
     */
    public function render(array $run): string
    {
        $findings = $run['findings'] ?? [];
        $errors = $run['errors'] ?? [];
        $totals = $run['totals'] ?? [];

        $critHigh = array_filter(
            $findings,
            fn ($f) => in_array(
                $f['severity'] ?? '',
                [Severity::Critical->value, Severity::High->value],
                true
            )
        );

        $sections = [
            $this->frontmatter($run),
            $this->header($run),
            $this->totalsSection($totals),
            $this->findingsSection($critHigh),
        ];

        if (! empty($errors)) {
            $sections[] = $this->errorsSection($errors);
        }

        $sections[] = $this->nextActions();

        return implode("\n", array_merge(...$sections));
    }

    /**
     * @param  array<string,mixed>  $run
     * @return array<int,string>
     */
    private function frontmatter(array $run): array
    {
        return [
            '---',
            'title: qv:scan latest report (auto-generated)',
            'type: reference',
            'scope: alle-projecten',
            'generated_from: ' . ($run['_source_file'] ?? 'unknown'),
            'generated_at: ' . ($run['started_at'] ?? 'unknown'),
            '---',
            '',
        ];
    }

    /**
     * @param  array<string,mixed>  $run
     * @return array<int,string>
     */
    private function header(array $run): array
    {
        $projects = $run['projects'] ?? [];
        $checks = $run['checks'] ?? [];

        return [
            '# qv:scan — laatste run (auto-generated)',
            '',
            '> Dit bestand wordt overschreven door `php artisan qv:log` na elke scan.',
            '> Voor **post-mortem, prose en fix-statussen** zie `security-findings.md` (handmatig).',
            '',
            '**Started:** ' . ($run['started_at'] ?? 'unknown') . '  ',
            '**Projects:** ' . (empty($projects) ? '—' : implode(', ', $projects)) . '  ',
            '**Checks:** ' . (empty($checks) ? '—' : implode(', ', $checks)),
            '',
        ];
    }

    /**
     * @param  array<string,int>  $totals
     * @return array<int,string>
     */
    private function totalsSection(array $totals): array
    {
        $rows = [];
        foreach (['critical', 'high', 'medium', 'low', 'informational', 'errors'] as $key) {
            $rows[] = [$key, (string) ($totals[$key] ?? 0)];
        }

        return array_merge(
            ['## Totals', ''],
            $this->renderTable(['Severity', 'Count'], $rows),
            ['']
        );
    }

    /**
     * @param  array<int,array<string,mixed>>  $critHigh
     * @return array<int,string>
     */
    private function findingsSection(array $critHigh): array
    {
        $out = ['## HIGH / CRITICAL findings', ''];

        if (empty($critHigh)) {
            $out[] = '_Geen HIGH/CRITICAL findings in laatste run._';
            $out[] = '';

            return $out;
        }

        $rows = [];
        foreach ($critHigh as $f) {
            $subject = $f['package'] ?? $f['host'] ?? '—';
            $title = $f['title'] ?? $f['message'] ?? '—';
            $advisory = $f['advisory_id'] ?? '';
            $rows[] = [
                $f['project'] ?? '—',
                $f['check'] ?? '—',
                $f['severity'] ?? '—',
                $subject,
                $advisory ? "{$advisory} — {$title}" : $title,
            ];
        }

        return array_merge(
            $out,
            $this->renderTable(
                ['Project', 'Check', 'Severity', 'Package / Host', 'Advisory / Title'],
                $rows
            ),
            ['']
        );
    }

    /**
     * @param  array<int,array<string,mixed>>  $errors
     * @return array<int,string>
     */
    private function errorsSection(array $errors): array
    {
        $rows = [];
        foreach ($errors as $err) {
            $rows[] = [
                $err['project'] ?? '—',
                $err['check'] ?? '—',
                $err['message'] ?? '—',
            ];
        }

        return array_merge(
            ['## Scanner errors', ''],
            $this->renderTable(['Project', 'Check', 'Message'], $rows),
            ['']
        );
    }

    /**
     * @return array<int,string>
     */
    private function nextActions(): array
    {
        return [
            '## Next actions',
            '',
            '- HIGH/CRITICAL in de tabel hierboven → onderzoek, fix, en documenteer in `security-findings.md`.',
            '- Na een fix: laat deze file automatisch worden overschreven door de volgende `qv:scan` + `qv:log`.',
            '',
        ];
    }

    /**
     * @param  array<int,string>             $headers
     * @param  array<int,array<int,string>>  $rows
     * @return array<int,string>
     */
    private function renderTable(array $headers, array $rows): array
    {
        $lines = [];
        $lines[] = '| ' . implode(' | ', array_map([$this, 'cell'], $headers)) . ' |';
        $lines[] = '|' . str_repeat('---|', count($headers));

        foreach ($rows as $row) {
            $lines[] = '| ' . implode(' | ', array_map([$this, 'cell'], $row)) . ' |';
        }

        return $lines;
    }

    private function cell(string $value): string
    {
        return str_replace(['|', "\n"], ['\|', ' '], $value);
    }
}
