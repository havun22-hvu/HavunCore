<?php

namespace App\Console\Commands;

use App\Services\QualitySafety\ScanReportRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class QualitySafetyLogCommand extends Command
{
    protected $signature = 'qv:log
        {--output= : Path relative to base_path() for the report (default: docs/kb/reference/qv-scan-latest.md)}';

    protected $description = 'Render the latest qv:scan run as a Markdown report (HIGH/CRITICAL findings) into the KB';

    public function handle(ScanReportRenderer $renderer): int
    {
        $disk = config('quality-safety.storage.disk', 'local');
        $root = rtrim(config('quality-safety.storage.root', 'qv-scans'), '/');

        $latest = $this->findLatestRun($disk, $root);

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

        return 0;
    }

    /**
     * Returns the newest run file path (relative to the disk root).
     *
     * The scanner writes `{root}/{YYYY-MM-DD}/run-{Hisv}-{pid}.json`. We only
     * scan the most recent date folder that contains files, keeping this O(1)
     * even after months of daily runs.
     */
    private function findLatestRun(string $disk, string $root): ?string
    {
        $storage = Storage::disk($disk);
        $today = Carbon::now()->toDateString();

        $todayDir = "{$root}/{$today}";
        $candidates = $storage->exists($todayDir) ? $storage->files($todayDir) : [];

        if (empty($candidates)) {
            $dateDirs = collect($storage->directories($root))
                ->sortDesc()
                ->values();

            foreach ($dateDirs as $dir) {
                $inDir = $storage->files($dir);
                if (! empty($inDir)) {
                    $candidates = $inDir;
                    break;
                }
            }
        }

        $jsonFiles = array_values(array_filter(
            $candidates,
            fn ($f) => str_ends_with($f, '.json')
        ));

        if (empty($jsonFiles)) {
            return null;
        }

        rsort($jsonFiles);

        return $jsonFiles[0];
    }
}
