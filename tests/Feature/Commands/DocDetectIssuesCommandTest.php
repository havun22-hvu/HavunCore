<?php

namespace Tests\Feature\Commands;

use App\Services\DocIntelligence\DocIndexer;
use App\Services\DocIntelligence\IssueDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class DocDetectIssuesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '127.0.0.1:11434/*' => Http::response(['embedding' => null], 200),
        ]);
    }

    public function test_detect_no_issues_shows_clean_message(): void
    {
        $detector = Mockery::mock(IssueDetector::class);
        $detector->shouldReceive('detectAll')
            ->with(null)
            ->once()
            ->andReturn([
                'duplicates' => 0,
                'outdated' => 0,
                'broken_links' => 0,
                'inconsistencies' => 0,
            ]);

        $this->app->instance(IssueDetector::class, $detector);

        $this->artisan('docs:detect')
            ->expectsOutputToContain('Detecting issues')
            ->expectsOutputToContain('No new issues detected')
            ->assertExitCode(0);
    }

    public function test_detect_with_issues_shows_total_and_warning(): void
    {
        $detector = Mockery::mock(IssueDetector::class);
        $detector->shouldReceive('detectAll')
            ->with(null)
            ->once()
            ->andReturn([
                'duplicates' => 2,
                'outdated' => 1,
                'broken_links' => 0,
                'inconsistencies' => 3,
            ]);

        $this->app->instance(IssueDetector::class, $detector);

        $this->artisan('docs:detect')
            ->expectsOutputToContain('Detecting issues')
            ->expectsOutputToContain('Duplicates found: 2')
            ->expectsOutputToContain('Outdated docs: 1')
            ->expectsOutputToContain('Broken links: 0')
            ->expectsOutputToContain('Inconsistencies: 3')
            ->expectsOutputToContain('Total new issues: 6')
            ->expectsOutputToContain('docs:issues')
            ->assertExitCode(0);
    }

    public function test_detect_with_project_argument(): void
    {
        $detector = Mockery::mock(IssueDetector::class);
        $detector->shouldReceive('detectAll')
            ->with('havuncore')
            ->once()
            ->andReturn([
                'duplicates' => 0,
                'outdated' => 0,
                'broken_links' => 0,
                'inconsistencies' => 0,
            ]);

        $this->app->instance(IssueDetector::class, $detector);

        $this->artisan('docs:detect', ['project' => 'havuncore'])
            ->expectsOutputToContain('No new issues detected')
            ->assertExitCode(0);
    }

    public function test_detect_with_index_option_indexes_all_first(): void
    {
        $indexer = Mockery::mock(DocIndexer::class);
        $indexer->shouldReceive('indexAll')
            ->once()
            ->andReturn([]);

        $detector = Mockery::mock(IssueDetector::class);
        $detector->shouldReceive('detectAll')
            ->with(null)
            ->once()
            ->andReturn([
                'duplicates' => 0,
                'outdated' => 0,
                'broken_links' => 0,
                'inconsistencies' => 0,
            ]);

        $this->app->instance(DocIndexer::class, $indexer);
        $this->app->instance(IssueDetector::class, $detector);

        $this->artisan('docs:detect', ['--index' => true])
            ->expectsOutputToContain('Indexing first')
            ->expectsOutputToContain('No new issues detected')
            ->assertExitCode(0);
    }

    public function test_detect_with_index_option_and_project_indexes_project(): void
    {
        $indexer = Mockery::mock(DocIndexer::class);
        $indexer->shouldReceive('indexProject')
            ->with('havuncore')
            ->once()
            ->andReturn(['indexed' => 5, 'skipped' => 0]);

        $detector = Mockery::mock(IssueDetector::class);
        $detector->shouldReceive('detectAll')
            ->with('havuncore')
            ->once()
            ->andReturn([
                'duplicates' => 1,
                'outdated' => 0,
                'broken_links' => 0,
                'inconsistencies' => 0,
            ]);

        $this->app->instance(DocIndexer::class, $indexer);
        $this->app->instance(IssueDetector::class, $detector);

        $this->artisan('docs:detect', ['project' => 'havuncore', '--index' => true])
            ->expectsOutputToContain('Indexing first')
            ->expectsOutputToContain('Total new issues: 1')
            ->assertExitCode(0);
    }
}
