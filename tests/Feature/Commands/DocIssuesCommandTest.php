<?php

namespace Tests\Feature\Commands;

use App\Models\DocIntelligence\DocIssue;
use App\Services\DocIntelligence\IssueDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class DocIssuesCommandTest extends TestCase
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

        DocIssue::query()->delete();

        Http::fake([
            '127.0.0.1:11434/*' => Http::response(['embedding' => null], 200),
        ]);
    }

    // ===================================================================
    // handle() — all issue types and severity icons
    // ===================================================================

    public function test_handle_displays_all_severity_icons(): void
    {
        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_HIGH,
            'title' => 'High severity issue',
            'details' => [],
            'affected_files' => ['a.md'],
            'suggested_action' => 'Update now',
            'status' => DocIssue::STATUS_OPEN,
        ]);
        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_DUPLICATE,
            'severity' => DocIssue::SEVERITY_MEDIUM,
            'title' => 'Medium severity issue',
            'details' => [],
            'affected_files' => ['b.md', 'c.md'],
            'suggested_action' => null,
            'status' => DocIssue::STATUS_OPEN,
        ]);
        DocIssue::create([
            'project' => null,
            'issue_type' => DocIssue::TYPE_BROKEN_LINK,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Low severity issue',
            'details' => [],
            'affected_files' => [],
            'suggested_action' => null,
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $this->artisan('docs:issues')
            ->expectsOutputToContain('Open Issues (3)')
            ->expectsOutputToContain('High severity issue')
            ->expectsOutputToContain('Medium severity issue')
            ->expectsOutputToContain('Low severity issue')
            ->expectsOutputToContain('cross-project')
            ->expectsOutputToContain('Update now')
            ->assertExitCode(0);
    }

    public function test_handle_displays_all_issue_type_icons(): void
    {
        // Create issues for each type to hit all match arms
        $types = [
            DocIssue::TYPE_INCONSISTENT => 'Inconsistent doc',
            DocIssue::TYPE_DUPLICATE => 'Duplicate doc',
            DocIssue::TYPE_OUTDATED => 'Outdated doc',
            DocIssue::TYPE_BROKEN_LINK => 'Broken link doc',
            DocIssue::TYPE_MISSING => 'Missing doc',
        ];

        foreach ($types as $type => $title) {
            DocIssue::create([
                'project' => 'testproject',
                'issue_type' => $type,
                'severity' => DocIssue::SEVERITY_LOW,
                'title' => $title,
                'details' => [],
                'affected_files' => ['test.md'],
                'suggested_action' => null,
                'status' => DocIssue::STATUS_OPEN,
            ]);
        }

        $this->artisan('docs:issues')
            ->expectsOutputToContain('Open Issues (5)')
            ->expectsOutputToContain('Inconsistent doc')
            ->expectsOutputToContain('Duplicate doc')
            ->expectsOutputToContain('Outdated doc')
            ->expectsOutputToContain('Broken link doc')
            ->expectsOutputToContain('Missing doc')
            ->assertExitCode(0);
    }

    public function test_handle_with_default_severity_icon(): void
    {
        // Create issue with unknown severity to test default match arm
        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => 'unknown_type',
            'severity' => 'critical',
            'title' => 'Unknown severity issue',
            'details' => [],
            'affected_files' => ['x.md'],
            'suggested_action' => null,
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $this->artisan('docs:issues')
            ->expectsOutputToContain('Unknown severity issue')
            ->assertExitCode(0);
    }

    public function test_handle_no_issues(): void
    {
        $this->artisan('docs:issues')
            ->expectsOutputToContain('No open issues found')
            ->assertExitCode(0);
    }

    public function test_handle_combined_filters(): void
    {
        DocIssue::create([
            'project' => 'havuncore',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_HIGH,
            'title' => 'Match this issue',
            'details' => [],
            'affected_files' => ['a.md'],
            'suggested_action' => null,
            'status' => DocIssue::STATUS_OPEN,
        ]);
        DocIssue::create([
            'project' => 'havuncore',
            'issue_type' => DocIssue::TYPE_DUPLICATE,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Skip this issue',
            'details' => [],
            'affected_files' => ['b.md'],
            'suggested_action' => null,
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $this->artisan('docs:issues', [
            'project' => 'havuncore',
            '--severity' => 'high',
            '--type' => 'outdated',
        ])
            ->expectsOutputToContain('Match this issue')
            ->assertExitCode(0);
    }

    // ===================================================================
    // showSummary() — all branches
    // ===================================================================

    public function test_summary_empty(): void
    {
        $detector = Mockery::mock(IssueDetector::class);
        $detector->shouldReceive('getIssueSummary')
            ->once()
            ->andReturn([]);

        $this->app->instance(IssueDetector::class, $detector);

        $this->artisan('docs:issues', ['--summary' => true])
            ->expectsOutputToContain('No open issues found')
            ->assertExitCode(0);
    }

    public function test_summary_with_data_shows_all_sections(): void
    {
        $detector = Mockery::mock(IssueDetector::class);
        $detector->shouldReceive('getIssueSummary')
            ->once()
            ->andReturn([
                'havuncore' => [
                    'total' => 5,
                    'high' => 2,
                    'medium' => 2,
                    'low' => 1,
                    'by_type' => [
                        'inconsistent' => 2,
                        'duplicate' => 1,
                        'outdated' => 1,
                        'broken_link' => 1,
                    ],
                ],
                'havunadmin' => [
                    'total' => 2,
                    'high' => 0,
                    'medium' => 1,
                    'low' => 1,
                    'by_type' => [
                        'missing' => 1,
                        'outdated' => 1,
                    ],
                ],
            ]);

        $this->app->instance(IssueDetector::class, $detector);

        $this->artisan('docs:issues', ['--summary' => true])
            ->expectsOutputToContain('SAMENVATTING')
            ->expectsOutputToContain('HIGH:   2 issues')
            ->expectsOutputToContain('MEDIUM: 3 issues')
            ->expectsOutputToContain('LOW:    2 issues')
            ->expectsOutputToContain('TOTAAL: 7 issues')
            ->expectsOutputToContain('PER PROJECT')
            ->expectsOutputToContain('havuncore: 5 issues')
            ->expectsOutputToContain('havunadmin: 2 issues')
            ->expectsOutputToContain('Inconsistenties: 2')
            ->expectsOutputToContain('Duplicaten: 1')
            ->expectsOutputToContain('Verouderd')
            ->expectsOutputToContain('Broken links: 1')
            ->expectsOutputToContain('Ontbrekend: 1')
            ->assertExitCode(0);
    }

    public function test_summary_project_with_only_low_severity(): void
    {
        $detector = Mockery::mock(IssueDetector::class);
        $detector->shouldReceive('getIssueSummary')
            ->once()
            ->andReturn([
                'testproject' => [
                    'total' => 1,
                    'high' => 0,
                    'medium' => 0,
                    'low' => 1,
                    'by_type' => [
                        'outdated' => 1,
                    ],
                ],
            ]);

        $this->app->instance(IssueDetector::class, $detector);

        // Should show green icon for project with no high/medium
        $this->artisan('docs:issues', ['--summary' => true])
            ->expectsOutputToContain('testproject: 1 issues')
            ->assertExitCode(0);
    }

    public function test_summary_project_with_medium_no_high(): void
    {
        $detector = Mockery::mock(IssueDetector::class);
        $detector->shouldReceive('getIssueSummary')
            ->once()
            ->andReturn([
                'testproject' => [
                    'total' => 2,
                    'high' => 0,
                    'medium' => 2,
                    'low' => 0,
                    'by_type' => [
                        'duplicate' => 2,
                    ],
                ],
            ]);

        $this->app->instance(IssueDetector::class, $detector);

        // Should show yellow icon for project with medium but no high
        $this->artisan('docs:issues', ['--summary' => true])
            ->expectsOutputToContain('testproject: 2 issues')
            ->assertExitCode(0);
    }

    public function test_summary_with_unknown_type_uses_default_label(): void
    {
        $detector = Mockery::mock(IssueDetector::class);
        $detector->shouldReceive('getIssueSummary')
            ->once()
            ->andReturn([
                'testproject' => [
                    'total' => 1,
                    'high' => 0,
                    'medium' => 0,
                    'low' => 1,
                    'by_type' => [
                        'custom_type' => 1,
                    ],
                ],
            ]);

        $this->app->instance(IssueDetector::class, $detector);

        // The default match arm should output the raw type name
        $this->artisan('docs:issues', ['--summary' => true])
            ->expectsOutputToContain('custom_type: 1')
            ->assertExitCode(0);
    }

    // ===================================================================
    // resolveIssue() — all paths
    // ===================================================================

    public function test_resolve_existing_issue(): void
    {
        $issue = DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Resolvable',
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
        $this->assertEquals('user', $issue->resolved_by);
    }

    public function test_resolve_nonexistent_issue(): void
    {
        $this->artisan('docs:issues', ['--resolve' => 99999])
            ->expectsOutputToContain('not found')
            ->assertExitCode(1);
    }

    // ===================================================================
    // ignoreIssue() — all paths
    // ===================================================================

    public function test_ignore_existing_issue(): void
    {
        $issue = DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_DUPLICATE,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Ignorable',
            'details' => [],
            'affected_files' => ['a.md'],
            'suggested_action' => 'Merge',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $this->artisan('docs:issues', ['--ignore' => $issue->id])
            ->expectsOutputToContain('marked as ignored')
            ->assertExitCode(0);

        $issue->refresh();
        $this->assertEquals(DocIssue::STATUS_IGNORED, $issue->status);
    }

    public function test_ignore_nonexistent_issue(): void
    {
        $this->artisan('docs:issues', ['--ignore' => 99999])
            ->expectsOutputToContain('not found')
            ->assertExitCode(1);
    }
}
