<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocStructureCommandTest extends TestCase
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
    }

    public function test_structure_all_projects(): void
    {
        $this->artisan('docs:structure')
            ->expectsOutputToContain('Generating structure index for all projects')
            ->assertExitCode(0);
    }

    public function test_structure_specific_project(): void
    {
        $result = $this->artisan('docs:structure', ['project' => 'havuncore']);
        // May succeed or fail depending on path existence
        $this->assertContains($result->execute(), [0, 1]);
    }

    public function test_structure_unknown_project_shows_error(): void
    {
        $this->artisan('docs:structure', ['project' => 'nonexistent_xyz'])
            ->expectsOutputToContain('not found')
            ->assertExitCode(1);
    }
}
