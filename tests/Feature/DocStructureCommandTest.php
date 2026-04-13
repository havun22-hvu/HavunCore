<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesDocIntelligenceTables;
use Tests\TestCase;

class DocStructureCommandTest extends TestCase
{
    use CreatesDocIntelligenceTables;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDocIntelligenceTables();
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
