<?php

namespace Tests\Unit;

use App\Models\DocIntelligence\DocEmbedding;
use App\Models\DocIntelligence\DocIssue;
use App\Models\DocIntelligence\DocRelation;
use App\Services\DocIntelligence\DocIndexer;
use App\Services\DocIntelligence\IssueDetector;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocIntelligenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocIndexer $indexer;
    private IssueDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        DocEmbedding::query()->delete();
        DocIssue::query()->delete();
        DocRelation::query()->delete();

        // Fake HTTP to prevent real Ollama calls
        Http::fake([
            '127.0.0.1:11434/*' => Http::response(['embedding' => null], 200),
        ]);

        $this->indexer = new DocIndexer();
        $this->detector = new IssueDetector($this->indexer);
    }

    // ===================================================================
    // IssueDetector — detectDuplicates()
    // ===================================================================

    public function test_detect_duplicates_finds_similar_documents(): void
    {
        // Create two docs with identical embeddings (similarity = 1.0 > 0.90 threshold)
        $embedding = ['word1' => 0.5, 'word2' => 0.3, 'word3' => 0.2];

        $this->createEmbedding('testproject', 'docs/guide-a.md', 'Guide about deployment', $embedding, 'docs');
        $this->createEmbedding('testproject', 'docs/guide-b.md', 'Guide about deployment copy', $embedding, 'docs');

        $found = $this->detector->detectDuplicates('testproject');

        $this->assertEquals(1, $found);

        // Verify issue was created
        $issue = DocIssue::where('issue_type', DocIssue::TYPE_DUPLICATE)->first();
        $this->assertNotNull($issue);
        $this->assertEquals('testproject', $issue->project);
        $this->assertEquals(DocIssue::SEVERITY_MEDIUM, $issue->severity);
        $this->assertCount(2, $issue->affected_files);

        // Verify relation was created
        $relation = DocRelation::where('relation_type', DocRelation::TYPE_DUPLICATES)->first();
        $this->assertNotNull($relation);
        $this->assertGreaterThanOrEqual(0.90, $relation->confidence);
    }

    public function test_detect_duplicates_ignores_different_documents(): void
    {
        // Completely different embeddings → similarity 0.0
        $this->createEmbedding('testproject', 'docs/guide-a.md', 'About servers', ['server' => 0.8, 'deploy' => 0.2], 'docs');
        $this->createEmbedding('testproject', 'docs/guide-b.md', 'About cooking', ['cooking' => 0.7, 'recipe' => 0.3], 'docs');

        $found = $this->detector->detectDuplicates('testproject');

        $this->assertEquals(0, $found);
        $this->assertEquals(0, DocIssue::count());
    }

    public function test_detect_duplicates_skips_shared_files(): void
    {
        $embedding = ['word1' => 0.5, 'word2' => 0.5];

        // CLAUDE.md is in sharedFilePatterns — should be skipped
        $this->createEmbedding('testproject', 'CLAUDE.md', 'Instructions', $embedding, 'docs');
        $this->createEmbedding('testproject', 'docs/other.md', 'Instructions copy', $embedding, 'docs');

        $found = $this->detector->detectDuplicates('testproject');

        // CLAUDE.md is filtered out, so only 1 doc remains → no pairs to compare
        $this->assertEquals(0, $found);
    }

    public function test_detect_duplicates_skips_code_file_types(): void
    {
        $embedding = ['class' => 0.6, 'model' => 0.4];

        // File type 'model' is in skipDuplicateTypes
        $this->createEmbedding('testproject', 'app/Models/User.php', 'User model', $embedding, 'model');
        $this->createEmbedding('testproject', 'app/Models/Post.php', 'Post model', $embedding, 'model');

        $found = $this->detector->detectDuplicates('testproject');

        $this->assertEquals(0, $found);
    }

    public function test_detect_duplicates_does_not_duplicate_existing_issues(): void
    {
        $embedding = ['word1' => 0.5, 'word2' => 0.5];

        $this->createEmbedding('testproject', 'docs/a.md', 'Content A', $embedding, 'docs');
        $this->createEmbedding('testproject', 'docs/b.md', 'Content B', $embedding, 'docs');

        // First run: creates issue
        $found1 = $this->detector->detectDuplicates('testproject');
        $this->assertEquals(1, $found1);

        // Second run: should not create duplicate issue
        $found2 = $this->detector->detectDuplicates('testproject');
        $this->assertEquals(0, $found2);

        $this->assertEquals(1, DocIssue::where('issue_type', DocIssue::TYPE_DUPLICATE)->count());
    }

    public function test_detect_duplicates_scans_all_projects_when_null(): void
    {
        $embedding = ['word1' => 0.5, 'word2' => 0.5];

        $this->createEmbedding('projecta', 'docs/a.md', 'Content', $embedding, 'docs');
        $this->createEmbedding('projecta', 'docs/b.md', 'Content copy', $embedding, 'docs');
        $this->createEmbedding('projectb', 'docs/c.md', 'Content', $embedding, 'docs');
        $this->createEmbedding('projectb', 'docs/d.md', 'Content copy', $embedding, 'docs');

        $found = $this->detector->detectDuplicates(null);

        // Should find duplicates in BOTH projects
        $this->assertEquals(2, $found);
    }

    public function test_detect_duplicates_skips_docs_without_embeddings(): void
    {
        // Doc without embedding (null)
        $this->createEmbedding('testproject', 'docs/a.md', 'Content A', null, 'docs');
        $this->createEmbedding('testproject', 'docs/b.md', 'Content B', null, 'docs');

        $found = $this->detector->detectDuplicates('testproject');

        $this->assertEquals(0, $found);
    }

    // ===================================================================
    // IssueDetector — detectOutdated()
    // ===================================================================

    public function test_detect_outdated_finds_old_documents(): void
    {
        // Document modified 120 days ago (> 90 day threshold)
        $this->createEmbedding('testproject', 'docs/old-guide.md', 'Old content', null, 'docs', now()->subDays(120));

        $found = $this->detector->detectOutdated('testproject');

        $this->assertEquals(1, $found);

        $issue = DocIssue::where('issue_type', DocIssue::TYPE_OUTDATED)->first();
        $this->assertNotNull($issue);
        $this->assertEquals(DocIssue::SEVERITY_LOW, $issue->severity);
        $this->assertStringContains('120', $issue->title);
    }

    public function test_detect_outdated_high_severity_for_very_old_docs(): void
    {
        // 200 days ago → severity HIGH (> 180)
        $this->createEmbedding('testproject', 'docs/ancient.md', 'Ancient doc', null, 'docs', now()->subDays(200));

        $found = $this->detector->detectOutdated('testproject');

        $this->assertEquals(1, $found);
        $issue = DocIssue::where('issue_type', DocIssue::TYPE_OUTDATED)->first();
        $this->assertEquals(DocIssue::SEVERITY_HIGH, $issue->severity);
    }

    public function test_detect_outdated_ignores_recent_documents(): void
    {
        // Document modified 30 days ago (< 90 day threshold)
        $this->createEmbedding('testproject', 'docs/recent.md', 'Fresh content', null, 'docs', now()->subDays(30));

        $found = $this->detector->detectOutdated('testproject');

        $this->assertEquals(0, $found);
    }

    public function test_detect_outdated_skips_code_file_types(): void
    {
        // Model file old, but should be skipped
        $this->createEmbedding('testproject', 'app/Models/User.php', 'User model', null, 'model', now()->subDays(200));

        $found = $this->detector->detectOutdated('testproject');

        $this->assertEquals(0, $found);
    }

    public function test_detect_outdated_skips_all_code_types(): void
    {
        $codeTypes = ['model', 'controller', 'middleware', 'command', 'migration', 'config', 'route', 'support', 'code', 'structure', 'service'];
        $i = 0;
        foreach ($codeTypes as $type) {
            $this->createEmbedding('testproject', "app/file{$i}.php", "Content {$type}", null, $type, now()->subDays(200));
            $i++;
        }

        $found = $this->detector->detectOutdated('testproject');

        $this->assertEquals(0, $found);
    }

    public function test_detect_outdated_does_not_duplicate_existing_issues(): void
    {
        $this->createEmbedding('testproject', 'docs/old.md', 'Old', null, 'docs', now()->subDays(120));

        $found1 = $this->detector->detectOutdated('testproject');
        $this->assertEquals(1, $found1);

        $found2 = $this->detector->detectOutdated('testproject');
        $this->assertEquals(0, $found2);
    }

    public function test_detect_outdated_filters_by_project(): void
    {
        $this->createEmbedding('projecta', 'docs/old.md', 'Old A', null, 'docs', now()->subDays(120));
        $this->createEmbedding('projectb', 'docs/old.md', 'Old B', null, 'docs', now()->subDays(120));

        $found = $this->detector->detectOutdated('projecta');

        $this->assertEquals(1, $found);
        $issue = DocIssue::first();
        $this->assertEquals('projecta', $issue->project);
    }

    // ===================================================================
    // IssueDetector — detectBrokenLinks()
    // ===================================================================

    public function test_detect_broken_links_finds_broken_markdown_links(): void
    {
        $content = 'See [this guide](nonexistent-file.md) for more info.';
        $this->createEmbedding('testproject', 'docs/readme.md', $content, null, 'docs');

        $found = $this->detector->detectBrokenLinks('testproject');

        $this->assertGreaterThanOrEqual(1, $found);

        $issue = DocIssue::where('issue_type', DocIssue::TYPE_BROKEN_LINK)->first();
        $this->assertNotNull($issue);
        $this->assertStringContains('nonexistent-file.md', $issue->title);
    }

    public function test_detect_broken_links_finds_wiki_style_links(): void
    {
        $content = 'See [[nonexistent-wiki-page]] for details.';
        $this->createEmbedding('testproject', 'docs/readme.md', $content, null, 'docs');

        $found = $this->detector->detectBrokenLinks('testproject');

        $this->assertGreaterThanOrEqual(1, $found);
    }

    public function test_detect_broken_links_skips_external_urls(): void
    {
        $content = 'Visit [Google](https://google.com) and [GitHub](http://github.com).';
        $this->createEmbedding('testproject', 'docs/links.md', $content, null, 'docs');

        $found = $this->detector->detectBrokenLinks('testproject');

        $this->assertEquals(0, $found);
    }

    public function test_detect_broken_links_skips_anchor_links(): void
    {
        $content = 'See [section](#my-section) below.';
        $this->createEmbedding('testproject', 'docs/anchors.md', $content, null, 'docs');

        $found = $this->detector->detectBrokenLinks('testproject');

        $this->assertEquals(0, $found);
    }

    public function test_detect_broken_links_does_not_duplicate_existing_issues(): void
    {
        $content = 'See [guide](missing.md) for info.';
        $this->createEmbedding('testproject', 'docs/readme.md', $content, null, 'docs');

        $found1 = $this->detector->detectBrokenLinks('testproject');
        $this->assertGreaterThanOrEqual(1, $found1);

        $found2 = $this->detector->detectBrokenLinks('testproject');
        $this->assertEquals(0, $found2);
    }

    public function test_detect_broken_links_no_issues_for_content_without_links(): void
    {
        $content = 'This is plain text without any links.';
        $this->createEmbedding('testproject', 'docs/plain.md', $content, null, 'docs');

        $found = $this->detector->detectBrokenLinks('testproject');

        $this->assertEquals(0, $found);
    }

    // ===================================================================
    // IssueDetector — detectInconsistencies()
    // ===================================================================

    public function test_detect_inconsistencies_finds_different_prices_for_same_label(): void
    {
        // Same label "toeslag" with different prices in two files
        $this->createEmbedding('testproject', 'docs/pricing-a.md', 'De toeslag bedraagt €5,00 per stuk', null, 'docs');
        $this->createEmbedding('testproject', 'docs/pricing-b.md', 'De toeslag bedraagt €7,50 per stuk', null, 'docs');

        $found = $this->detector->detectInconsistencies('testproject');

        $this->assertGreaterThanOrEqual(1, $found);

        $issue = DocIssue::where('issue_type', DocIssue::TYPE_INCONSISTENT)->first();
        $this->assertNotNull($issue);
        $this->assertEquals(DocIssue::SEVERITY_HIGH, $issue->severity);
    }

    public function test_detect_inconsistencies_ignores_same_price_for_same_label(): void
    {
        // Same label "toeslag" with same price → no inconsistency
        $this->createEmbedding('testproject', 'docs/pricing-a.md', 'De toeslag bedraagt €5,00 per stuk', null, 'docs');
        $this->createEmbedding('testproject', 'docs/pricing-b.md', 'De toeslag bedraagt €5,00 per stuk', null, 'docs');

        $found = $this->detector->detectInconsistencies('testproject');

        $this->assertEquals(0, $found);
    }

    public function test_detect_inconsistencies_no_issues_without_prices(): void
    {
        $this->createEmbedding('testproject', 'docs/guide.md', 'This document has no prices at all.', null, 'docs');

        $found = $this->detector->detectInconsistencies('testproject');

        $this->assertEquals(0, $found);
    }

    public function test_detect_inconsistencies_does_not_duplicate_existing_issues(): void
    {
        $this->createEmbedding('testproject', 'docs/a.md', 'De toeslag bedraagt €5,00 per stuk', null, 'docs');
        $this->createEmbedding('testproject', 'docs/b.md', 'De toeslag bedraagt €10,00 per stuk', null, 'docs');

        $found1 = $this->detector->detectInconsistencies('testproject');
        $this->assertGreaterThanOrEqual(1, $found1);

        $found2 = $this->detector->detectInconsistencies('testproject');
        $this->assertEquals(0, $found2);
    }

    // ===================================================================
    // IssueDetector — detectAll()
    // ===================================================================

    public function test_detect_all_runs_all_detectors(): void
    {
        // Create data that triggers outdated detection
        $this->createEmbedding('testproject', 'docs/old.md', 'Old content without links', null, 'docs', now()->subDays(120));

        $results = $this->detector->detectAll('testproject');

        $this->assertArrayHasKey('duplicates', $results);
        $this->assertArrayHasKey('outdated', $results);
        $this->assertArrayHasKey('broken_links', $results);
        $this->assertArrayHasKey('inconsistencies', $results);
        $this->assertIsInt($results['duplicates']);
        $this->assertIsInt($results['outdated']);
        $this->assertIsInt($results['broken_links']);
        $this->assertIsInt($results['inconsistencies']);
    }

    // ===================================================================
    // IssueDetector — getOpenIssues() & getIssueSummary()
    // ===================================================================

    public function test_get_open_issues_returns_only_open(): void
    {
        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_HIGH,
            'title' => 'Open issue',
            'details' => [],
            'affected_files' => ['test.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);
        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Resolved issue',
            'details' => [],
            'affected_files' => ['test2.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_RESOLVED,
        ]);

        $issues = $this->detector->getOpenIssues('testproject');

        $this->assertCount(1, $issues);
        $this->assertEquals('Open issue', $issues->first()->title);
    }

    public function test_get_open_issues_ordered_by_severity(): void
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
            'issue_type' => DocIssue::TYPE_DUPLICATE,
            'severity' => DocIssue::SEVERITY_HIGH,
            'title' => 'High issue',
            'details' => [],
            'affected_files' => ['b.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $issues = $this->detector->getOpenIssues('testproject');

        $this->assertEquals('High issue', $issues->first()->title);
        $this->assertEquals('Low issue', $issues->last()->title);
    }

    public function test_get_open_issues_filters_by_project(): void
    {
        DocIssue::create([
            'project' => 'projecta',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Issue A',
            'details' => [],
            'affected_files' => ['a.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);
        DocIssue::create([
            'project' => 'projectb',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Issue B',
            'details' => [],
            'affected_files' => ['b.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $issues = $this->detector->getOpenIssues('projecta');

        $this->assertCount(1, $issues);
        $this->assertEquals('projecta', $issues->first()->project);
    }

    public function test_get_issue_summary_groups_by_project(): void
    {
        DocIssue::create([
            'project' => 'projecta',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_HIGH,
            'title' => 'Outdated A',
            'details' => [],
            'affected_files' => ['a.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);
        DocIssue::create([
            'project' => 'projecta',
            'issue_type' => DocIssue::TYPE_DUPLICATE,
            'severity' => DocIssue::SEVERITY_MEDIUM,
            'title' => 'Duplicate A',
            'details' => [],
            'affected_files' => ['b.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);
        DocIssue::create([
            'project' => 'projectb',
            'issue_type' => DocIssue::TYPE_BROKEN_LINK,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Broken B',
            'details' => [],
            'affected_files' => ['c.md'],
            'suggested_action' => 'Fix',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $summary = $this->detector->getIssueSummary();

        $this->assertArrayHasKey('projecta', $summary);
        $this->assertArrayHasKey('projectb', $summary);
        $this->assertEquals(2, $summary['projecta']['total']);
        $this->assertEquals(1, $summary['projecta']['high']);
        $this->assertEquals(1, $summary['projecta']['medium']);
        $this->assertEquals(1, $summary['projectb']['total']);
        $this->assertEquals(1, $summary['projectb']['low']);
        $this->assertEquals(1, $summary['projecta']['by_type'][DocIssue::TYPE_OUTDATED]);
        $this->assertEquals(1, $summary['projecta']['by_type'][DocIssue::TYPE_DUPLICATE]);
    }

    public function test_get_issue_summary_empty_when_no_issues(): void
    {
        $summary = $this->detector->getIssueSummary();

        $this->assertEmpty($summary);
    }

    // ===================================================================
    // DocIndexer — search()
    // ===================================================================

    public function test_search_returns_results_sorted_by_similarity(): void
    {
        // Create docs with different embeddings
        $this->createEmbedding('testproject', 'docs/deploy.md', 'Deployment guide for production servers', ['deploy' => 0.8, 'server' => 0.2], 'docs');
        $this->createEmbedding('testproject', 'docs/cooking.md', 'How to cook pasta', ['cooking' => 0.7, 'pasta' => 0.3], 'docs');

        // Search — the local embedding for query will be TF-IDF based
        $results = $this->indexer->search('deploy server production', 'testproject', 5);

        $this->assertIsArray($results);
        // Results should be sorted by similarity descending
        if (count($results) > 1) {
            $this->assertGreaterThanOrEqual($results[1]['similarity'], $results[0]['similarity']);
        }
    }

    public function test_search_filters_by_project(): void
    {
        $this->createEmbedding('projecta', 'docs/guide.md', 'Guide content', ['guide' => 0.5], 'docs');
        $this->createEmbedding('projectb', 'docs/guide.md', 'Other guide', ['guide' => 0.5], 'docs');

        $results = $this->indexer->search('guide', 'projecta', 10);

        foreach ($results as $result) {
            $this->assertEquals('projecta', $result['project']);
        }
    }

    public function test_search_filters_by_file_type(): void
    {
        $this->createEmbedding('testproject', 'docs/guide.md', 'Guide content', ['guide' => 0.5], 'docs');
        $this->createEmbedding('testproject', 'app/Models/User.php', 'User model', ['user' => 0.5], 'model');

        $results = $this->indexer->search('content', 'testproject', 10, 'docs');

        foreach ($results as $result) {
            // All results should come from docs file type documents
            $doc = DocEmbedding::where('project', $result['project'])
                ->where('file_path', $result['file_path'])
                ->first();
            $this->assertEquals('docs', $doc->file_type);
        }
    }

    public function test_search_respects_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->createEmbedding('testproject', "docs/file{$i}.md", "Content {$i}", ["word{$i}" => 0.5], 'docs');
        }

        $results = $this->indexer->search('content', 'testproject', 3);

        $this->assertLessThanOrEqual(3, count($results));
    }

    public function test_search_returns_expected_structure(): void
    {
        $this->createEmbedding('testproject', 'docs/guide.md', 'Guide about deployment', ['deploy' => 0.5], 'docs');

        $results = $this->indexer->search('deploy', 'testproject', 5);

        $this->assertNotEmpty($results);
        $result = $results[0];
        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('file_path', $result);
        $this->assertArrayHasKey('similarity', $result);
        $this->assertArrayHasKey('snippet', $result);
        $this->assertArrayHasKey('file_modified_at', $result);
        $this->assertArrayHasKey('indexed_at', $result);
    }

    public function test_search_empty_results_for_no_documents(): void
    {
        $results = $this->indexer->search('anything', 'emptyproject', 5);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ===================================================================
    // DocIndexer — getProjectPaths() / getProjectPath()
    // ===================================================================

    public function test_get_project_paths_contains_known_projects(): void
    {
        $paths = $this->indexer->getProjectPaths();

        $this->assertArrayHasKey('havuncore', $paths);
        $this->assertArrayHasKey('havunadmin', $paths);
        $this->assertArrayHasKey('herdenkingsportaal', $paths);
    }

    public function test_get_project_path_is_case_insensitive(): void
    {
        $path1 = $this->indexer->getProjectPath('HavunCore');
        $path2 = $this->indexer->getProjectPath('havuncore');

        $this->assertEquals($path1, $path2);
    }

    // ===================================================================
    // DocIndexer — calculateSimilarity() edge cases
    // ===================================================================

    public function test_calculate_similarity_with_single_dimension(): void
    {
        $similarity = $this->indexer->calculateSimilarity(['a' => 1.0], ['a' => 1.0]);

        $this->assertEqualsWithDelta(1.0, $similarity, 0.001);
    }

    public function test_calculate_similarity_with_negative_values(): void
    {
        $embedding1 = ['a' => 1.0, 'b' => -1.0];
        $embedding2 = ['a' => -1.0, 'b' => 1.0];

        $similarity = $this->indexer->calculateSimilarity($embedding1, $embedding2);

        // Opposite vectors should have -1.0 similarity
        $this->assertEqualsWithDelta(-1.0, $similarity, 0.001);
    }

    // ===================================================================
    // DocIndexer — detectFileType() additional cases
    // ===================================================================

    public function test_detect_file_type_support_types(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('support', $method->invoke($this->indexer, 'app/Enums/Status.php'));
        $this->assertEquals('support', $method->invoke($this->indexer, 'app/DTOs/UserData.php'));
        $this->assertEquals('support', $method->invoke($this->indexer, 'app/Events/OrderPlaced.php'));
        $this->assertEquals('support', $method->invoke($this->indexer, 'app/Jobs/ProcessPayment.php'));
        $this->assertEquals('support', $method->invoke($this->indexer, 'app/Listeners/SendEmail.php'));
        $this->assertEquals('support', $method->invoke($this->indexer, 'app/Traits/HasSlug.php'));
        $this->assertEquals('support', $method->invoke($this->indexer, 'app/Exceptions/CustomException.php'));
        $this->assertEquals('support', $method->invoke($this->indexer, 'app/Contracts/PaymentGateway.php'));
    }

    public function test_detect_file_type_middleware(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('middleware', $method->invoke($this->indexer, 'app/Http/Middleware/Authenticate.php'));
    }

    public function test_detect_file_type_command(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('command', $method->invoke($this->indexer, 'app/Console/Commands/ImportData.php'));
    }

    public function test_detect_file_type_test(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('test', $method->invoke($this->indexer, 'tests/Unit/UserTest.php'));
    }

    public function test_detect_file_type_generic_code(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        // A PHP file not matching any known pattern
        $this->assertEquals('code', $method->invoke($this->indexer, 'src/helpers.php'));
    }

    // ===================================================================
    // DocIndexer — extractCodeSummary() additional cases
    // ===================================================================

    public function test_extract_code_summary_for_config(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $config = <<<'PHP'
<?php

return [
    // Application name
    'name' => env('APP_NAME', 'Laravel'),
    'debug' => env('APP_DEBUG', false),
];
PHP;

        $summary = $method->invoke($this->indexer, 'config/app.php', $config);

        $this->assertStringContainsString('[TYPE] Configuration', $summary);
        $this->assertStringContainsString('name', $summary);
        $this->assertStringContainsString('debug', $summary);
    }

    public function test_extract_code_summary_for_javascript(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $js = <<<'JS'
import axios from 'axios';

export default class ApiClient {
    constructor() {}
}

export function fetchUsers() {
    return axios.get('/api/users');
}
JS;

        $summary = $method->invoke($this->indexer, 'src/api.js', $js);

        $this->assertStringContainsString('[TYPE] JavaScript/TypeScript', $summary);
        $this->assertStringContainsString('import', $summary);
    }

    public function test_extract_code_summary_fallback_for_sparse_file(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        // A PHP file with no recognizable patterns
        $content = '<?php // empty file';

        $summary = $method->invoke($this->indexer, 'src/empty.php', $content);

        // Should fall back to including raw content when summary is too short
        $this->assertStringContainsString('empty file', $summary);
    }

    // ===================================================================
    // DocIndexer — indexProject() with invalid project
    // ===================================================================

    public function test_index_project_returns_error_for_unknown_project(): void
    {
        $result = $this->indexer->indexProject('nonexistent_project_xyz');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not found', $result['error']);
    }

    // ===================================================================
    // DocIndexer — estimateTokenCount()
    // ===================================================================

    public function test_estimate_token_count_basic(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'estimateTokenCount');
        $method->setAccessible(true);

        // "Hello World" = 11 chars → ceil(11/4) = 3 tokens
        $result = $method->invoke($this->indexer, 'Hello World');
        $this->assertEquals(3, $result);
    }

    public function test_estimate_token_count_empty_string(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'estimateTokenCount');
        $method->setAccessible(true);

        $result = $method->invoke($this->indexer, '');
        $this->assertEquals(0, $result);
    }

    public function test_estimate_token_count_long_content(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'estimateTokenCount');
        $method->setAccessible(true);

        $content = str_repeat('a', 1000);
        $result = $method->invoke($this->indexer, $content);
        $this->assertEquals(250, $result);
    }

    // ===================================================================
    // DocIndexer — generateLocalEmbedding()
    // ===================================================================

    public function test_generate_local_embedding_returns_array(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'generateLocalEmbedding');
        $method->setAccessible(true);

        $embedding = $method->invoke($this->indexer, 'The quick brown fox jumps over the lazy dog');

        $this->assertIsArray($embedding);
        $this->assertNotEmpty($embedding);
    }

    public function test_generate_local_embedding_removes_stopwords(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'generateLocalEmbedding');
        $method->setAccessible(true);

        $embedding = $method->invoke($this->indexer, 'the is a an deployment server production');

        // Stopwords like 'the', 'is', 'a', 'an' should be removed
        $this->assertArrayNotHasKey('the', $embedding);
        $this->assertArrayNotHasKey('is', $embedding);
        $this->assertArrayNotHasKey('a', $embedding);
        // Meaningful words should remain
        $this->assertArrayHasKey('deployment', $embedding);
        $this->assertArrayHasKey('server', $embedding);
    }

    public function test_generate_local_embedding_normalizes_values(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'generateLocalEmbedding');
        $method->setAccessible(true);

        $embedding = $method->invoke($this->indexer, 'deploy deploy deploy server server');

        // Values should sum roughly to 1.0 (normalized)
        $sum = array_sum($embedding);
        $this->assertEqualsWithDelta(1.0, $sum, 0.01);
    }

    // ===================================================================
    // DocIndexer — extractCodeSummary() additional
    // ===================================================================

    public function test_extract_code_summary_for_blade(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $blade = <<<'BLADE'
@extends('layouts.app')
@section('content')
    <div><!-- DO NOT REMOVE --></div>
    @include('partials.header')
@endsection
BLADE;

        $summary = $method->invoke($this->indexer, 'resources/views/home.blade.php', $blade);

        $this->assertStringContainsString('[TYPE] Blade template', $summary);
        $this->assertStringContainsString('@extends', $summary);
        $this->assertStringContainsString('DO NOT REMOVE', $summary);
    }

    public function test_extract_code_summary_for_route(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $routes = <<<'PHP'
<?php
Route::get('/api/users', [UserController::class, 'index']);
Route::post('/api/users', [UserController::class, 'store']);
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
PHP;

        $summary = $method->invoke($this->indexer, 'routes/api.php', $routes);

        $this->assertStringContainsString('[TYPE] Route definitions', $summary);
        $this->assertStringContainsString('Route::get', $summary);
        $this->assertStringContainsString('Route::post', $summary);
    }

    public function test_extract_code_summary_for_migration(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $migration = <<<'PHP'
<?php
Schema::create('users', function (Blueprint $table) {
    $table->id('id');
    $table->string('email')->unique();
    $table->string('name');
});
PHP;

        $summary = $method->invoke($this->indexer, 'database/migrations/001_create_users.php', $migration);

        $this->assertStringContainsString('[TYPE] Database migration', $summary);
        $this->assertStringContainsString('[TABLE] users (create)', $summary);
    }

    public function test_extract_code_summary_for_php_class(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $php = <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model {
    protected $fillable = ['name', 'email'];

    public function posts() {
        return $this->hasMany(Post::class);
    }

    protected function isAdmin() {}
}
PHP;

        $summary = $method->invoke($this->indexer, 'app/Models/User.php', $php);

        $this->assertStringContainsString('[NAMESPACE] App\\Models', $summary);
        $this->assertStringContainsString('[CLASS] class User', $summary);
        $this->assertStringContainsString('[METHOD]', $summary);
        $this->assertStringContainsString('[RELATION] hasMany', $summary);
    }

    // ===================================================================
    // DocIndexer — indexFile() via indexProject with temp file
    // ===================================================================

    public function test_index_file_creates_embedding_record(): void
    {
        // Use reflection to test indexFile directly
        $method = new \ReflectionMethod(DocIndexer::class, 'indexFile');
        $method->setAccessible(true);

        $tempDir = sys_get_temp_dir() . '/havuncore_indexfile_test_' . uniqid();
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/test.md', '# Test Document');

        $result = $method->invoke($this->indexer, 'testproject', 'test.md', $tempDir . '/test.md', false);

        $this->assertTrue($result);

        $doc = DocEmbedding::where('project', 'testproject')->where('file_path', 'test.md')->first();
        $this->assertNotNull($doc);
        $this->assertEquals('# Test Document', $doc->content);
        $this->assertEquals('docs', $doc->file_type);

        // Cleanup
        unlink($tempDir . '/test.md');
        rmdir($tempDir);
    }

    public function test_index_file_skips_unchanged(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'indexFile');
        $method->setAccessible(true);

        $tempDir = sys_get_temp_dir() . '/havuncore_indexfile_test2_' . uniqid();
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/test.md', '# Same Content');

        // First index
        $result1 = $method->invoke($this->indexer, 'testproject', 'test.md', $tempDir . '/test.md', false);
        $this->assertTrue($result1);

        // Second index - same content, should skip
        $result2 = $method->invoke($this->indexer, 'testproject', 'test.md', $tempDir . '/test.md', false);
        $this->assertFalse($result2);

        // Cleanup
        unlink($tempDir . '/test.md');
        rmdir($tempDir);
    }

    public function test_index_file_reindexes_with_force(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'indexFile');
        $method->setAccessible(true);

        $tempDir = sys_get_temp_dir() . '/havuncore_indexfile_test3_' . uniqid();
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/test.md', '# Force Test');

        // First index
        $method->invoke($this->indexer, 'testproject', 'test.md', $tempDir . '/test.md', false);

        // Second with force - should re-index
        $result = $method->invoke($this->indexer, 'testproject', 'test.md', $tempDir . '/test.md', true);
        $this->assertTrue($result);

        // Cleanup
        unlink($tempDir . '/test.md');
        rmdir($tempDir);
    }

    public function test_index_file_returns_false_for_nonexistent(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'indexFile');
        $method->setAccessible(true);

        $result = $method->invoke($this->indexer, 'testproject', 'nope.md', '/tmp/does_not_exist.md', false);
        $this->assertFalse($result);
    }

    // ===================================================================
    // DocIndexer — cleanupOrphaned()
    // ===================================================================

    public function test_cleanup_orphaned_removes_deleted_files(): void
    {
        // Create a doc pointing to a non-existent file
        DocEmbedding::create([
            'project' => 'havuncore',
            'file_path' => 'docs/deleted_file_that_does_not_exist_xyz.md',
            'content' => 'Old content',
            'content_hash' => hash('sha256', 'deleted'),
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        $removed = $this->indexer->cleanupOrphaned('havuncore');

        $this->assertGreaterThanOrEqual(1, $removed);
    }

    public function test_cleanup_orphaned_returns_zero_for_unknown_project(): void
    {
        $removed = $this->indexer->cleanupOrphaned('nonexistent_project');
        $this->assertEquals(0, $removed);
    }

    // ===================================================================
    // DocIndexer — toRelativePath()
    // ===================================================================

    public function test_to_relative_path(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'toRelativePath');
        $method->setAccessible(true);

        $result = $method->invoke($this->indexer, 'D:/GitHub/HavunCore/docs/readme.md', 'D:/GitHub/HavunCore');
        $this->assertEquals('docs/readme.md', $result);
    }

    // ===================================================================
    // Helpers
    // ===================================================================

    /**
     * Custom assertion for string contains (shorter than assertStringContainsString)
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }

    private function createEmbedding(
        string $project,
        string $filePath,
        string $content,
        ?array $embedding = null,
        string $fileType = 'docs',
        ?Carbon $fileModifiedAt = null,
    ): DocEmbedding {
        return DocEmbedding::create([
            'project' => $project,
            'file_path' => $filePath,
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'embedding' => $embedding,
            'embedding_model' => $embedding ? 'tfidf-fallback' : null,
            'file_type' => $fileType,
            'token_count' => (int) ceil(strlen($content) / 4),
            'file_modified_at' => $fileModifiedAt ?? now(),
        ]);
    }
}
