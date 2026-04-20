<?php

namespace App\Services\CriticalPaths;

/**
 * Check whether test-file references from `critical-paths-*.md` actually
 * resolve on disk. Supports both direct paths (`tests/Feature/X.php`) and
 * globs (`tests/Unit/Vault/*.php`).
 *
 * All paths are interpreted relative to `$basePath` (typically the project
 * root, e.g. `base_path()`).
 */
class ReferenceChecker
{
    public function __construct(private readonly string $basePath) {}

    /**
     * @return array{path: string, exists: bool, matches: list<string>, error: ?string}
     */
    public function check(string $reference): array
    {
        $absolute = $this->basePath . DIRECTORY_SEPARATOR . ltrim($reference, '/\\');

        if (str_contains($reference, '*')) {
            $matches = glob($absolute) ?: [];
            $relative = array_map(
                fn ($abs) => $this->relativize($abs),
                $matches
            );

            return [
                'path' => $reference,
                'exists' => count($matches) > 0,
                'matches' => $relative,
                'error' => count($matches) === 0
                    ? "glob matched 0 files"
                    : null,
            ];
        }

        $exists = is_file($absolute);

        return [
            'path' => $reference,
            'exists' => $exists,
            'matches' => $exists ? [$reference] : [],
            'error' => $exists ? null : 'file missing',
        ];
    }

    /**
     * @param list<string> $references
     * @return list<array{path: string, exists: bool, matches: list<string>, error: ?string}>
     */
    public function checkAll(array $references): array
    {
        return array_values(array_map($this->check(...), $references));
    }

    private function relativize(string $absolute): string
    {
        $base = rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR;
        if (str_starts_with($absolute, $base)) {
            return str_replace('\\', '/', substr($absolute, strlen($base)));
        }

        return str_replace('\\', '/', $absolute);
    }
}
