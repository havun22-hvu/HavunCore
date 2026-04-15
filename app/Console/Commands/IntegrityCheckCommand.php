<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\DomCrawler\Crawler;

class IntegrityCheckCommand extends Command
{
    protected $signature = 'integrity:check
        {--project= : Project root to check (default: current directory)}
        {--json : Output results as JSON}';

    protected $description = 'Validate .integrity.json against codebase — checks text content and CSS selectors';

    public function handle(): int
    {
        $projectRoot = $this->option('project') ?: base_path();
        $integrityFile = rtrim($projectRoot, '/\\') . '/.integrity.json';

        if (! file_exists($integrityFile)) {
            $this->info('No .integrity.json found — skipping integrity check');

            return 0;
        }

        $config = json_decode(file_get_contents($integrityFile), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON in .integrity.json: ' . json_last_error_msg());

            return 1;
        }

        $version = $config['version'] ?? '1.0';
        $project = $config['project'] ?? 'unknown';
        $checks = $config['checks'] ?? [];

        $this->info("Integrity check: {$project} (schema v{$version})");
        $this->newLine();

        $passed = 0;
        $failed = 0;
        $results = [];

        foreach ($checks as $check) {
            $filePath = rtrim($projectRoot, '/\\') . '/' . $check['file'];

            if (! file_exists($filePath)) {
                $this->error("FAIL: {$check['file']} — FILE NOT FOUND");
                $this->line("  → {$check['description']}");
                $failed++;
                $results[] = [
                    'file' => $check['file'],
                    'status' => 'fail',
                    'reason' => 'file_not_found',
                    'description' => $check['description'],
                ];

                continue;
            }

            $content = file_get_contents($filePath);
            $failures = [];

            // must_contain: plain text search
            if (! empty($check['must_contain'])) {
                foreach ($check['must_contain'] as $term) {
                    if (! str_contains($content, $term)) {
                        $failures[] = "text: {$term}";
                    }
                }
            }

            // must_contain_selector: CSS selector search in HTML files
            if (! empty($check['must_contain_selector'])) {
                $failures = array_merge($failures, $this->checkSelectors($content, $check['must_contain_selector']));
            }

            // must_contain_route: check route names exist
            if (! empty($check['must_contain_route'])) {
                foreach ($check['must_contain_route'] as $routeName) {
                    if (! \Illuminate\Support\Facades\Route::has($routeName)) {
                        $failures[] = "route: {$routeName}";
                    }
                }
            }

            if (! empty($failures)) {
                $this->error("FAIL: {$check['file']}");
                $this->line("  → {$check['description']}");
                $this->line('  → Missing: ' . implode(', ', $failures));
                $failed++;
                $results[] = [
                    'file' => $check['file'],
                    'status' => 'fail',
                    'reason' => 'missing_elements',
                    'missing' => $failures,
                    'description' => $check['description'],
                ];
            } else {
                $checkCount = count($check['must_contain'] ?? [])
                    + count($check['must_contain_selector'] ?? [])
                    + count($check['must_contain_route'] ?? []);
                $this->info("OK: {$check['file']} ({$checkCount} checks)");
                $passed++;
                $results[] = [
                    'file' => $check['file'],
                    'status' => 'pass',
                    'checks' => $checkCount,
                ];
            }
        }

        $this->newLine();
        $this->line("{$passed} passed, {$failed} failed");

        if ($this->option('json')) {
            $this->newLine();
            $this->line(json_encode([
                'project' => $project,
                'version' => $version,
                'passed' => $passed,
                'failed' => $failed,
                'results' => $results,
            ], JSON_PRETTY_PRINT));
        }

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Check CSS selectors in HTML content using regex (no external dependency needed).
     */
    private function checkSelectors(string $content, array $selectors): array
    {
        $failures = [];

        foreach ($selectors as $selector) {
            if (! $this->selectorExists($content, $selector)) {
                $failures[] = "selector: {$selector}";
            }
        }

        return $failures;
    }

    /**
     * Check if a CSS selector likely exists in HTML content.
     * Supports: tag, .class, #id, [attribute], and combinations.
     */
    private function selectorExists(string $html, string $selector): bool
    {
        // #id — check for id="value"
        if (preg_match('/^#([\w-]+)$/', $selector, $m)) {
            return (bool) preg_match('/\bid=["\']' . preg_quote($m[1], '/') . '["\']/i', $html);
        }

        // .class — check for class="... value ..."
        if (preg_match('/^\.([\w-]+)$/', $selector, $m)) {
            return (bool) preg_match('/\bclass=["\'][^"\']*\b' . preg_quote($m[1], '/') . '\b[^"\']*["\']/i', $html);
        }

        // tag.class — check for <tag ... class="... value ..."
        if (preg_match('/^([\w-]+)\.([\w-]+)$/', $selector, $m)) {
            return (bool) preg_match('/<' . preg_quote($m[1], '/') . '\b[^>]*class=["\'][^"\']*\b' . preg_quote($m[2], '/') . '\b/i', $html);
        }

        // tag#id
        if (preg_match('/^([\w-]+)#([\w-]+)$/', $selector, $m)) {
            return (bool) preg_match('/<' . preg_quote($m[1], '/') . '\b[^>]*id=["\']' . preg_quote($m[2], '/') . '["\']/i', $html);
        }

        // [attribute] — check for attribute presence
        if (preg_match('/^\[([\w-]+)\]$/', $selector, $m)) {
            return (bool) preg_match('/\b' . preg_quote($m[1], '/') . '\s*=/i', $html);
        }

        // [attribute="value"]
        if (preg_match('/^\[([\w-]+)=["\']?([^"\']+)["\']?\]$/', $selector, $m)) {
            return (bool) preg_match('/\b' . preg_quote($m[1], '/') . '\s*=\s*["\']?' . preg_quote($m[2], '/') . '/i', $html);
        }

        // tag — bare tag name
        if (preg_match('/^[\w-]+$/', $selector)) {
            return (bool) preg_match('/<' . preg_quote($selector, '/') . '[\s>]/i', $html);
        }

        // Fallback: check as literal string
        return str_contains($html, $selector);
    }
}
