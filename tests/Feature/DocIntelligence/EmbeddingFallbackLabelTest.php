<?php

namespace Tests\Feature\DocIntelligence;

use App\Models\DocIntelligence\DocEmbedding;
use App\Services\DocIntelligence\DocIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\CreatesDocIntelligenceTables;
use Tests\TestCase;

/**
 * Regression tests for the embedding fallback bug (found 15-07-2026).
 *
 * generateLocalEmbedding() always returns a non-empty array, so the old
 * `$embedding ? $model : 'tfidf-fallback'` was always truthy: every TF fallback
 * was stored as if it were a real nomic-embed-text vector. Because indexFile()
 * skips on content_hash, those rows could never recover — the entire KB silently
 * ran on keyword matching instead of semantic search.
 */
#[Group('doc-intelligence')]
class EmbeddingFallbackLabelTest extends TestCase
{
    use CreatesDocIntelligenceTables;
    use RefreshDatabase;

    private string $docPad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDocIntelligenceTables();

        $this->docPad = storage_path('framework/testing/kb-doc.md');
        @mkdir(dirname($this->docPad), 0777, true);
        file_put_contents($this->docPad, "# Test\n\nInhoud over scoreboard tokens en beveiliging.");
    }

    protected function tearDown(): void
    {
        @unlink($this->docPad);
        parent::tearDown();
    }

    /** A real vector is a flat list of floats. */
    private function echteVector(): array
    {
        return array_map(static fn (int $i): float => $i / 768, range(1, 768));
    }

    /** indexFile() is protected; reach it directly rather than widening the API for tests. */
    private function indexeer(bool $force = false): bool
    {
        $indexer = new DocIndexer();
        $methode = new \ReflectionMethod($indexer, 'indexFile');
        $methode->setAccessible(true);

        return $methode->invoke($indexer, 'havuncore', 'docs/kb/test.md', $this->docPad, $force);
    }

    public function test_fallback_is_labelled_as_fallback_not_as_the_real_model(): void
    {
        // Ollama unreachable → the indexer must fall back, and say so.
        Http::fake(['127.0.0.1:11434/*' => Http::response(null, 500)]);

        $this->indexeer();

        $row = DocEmbedding::where('file_path', 'docs/kb/test.md')->firstOrFail();

        $this->assertSame(DocIndexer::FALLBACK_MODEL, $row->embedding_model);
        $this->assertTrue(DocIndexer::isFallbackEmbedding($row->embedding));
    }

    public function test_real_ollama_vector_is_labelled_with_the_real_model(): void
    {
        Http::fake(['127.0.0.1:11434/*' => Http::response(['embedding' => $this->echteVector()], 200)]);

        $this->indexeer();

        $row = DocEmbedding::where('file_path', 'docs/kb/test.md')->firstOrFail();

        $this->assertSame('nomic-embed-text', $row->embedding_model);
        $this->assertFalse(DocIndexer::isFallbackEmbedding($row->embedding));
        $this->assertCount(768, $row->embedding);
    }

    /**
     * The core of the bug: a degraded row must be re-embedded once Ollama is back,
     * even though the file is byte-for-byte unchanged.
     */
    public function test_degraded_row_is_upgraded_once_ollama_returns(): void
    {
        // One fake with a switch: calling Http::fake() twice appends stubs rather
        // than replacing them, so the first one would keep winning. Must be a real
        // closure — fn() captures by value and the switch would never flip.
        $ollamaBereikbaar = false;
        Http::fake(function () use (&$ollamaBereikbaar) {
            return $ollamaBereikbaar
                ? Http::response(['embedding' => $this->echteVector()], 200)
                : Http::response(null, 500);
        });

        $this->indexeer();

        $this->assertSame(DocIndexer::FALLBACK_MODEL, DocEmbedding::firstOrFail()->embedding_model);

        // Ollama is back. Same file, same hash — must NOT be skipped.
        $ollamaBereikbaar = true;
        $geindexeerd = $this->indexeer();

        $this->assertTrue($geindexeerd, 'Degraded row was skipped instead of re-embedded');

        $row = DocEmbedding::firstOrFail();
        $this->assertSame('nomic-embed-text', $row->embedding_model);
        $this->assertCount(768, $row->embedding);
    }

    /**
     * The historic rows carry a lying label, so recovery cannot key on the label
     * alone — it has to recognise the fallback by its shape.
     */
    public function test_mislabelled_historic_row_is_still_upgraded(): void
    {
        $ollamaBereikbaar = false;
        Http::fake(function () use (&$ollamaBereikbaar) {
            return $ollamaBereikbaar
                ? Http::response(['embedding' => $this->echteVector()], 200)
                : Http::response(null, 500);
        });

        $this->indexeer();

        // Reproduce the pre-fix state: word map on disk, real model in the label.
        DocEmbedding::query()->update(['embedding_model' => 'nomic-embed-text']);

        $ollamaBereikbaar = true;
        $geindexeerd = $this->indexeer();

        $this->assertTrue($geindexeerd, 'Mislabelled fallback row was not detected as degraded');
        $this->assertCount(768, DocEmbedding::firstOrFail()->embedding);
    }

    public function test_healthy_row_is_still_skipped_when_unchanged(): void
    {
        Http::fake(['127.0.0.1:11434/*' => Http::response(['embedding' => $this->echteVector()], 200)]);

        $this->assertTrue($this->indexeer(), 'First index should write');
        $this->assertFalse($this->indexeer(), 'Unchanged healthy row must be skipped');
    }

    /**
     * Ollama rejects over-long input with a 500 instead of truncating it. Retrying
     * with a shorter prompt must win a real embedding rather than silently degrade.
     */
    public function test_over_long_content_is_retried_shorter_instead_of_falling_back(): void
    {
        // Stays under the chunker's ceiling so this measures the retry ladder
        // alone: a chunked file embeds each slice separately and would add calls
        // that have nothing to do with shrinking. Ollama counts tokens, not
        // characters, so it rejecting text this size is realistic enough.
        file_put_contents($this->docPad, str_repeat('scoreboard token beveiliging ', 100));

        $pogingen = 0;
        Http::fake(function () use (&$pogingen) {
            $pogingen++;

            // Mimic Ollama: only the third, shortest attempt fits the context.
            return $pogingen < 3
                ? Http::response(['error' => 'the input length exceeds the context length'], 500)
                : Http::response(['embedding' => $this->echteVector()], 200);
        });

        $this->indexeer();

        $row = DocEmbedding::firstOrFail();
        $this->assertSame(3, $pogingen, 'Should have shrunk the prompt twice before succeeding');
        $this->assertSame('nomic-embed-text', $row->embedding_model);
        $this->assertCount(768, $row->embedding);
    }

    /** A genuine outage fails identically at every size — retrying is pointless. */
    public function test_real_outage_is_not_retried_at_smaller_sizes(): void
    {
        $pogingen = 0;
        Http::fake(function () use (&$pogingen) {
            $pogingen++;

            return Http::response(['error' => 'model not found'], 500);
        });

        $this->indexeer();

        $this->assertSame(1, $pogingen, 'A non-length error must not trigger retries');
        $this->assertSame(DocIndexer::FALLBACK_MODEL, DocEmbedding::firstOrFail()->embedding_model);
    }
}
