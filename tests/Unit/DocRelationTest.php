<?php

namespace Tests\Unit;

use App\Models\DocIntelligence\DocRelation;
use App\Models\DocIntelligence\DocEmbedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_type_label_references(): void
    {
        $relation = new DocRelation(['relation_type' => DocRelation::TYPE_REFERENCES]);
        $this->assertStringContains('References', $relation->getTypeLabel());
    }

    public function test_get_type_label_duplicates(): void
    {
        $relation = new DocRelation(['relation_type' => DocRelation::TYPE_DUPLICATES]);
        $this->assertStringContains('Duplicates', $relation->getTypeLabel());
    }

    public function test_get_type_label_contradicts(): void
    {
        $relation = new DocRelation(['relation_type' => DocRelation::TYPE_CONTRADICTS]);
        $this->assertStringContains('Contradicts', $relation->getTypeLabel());
    }

    public function test_get_type_label_extends(): void
    {
        $relation = new DocRelation(['relation_type' => DocRelation::TYPE_EXTENDS]);
        $this->assertStringContains('Extends', $relation->getTypeLabel());
    }

    public function test_get_type_label_unknown_returns_raw(): void
    {
        $relation = new DocRelation(['relation_type' => 'custom_type']);
        $this->assertEquals('custom_type', $relation->getTypeLabel());
    }

    public function test_source_document_returns_embedding(): void
    {
        $embedding = DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/source.md',
            'content' => 'Source content',
            'content_hash' => hash('sha256', 'source'),
            'embedding' => null,
            'embedding_model' => null,
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        $relation = DocRelation::create([
            'source_project' => 'testproject',
            'source_file' => 'docs/source.md',
            'target_project' => 'testproject',
            'target_file' => 'docs/target.md',
            'relation_type' => DocRelation::TYPE_REFERENCES,
            'confidence' => 0.9,
            'auto_detected' => true,
        ]);

        $result = $relation->sourceDocument();
        $this->assertNotNull($result);
        $this->assertEquals('docs/source.md', $result->file_path);
    }

    public function test_source_document_returns_null_when_not_found(): void
    {
        $relation = DocRelation::create([
            'source_project' => 'nonexistent',
            'source_file' => 'docs/nope.md',
            'target_project' => 'testproject',
            'target_file' => 'docs/target.md',
            'relation_type' => DocRelation::TYPE_REFERENCES,
            'confidence' => 0.5,
            'auto_detected' => false,
        ]);

        $this->assertNull($relation->sourceDocument());
    }

    public function test_target_document_returns_embedding(): void
    {
        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/target.md',
            'content' => 'Target content',
            'content_hash' => hash('sha256', 'target'),
            'embedding' => null,
            'embedding_model' => null,
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        $relation = DocRelation::create([
            'source_project' => 'testproject',
            'source_file' => 'docs/source.md',
            'target_project' => 'testproject',
            'target_file' => 'docs/target.md',
            'relation_type' => DocRelation::TYPE_DUPLICATES,
            'confidence' => 0.95,
            'auto_detected' => true,
        ]);

        $result = $relation->targetDocument();
        $this->assertNotNull($result);
        $this->assertEquals('docs/target.md', $result->file_path);
    }

    public function test_scope_of_type(): void
    {
        DocRelation::create([
            'source_project' => 'p', 'source_file' => 'a.md',
            'target_project' => 'p', 'target_file' => 'b.md',
            'relation_type' => DocRelation::TYPE_DUPLICATES,
            'confidence' => 0.9, 'auto_detected' => true,
        ]);
        DocRelation::create([
            'source_project' => 'p', 'source_file' => 'c.md',
            'target_project' => 'p', 'target_file' => 'd.md',
            'relation_type' => DocRelation::TYPE_REFERENCES,
            'confidence' => 0.8, 'auto_detected' => true,
        ]);

        $this->assertCount(1, DocRelation::ofType(DocRelation::TYPE_DUPLICATES)->get());
        $this->assertCount(1, DocRelation::ofType(DocRelation::TYPE_REFERENCES)->get());
    }

    public function test_scope_high_confidence(): void
    {
        DocRelation::create([
            'source_project' => 'p', 'source_file' => 'a.md',
            'target_project' => 'p', 'target_file' => 'b.md',
            'relation_type' => DocRelation::TYPE_DUPLICATES,
            'confidence' => 0.95, 'auto_detected' => true,
        ]);
        DocRelation::create([
            'source_project' => 'p', 'source_file' => 'c.md',
            'target_project' => 'p', 'target_file' => 'd.md',
            'relation_type' => DocRelation::TYPE_REFERENCES,
            'confidence' => 0.5, 'auto_detected' => true,
        ]);

        $this->assertCount(1, DocRelation::highConfidence(0.8)->get());
    }

    public function test_scope_problematic(): void
    {
        DocRelation::create([
            'source_project' => 'p', 'source_file' => 'a.md',
            'target_project' => 'p', 'target_file' => 'b.md',
            'relation_type' => DocRelation::TYPE_DUPLICATES,
            'confidence' => 0.9, 'auto_detected' => true,
        ]);
        DocRelation::create([
            'source_project' => 'p', 'source_file' => 'c.md',
            'target_project' => 'p', 'target_file' => 'd.md',
            'relation_type' => DocRelation::TYPE_CONTRADICTS,
            'confidence' => 0.8, 'auto_detected' => true,
        ]);
        DocRelation::create([
            'source_project' => 'p', 'source_file' => 'e.md',
            'target_project' => 'p', 'target_file' => 'f.md',
            'relation_type' => DocRelation::TYPE_REFERENCES,
            'confidence' => 0.7, 'auto_detected' => true,
        ]);

        $this->assertCount(2, DocRelation::problematic()->get());
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
