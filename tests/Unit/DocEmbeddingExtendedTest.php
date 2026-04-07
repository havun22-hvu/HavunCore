<?php

namespace Tests\Unit;

use App\Models\DocIntelligence\DocEmbedding;
use App\Models\DocIntelligence\DocIssue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocEmbeddingExtendedTest extends TestCase
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
    }

    public function test_issues_relationship_returns_matching_issues(): void
    {
        $embedding = DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/guide.md',
            'content' => 'Guide content',
            'content_hash' => hash('sha256', 'guide'),
            'embedding' => null,
            'embedding_model' => null,
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Guide is old',
            'details' => [],
            'affected_files' => ['docs/guide.md'],
            'suggested_action' => 'Update',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Other doc',
            'details' => [],
            'affected_files' => ['docs/other.md'],
            'suggested_action' => 'Update',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $issues = $embedding->issues;
        $this->assertCount(1, $issues);
        $this->assertEquals('Guide is old', $issues->first()->title);
    }

    public function test_scope_of_type(): void
    {
        DocEmbedding::create([
            'project' => 'test', 'file_path' => 'app/Model.php',
            'content' => 'Model', 'content_hash' => hash('sha256', 'model'),
            'embedding' => null, 'embedding_model' => null,
            'file_type' => 'model', 'token_count' => 10, 'file_modified_at' => now(),
        ]);
        DocEmbedding::create([
            'project' => 'test', 'file_path' => 'docs/readme.md',
            'content' => 'Readme', 'content_hash' => hash('sha256', 'readme'),
            'embedding' => null, 'embedding_model' => null,
            'file_type' => 'docs', 'token_count' => 10, 'file_modified_at' => now(),
        ]);

        $this->assertCount(1, DocEmbedding::ofType('model')->get());
        $this->assertCount(1, DocEmbedding::ofType('docs')->get());
    }
}
