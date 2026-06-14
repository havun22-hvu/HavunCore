<?php

namespace Tests\Unit;

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
class IssueDetectorCoverageTest extends TestCase
{
    use CreatesDocIntelligenceTables;
    use RefreshDatabase;

    private DocIndexer $indexer;
    private IssueDetector $detector;

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

    // ===================================================================
    // detectOutdated — frozen (archive) docs
    // ===================================================================

    public function test_detect_outdated_flags_stale_regular_doc(): void
    {
        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/guide.md',
            'content' => 'Stale guide content.',
            'content_hash' => hash('sha256', 'stale-guide'),
            'embedding' => null,
            'embedding_model' => null,
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now()->subDays(200),
        ]);

        $this->assertEquals(1, $this->detector->detectOutdated('testproject'));

        // Pure age-based staleness is never HIGH — >180d = MEDIUM, reserve HIGH for content faults
        $issue = DocIssue::where('issue_type', DocIssue::TYPE_OUTDATED)->first();
        $this->assertEquals(DocIssue::SEVERITY_MEDIUM, $issue->severity);
    }

    public function test_detect_outdated_uses_low_severity_under_180_days(): void
    {
        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/recent-ish.md',
            'content' => 'Mildly stale content.',
            'content_hash' => hash('sha256', 'mild-stale'),
            'embedding' => null,
            'embedding_model' => null,
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now()->subDays(120),
        ]);

        $this->assertEquals(1, $this->detector->detectOutdated('testproject'));
        $issue = DocIssue::where('issue_type', DocIssue::TYPE_OUTDATED)->first();
        $this->assertEquals(DocIssue::SEVERITY_LOW, $issue->severity);
    }

    public function test_detect_outdated_skips_archived_docs(): void
    {
        // Archived/legacy docs are intentionally frozen — never flagged outdated
        foreach (['docs/archive/old-fix.md', 'docs/archived/legacy.md', 'legacy/notes.md'] as $i => $path) {
            DocEmbedding::create([
                'project' => 'testproject',
                'file_path' => $path,
                'content' => 'Frozen archived content.',
                'content_hash' => hash('sha256', 'frozen-' . $i),
                'embedding' => null,
                'embedding_model' => null,
                'file_type' => 'docs',
                'token_count' => 10,
                'file_modified_at' => now()->subDays(300),
            ]);
        }

        $this->assertEquals(0, $this->detector->detectOutdated('testproject'));
    }

    public function test_detect_outdated_skips_date_stamped_snapshots(): void
    {
        // Date-stamped snapshots (mutation-baseline-2026-04-17.md) are point-in-time
        // records, superseded by newer snapshots — never flagged outdated.
        foreach (['docs/kb/reference/mutation-baseline-2026-04-17.md', 'reports/qv-scan-2026-05-02.md'] as $i => $path) {
            DocEmbedding::create([
                'project' => 'testproject',
                'file_path' => $path,
                'content' => 'Snapshot taken at a fixed point in time.',
                'content_hash' => hash('sha256', 'snapshot-' . $i),
                'embedding' => null,
                'embedding_model' => null,
                'file_type' => 'docs',
                'token_count' => 10,
                'file_modified_at' => now()->subDays(300),
            ]);
        }

        $this->assertEquals(0, $this->detector->detectOutdated('testproject'));
    }

    // ===================================================================
    // detectBrokenLinks — anchor/fragment handling
    // ===================================================================

    public function test_detect_broken_links_ignores_fragment_on_existing_file(): void
    {
        // A link to an existing sibling file WITH a section anchor must not be flagged.
        // The detector resolves against the indexer's project path; an unknown project
        // resolves to null base path, so file resolution would fail for a real missing
        // file — here we assert the fragment itself is not what trips the check by using
        // a self-reference that always resolves: the document's own file.
        DocEmbedding::create([
            'project' => 'havuncore', // real configured project path exists on disk
            'file_path' => 'README.md',
            'content' => 'See [the readme section](./README.md#installation) for details.',
            'content_hash' => hash('sha256', 'frag-ok'),
            'embedding' => null,
            'embedding_model' => null,
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        $this->assertEquals(0, $this->detector->detectBrokenLinks('havuncore'));
    }

    public function test_detect_broken_links_still_flags_missing_file_with_fragment(): void
    {
        DocEmbedding::create([
            'project' => 'havuncore',
            'file_path' => 'README.md',
            'content' => 'See [missing](./this-file-does-not-exist-xyz.md#section).',
            'content_hash' => hash('sha256', 'frag-missing'),
            'embedding' => null,
            'embedding_model' => null,
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        $this->assertEquals(1, $this->detector->detectBrokenLinks('havuncore'));
    }

    // ===================================================================
    // detectDuplicates — lexical-overlap gate
    // ===================================================================

    public function test_detect_duplicates_ignores_same_topic_without_verbatim_overlap(): void
    {
        // Identical embeddings pass the cosine gate, but the content shares no
        // verbatim passages (only a topic) — must NOT be flagged as duplicate.
        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/server-quickref.md',
            'content' => 'Hetzner server IP and SSH deploy keys for every Havun project deployment.',
            'content_hash' => hash('sha256', 'topic-a'),
            'embedding' => ['server' => 1.0],
            'embedding_model' => 'tfidf-fallback',
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/server-reference.md',
            'content' => 'Directory layout, nginx vhost configs, Vite versus CDN build tooling matrix.',
            'content_hash' => hash('sha256', 'topic-b'),
            'embedding' => ['server' => 1.0],
            'embedding_model' => 'tfidf-fallback',
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        $this->assertEquals(0, $this->detector->detectDuplicates('testproject'));
    }

    public function test_detect_duplicates_flags_verbatim_copy(): void
    {
        // Same embedding AND shared verbatim passages = a real copy-paste duplicate.
        $body = 'This is a shared icons readme describing every generated icon size and the build output directory layout for the progressive web app frontend bundle.';

        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'frontend/public/ICONS-README.md',
            'content' => $body,
            'content_hash' => hash('sha256', 'copy-a'),
            'embedding' => ['icons' => 1.0],
            'embedding_model' => 'tfidf-fallback',
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/ICONS-README.md',
            'content' => $body,
            'content_hash' => hash('sha256', 'copy-b'),
            'embedding' => ['icons' => 1.0],
            'embedding_model' => 'tfidf-fallback',
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        $this->assertEquals(1, $this->detector->detectDuplicates('testproject'));
    }
}
