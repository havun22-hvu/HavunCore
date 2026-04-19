<?php

namespace App\Console\Commands;

use App\Services\QualitySafety\ScanReportRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class QualitySafetyLogCommand extends Command
{
    protected $signature = 'qv:log
        {--output= : Relative path under base_path() to write the report (default: docs/kb/reference/qv-scan-latest.md)}';

    protected $description = 'Render the latest qv:scan run as a Markdown report (HIGH/CRITICAL findings) into the KB';

    public function handle(ScanReportRenderer $renderer): int
    {
        $latest = $this->findLatestRun();

        if ($latest === null) {
            $this->warn('No qv:scan runs found in storage/app/' . config('quality-safety.storage.root', 'qv-scans'));

            return 1;
        }

        $run = json_decode(Storage::disk(config('quality-safety.storage.disk', 'local'))->get($latest), true);

        if (! is_array($run)) {
            $this->error("Latest run file is not valid JSON: {$latest}");

            return 1;
        }

        $markdown = $renderer->render($run, $latest);

        $output = $this->option('output') ?: 'docs/kb/reference/qv-scan-latest.md';
        $absolute = base_path($output);

        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0755, true);
        }

        file_put_contents($absolute, $markdown);

        $this->info("Wrote report to {$output}");
        $this->line(sprintf(
            'Findings — critical: %d | high: %d (medium/low/info skipped in auto-report)',
            $run['totals']['critical'] ?? 0,
            $run['totals']['high'] ?? 0,
        ));

        return 0;
    }

    private function findLatestRun(): ?string
    {
        $disk = config('quality-safety.storage.disk', 'local');
        $root = rtrim(config('quality-safety.storage.root', 'qv-scans'), '/');

        $files = Storage::disk($disk)->allFiles($root);
        $jsonFiles = array_filter($files, fn ($f) => str_ends_with($f, '.json'));

        if (empty($jsonFiles)) {
            return null;
        }

        rsort($jsonFiles);

        return $jsonFiles[0];
    }
}
