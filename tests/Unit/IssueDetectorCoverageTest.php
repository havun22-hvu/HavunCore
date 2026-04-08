<?php

namespace Tests\Unit;

use App\Models\DocIntelligence\DocEmbedding;
use App\Models\DocIntelligence\DocIssue;
use App\Models\DocIntelligence\DocRelation;
use App\Services\DocIntelligence\DocIndexer;
use App\Services\DocIntelligence\IssueDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IssueDetectorCoverageTest extends TestCase
{
    use RefreshDatabase;

    private DocIndexer $indexer;
    private IssueDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.doc_intelligence.database' => ':memory:',
        ]);

        DB::purge('doc_intelligence');

        $schema = Schema::connection('doc_intelligence');
        if (! $schema->hasTable('doc_embeddings')) {
            $this->artisan('migrate', [
                '--database' => 'doc_intelligence',
                '--path' => 'database/migrations',
                '--realpath' => false,
            ]);
        }

        DocEmbedding::query()->delete();
        DocIssue::query()->delete();
        DocRelation::query()->delete();

        Http::fake([
            '127.0.0.1:11434/*' => Http::response(['embedding' => null], 200),
        ]);

        $this->indexer = new DocIndexer();
        $this->detector = new IssueDetector($this->indexer);
    }

    // ===================================================================
    // getOpenIssues
    // ===================================================================

    public function test_get_open_issues_returns_sorted_by_severity(): void
    {
        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Low issue',
            'details' => [],
            'affected_files' => ['a.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_INCONSISTENT,
            'severity' => DocIssue::SEVERITY_HIGH,
            'title' => 'High issue',
            'details' => [],
            'affected_files' => ['b.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_DUPLICATE,
            'severity' => DocIssue::SEVERITY_MEDIUM,
            'title' => 'Medium issue',
            'details' => [],
            'affected_files' => ['c.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        // Resolved issue — should not be included
        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_HIGH,
            'title' => 'Resolved',
            'details' => [],
            'affected_files' => ['d.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_RESOLVED,
        ]);

        $issues = $this->detector->getOpenIssues('testproject');

        $this->assertCount(3, $issues);
        // First should be high severity
        $this->assertEquals(DocIssue::SEVERITY_HIGH, $issues->first()->severity);
        // Last should be low severity
        $this->assertEquals(DocIssue::SEVERITY_LOW, $issues->last()->severity);
    }

    public function test_get_open_issues_all_projects(): void
    {
        DocIssue::create([
            'project' => 'projecta',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'A issue',
            'details' => [],
            'affected_files' => ['a.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        DocIssue::create([
            'project' => 'projectb',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'B issue',
            'details' => [],
            'affected_files' => ['b.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $issues = $this->detector->getOpenIssues(null);

        $this->assertCount(2, $issues);
    }

    // ===================================================================
    // getIssueSummary
    // ===================================================================

    public function test_get_issue_summary_groups_by_project(): void
    {
        DocIssue::create([
            'project' => 'projecta',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_HIGH,
            'title' => 'High A',
            'details' => [],
            'affected_files' => ['a.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        DocIssue::create([
            'project' => 'projecta',
            'issue_type' => DocIssue::TYPE_DUPLICATE,
            'severity' => DocIssue::SEVERITY_MEDIUM,
            'title' => 'Medium A',
            'details' => [],
            'affected_files' => ['b.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        DocIssue::create([
            'project' => 'projectb',
            'issue_type' => DocIssue::TYPE_BROKEN_LINK,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Low B',
            'details' => [],
            'affected_files' => ['c.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        // Resolved — should not be counted
        DocIssue::create([
            'project' => 'projecta',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Resolved',
            'details' => [],
            'affected_files' => ['d.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_RESOLVED,
        ]);

        $summary = $this->detector->getIssueSummary();

        $this->assertArrayHasKey('projecta', $summary);
        $this->assertArrayHasKey('projectb', $summary);
        $this->assertEquals(2, $summary['projecta']['total']);
        $this->assertEquals(1, $summary['projecta']['high']);
        $this->assertEquals(1, $summary['projecta']['medium']);
        $this->assertEquals(0, $summary['projecta']['low']);
        $this->assertEquals(1, $summary['projectb']['total']);
        $this->assertEquals(1, $summary['projectb']['low']);

        // Check by_type
        $this->assertEquals(1, $summary['projecta']['by_type'][DocIssue::TYPE_OUTDATED]);
        $this->assertEquals(1, $summary['projecta']['by_type'][DocIssue::TYPE_DUPLICATE]);
        $this->assertEquals(1, $summary['projectb']['by_type'][DocIssue::TYPE_BROKEN_LINK]);
    }

    // ===================================================================
    // detectAll
    // ===================================================================

    public function test_detect_all_returns_all_issue_types(): void
    {
        // Create some embeddings to check
        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/guide.md',
            'content' => 'Guide content with [broken link](missing.md)',
            'content_hash' => hash('sha256', 'guide'),
            'embedding' => ['guide' => 1.0],
            'embedding_model' => 'tfidf-fallback',
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now()->subDays(200),
        ]);

        $results = $this->detector->detectAll('testproject');

        $this->assertArrayHasKey('duplicates', $results);
        $this->assertArrayHasKey('outdated', $results);
        $this->assertArrayHasKey('broken_links', $results);
        $this->assertArrayHasKey('inconsistencies', $results);
    }

    // ===================================================================
    // detectInconsistencies
    // ===================================================================

    public function test_detect_inconsistencies_finds_different_prices(): void
    {
        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/pricing-a.md',
            'content' => 'De toeslag bedraagt toeslag €5,00 per maand.',
            'content_hash' => hash('sha256', 'pricing-a'),
            'embedding' => null,
            'embedding_model' => null,
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/pricing-b.md',
            'content' => 'De toeslag bedraagt toeslag €10,00 per maand.',
            'content_hash' => hash('sha256', 'pricing-b'),
            'embedding' => null,
            'embedding_model' => null,
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        $found = $this->detector->detectInconsistencies('testproject');

        // Should detect inconsistency for 'toeslag' with €5,00 vs €10,00
        $this->assertGreaterThanOrEqual(1, $found);
    }

    public function test_detect_inconsistencies_ignores_same_prices(): void
    {
        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/pricing-a.md',
            'content' => 'De toeslag bedraagt toeslag €5,00 per maand.',
            'content_hash' => hash('sha256', 'same-a'),
            'embedding' => null,
            'embedding_model' => null,
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/pricing-b.md',
            'content' => 'De toeslag bedraagt toeslag €5,00 per maand.',
            'content_hash' => hash('sha256', 'same-b'),
            'embedding' => null,
            'embedding_model' => null,
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        $found = $this->detector->detectInconsistencies('testproject');

        // Same price = no inconsistency
        $this->assertEquals(0, $found);
    }
}
