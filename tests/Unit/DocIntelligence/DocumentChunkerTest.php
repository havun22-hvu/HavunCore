<?php

namespace Tests\Unit\DocIntelligence;

use App\Services\DocIntelligence\DocumentChunker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentChunkerTest extends TestCase
{
    private DocumentChunker $chunker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chunker = new DocumentChunker();
    }

    #[Test]
    public function empty_content_yields_no_chunks(): void
    {
        $this->assertSame([], $this->chunker->chunk(''));
        $this->assertSame([], $this->chunker->chunk("   \n\n  "));
    }

    #[Test]
    public function a_short_document_stays_one_chunk(): void
    {
        $content = "# Titel\n\nEen kort document.";

        $chunks = $this->chunker->chunk($content);

        $this->assertCount(1, $chunks);
        $this->assertSame($content, $chunks[0]['content']);
    }

    #[Test]
    public function a_long_document_splits_on_headings(): void
    {
        $content = "# Titel\n\n" . str_repeat("Inleiding. ", 300)
            . "\n\n## Betalingen\n\n" . str_repeat("De club betaalt. ", 300)
            . "\n\n## Ledenbeheer\n\n" . str_repeat("Een lid heeft een band. ", 300);

        $chunks = $this->chunker->chunk($content);

        $this->assertGreaterThan(1, count($chunks));
        $headings = array_column($chunks, 'heading');
        $this->assertContains('Titel › Betalingen', $headings);
        $this->assertContains('Titel › Ledenbeheer', $headings);
    }

    #[Test]
    public function the_tail_of_a_long_document_survives(): void
    {
        // The whole point: this sentence sits far past the old truncation
        // ceiling and used to be unfindable.
        $content = "# Start\n\n" . str_repeat("Vulling. ", 2000)
            . "\n\n## Slot\n\nDe laatste afspraak is bindend.";

        $chunks = $this->chunker->chunk($content);

        $alleTekst = implode("\n", array_column($chunks, 'content'));
        $this->assertStringContainsString('De laatste afspraak is bindend.', $alleTekst);
    }

    #[Test]
    public function no_chunk_exceeds_the_ceiling(): void
    {
        $content = "# Titel\n\n" . str_repeat("Een zin zonder alineagrenzen. ", 1000);

        foreach ($this->chunker->chunk($content) as $chunk) {
            $this->assertLessThanOrEqual(DocumentChunker::MAX_CHARS, mb_strlen($chunk['content']));
        }
    }

    #[Test]
    public function a_hash_inside_a_code_fence_is_not_a_heading(): void
    {
        // A shell comment would otherwise open a new section and split the
        // fence in half -- which is exactly what makes IssueDetector report
        // links and fences it can no longer parse.
        $content = "# Echt\n\n" . str_repeat("Tekst. ", 400)
            . "\n\n```bash\n# dit is een comment, geen kop\nrm -rf /tmp/x\n```\n\n"
            . str_repeat("Meer tekst. ", 400);

        $headings = array_column($this->chunker->chunk($content), 'heading');

        $this->assertNotContains('Echt › dit is een comment, geen kop', $headings);
        foreach ($headings as $heading) {
            $this->assertStringNotContainsString('comment', (string) $heading);
        }
    }

    #[Test]
    public function code_files_split_without_heading_detection(): void
    {
        $content = str_repeat("Route::get('/pad', [Controller::class, 'method']);\n", 500);

        $chunks = $this->chunker->chunk($content, 'code');

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertNull($chunk['heading']);
            $this->assertLessThanOrEqual(DocumentChunker::MAX_CHARS, mb_strlen($chunk['content']));
        }
    }

    #[Test]
    public function a_single_line_past_the_ceiling_is_cut_rather_than_dropped(): void
    {
        $content = str_repeat('x', DocumentChunker::MAX_CHARS * 2 + 500);

        $chunks = $this->chunker->chunk($content, 'code');

        $this->assertSame(
            mb_strlen($content),
            array_sum(array_map(fn($c) => mb_strlen($c['content']), $chunks))
        );
    }

    #[Test]
    public function headings_nest_into_a_path(): void
    {
        // The sibling rule (## Twee replacing ## Een rather than nesting under
        // it) is already proven by a_long_document_splits_on_headings; what only
        // this case shows is a three-level path.
        $content = "# Doc\n\n## Een\n\n### Diep\n\n" . str_repeat("A. ", 800)
            . "\n\n## Twee\n\n" . str_repeat("B. ", 800);

        $headings = array_column($this->chunker->chunk($content), 'heading');

        $this->assertContains('Doc › Een › Diep', $headings);
    }

    #[Test]
    public function the_embeddable_text_carries_context_the_chunk_lacks(): void
    {
        $tekst = $this->chunker->embeddableText('docs/business-rules.md', 'Regels › Betalingen', 'De club betaalt.');

        $this->assertStringContainsString('docs/business-rules.md › Regels › Betalingen', $tekst);
        $this->assertStringContainsString('De club betaalt.', $tekst);
    }

    #[Test]
    public function the_embeddable_text_falls_back_to_the_path_without_a_heading(): void
    {
        $tekst = $this->chunker->embeddableText('routes/web.php', null, 'Route::get(...);');

        $this->assertStringStartsWith('routes/web.php', $tekst);
    }
}
