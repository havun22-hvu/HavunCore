<?php

namespace Tests\Unit;

use App\Models\DocIntelligence\DocIssue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesDocIntelligenceTables;
use Tests\TestCase;

class DocIssueTest extends TestCase
{
    use CreatesDocIntelligenceTables;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDocIntelligenceTables();
    }

    public function test_resolve_marks_as_resolved(): void
    {
        $issue = DocIssue::create([
            'project' => 'test', 'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW, 'title' => 'Old doc',
            'details' => [], 'affected_files' => ['a.md'],
            'suggested_action' => 'Fix', 'status' => DocIssue::STATUS_OPEN,
        ]);

        $issue->resolve('claude');

        $fresh = $issue->fresh();
        $this->assertEquals(DocIssue::STATUS_RESOLVED, $fresh->status);
        $this->assertEquals('claude', $fresh->resolved_by);
        $this->assertNotNull($fresh->resolved_at);
    }

    public function test_ignore_marks_as_ignored(): void
    {
        $issue = DocIssue::create([
            'project' => 'test', 'issue_type' => DocIssue::TYPE_DUPLICATE,
            'severity' => DocIssue::SEVERITY_MEDIUM, 'title' => 'Dup',
            'details' => [], 'affected_files' => ['a.md', 'b.md'],
            'suggested_action' => 'Merge', 'status' => DocIssue::STATUS_OPEN,
        ]);

        $issue->ignore();

        $this->assertEquals(DocIssue::STATUS_IGNORED, $issue->fresh()->status);
    }

    public function test_get_type_label(): void
    {
        $cases = [
            DocIssue::TYPE_INCONSISTENT => 'Inconsistent',
            DocIssue::TYPE_DUPLICATE => 'Duplicate',
            DocIssue::TYPE_OUTDATED => 'Outdated',
            DocIssue::TYPE_MISSING => 'Missing',
            DocIssue::TYPE_BROKEN_LINK => 'Broken Link',
            DocIssue::TYPE_ORPHANED => 'Orphaned',
        ];

        foreach ($cases as $type => $expected) {
            $issue = new DocIssue(['issue_type' => $type]);
            $this->assertStringContainsString($expected, $issue->getTypeLabel());
        }
    }

    public function test_get_type_label_unknown(): void
    {
        $issue = new DocIssue(['issue_type' => 'custom']);
        $this->assertEquals('custom', $issue->getTypeLabel());
    }

    public function test_get_severity_label(): void
    {
        $issue = new DocIssue(['severity' => DocIssue::SEVERITY_HIGH]);
        $this->assertStringContainsString('High', $issue->getSeverityLabel());

        $issue = new DocIssue(['severity' => DocIssue::SEVERITY_MEDIUM]);
        $this->assertStringContainsString('Medium', $issue->getSeverityLabel());

        $issue = new DocIssue(['severity' => DocIssue::SEVERITY_LOW]);
        $this->assertStringContainsString('Low', $issue->getSeverityLabel());
    }

    public function test_get_severity_label_unknown(): void
    {
        $issue = new DocIssue(['severity' => 'critical']);
        $this->assertEquals('critical', $issue->getSeverityLabel());
    }

    public function test_scope_high_priority(): void
    {
        DocIssue::create([
            'project' => 'test', 'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_HIGH, 'title' => 'High severity',
            'details' => [], 'affected_files' => ['a.md'],
            'suggested_action' => 'Fix', 'status' => DocIssue::STATUS_OPEN,
        ]);
        DocIssue::create([
            'project' => 'test', 'issue_type' => DocIssue::TYPE_INCONSISTENT,
            'severity' => DocIssue::SEVERITY_LOW, 'title' => 'Inconsistent but low',
            'details' => [], 'affected_files' => ['b.md'],
            'suggested_action' => 'Fix', 'status' => DocIssue::STATUS_OPEN,
        ]);
        DocIssue::create([
            'project' => 'test', 'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW, 'title' => 'Low and not inconsistent',
            'details' => [], 'affected_files' => ['c.md'],
            'suggested_action' => 'Fix', 'status' => DocIssue::STATUS_OPEN,
        ]);

        $highPriority = DocIssue::highPriority()->get();
        $this->assertCount(2, $highPriority);
    }
}
