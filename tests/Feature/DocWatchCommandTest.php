<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\CreatesDocIntelligenceTables;
use Tests\TestCase;

class DocWatchCommandTest extends TestCase
{
    use CreatesDocIntelligenceTables;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDocIntelligenceTables();

        Http::fake([
            '127.0.0.1:11434/*' => Http::response(['embedding' => null], 200),
        ]);
    }

    public function test_watch_once_runs_single_cycle(): void
    {
        $this->artisan('docs:watch', ['--once' => true])
            ->assertExitCode(0);
    }

    public function test_watch_once_with_interval_option(): void
    {
        $this->artisan('docs:watch', ['--once' => true, '--interval' => 10])
            ->assertExitCode(0);
    }
}
