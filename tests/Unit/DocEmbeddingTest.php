<?php

namespace Tests\Unit;

use App\Models\DocIntelligence\DocEmbedding;
use App\Models\DocIntelligence\DocIssue;
use App\Models\DocIntelligence\DocRelation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocEmbeddingTest extends TestCase
{
    use RefreshDatabase;

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
    }

    // ===================================================================
    // Scopes
    // ===================================================================

    public function test_scope_for_project_filters_correctly(): void
    {
        DocEmbedding::create([
            'project' => 'projecta',
            'file_path' => 'docs/a.md',
            'content' => 'Content A',
            'content_hash' => hash('sha256', 'a'),
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);
        DocEmbedding::create([
            'project' => 'projectb',
            'file_path' => 'docs/b.md',
            'content' => 'Content B',
            'content_hash' => hash('sha256', 'b'),
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        $results = DocEmbedding::forProject('projecta')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('projecta', $results->first()->project);
    }

    public function test_scope_for_project_is_case_insensitive(): void
    {
        DocEmbedding::create([
            'project' => 'havuncore',
            'file_path' => 'docs/test.md',
            'content' => 'Test',
            'content_hash' => hash('sha256', 'test'),
            'file_type' => 'docs',
            'token_count' => 5,
            'file_modified_at' => now(),
        ]);

        // The scope lowercases the input
        $results = DocEmbedding::forProject('HavunCore')->get();
        $this->assertCount(1, $results);
    }

    public function test_scope_of_type_filters_correctly(): void
    {
        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/guide.md',
            'content' => 'Guide',
            'content_hash' => hash('sha256', 'guide'),
            'file_type' => 'docs',
            'token_count' => 5,
            'file_modified_at' => now(),
        ]);
        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'app/Models/User.php',
            'content' => 'User model',
            'content_hash' => hash('sha256', 'user'),
            'file_type' => 'model',
            'token_count' => 5,
            'file_modified_at' => now(),
        ]);

        $results = DocEmbedding::ofType('model')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('model', $results->first()->file_type);
    }

    // ===================================================================
    // cosineSimilarity
    // ===================================================================

    public function test_cosine_similarity_identical_vectors(): void
    {
        $doc = new DocEmbedding();
        $doc->embedding = [0.5, 0.3, 0.2];

        $similarity = $doc->cosineSimilarity([0.5, 0.3, 0.2]);

        $this->assertEqualsWithDelta(1.0, $similarity, 0.001);
    }

    public function test_cosine_similarity_opposite_vectors(): void
    {
        $doc = new DocEmbedding();
        $doc->embedding = [1.0, 0.0];

        $similarity = $doc->cosineSimilarity([-1.0, 0.0]);

        $this->assertEqualsWithDelta(-1.0, $similarity, 0.001);
    }

    public function test_cosine_similarity_orthogonal_vectors(): void
    {
        $doc = new DocEmbedding();
        $doc->embedding = [1.0, 0.0];

        $similarity = $doc->cosineSimilarity([0.0, 1.0]);

        $this->assertEqualsWithDelta(0.0, $similarity, 0.001);
    }

    public function test_cosine_similarity_empty_embedding(): void
    {
        $doc = new DocEmbedding();
        $doc->embedding = [];

        $this->assertEquals(0.0, $doc->cosineSimilarity([1.0, 2.0]));
    }

    public function test_cosine_similarity_null_embedding(): void
    {
        $doc = new DocEmbedding();
        $doc->embedding = null;

        $this->assertEquals(0.0, $doc->cosineSimilarity([1.0]));
    }

    public function test_cosine_similarity_empty_other(): void
    {
        $doc = new DocEmbedding();
        $doc->embedding = [1.0, 2.0];

        $this->assertEquals(0.0, $doc->cosineSimilarity([]));
    }

    public function test_cosine_similarity_zero_vectors(): void
    {
        $doc = new DocEmbedding();
        $doc->embedding = [0.0, 0.0];

        $this->assertEquals(0.0, $doc->cosineSimilarity([0.0, 0.0]));
    }

    // ===================================================================
    // getLocalPath
    // ===================================================================

    public function test_get_local_path_known_project(): void
    {
        $doc = new DocEmbedding();
        $doc->project = 'havuncore';
        $doc->file_path = 'docs/readme.md';

        $path = $doc->getLocalPath();

        $this->assertEquals('D:/GitHub/HavunCore/docs/readme.md', $path);
    }

    public function test_get_local_path_unknown_project_uses_fallback(): void
    {
        $doc = new DocEmbedding();
        $doc->project = 'newproject';
        $doc->file_path = 'README.md';

        $path = $doc->getLocalPath();

        $this->assertEquals('D:/GitHub/newproject/README.md', $path);
    }

    // ===================================================================
    // Casts
    // ===================================================================

    public function test_embedding_is_cast_to_array(): void
    {
        $embedding = [0.1, 0.2, 0.3];

        $doc = DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/test.md',
            'content' => 'Test',
            'content_hash' => hash('sha256', 'cast-test'),
            'embedding' => $embedding,
            'embedding_model' => 'test',
            'file_type' => 'docs',
            'token_count' => 5,
            'file_modified_at' => now(),
        ]);

        $doc->refresh();
        $this->assertIsArray($doc->embedding);
        $this->assertEqualsWithDelta(0.1, $doc->embedding[0], 0.001);
    }

    public function test_file_modified_at_is_cast_to_datetime(): void
    {
        $doc = DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/date.md',
            'content' => 'Test',
            'content_hash' => hash('sha256', 'date-test'),
            'file_type' => 'docs',
            'token_count' => 5,
            'file_modified_at' => '2026-01-15 10:30:00',
        ]);

        $doc->refresh();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $doc->file_modified_at);
    }

    // ===================================================================
    // issues() relation
    // ===================================================================

    public function test_issues_relation_returns_related_issues(): void
    {
        $doc = DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/guide.md',
            'content' => 'Guide content',
            'content_hash' => hash('sha256', 'relation-test'),
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        DocIssue::create([
            'project' => 'testproject',
            'issue_type' => DocIssue::TYPE_OUTDATED,
            'severity' => DocIssue::SEVERITY_LOW,
            'title' => 'Related issue',
            'details' => [],
            'affected_files' => ['docs/guide.md'],
            'suggested_action' => 'Update',
            'status' => DocIssue::STATUS_OPEN,
        ]);

        $issues = $doc->issues;
        $this->assertCount(1, $issues);
        $this->assertEquals('Related issue', $issues->first()->title);
    }
}
