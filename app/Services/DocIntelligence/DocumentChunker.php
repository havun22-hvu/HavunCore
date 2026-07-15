<?php

namespace App\Services\DocIntelligence;

/**
 * Splits a document into slices small enough for Ollama to embed whole.
 *
 * Ollama serves nomic-embed-text with a ~2048-token context and rejects longer
 * input outright, so DocIndexer used to truncate — the tail of every long
 * document was simply never embedded. Chunking exists to make that tail
 * findable, and the whole point is lost if a chunk is cut where it stops making
 * sense, hence: split on headings, never inside a fenced code block.
 *
 * Pure text in, pure array out — no database, no Ollama, fully unit-testable.
 * See docs/kb/plans/kb-chunking-plan.md.
 */
class DocumentChunker
{
    /**
     * Aim well under the token ceiling. Characters-per-token swings hard with
     * content — dense code hits the ceiling far sooner than prose — so target a
     * size that leaves room rather than one that flirts with rejection.
     */
    public const TARGET_CHARS = 2500;
    public const MAX_CHARS = 4000;

    /**
     * @return array<int, array{content: string, heading: ?string}>
     */
    public function chunk(string $content, string $fileType = 'docs'): array
    {
        if (trim($content) === '') {
            return [];
        }

        if (mb_strlen($content) <= self::MAX_CHARS) {
            return [['content' => $content, 'heading' => null]];
        }

        $sections = $fileType === 'docs'
            ? $this->splitOnHeadings($content)
            : [['content' => $content, 'heading' => null]];

        $chunks = [];
        foreach ($sections as $section) {
            foreach ($this->enforceMaxSize($section['content']) as $piece) {
                if (trim($piece) === '') {
                    continue;
                }
                $chunks[] = ['content' => $piece, 'heading' => $section['heading']];
            }
        }

        return $chunks;
    }

    /**
     * Split markdown on its headings, keeping each heading with the text below
     * it. Headings inside a fenced code block are not headings — a shell comment
     * (`# rm -rf`) would otherwise start a new section.
     *
     * @return array<int, array{content: string, heading: ?string}>
     */
    protected function splitOnHeadings(string $content): array
    {
        $lines = explode("\n", $content);
        $sections = [];
        $current = [];
        $path = [];          // heading path: level => text
        $heading = null;
        $inFence = false;

        foreach ($lines as $line) {
            if ($this->isFenceDelimiter($line)) {
                $inFence = !$inFence;
            }

            if (!$inFence && preg_match('/^(#{1,6})\s+(.+?)\s*$/', $line, $m)) {
                if (trim(implode("\n", $current)) !== '') {
                    $sections[] = ['content' => implode("\n", $current), 'heading' => $heading];
                }

                // Keys stay ascending by construction: everything at or below
                // this level is dropped before the deepest one is appended.
                $level = strlen($m[1]);
                $path = array_filter($path, fn($l) => $l < $level, ARRAY_FILTER_USE_KEY);
                $path[$level] = trim($m[2]);
                $heading = implode(' › ', $path);

                $current = [$line];
                continue;
            }

            $current[] = $line;
        }

        if (trim(implode("\n", $current)) !== '') {
            $sections[] = ['content' => implode("\n", $current), 'heading' => $heading];
        }

        return $sections;
    }

    /**
     * A section can still exceed the ceiling on its own — a heading-less
     * routes/web.php, or one long chapter. Split it down, preferring the
     * biggest boundary that fits: paragraphs, then lines.
     *
     * @return array<int, string>
     */
    protected function enforceMaxSize(string $text): array
    {
        if (mb_strlen($text) <= self::MAX_CHARS) {
            return [$text];
        }

        $pieces = $this->packUnits(preg_split('/(\n{2,})/', $text, -1, PREG_SPLIT_DELIM_CAPTURE), "\n\n");

        $result = [];
        foreach ($pieces as $piece) {
            if (mb_strlen($piece) <= self::MAX_CHARS) {
                $result[] = $piece;
                continue;
            }

            foreach ($this->packUnits(explode("\n", $piece), "\n") as $sub) {
                // A single line over the ceiling (minified asset, long data URI)
                // has no natural seam left; a hard cut beats dropping it.
                $result = array_merge($result, mb_str_split($sub, self::MAX_CHARS));
            }
        }

        return $result;
    }

    /**
     * Greedily fill chunks up to TARGET_CHARS, keeping whole units together. A
     * single unit larger than that passes through as-is — enforceMaxSize() is
     * what holds the ceiling.
     *
     * @param  array<int, string>  $units
     * @return array<int, string>
     */
    protected function packUnits(array $units, string $glue): array
    {
        $chunks = [];
        $buffer = '';

        foreach ($units as $unit) {
            if ($unit === '') {
                continue;
            }

            $candidate = $buffer === '' ? $unit : $buffer . $glue . $unit;

            if ($buffer !== '' && mb_strlen($candidate) > self::TARGET_CHARS) {
                $chunks[] = $buffer;
                $buffer = $unit;
                continue;
            }

            $buffer = $candidate;
        }

        if (trim($buffer) !== '') {
            $chunks[] = $buffer;
        }

        return $chunks;
    }

    protected function isFenceDelimiter(string $line): bool
    {
        return (bool) preg_match('/^\s*(```|~~~)/', $line);
    }

    /**
     * What actually gets embedded. A chunk from the middle of a document says
     * little on its own: "the club pays the invoice" is meaningless without
     * knowing it sits under Billing in business-rules.md. The prefix is not
     * stored — it only steers the vector.
     */
    public function embeddableText(string $filePath, ?string $heading, string $content): string
    {
        $prefix = $heading !== null && $heading !== ''
            ? "{$filePath} › {$heading}"
            : $filePath;

        return "{$prefix}\n\n{$content}";
    }
}
