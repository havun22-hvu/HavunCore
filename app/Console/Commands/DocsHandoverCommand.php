<?php

namespace App\Console\Commands;

use App\Services\QualitySafety\LatestRunFinder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class DocsHandoverCommand extends Command
{
    /**
     * Cap on findings shown in the handover. Beyond this we render a
     * "+N meer" hint so silent truncation can't hide HIGH/CRIT escapes.
     */
    private const MAX_FINDINGS = 10;

    protected $signature = 'docs:handover
                            {--days=7 : Aantal dagen aan git-historie meenemen}
                            {--output=docs/handover.md : Pad naar de handover-file}';

    protected $description = 'Genereer een publieke handover.md uit recente git-commits + V&K state.';

    public function handle(LatestRunFinder $latestRunFinder): int
    {
        $days = (int) $this->option('days');
        $rawOutput = (string) $this->option('output');
        $output = $this->isAbsolutePath($rawOutput) ? $rawOutput : base_path($rawOutput);

        $commits = $this->recentCommits($days);
        $qvSummary = $this->latestQvSummary($latestRunFinder);
        $generatedAt = CarbonImmutable::now()->toDayDateTimeString();

        $body = $this->renderHandover($commits, $qvSummary, $days, $generatedAt);

        File::ensureDirectoryExists(dirname($output));
        File::put($output, $body);

        $this->info("Handover bijgewerkt: {$this->option('output')} ({$generatedAt})");

        return self::SUCCESS;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[/\\\\]#', $path) === 1;
    }

    /**
     * Extract totals uit kb-audit-latest.md frontmatter/samenvatting zodat
     * handover direct toont of er CRIT/HIGH KB-issues zijn. Zelfde pattern
     * als latestQvSummary maar dan voor het wekelijkse audit-rapport.
     *
     * @return array{critical:int,high:int,medium:int,low:int}|null
     */
    private function latestKbAuditTotals(): ?array
    {
        $path = base_path('docs/kb/reference/kb-audit-latest.md');
        if (! File::exists($path)) {
            return null;
        }
        $raw = File::get($path);

        $totals = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($totals as $sev => $_) {
            $pattern = sprintf('/- .*%s: \*\*(\d+)\*\*/i', preg_quote($sev, '/'));
            if (preg_match($pattern, $raw, $m)) {
                $totals[$sev] = (int) $m[1];
            }
        }

        return $totals;
    }

    /**
     * @return list<array{hash:string,subject:string,date:string}>
     */
    protected function recentCommits(int $days): array
    {
        $process = new Process([
            'git', 'log',
            "--since={$days} days ago",
            '--pretty=format:%h|%ad|%s',
            '--date=short',
            '--no-merges',
        ], base_path());
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        return collect(explode("\n", trim($process->getOutput())))
            ->filter()
            ->map(function (string $line) {
                [$hash, $date, $subject] = explode('|', $line, 3) + ['', '', ''];

                return ['hash' => $hash, 'date' => $date, 'subject' => $subject];
            })
            ->values()
            ->all();
    }

    /**
     * Reads the latest qv:scan run-JSON directly (same source-of-truth as
     * qv:log). Avoids the format-drift trap of regex-parsing the rendered
     * markdown — if ScanReportRenderer changes its layout, this still works.
     *
     * @return array{generated_at:?string,totals:?array<string,int>,findings:list<array<string,mixed>>,findings_total:int}
     */
    protected function latestQvSummary(LatestRunFinder $finder): array
    {
        $disk = (string) config('quality-safety.storage.disk', 'local');
        $latest = $finder->findPath($disk);
        if ($latest === null) {
            return ['generated_at' => null, 'totals' => null, 'findings' => [], 'findings_total' => 0];
        }

        $raw = Storage::disk($disk)->get($latest);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($data)) {
            return ['generated_at' => null, 'totals' => null, 'findings' => [], 'findings_total' => 0];
        }

        $highCrit = array_values(array_filter(
            $data['findings'] ?? [],
            fn ($f) => is_array($f) && in_array($f['severity'] ?? null, ['high', 'critical'], true)
        ));

        return [
            'generated_at' => $data['started_at'] ?? null,
            'totals' => isset($data['totals']) && is_array($data['totals'])
                ? array_map('intval', $data['totals'])
                : null,
            'findings' => array_slice($highCrit, 0, self::MAX_FINDINGS),
            'findings_total' => count($highCrit),
        ];
    }

    /**
     * @param  list<array{hash:string,subject:string,date:string}>  $commits
     * @param  array{generated_at:?string,totals:?array<string,int>,findings:list<array<string,mixed>>,findings_total:int}  $qv
     */
    protected function renderHandover(array $commits, array $qv, int $days, string $generatedAt): string
    {
        $lines = [];
        $lines[] = '# Handover (auto-generated)';
        $lines[] = '';
        $lines[] = "> **Auto-gegenereerd door `php artisan docs:handover`** op {$generatedAt}.";
        $lines[] = '> Bewerk dit bestand niet handmatig — wijzigingen worden overschreven.';
        $lines[] = '> Voor session-detail zie `.claude/handover.md`. Voor V&K-architectuur zie';
        $lines[] = '> `docs/kb/runbooks/kwaliteit-veiligheid-systeem.md`.';
        $lines[] = '';

        $lines[] = '## Recente activiteit (laatste ' . $days . ' dagen)';
        $lines[] = '';
        if ($commits === []) {
            $lines[] = '_Geen commits in deze periode._';
        } else {
            $lines[] = '| Datum | Hash | Bericht |';
            $lines[] = '|-------|------|---------|';
            foreach ($commits as $c) {
                $subject = str_replace('|', '\\|', $c['subject']);
                $lines[] = "| {$c['date']} | `{$c['hash']}` | {$subject} |";
            }
        }
        $lines[] = '';

        $lines[] = '## V&K status (laatste qv:scan)';
        $lines[] = '';
        if ($qv['totals'] === null) {
            $lines[] = '_Nog geen `qv:scan` snapshot beschikbaar._';
        } else {
            $totals = $qv['totals'];
            $lines[] = "**Totals:** critical {$totals['critical']} | high {$totals['high']} | medium {$totals['medium']} | low {$totals['low']}";
            if ($qv['generated_at']) {
                $lines[] = '';
                $lines[] = "_Snapshot timestamp: {$qv['generated_at']}_";
            }
            if ($qv['findings'] !== []) {
                $lines[] = '';
                $lines[] = '**HIGH/CRITICAL findings:**';
                $lines[] = '';
                foreach ($qv['findings'] as $f) {
                    $sev = strtoupper((string) ($f['severity'] ?? '?'));
                    $proj = (string) ($f['project'] ?? '?');
                    $check = (string) ($f['check'] ?? '?');
                    $msg = (string) ($f['message'] ?? $f['title'] ?? '');
                    $lines[] = "- **[{$sev}]** `{$proj}/{$check}` — {$msg}";
                }
                $hidden = $qv['findings_total'] - count($qv['findings']);
                if ($hidden > 0) {
                    $lines[] = "- _… +{$hidden} meer (zie `docs/kb/reference/qv-scan-latest.md`)_";
                }
            }
        }
        $lines[] = '';

        $auditTotals = $this->latestKbAuditTotals();
        if ($auditTotals !== null) {
            $lines[] = '## KB audit (laatste wekelijkse run)';
            $lines[] = '';
            $lines[] = sprintf(
                '**Totals:** critical %d | high %d | medium %d | low %d',
                $auditTotals['critical'],
                $auditTotals['high'],
                $auditTotals['medium'],
                $auditTotals['low']
            );
            if ($auditTotals['critical'] + $auditTotals['high'] > 0) {
                $lines[] = '';
                $lines[] = '_Zie `docs/kb/reference/kb-audit-latest.md` voor detail._';
            }
            $lines[] = '';
        }

        $lines[] = '## Verdiepende bronnen';
        $lines[] = '';
        $lines[] = '- **Architectuur V&K:** `docs/kb/runbooks/kwaliteit-veiligheid-systeem.md`';
        $lines[] = '- **Kritieke paden + MSI gates:** `docs/kb/reference/critical-paths-havuncore.md`';
        $lines[] = '- **Mutation-test setup:** `docs/kb/runbooks/infection-setup-plan.md`';
        $lines[] = '- **qv:scan snapshot:** `docs/kb/reference/qv-scan-latest.md`';
        $lines[] = '- **Findings auto-log:** `docs/kb/reference/security-findings-log.md`';
        $lines[] = '- **Findings curated:** `docs/kb/reference/security-findings.md`';
        $lines[] = '';

        return implode("\n", $lines) . "\n";
    }
}
