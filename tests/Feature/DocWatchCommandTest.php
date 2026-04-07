<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocWatchCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.doc_intelligence.database' => ':memory:']);
        DB::purge('doc_intelligence');

        $schema = Schema::connection('doc_intelligence');
        if (! $schema->hasTable('doc_embeddings')) {
            $this->artisan('migrate', [
                '--database' => 'doc_intelligence',
                '--path' => 'database/migrations',
                '--realpath' => false,
            ]);
        }

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
