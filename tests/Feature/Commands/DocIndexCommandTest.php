<?php

namespace Tests\Feature\Commands;

use App\Services\DocIntelligence\DocIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class DocIndexCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '127.0.0.1:11434/*' => Http::response(['embedding' => null], 200),
        ]);
    }

    public function test_index_all_projects_with_successful_results(): void
    {
        $indexer = Mockery::mock(DocIndexer::class);
        $indexer->shouldReceive('indexAll')
            ->with(false, true)
            ->once()
            ->andReturn([
                'havuncore' => [
                    'indexed' => 10,
                    'indexed_md' => 7,
                    'indexed_code' => 3,
                    'skipped' => 2,
                    'errors' => [],
                ],
                'havunadmin' => [
                    'indexed' => 5,
                    'indexed_md' => 5,
                    'indexed_code' => 0,
                    'skipped' => 1,
                    'errors' => [],
                ],
            ]);

        $this->app->instance(DocIndexer::class, $indexer);

        $this->artisan('docs:index')
            ->expectsOutputToContain('Indexing all projects')
            ->expectsOutputToContain('havuncore: 10 indexed (7 md, 3 code), 2 skipped')
            ->expectsOutputToContain('havunadmin: 5 indexed (5 md, 0 code), 1 skipped')
            ->assertExitCode(0);
    }

    public function test_index_all_projects_with_errors_in_results(): void
    {
        $indexer = Mockery::mock(DocIndexer::class);
        $indexer->shouldReceive('indexAll')
            ->with(false, true)
            ->once()
            ->andReturn([
                'havuncore' => [
                    'indexed' => 3,
                    'indexed_md' => 3,
                    'indexed_code' => 0,
                    'skipped' => 0,
                    'errors' => ['Failed to read file X.md'],
                ],
                'badproject' => [
                    'error' => 'Project path not found',
                ],
            ]);

        $this->app->instance(DocIndexer::class, $indexer);

        $this->artisan('docs:index')
            ->expectsOutputToContain('Indexing all projects')
            ->expectsOutputToContain('Failed to read file X.md')
            ->expectsOutputToContain('badproject: Project path not found')
            ->assertExitCode(0);
    }

    public function test_index_all_with_no_code_option(): void
    {
        $indexer = Mockery::mock(DocIndexer::class);
        $indexer->shouldReceive('indexAll')
            ->with(false, false)
            ->once()
            ->andReturn([]);

        $this->app->instance(DocIndexer::class, $indexer);

        $this->artisan('docs:index', ['--no-code' => true])
            ->expectsOutputToContain('MD only')
            ->assertExitCode(0);
    }

    public function test_index_all_with_force_option(): void
    {
        $indexer = Mockery::mock(DocIndexer::class);
        $indexer->shouldReceive('indexAll')
            ->with(true, true)
            ->once()
            ->andReturn([]);

        $this->app->instance(DocIndexer::class, $indexer);

        $this->artisan('docs:index', ['project' => 'all', '--force' => true])
            ->expectsOutputToContain('Indexing all projects')
            ->assertExitCode(0);
    }

    public function test_index_specific_project_success(): void
    {
        $indexer = Mockery::mock(DocIndexer::class);
        $indexer->shouldReceive('indexProject')
            ->with('havuncore', false, true)
            ->once()
            ->andReturn([
                'indexed' => 8,
                'indexed_md' => 5,
                'indexed_code' => 3,
                'skipped' => 1,
                'errors' => [],
            ]);

        $this->app->instance(DocIndexer::class, $indexer);

        $this->artisan('docs:index', ['project' => 'havuncore'])
            ->expectsOutputToContain('Indexing project: havuncore')
            ->expectsOutputToContain('Indexed: 8 (5 md, 3 code), Skipped: 1')
            ->assertExitCode(0);
    }

    public function test_index_specific_project_with_error(): void
    {
        $indexer = Mockery::mock(DocIndexer::class);
        $indexer->shouldReceive('indexProject')
            ->with('nonexistent', false, true)
            ->once()
            ->andReturn([
                'error' => 'Project path not found: nonexistent',
            ]);

        $this->app->instance(DocIndexer::class, $indexer);

        $this->artisan('docs:index', ['project' => 'nonexistent'])
            ->expectsOutputToContain('Project path not found')
            ->assertExitCode(1);
    }

    public function test_index_specific_project_with_warnings(): void
    {
        $indexer = Mockery::mock(DocIndexer::class);
        $indexer->shouldReceive('indexProject')
            ->with('havuncore', false, true)
            ->once()
            ->andReturn([
                'indexed' => 5,
                'indexed_md' => 5,
                'indexed_code' => 0,
                'skipped' => 0,
                'errors' => ['Could not parse file.md', 'Embedding failed for doc.md'],
            ]);

        $this->app->instance(DocIndexer::class, $indexer);

        $this->artisan('docs:index', ['project' => 'havuncore'])
            ->expectsOutputToContain('Errors')
            ->expectsOutputToContain('Could not parse file.md')
            ->expectsOutputToContain('Embedding failed for doc.md')
            ->assertExitCode(0);
    }

    public function test_index_specific_project_no_code(): void
    {
        $indexer = Mockery::mock(DocIndexer::class);
        $indexer->shouldReceive('indexProject')
            ->with('havuncore', false, false)
            ->once()
            ->andReturn([
                'indexed' => 3,
                'indexed_md' => 3,
                'indexed_code' => 0,
                'skipped' => 0,
                'errors' => [],
            ]);

        $this->app->instance(DocIndexer::class, $indexer);

        $this->artisan('docs:index', ['project' => 'havuncore', '--no-code' => true])
            ->expectsOutputToContain('MD only')
            ->assertExitCode(0);
    }

    public function test_index_specific_project_result_without_indexed_md_key(): void
    {
        // Tests the fallback: $result['indexed_md'] ?? $result['indexed']
        $indexer = Mockery::mock(DocIndexer::class);
        $indexer->shouldReceive('indexProject')
            ->with('havuncore', false, true)
            ->once()
            ->andReturn([
                'indexed' => 4,
                'skipped' => 0,
                'errors' => [],
            ]);

        $this->app->instance(DocIndexer::class, $indexer);

        $this->artisan('docs:index', ['project' => 'havuncore'])
            ->expectsOutputToContain('Indexed: 4 (4 md, 0 code), Skipped: 0')
            ->assertExitCode(0);
    }

    public function test_index_all_result_without_indexed_md_key(): void
    {
        // Tests the fallback for all-project path: $result['indexed_md'] ?? $result['indexed']
        $indexer = Mockery::mock(DocIndexer::class);
        $indexer->shouldReceive('indexAll')
            ->with(false, true)
            ->once()
            ->andReturn([
                'testproj' => [
                    'indexed' => 6,
                    'skipped' => 0,
                    'errors' => [],
                ],
            ]);

        $this->app->instance(DocIndexer::class, $indexer);

        $this->artisan('docs:index')
            ->expectsOutputToContain('testproj: 6 indexed (6 md, 0 code), 0 skipped')
            ->assertExitCode(0);
    }
}
