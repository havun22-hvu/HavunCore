<?php

namespace App\Services\DocsAudit;

use App\Enums\Severity;

/**
 * Basale structuur-checks per MD-file:
 * - Missende frontmatter → High
 * - Missende H1 → Low
 * - Lege section (## Header zonder body tot volgende header) → Low
 * - Unbalanced code-fences → Medium (parse-issue signal)
 * - File > 500 regels → Low (kandidaat voor splitsing)
 * - File < 5 regels → Info (kandidaat voor inline verwerking)
 */
class StructureChecker
{
    private const BIG_FILE_LINES = 500;

    private const TINY_FILE_LINES = 5;

    /**
     * @return list<array<string,mixed>>
     */
    public function check(string $absolutePath): array
    {
        $content = @file_get_contents($absolutePath);
        if ($content === false) {
            return [];
        }

        $findings = [];
        $lines = explode("\n", $content);
        $lineCount = count($lines);

        if (! $this->hasFrontmatter($content)) {
            $findings[] = $this->finding($absolutePath, Severity::High, 'Ontbrekende frontmatter', 'Voeg `---` block toe met title/type/scope');
        }

        if (! $this->hasH1($content)) {
            $findings[] = $this->finding($absolutePath, Severity::Low, 'Geen H1 gevonden', 'Voeg `# Titel` toe');
        }

        foreach ($this->emptySections($lines) as $header) {
            $findings[] = $this->finding($absolutePath, Severity::Low, "Lege section: {$header}", 'Vul aan of verwijder');
        }

        if ($this->unbalancedCodeFences($content)) {
            // Low i.p.v. Medium: demo-content in KB-docs (voorbeelden van
            // FOUT-patronen) kan legitiem unbalanced fences hebben.
            // Markdown-renderers handlen dit meestal door tot EOF te lopen.
            $findings[] = $this->finding($absolutePath, Severity::Low, 'Oneven aantal ```-fences', 'Controleer of dit bewust is (demo); sluit anders code-block(s)');
        }

        if ($lineCount > self::BIG_FILE_LINES) {
            // Low i.p.v. High — een grote file is geen quality-breuk, alleen
            // een ergonomic hint voor leesbaarheid/onderhoud.
            $findings[] = $this->finding($absolutePath, Severity::Low, "File is {$lineCount} regels (> " . self::BIG_FILE_LINES . ')', 'Overweeg splitsing');
        } elseif ($lineCount < self::TINY_FILE_LINES) {
            $findings[] = $this->finding($absolutePath, Severity::Info, "File is {$lineCount} regels (< " . self::TINY_FILE_LINES . ')', 'Overweeg inline verwerking');
        }

        return $findings;
    }

    private function hasFrontmatter(string $content): bool
    {
        return (bool) preg_match('/^---\n.*?\n---/s', $content);
    }

    private function hasH1(string $content): bool
    {
        return (bool) preg_match('/^#\s+\S/m', $content);
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function emptySections(array $lines): array
    {
        // Alleen H2-secties checken. Een H2 met H3/H4-subsecties heeft
        // inhoud via zijn kinderen; dat mag geen "leeg" melden. Een H3
        // zonder body valt onder zijn H2-ouder en is geen eigen section.
        $empty = [];
        $currentHeader = null;
        $currentHasBody = false;

        foreach ($lines as $line) {
            if (preg_match('/^##\s+(.+)$/', $line, $m)) {
                if ($currentHeader !== null && ! $currentHasBody) {
                    $empty[] = $currentHeader;
                }
                $currentHeader = trim($m[1]);
                $currentHasBody = false;
                continue;
            }
            // Any non-empty line (including H3/H4/text/code) = content.
            if (trim($line) !== '') {
                $currentHasBody = true;
            }
        }

        if ($currentHeader !== null && ! $currentHasBody) {
            $empty[] = $currentHeader;
        }

        return $empty;
    }

    private function unbalancedCodeFences(string $content): bool
    {
        return substr_count($content, "\n```") % 2 !== 0;
    }

    private function finding(string $path, Severity $severity, string $detail, string $action): array
    {
        return [
            'severity' => $severity->value,
            'detector' => 'structure',
            'file' => $path,
            'detail' => $detail,
            'action' => $action,
        ];
    }
}
