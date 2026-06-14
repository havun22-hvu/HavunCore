<?php

namespace App\Services\DocIntelligence;

use App\Models\DocIntelligence\DocEmbedding;
use App\Models\DocIntelligence\DocIssue;
use App\Models\DocIntelligence\DocRelation;
use Carbon\Carbon;

class IssueDetector
{
    protected DocIndexer $indexer;

    // Thresholds
    protected float $duplicateThreshold = 0.90;  // Embedding similarity above this = candidate duplicate
    protected float $duplicateLexicalThreshold = 0.30; // Verbatim overlap (word-trigram Jaccard) required to confirm
    protected int $outdatedDays = 90;             // Days without update = outdated

    /**
     * Files that are intentionally shared across projects (skip duplicate detection)
     */
    protected array $sharedFilePatterns = [
        '.claude/commands/',
        '.claude/archive/',
        '_structure/',
        'CLAUDE.md',
    ];

    /**
     * File types to exclude from duplicate detection (code has structural similarity, not content duplicates)
     */
    protected array $skipDuplicateTypes = [
        'model', 'controller', 'middleware', 'command', 'migration', 'config', 'route', 'support', 'code', 'structure', 'service',
    ];

    public function __construct(DocIndexer $indexer)
    {
        $this->indexer = $indexer;
    }

    /**
     * Run all issue detection for a project
     */
    public function detectAll(?string $project = null): array
    {
        $results = [
            'duplicates' => $this->detectDuplicates($project),
            'outdated' => $this->detectOutdated($project),
            'broken_links' => $this->detectBrokenLinks($project),
            'inconsistencies' => $this->detectInconsistencies($project),
        ];

        return $results;
    }

