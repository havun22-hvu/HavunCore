<?php

namespace App\Console\Commands;

use App\Services\QualitySafety\LatestRunFinder;
use App\Services\QualitySafety\ScanReportRenderer;
use App\Services\QualitySafety\SecurityFindingsLogAppender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class QualitySafetyLogCommand extends Command
{
    protected $signature = 'qv:log
        {--output= : Path relative to base_path() for the report (default: docs/kb/reference/qv-scan-latest.md)}
        {--append-log= : Path relative to base_path() for the append-only HIGH/CRIT log (default: docs/kb/reference/security-findings-log.md)}
        {--no-append : Disable auto-append of HIGH/CRITICAL findings to the security-findings-log.md}';

    protected $description = 'Render the latest qv:scan run as a Markdown report (HIGH/CRITICAL findings) into the KB';

    public function handle(
        ScanReportRenderer $renderer,
        LatestRunFinder $finder,
        SecurityFindingsLogAppender $appender,
    ): int {
        $disk = (string) config('quality-safety.storage.disk', 'local');
        $root = rtrim((string) config('quality-safety.storage.root', 'qv-scans'), '/');

        $latest = $finder->findPath($disk, $root);

        if ($latest === null) {
            $this->warn("No qv:scan runs found in storage/app/{$root}");

            return 1;
        }

        $run = json_decode(Storage::disk($disk)->get($latest), true);

        if (! is_array($run)) {
            $this->error("Latest run file is not valid JSON: {$latest}");

            return 1;
        }

        $run['_source_file'] = $latest;
        $markdown = $renderer->render($run);

        $output = $this->option('output') ?: 'docs/kb/reference/qv-scan-latest.md';
        $absolute = base_path($output);
        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, $markdown);

        $this->info("Wrote report to {$output}");
        $this->line(sprintf(
            'Findings — critical: %d | high: %d (medium/low/info skipped in auto-report)',
            $run['totals']['critical'] ?? 0,
            $run['totals']['high'] ?? 0,
        ));

        if (! $this->option('no-append')) {
            $logPath = $this->option('append-log') ?: 'docs/kb/reference/security-findings-log.md';
            $absoluteLog = base_path($logPath);
            $appended = $appender->append($run, $absoluteLog);

            if ($appended > 0) {
                $this->info("Appended {$appended} HIGH/CRITICAL finding(s) to {$logPath}");
            } else {
                $this->line("No HIGH/CRITICAL findings to append — {$logPath} unchanged");
            }
        }

        return 0;
    }
}
