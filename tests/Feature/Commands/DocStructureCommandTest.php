<?php

namespace Tests\Feature\Commands;

use App\Services\DocIntelligence\StructureIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\CreatesDocIntelligenceTables;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('doc-intelligence')]
class DocStructureCommandTest extends TestCase
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

    public function test_structure_all_projects_with_results(): void
    {
        $indexer = Mockery::mock(StructureIndexer::class);
        $indexer->shouldReceive('indexAll')
            ->with(false)
            ->once()
            ->andReturn([
                'havuncore' => [
                    'models' => 5,
                    'controllers' => 8,
                    'services' => 3,
                    'migrations' => 12,
                    'routes' => 20,
                ],
                'havunadmin' => [
                    'models' => 3,
                    'controllers' => 4,
                    'services' => 1,
                    'migrations' => 6,
                    'routes' => 10,
                ],
            ]);

        $this->app->instance(StructureIndexer::class, $indexer);

        $this->artisan('docs:structure')
            ->expectsOutputToContain('Generating structure index for all projects')
            ->expectsOutputToContain('havuncore: 5m / 8c / 3s / 12mig / 20r')
            ->expectsOutputToContain('havunadmin: 3m / 4c / 1s / 6mig / 10r')
            ->assertExitCode(0);
    }

    public function test_structure_all_projects_with_errors(): void
    {
        $indexer = Mockery::mock(StructureIndexer::class);
        $indexer->shouldReceive('indexAll')
            ->with(false)
            ->once()
            ->andReturn([
                'havuncore' => [
                    'models' => 5,
                    'controllers' => 8,
                    'services' => 3,
                    'migrations' => 12,
                    'routes' => 20,
                ],
                'badproject' => [
                    'error' => 'Project path not found: badproject',
                ],
            ]);

        $this->app->instance(StructureIndexer::class, $indexer);

        $this->artisan('docs:structure')
            ->expectsOutputToContain('badproject: Project path not found')
            ->assertExitCode(0);
    }

    public function test_structure_all_with_force_option(): void
    {
        $indexer = Mockery::mock(StructureIndexer::class);
        $indexer->shouldReceive('indexAll')
            ->with(true)
            ->once()
            ->andReturn([]);

        $this->app->instance(StructureIndexer::class, $indexer);

        $this->artisan('docs:structure', ['--force' => true])
            ->expectsOutputToContain('Generating structure index for all projects')
            ->assertExitCode(0);
    }

    public function test_structure_specific_project_success(): void
    {
        $indexer = Mockery::mock(StructureIndexer::class);
        $indexer->shouldReceive('indexProject')
            ->with('havuncore', false)
            ->once()
            ->andReturn([
                'models' => 5,
                'controllers' => 8,
                'services' => 3,
                'migrations' => 12,
                'routes' => 20,
                'summary_preview' => 'Laravel project with 5 models and 8 controllers',
            ]);

        $this->app->instance(StructureIndexer::class, $indexer);

        $this->artisan('docs:structure', ['project' => 'havuncore'])
            ->expectsOutputToContain('Generating structure index for: havuncore')
            ->expectsOutputToContain('Models: 5 | Controllers: 8 | Services: 3 | Migrations: 12 | Routes: 20')
            ->expectsOutputToContain('Laravel project with 5 models and 8 controllers')
            ->assertExitCode(0);
    }

    public function test_structure_specific_project_error(): void
    {
        $indexer = Mockery::mock(StructureIndexer::class);
        $indexer->shouldReceive('indexProject')
            ->with('nonexistent', false)
            ->once()
            ->andReturn([
                'error' => 'Project path not found: nonexistent',
            ]);

        $this->app->instance(StructureIndexer::class, $indexer);

        $this->artisan('docs:structure', ['project' => 'nonexistent'])
            ->expectsOutputToContain('Project path not found')
            ->assertExitCode(1);
    }

    public function test_structure_specific_project_with_force(): void
    {
        $indexer = Mockery::mock(StructureIndexer::class);
        $indexer->shouldReceive('indexProject')
            ->with('havuncore', true)
            ->once()
            ->andReturn([
                'models' => 2,
                'controllers' => 3,
                'services' => 1,
                'migrations' => 4,
                'routes' => 8,
                'summary_preview' => 'Reindexed project',
            ]);

        $this->app->instance(StructureIndexer::class, $indexer);

        $this->artisan('docs:structure', ['project' => 'havuncore', '--force' => true])
            ->expectsOutputToContain('Generating structure index for: havuncore')
            ->assertExitCode(0);
    }
}