    /**
     * Detect duplicate content — only WITHIN same project, skip shared files
     */
    public function detectDuplicates(?string $project = null): int
    {
        $issuesFound = 0;
        $projects = $project
            ? [strtolower($project)]
            : DocEmbedding::distinct()->pluck('project')->toArray();

        foreach ($projects as $proj) {
            $documents = DocEmbedding::where('project', $proj)
                ->whereNotNull('embedding')
                ->get()
                ->filter(fn ($doc) => !$this->isSharedFile($doc->file_path)
                    && !in_array($doc->file_type, $this->skipDuplicateTypes));

            $docs = $documents->values();
            $count = $docs->count();
            $checked = [];

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $doc1 = $docs[$i];
                    $doc2 = $docs[$j];

                    $similarity = $this->indexer->calculateSimilarity($doc1->embedding ?? [], $doc2->embedding ?? []);

                    // Embedding cosine catches same-TOPIC docs (e.g. two server docs, parallel
                    // per-project references, ADRs) which are not real duplicates. Require verbatim
                    // overlap as a second gate so only genuine copy-paste is flagged.
                    if ($similarity >= $this->duplicateThreshold
                        && $this->lexicalOverlap($doc1->content ?? '', $doc2->content ?? '') >= $this->duplicateLexicalThreshold) {
                        $existingIssue = DocIssue::where('issue_type', DocIssue::TYPE_DUPLICATE)
                            ->where('project', $proj)
                            ->where('status', DocIssue::STATUS_OPEN)
                            ->whereJsonContains('affected_files', "{$proj}:{$doc1->file_path}")
                            ->whereJsonContains('affected_files', "{$proj}:{$doc2->file_path}")
                            ->first();

                        if (!$existingIssue) {
                            DocIssue::create([
                                'project' => $proj,
                                'issue_type' => DocIssue::TYPE_DUPLICATE,
                                'severity' => DocIssue::SEVERITY_MEDIUM,
                                'title' => "Duplicate content detected",
                                'details' => [
                                    'similarity' => round($similarity * 100, 1) . '%',
                                    'file1' => ['project' => $doc1->project, 'path' => $doc1->file_path],
                                    'file2' => ['project' => $doc2->project, 'path' => $doc2->file_path],
                                ],
                                'affected_files' => [
                                    "{$proj}:{$doc1->file_path}",
                                    "{$proj}:{$doc2->file_path}",
                                ],
                                'suggested_action' => "Review both files and consolidate into one location.",
                            ]);

                            DocRelation::updateOrCreate(
                                [
                                    'source_project' => $doc1->project,
                                    'source_file' => $doc1->file_path,
                                    'target_project' => $doc2->project,
                                    'target_file' => $doc2->file_path,
                                    'relation_type' => DocRelation::TYPE_DUPLICATES,
                                ],
                                ['confidence' => $similarity, 'auto_detected' => true]
                            );

                            $issuesFound++;
                        }
                    }
                }
            }
        }

        return $issuesFound;
    }

    /**
     * Check if a file path matches shared file patterns (intentionally duplicated across projects)
     */
    protected function isSharedFile(string $filePath): bool
    {
        foreach ($this->sharedFilePatterns as $pattern) {
            if (str_contains($filePath, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verbatim overlap between two documents as the Jaccard index over word trigrams
     * (3-word shingles). Real copy-paste duplicates share long verbatim passages and
     * score high; documents that merely share a topic vocabulary score low.
     *
     * @return float 0.0–1.0
     */
    protected function lexicalOverlap(string $a, string $b): float
    {
        $shinglesA = $this->wordTrigrams($a);
        $shinglesB = $this->wordTrigrams($b);

        if (empty($shinglesA) || empty($shinglesB)) {
            // Too short to compare reliably — fall back to "treat as overlapping"
            // so very small near-identical stubs are still caught.
            return 1.0;
        }

        $intersection = count(array_intersect_key($shinglesA, $shinglesB));
        $union = count($shinglesA + $shinglesB);

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Build a set (keys = shingle) of normalized 3-word shingles from text.
     */
    protected function wordTrigrams(string $text): array
    {
        $normalized = mb_strtolower($text);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized);
        $words = preg_split('/\s+/', trim($normalized), -1, PREG_SPLIT_NO_EMPTY);

        $shingles = [];
        $count = count($words);
        for ($i = 0; $i + 2 < $count; $i++) {
            $shingles[$words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2]] = true;
        }

        return $shingles;
    }

    /**
     * File types to skip for outdated detection (stable code doesn't need regular updates)
     */
    protected array $skipOutdatedTypes = [
        'model', 'controller', 'middleware', 'command', 'migration',
        'config', 'route', 'support', 'code', 'structure', 'service',
    ];

    /**
     * Path fragments for intentionally frozen docs — never flag these as outdated.
     * Archived/legacy/historical docs are deliberately not maintained.
     */
    protected array $frozenDocPatterns = [
        '/archive/', 'archive/', '/archived/', 'archived/', '/legacy/', 'legacy/', '/_history/',
    ];

    /**
     * Check if a document is intentionally frozen (archived) and should never be flagged outdated.
     */
    protected function isFrozenDoc(string $filePath): bool
    {
        $normalized = str_replace('\\', '/', $filePath);
        foreach ($this->frozenDocPatterns as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        // Date-stamped snapshot files (e.g. mutation-baseline-2026-04-17.md, qv-scan-2026-05-02.md)
        // are point-in-time records — a newer snapshot supersedes them, the old one stays frozen.
        if (preg_match('#[-_/]\d{4}-\d{2}-\d{2}\.md$#i', $normalized)) {
            return true;
        }

        return false;
    }

    /**
     * Detect outdated documents (only .md files — code files are stable by nature)
     */
    public function detectOutdated(?string $project = null): int
    {
        $cutoffDate = Carbon::now()->subDays($this->outdatedDays);

        $documents = DocEmbedding::when($project, function ($q) use ($project) {
            return $q->where('project', strtolower($project));
        })
        ->where('file_modified_at', '<', $cutoffDate)
        ->get();

        $issuesFound = 0;

        foreach ($documents as $doc) {
            // Skip code files — they are stable by nature and don't need regular updates
            if (in_array($doc->file_type, $this->skipOutdatedTypes)) {
                continue;
            }

            // Skip intentionally frozen docs (archive/legacy) — they are never "outdated"
            if ($this->isFrozenDoc($doc->file_path)) {
                continue;
            }

            // Skip if already has open issue
            $existingIssue = DocIssue::where('issue_type', DocIssue::TYPE_OUTDATED)
                ->where('status', DocIssue::STATUS_OPEN)
                ->whereJsonContains('affected_files', "{$doc->project}:{$doc->file_path}")
                ->first();

            if (!$existingIssue) {
                $daysSinceUpdate = Carbon::parse($doc->file_modified_at)->diffInDays(Carbon::now());

                DocIssue::create([
                    'project' => $doc->project,
                    'issue_type' => DocIssue::TYPE_OUTDATED,
                    // Pure age-based staleness is a maintenance reminder, never a critical problem.
                    // HIGH is reserved for real content faults (broken links, price inconsistencies).
                    'severity' => $daysSinceUpdate > 180 ? DocIssue::SEVERITY_MEDIUM : DocIssue::SEVERITY_LOW,
                    'title' => "Document not updated in {$daysSinceUpdate} days",
                    'details' => [
                        'last_modified' => $doc->file_modified_at->format('Y-m-d'),
                        'days_since_update' => $daysSinceUpdate,
                    ],
                    'affected_files' => ["{$doc->project}:{$doc->file_path}"],
                    'suggested_action' => "Review if this document is still accurate and up-to-date.",
                ]);

                $issuesFound++;
            }
        }

        return $issuesFound;
    }

    /**
     * Detect broken links (references to non-existent files)
     */
    public function detectBrokenLinks(?string $project = null): int
    {
        $documents = DocEmbedding::when($project, function ($q) use ($project) {
            return $q->where('project', strtolower($project));
        })->get();

        $issuesFound = 0;

        foreach ($documents as $doc) {
            // Strip fenced code blocks first (```mermaid ... ```, ``` ... ```).
            // Mermaid diagram labels use [ ] and <br/> which the link regexes
            // otherwise misread as broken markdown/wiki links.
            $content = preg_replace('/```.*?```/s', '', $doc->content);

            // Find markdown links in content
            preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $content, $matches);

            // Wiki-style links [[...]] — skip in .claude/ docs (handover/context),
            // where they are memory-store cross-references by convention, not doc links.
            $isClaudeDoc = str_contains(str_replace('\\', '/', $doc->file_path), '.claude/');
            $wikiMatches = [1 => []];
            if (!$isClaudeDoc) {
                preg_match_all('/\[\[([^\]]+)\]\]/', $content, $wikiMatches);
            }

            $links = array_merge($matches[2] ?? [], $wikiMatches[1] ?? []);

            foreach ($links as $link) {
                // Skip external URLs and non-file schemes (mailto:, tel:, etc.)
                if (preg_match('/^https?:\/\//', $link)) continue;
                if (preg_match('/^(mailto|tel|sms|ftp|data):/i', $link)) continue;
                if (str_starts_with($link, '#')) continue; // Anchor links

                // Strip a URL fragment (./README.md#section) and query — only the file part
                // determines whether the target exists. Without this, "README.md#anchor"
                // is treated as a (non-existent) filename and reported as a false positive.
                $link = preg_replace('/[#?].*$/', '', $link);
                if ($link === '') continue; // was a pure fragment/query

                // Check if file exists
                // Handle both absolute paths (D:/GitHub/...) and relative paths
                $isAbsolute = preg_match('/^[A-Za-z]:/', $doc->file_path) || str_starts_with($doc->file_path, '/');

                if ($isAbsolute) {
                    $docDir = dirname($doc->file_path);
                    $basePath = $this->indexer->getProjectPath($doc->project);
                } else {
                    $basePath = $this->indexer->getProjectPath($doc->project);
                    $docDir = dirname($basePath . '/' . $doc->file_path);
                }

                // Try relative path from document directory
                $targetPath = realpath($docDir . '/' . $link);
                if (!$targetPath) {
                    // Try from project root
                    $targetPath = realpath($basePath . '/' . $link);
                }
                if (!$targetPath && !str_ends_with($link, '.md')) {
                    // Try adding .md extension
                    $targetPath = realpath($docDir . '/' . $link . '.md');
                    if (!$targetPath) {
                        $targetPath = realpath($basePath . '/' . $link . '.md');
                    }
                }

                if (!$targetPath) {
                    // Check if issue already exists
                    $existingIssue = DocIssue::where('issue_type', DocIssue::TYPE_BROKEN_LINK)
                        ->where('status', DocIssue::STATUS_OPEN)
                        ->whereJsonContains('affected_files', "{$doc->project}:{$doc->file_path}")
                        ->where('title', 'LIKE', "%{$link}%")
                        ->first();

                    if (!$existingIssue) {
                        DocIssue::create([
                            'project' => $doc->project,
                            'issue_type' => DocIssue::TYPE_BROKEN_LINK,
                            'severity' => DocIssue::SEVERITY_MEDIUM,
                            'title' => "Broken link to: {$link}",
                            'details' => [
                                'source_file' => $doc->file_path,
                                'broken_link' => $link,
                            ],
                            'affected_files' => ["{$doc->project}:{$doc->file_path}"],
                            'suggested_action' => "Fix or remove the broken link.",
                        ]);

                        $issuesFound++;
                    }
                }
            }
        }

        return $issuesFound;
    }

    /**
     * Detect potential inconsistencies: same context label with different price values.
     *
     * Approach: extract "label + price" pairs (e.g. "toeslag €0,50", "staffel €20").
     * Group by normalized label per project. Only flag when the SAME label has
     * DIFFERENT prices — that's a real inconsistency.
     */
    public function detectInconsistencies(?string $project = null): int
    {
        $documents = DocEmbedding::when($project, function ($q) use ($project) {
            return $q->where('project', strtolower($project));
        })->get();

        $issuesFound = 0;

        // Extract contextual price mentions: last 1-3 meaningful words before € sign
        $priceContexts = [];
        foreach ($documents as $doc) {
            // Match: 1-3 words directly before €amount
            preg_match_all('/((?:\b[a-zA-Z\x{00C0}-\x{024F}]{2,}\s+){1,3})\€\s*(\d+[,.]?\d*)/u', $doc->content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $label = $this->normalizePriceLabel($match[1]);
                if (!$label) continue;

                $price = str_replace(',', '.', $match[2]);

                $priceContexts[$doc->project][$label][$price][] = $doc->file_path;
            }
        }

        // Flag only when same label has different prices within a project
        foreach ($priceContexts as $proj => $labels) {
            foreach ($labels as $label => $prices) {
                if (count($prices) <= 1) continue;

                $existingIssue = DocIssue::where('issue_type', DocIssue::TYPE_INCONSISTENT)
                    ->where('project', $proj)
                    ->where('status', DocIssue::STATUS_OPEN)
                    ->where('title', 'LIKE', "%{$label}%")
                    ->first();

                if (!$existingIssue) {
                    $affectedFiles = [];
                    $priceDetails = [];
                    foreach ($prices as $price => $files) {
                        foreach ($files as $file) {
                            $affectedFiles[] = "{$proj}:{$file}";
                        }
                        $priceDetails["€{$price}"] = $files;
                    }

                    DocIssue::create([
                        'project' => $proj,
                        'issue_type' => DocIssue::TYPE_INCONSISTENT,
                        'severity' => DocIssue::SEVERITY_HIGH,
                        'title' => "Inconsistent price for '{$label}'",
                        'details' => [
                            'label' => $label,
                            'prices_found' => array_keys($prices),
                            'files_per_price' => $priceDetails,
                        ],
                        'affected_files' => array_unique($affectedFiles),
                        'suggested_action' => "Review: '{$label}' has different prices in these files.",
                    ]);

                    $issuesFound++;
                }
            }
        }

        return $issuesFound;
    }

    /**
     * Normalize a price label: strip whitespace, lowercase, keep only meaningful words.
     * Returns null if the label is too generic to be useful.
     */
    protected function normalizePriceLabel(string $raw): ?string
    {
        $label = mb_strtolower(trim($raw));
        // Remove common filler/generic words
        $fillerWords = ['van', 'per', 'voor', 'bij', 'het', 'de', 'een', 'is', 'en', 'of', 'met',
            'tot', 'aan', 'naar', 'dan', 'als', 'nog', 'ook', 'maar', 'wel', 'niet', 'meer',
            'start', 'vanaf', 'mogelijk', 'maximaal', 'minimaal', 'ongeveer', 'circa', 'max', 'min'];
        $label = preg_replace('/\b(' . implode('|', $fillerWords) . ')\b/', '', $label);
        // Strip leading/trailing punctuation and whitespace
        $label = preg_replace('/^[\s\-\:\|\*\#\>\(\)\'\"\.\/\,]+|[\s\-\:\|\*\#\>\(\)\'\"\.\/\,]+$/', '', $label);
        $label = preg_replace('/\s+/', ' ', trim($label));

        // Must contain at least one alphabetic word of 3+ characters
        if (!preg_match('/[a-zA-Z\x{00C0}-\x{024F}]{3,}/u', $label)) return null;

        // Too short after cleanup = not meaningful
        if (mb_strlen($label) < 3) return null;

        return $label;
    }

    /**
     * Get open issues for a project
     */
    public function getOpenIssues(?string $project = null): \Illuminate\Database\Eloquent\Collection
    {
        return DocIssue::open()
            ->when($project, function ($q) use ($project) {
                return $q->where('project', strtolower($project));
            })
            ->orderByRaw("CASE severity WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get issue summary per project
     */
    public function getIssueSummary(): array
    {
        $issues = DocIssue::open()->get();

        $summary = [];
        foreach ($issues as $issue) {
            $project = $issue->project ?? 'cross-project';
            if (!isset($summary[$project])) {
                $summary[$project] = [
                    'total' => 0,
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0,
                    'by_type' => [],
                ];
            }

            $summary[$project]['total']++;
            $summary[$project][$issue->severity]++;
            $summary[$project]['by_type'][$issue->issue_type] =
                ($summary[$project]['by_type'][$issue->issue_type] ?? 0) + 1;
        }

        return $summary;
    }

}
