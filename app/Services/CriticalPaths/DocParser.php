<?php

namespace App\Services\CriticalPaths;

/**
 * Parse a `critical-paths-{project}.md` document into a structured list of
 * critical paths with their test-file references.
 *
 * Expected markdown format (see docs/kb/reference/critical-paths-havuncore.md):
 *
 *     ## Pad 1 — Vault (credentials-brokerage)
 *     ...
 *     **Tests die dit afdekken:**
 *
 *     - `tests/Feature/VaultControllerTest.php` (happy path + auth)
 *     - `tests/Unit/Vault/*` (service/encryptie)
 *
 * Parenthetical commentary is stripped; glob paths are kept as-is (expanded
 * later by ReferenceChecker).
 */
class DocParser
{
    /**
     * @return array<int, array{name: string, references: list<string>}>
     */
    public function parse(string $markdown): array
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $paths = [];
        $current = null;
        $inTestsSection = false;

        foreach ($lines as $line) {
            if (preg_match('/^##\s+Pad\s+\d+\s*[—\-:]\s*(.+?)\s*$/u', $line, $m)) {
                if ($current !== null) {
                    $paths[] = $current;
                }
                $current = ['name' => trim($m[1]), 'references' => []];
                $inTestsSection = false;
                continue;
            }

            if ($current === null) {
                continue;
            }

            if (preg_match('/^\*\*Tests(?:\s+die\s+dit\s+(?:af)?dekken)?:\*\*\s*$/iu', $line)) {
                $inTestsSection = true;
                continue;
            }

            if ($inTestsSection && preg_match('/^(##\s|\*\*[^*]+:\*\*)/u', $line)) {
                $inTestsSection = false;
            }

            if ($inTestsSection && preg_match('/^\s*-\s+`([^`]+\.(?:php|ts|tsx|js|jsx))`/u', $line, $m)) {
                $current['references'][] = $m[1];
            }
        }

        if ($current !== null) {
            $paths[] = $current;
        }

        return $paths;
    }

    /**
     * @return array<int, array{name: string, references: list<string>}>
     */
    public function parseFile(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        return $this->parse((string) file_get_contents($path));
    }
}
