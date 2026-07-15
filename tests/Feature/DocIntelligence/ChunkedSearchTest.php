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
 * The regression net for the bug chunking exists to fix: everything past
 * Ollama's context ceiling used to be embedded away, leaving 22-59% of the KB
 * unsearchable. See docs/kb/plans/kb-chunking-plan.md.
 */
#[Group('doc-intelligence')]
class ChunkedSearchTest extends TestCase
{
    use CreatesDocIntelligenceTables;
    use RefreshDatabase;

    private string $projectPad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDocIntelligenceTables();

        $this->projectPad = sys_get_temp_dir() . '/chunk_test_' . getmypid();
        if (! is_dir($this->projectPad . '/docs')) {
            mkdir($this->projectPad . '/docs', 0777, true);
        }

        config(['havun-projects.testproject' => [
            'path' => $this->projectPad,
            'server_path' => $this->projectPad,
        ]]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->projectPad . '/docs/*') ?: []);
        parent::tearDown();
    }

    /**
     * Give every distinct prompt its own vector, so cosine similarity can
     * actually tell chunks apart instead of scoring them all identical.
     */
    private function fakeOllamaMetUniekeVectoren(): void
    {
        Http::fake(function ($request) {
            $zaad = crc32($request->data()['prompt'] ?? '');
            mt_srand($zaad);
            $vector = [];
            for ($i = 0; $i < 768; $i++) {
                $vector[] = mt_rand(-1000, 1000) / 1000;
            }

            return Http::response(['embedding' => $vector], 200);
        });
    }

    public function test_a_long_document_is_stored_as_multiple_chunks(): void
    {
        $this->fakeOllamaMetUniekeVectoren();

        file_put_contents($this->projectPad . '/docs/lang.md',
            "# Regels\n\n" . str_repeat("Algemene bepaling. ", 400)
            . "\n\n## Betalingen\n\n" . str_repeat("De club betaalt per kwartaal. ", 400)
        );

        app(DocIndexer::class)->indexProject('testproject', includeCode: false);

        $doc = DocEmbedding::where('file_path', 'docs/lang.md')->firstOrFail();

        $this->assertGreaterThan(1, $doc->chunks()->count(), 'A long document must be chunked');
        $this->assertContains('Regels › Betalingen', $doc->chunks->pluck('heading')->all());
    }

    public function test_the_tail_of_a_long_document_is_embedded_not_truncated(): void
    {
        $this->fakeOllamaMetUniekeVectoren();

        $staart = 'De eindafrekening volgt binnen dertig dagen.';
        file_put_contents($this->projectPad . '/docs/lang.md',
            "# Regels\n\n" . str_repeat("Algemene bepaling. ", 600)
            . "\n\n## Slot\n\n{$staart}"
        );

        app(DocIndexer::class)->indexProject('testproject', includeCode: false);

        $doc = DocEmbedding::where('file_path', 'docs/lang.md')->firstOrFail();
        $staartChunk = $doc->chunks->first(fn($c) => str_contains($c->content, $staart));

        $this->assertNotNull($staartChunk, 'The tail must live in a chunk of its own');
        $this->assertCount(768, $staartChunk->embedding, 'And that chunk must carry a real vector');
        $this->assertSame('nomic-embed-text', $staartChunk->embedding_model);
    }

    public function test_one_document_yields_one_search_result_not_one_per_chunk(): void
    {
        $this->fakeOllamaMetUniekeVectoren();

        file_put_contents($this->projectPad . '/docs/lang.md',
            "# Regels\n\n" . str_repeat("Algemene bepaling. ", 400)
            . "\n\n## Betalingen\n\n" . str_repeat("De club betaalt per kwartaal. ", 400)
        );

        $indexer = app(DocIndexer::class);
        $indexer->indexProject('testproject', includeCode: false);

        $resultaten = $indexer->search('betalingen', 'testproject', 5);

        $paden = array_column($resultaten, 'file_path');
        $this->assertSame($paden, array_unique($paden), 'A file must not occupy several result slots');
    }

    public function test_the_snippet_is_the_matching_passage_not_the_frontmatter(): void
    {
        // A real Ollama call gets the chunk's own text; the query gets its own
        // vector. Steering the fake by content lets us assert which chunk wins.
        Http::fake(function ($request) {
            $prompt = $request->data()['prompt'] ?? '';
            $isBetaling = str_contains($prompt, 'betaal') || str_contains($prompt, 'Betalingen');

            return Http::response(['embedding' => array_fill(0, 768, $isBetaling ? 1.0 : 0.01)], 200);
        });

        file_put_contents($this->projectPad . '/docs/lang.md',
            "---\ntitle: Regels\n---\n\n# Regels\n\n" . str_repeat("Algemene bepaling. ", 400)
            . "\n\n## Betalingen\n\nDe club betaalt per kwartaal."
        );

        $indexer = app(DocIndexer::class);
        $indexer->indexProject('testproject', includeCode: false);

        $resultaten = $indexer->search('betaal', 'testproject', 5);

        $this->assertNotEmpty($resultaten);
        $this->assertStringNotContainsString('title: Regels', $resultaten[0]['snippet']);
        $this->assertSame('Regels › Betalingen', $resultaten[0]['heading']);
    }

    public function test_a_row_without_chunks_still_ranks_on_its_own_vector(): void
    {
        // Rows indexed before chunking existed must keep working until the next
        // index run re-chunks them, or search goes dark during the migration.
        $this->fakeOllamaMetUniekeVectoren();

        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/oud.md',
            'content' => 'Een oud document zonder chunks.',
            'content_hash' => hash('sha256', 'oud'),
            'embedding' => array_fill(0, 768, 0.5),
            'embedding_model' => 'nomic-embed-text',
            'file_type' => 'docs',
        ]);

        $resultaten = app(DocIndexer::class)->search('document', 'testproject', 5);

        $this->assertCount(1, $resultaten);
        $this->assertSame('docs/oud.md', $resultaten[0]['file_path']);
        $this->assertGreaterThan(0.0, $resultaten[0]['similarity']);
    }

    public function test_re_indexing_a_shrunk_file_leaves_no_stale_chunks(): void
    {
        $this->fakeOllamaMetUniekeVectoren();

        $pad = $this->projectPad . '/docs/krimp.md';
        file_put_contents($pad,
            "# Groot\n\n" . str_repeat("Veel tekst. ", 400)
            . "\n\n## Verdwijnt\n\nDeze sectie gaat weg.\n"
        );

        $indexer = app(DocIndexer::class);
        $indexer->indexProject('testproject', includeCode: false);

        file_put_contents($pad, "# Klein\n\nNog maar een regel.");
        $indexer->indexProject('testproject', includeCode: false);

        $doc = DocEmbedding::where('file_path', 'docs/krimp.md')->firstOrFail();

        $this->assertSame(1, $doc->chunks()->count());
        $this->assertStringNotContainsString('Deze sectie gaat weg', $doc->chunks()->first()->content);
    }

    public function test_deleting_a_document_takes_its_chunks_with_it(): void
    {
        $this->fakeOllamaMetUniekeVectoren();

        file_put_contents($this->projectPad . '/docs/weg.md',
            "# Weg\n\n" . str_repeat("Tekst. ", 800)
        );

        $indexer = app(DocIndexer::class);
        $indexer->indexProject('testproject', includeCode: false);

        $doc = DocEmbedding::where('file_path', 'docs/weg.md')->firstOrFail();
        $this->assertGreaterThan(1, $doc->chunks()->count());

        unlink($this->projectPad . '/docs/weg.md');
        $indexer->cleanupOrphaned('testproject');

        $this->assertSame(0, \DB::connection('doc_intelligence')
            ->table('doc_chunks')->where('doc_embedding_id', $doc->id)->count());
    }
}
