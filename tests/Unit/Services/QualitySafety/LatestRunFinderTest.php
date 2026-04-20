<?php

namespace Tests\Unit\Services\QualitySafety;

use App\Services\QualitySafety\LatestRunFinder;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LatestRunFinderTest extends TestCase
{
    public function test_returns_null_when_no_runs(): void
    {
        Storage::fake('local');

        $this->assertNull((new LatestRunFinder)->findPath());
    }

    public function test_picks_today_when_today_folder_has_runs(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $today = now()->toDateString();

        $disk->put("qv-scans/2025-01-01/run-120000-1.json", '{}');
        $disk->put("qv-scans/{$today}/run-080000-1.json", '{}');
        $disk->put("qv-scans/{$today}/run-093000-2.json", '{}');

        $this->assertSame(
            "qv-scans/{$today}/run-093000-2.json",
            (new LatestRunFinder)->findPath()
        );
    }

    public function test_falls_back_to_newest_date_folder_when_today_empty(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        $disk->put('qv-scans/2025-01-01/run-120000-1.json', '{}');
        $disk->put('qv-scans/2025-12-31/run-235959-9.json', '{}');

        $this->assertSame(
            'qv-scans/2025-12-31/run-235959-9.json',
            (new LatestRunFinder)->findPath()
        );
    }

    public function test_ignores_non_json_files(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $today = now()->toDateString();

        $disk->put("qv-scans/{$today}/notes.txt", 'ignore me');
        $disk->put("qv-scans/{$today}/run-100000-1.json", '{}');

        $this->assertSame(
            "qv-scans/{$today}/run-100000-1.json",
            (new LatestRunFinder)->findPath()
        );
    }
}
