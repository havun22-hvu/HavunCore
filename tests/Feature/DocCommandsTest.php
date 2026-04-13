<?php

namespace Tests\Feature;

use App\Models\DocIntelligence\DocEmbedding;
use App\Models\DocIntelligence\DocIssue;
use App\Models\DocIntelligence\DocRelation;
use App\Services\DocIntelligence\DocIndexer;
use App\Services\DocIntelligence\IssueDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\CreatesDocIntelligenceTables;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('doc-intelligence')]
class DocCommandsTest extends TestCase
{
    use CreatesDocIntelligenceTables;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDocIntelligenceTables();

        DocEmbedding::query()->delete();
        DocIssue::query()->delete();
        DocRelation::query()->delete();

        Http::fake([
            '127.0.0.1:11434/*' => Http::response(['embedding' => null], 200),
        ]);
    }

    // ===================================================================
    // docs:issues — list issues
    // ===================================================================

    public function test_docs_issues_shows_no_issues_message(): void
    {
        $this->artisan('docs:issues')
            ->expectsOutputToContain('No open issues found')
            ->assertExitCode(0);
    }

    public function test_docs_issues_lists_open_issues(): void
    {
        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_HIGH,
            'title' => 'Old documentation found',
            'details' => [],
            'affected_files' => ['docs/old.md'],
            'suggested_action' => 'Update the doc',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $this->artisan('docs:issues')
            ->expectsOutputToContain('Open Issues (1)')
            ->expectsOutputToContain('Old documentation found')
            ->expectsOutputToContain('testproject')
            ->assertExitCode(0);
    }

    public function test_docs_issues_filters_by_project(): void
    {
        DocIssue::create([
            'project' => 'projecta',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Issue in A',
            'details' => [],
            'affected_files' => ['a.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);
        DocIssue::create([
            'project' => 'projectb',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Issue in B',
            'details' => [],
            'affected_files' => ['b.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $this->artisan('docs:issues', ['project' => 'projecta'])
            ->expectsOutputToContain('Issue in A')
            ->assertExitCode(0);
    }

    public function test_docs_issues_filters_by_severity(): void
    {
        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_HIGH,
            'title' => 'High issue',
            'details' => [],
            'affected_files' => ['a.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);
        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Low issue',
            'details' => [],
            'affected_files' => ['b.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $this->artisan('docs:issues', ['--severity' => 'high'])
            ->expectsOutputToContain('High issue')
            ->assertExitCode(0);
    }

    public function test_docs_issues_filters_by_type(): void
    {
        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_DUPLICATE,
            'severity' => DocIssue::SEVERITY_MEDIUM,
            'title' => 'Duplicate found',
            'details' => [],
            'affected_files' => ['a.md', 'b.md'],
            'suggested_action' => 'Merge',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $this->artisan('docs:issues', ['--type' => 'duplicate'])
            ->expectsOutputToContain('Duplicate found')
            ->assertExitCode(0);
    }

    // ===================================================================
    // docs:issues --resolve / --ignore
    // ===================================================================

    public function test_docs_issues_resolves_issue(): void
    {
        $issue = DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Resolvable issue',
            'details' => [],
            'affected_files' => ['a.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $this->artisan('docs:issues', ['--resolve' => $issue->id])
            ->expectsOutputToContain('marked as resolved')
            ->assertExitCode(0);

        $issue->refresh();
        $this->assertEquals(DocIssue::STATUS_RESOLVED, $issue->status);
    }

    public function test_docs_issues_resolve_nonexistent_fails(): void
    {
        $this->artisan('docs:issues', ['--resolve' => 99999])
            ->expectsOutputToContain('not found')
            ->assertExitCode(1);
    }

    public function test_docs_issues_ignores_issue(): void
    {
        $issue = DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Ignorable issue',
            'details' => [],
            'affected_files' => ['a.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $this->artisan('docs:issues', ['--ignore' => $issue->id])
            ->expectsOutputToContain('marked as ignored')
            ->assertExitCode(0);

        $issue->refresh();
        $this->assertEquals(DocIssue::STATUS_IGNORED, $issue->status);
    }

    public function test_docs_issues_ignore_nonexistent_fails(): void
    {
        $this->artisan('docs:issues', ['--ignore' => 99999])
            ->expectsOutputToContain('not found')
            ->assertExitCode(1);
    }

    // ===================================================================
    // docs:issues --summary
    // ===================================================================

    public function test_docs_issues_summary_empty(): void
    {
        $this->artisan('docs:issues', ['--summary' => true])
            ->expectsOutputToContain('No open issues found')
            ->assertExitCode(0);
    }

    public function test_docs_issues_summary_shows_totals(): void
    {
        DocIssue::create([
            'project' => 'projecta',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_HIGH,
            'title' => 'High in A',
            'details' => [],
            'affected_files' => ['a.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);
        DocIssue::create([
            'project' => 'projectb',
            'issue_type' => DocIssue::TYPE_DUPLICATE,
            'severity' => DocIssue::SEVERITY_MEDIUM,
            'title' => 'Medium in B',
            'details' => [],
            'affected_files' => ['b.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $this->artisan('docs:issues', ['--summary' => true])
            ->expectsOutputToContain('SAMENVATTING')
            ->expectsOutputToContain('PER PROJECT')
            ->expectsOutputToContain('projecta')
            ->expectsOutputToContain('projectb')
            ->assertExitCode(0);
    }

    // ===================================================================
    // docs:search
    // ===================================================================

    public function test_docs_search_no_results(): void
    {
        $this->artisan('docs:search', ['query' => 'nonexistent'])
            ->expectsOutputToContain('No results found')
            ->assertExitCode(0);
    }

    public function test_docs_search_returns_results(): void
    {
        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/deploy.md',
            'content' => 'Guide about deployment to production servers with docker',
            'content_hash' => hash('sha256', 'deploy content'),
            'embedding' => ['deploy' => 0.8, 'production' => 0.2],
            'embedding_model' => 'tfidf-fallback',
            'file_type' => 'docs',
            'token_count' => 50,
            'file_modified_at' => now(),
        ]);

        $this->artisan('docs:search', ['query' => 'deploy production'])
            ->expectsOutputToContain('testproject')
            ->assertExitCode(0);
    }

    public function test_docs_search_with_project_filter(): void
    {
        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/guide.md',
            'content' => 'Guide content here',
            'content_hash' => hash('sha256', 'guide'),
            'embedding' => ['guide' => 0.5],
            'embedding_model' => 'tfidf-fallback',
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        $this->artisan('docs:search', [
            'query' => 'guide',
            '--project' => 'testproject',
        ])
            ->expectsOutputToContain('testproject')
            ->assertExitCode(0);
    }

    public function test_docs_search_with_type_filter(): void
    {
        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/guide.md',
            'content' => 'Guide content',
            'content_hash' => hash('sha256', 'guide type'),
            'embedding' => ['guide' => 0.5],
            'embedding_model' => 'tfidf-fallback',
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        $this->artisan('docs:search', [
            'query' => 'guide',
            '--type' => 'docs',
        ])
            ->expectsOutputToContain('Filter: type=docs')
            ->assertExitCode(0);
    }

    // ===================================================================
    // docs:detect
    // ===================================================================

    public function test_docs_detect_runs_detection(): void
    {
        $this->artisan('docs:detect')
            ->expectsOutputToContain('Detecting issues')
            ->expectsOutputToContain('Results')
            ->assertExitCode(0);
    }

    public function test_docs_detect_with_project(): void
    {
        $this->artisan('docs:detect', ['project' => 'testproject'])
            ->expectsOutputToContain('Detecting issues')
            ->assertExitCode(0);
    }

    public function test_docs_detect_with_index_option(): void
    {
        // The --index option runs indexer first; it will find no real files but should not error
        $this->artisan('docs:detect', ['--index' => true])
            ->expectsOutputToContain('Indexing first')
            ->expectsOutputToContain('Detecting issues')
            ->assertExitCode(0);
    }

    public function test_docs_detect_shows_total_when_issues_found(): void
    {
        // Create data that will trigger outdated detection
        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/old.md',
            'content' => 'Old content without links',
            'content_hash' => hash('sha256', 'old'),
            'embedding' => null,
            'embedding_model' => null,
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now()->subDays(120),
        ]);

        $this->artisan('docs:detect', ['project' => 'testproject'])
            ->expectsOutputToContain('Total new issues')
            ->assertExitCode(0);
    }

    // ===================================================================
    // docs:index
    // ===================================================================

    public function test_docs_index_all_projects(): void
    {
        $indexer = $this->mock(DocIndexer::class);
        $indexer->shouldReceive('indexAll')
            ->once()
            ->andReturn(['havuncore' => ['indexed' => 5, 'indexed_md' => 5, 'indexed_code' => 0, 'skipped' => 0, 'errors' => []]]);

        $this->artisan('docs:index')
            ->expectsOutputToContain('Indexing all projects')
            ->assertExitCode(0);
    }

    public function test_docs_index_specific_project(): void
    {
        $indexer = $this->mock(DocIndexer::class);
        $indexer->shouldReceive('indexProject')
            ->with('havuncore', false, true)
            ->once()
            ->andReturn(['indexed' => 3, 'indexed_md' => 3, 'indexed_code' => 0, 'skipped' => 1, 'errors' => []]);

        $this->artisan('docs:index', ['project' => 'havuncore'])
            ->assertExitCode(0);
    }

    public function test_docs_index_unknown_project_shows_error(): void
    {
        $indexer = $this->mock(DocIndexer::class);
        $indexer->shouldReceive('indexProject')
            ->with('nonexistent_xyz', false, true)
            ->once()
            ->andReturn(['error' => 'Project not found: nonexistent_xyz']);

        $this->artisan('docs:index', ['project' => 'nonexistent_xyz'])
            ->expectsOutputToContain('not found')
            ->assertExitCode(1);
    }

    public function test_docs_index_with_no_code_option(): void
    {
        $indexer = $this->mock(DocIndexer::class);
        $indexer->shouldReceive('indexProject')
            ->with('havuncore', false, false)
            ->once()
            ->andReturn(['indexed' => 2, 'indexed_md' => 2, 'indexed_code' => 0, 'skipped' => 0, 'errors' => []]);

        $this->artisan('docs:index', ['project' => 'havuncore', '--no-code' => true])
            ->assertExitCode(0);
    }

    // ===================================================================
    // docs:watch --once
    // ===================================================================

    public function test_docs_watch_once(): void
    {
        $indexer = $this->mock(DocIndexer::class)->shouldIgnoreMissing();
        $indexer->shouldReceive('indexProject')
            ->andReturn(['indexed' => 1, 'indexed_md' => 1, 'indexed_code' => 0, 'skipped' => 0, 'errors' => []]);

        $this->artisan('docs:watch', ['--once' => true])
            ->assertExitCode(0);
    }
}
