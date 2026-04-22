<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class DocsHandoverCommand extends Command
{
    protected $signature = 'docs:handover
                            {--days=7 : Aantal dagen aan git-historie meenemen}
                            {--output=docs/handover.md : Pad naar de handover-file}';

    protected $description = 'Genereer een publieke handover.md uit recente git-commits + V&K state.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $output = base_path((string) $this->option('output'));

        $commits = $this->recentCommits($days);
        $qvSummary = $this->latestQvSummary();
        $generatedAt = CarbonImmutable::now()->toDayDateTimeString();

        $body = $this->renderHandover($commits, $qvSummary, $days, $generatedAt);

        File::ensureDirectoryExists(dirname($output));
        File::put($output, $body);

        $this->info("Handover bijgewerkt: {$this->option('output')} ({$generatedAt})");

        return self::SUCCESS;
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
     * @return array{generated_at:?string,totals:?array<string,int>,findings:list<string>}
     */
    protected function latestQvSummary(): array
    {
        $path = base_path('docs/kb/reference/qv-scan-latest.md');
        if (! File::exists($path)) {
            return ['generated_at' => null, 'totals' => null, 'findings' => []];
        }

        $raw = File::get($path);

        // Pull totals line: "0 critical, 2 high, 0 medium..."
        $totals = null;
        if (preg_match('/critical:\s*(\d+).*?high:\s*(\d+).*?medium:\s*(\d+).*?low:\s*(\d+)/i', $raw, $m)) {
            $totals = [
                'critical' => (int) $m[1],
                'high' => (int) $m[2],
                'medium' => (int) $m[3],
                'low' => (int) $m[4],
            ];
        }

        // Pull HIGH/CRITICAL one-liners.
        preg_match_all('/^[-*]\s*(\[(?:high|critical)\][^\n]+)/im', $raw, $hits);
        $findings = $hits[1] ?? [];

        // Pull "generated at" stamp if present.
        $generatedAt = null;
        if (preg_match('/(?:scan|generated)[^0-9]*([0-9]{4}-[0-9]{2}-[0-9]{2}[T 0-9:+-]+)/i', $raw, $m)) {
            $generatedAt = $m[1];
        }

        return ['generated_at' => $generatedAt, 'totals' => $totals, 'findings' => array_slice($findings, 0, 10)];
    }

    /**
     * @param  list<array{hash:string,subject:string,date:string}>  $commits
     * @param  array{generated_at:?string,totals:?array<string,int>,findings:list<string>}  $qv
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
                    $lines[] = "- {$f}";
                }
            }
        }
        $lines[] = '';

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
