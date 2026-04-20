<?php

namespace App\Services\QualitySafety;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Locate the most recent qv:scan run-JSON on the storage disk.
 *
 * The scanner writes `{root}/{YYYY-MM-DD}/run-{Hisv}-{pid}.json`. We look in
 * today's folder first and fall back to the newest non-empty date folder, so
 * the lookup stays O(1) in the number of folders regardless of how long the
 * scheduler has been running.
 */
class LatestRunFinder
{
    public function findPath(?string $disk = null, ?string $root = null): ?string
    {
        $disk = $disk ?? (string) config('quality-safety.storage.disk', 'local');
        $root = rtrim($root ?? (string) config('quality-safety.storage.root', 'qv-scans'), '/');

        $storage = Storage::disk($disk);
        $todayDir = "{$root}/" . Carbon::now()->toDateString();
        $candidates = $storage->exists($todayDir) ? $storage->files($todayDir) : [];

        if (empty($candidates)) {
            $dateDirs = collect($storage->directories($root))->sortDesc()->values();
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
