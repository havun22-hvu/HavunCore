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
    protected float $duplicateThreshold = 0.85;  // Similarity above this = duplicate
    protected int $outdatedDays = 90;             // Days without update = outdated

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
     * Detect duplicate content across documents
     */
    public function detectDuplicates(?string $project = null): int
    {
        $documents = DocEmbedding::when($project, function ($q) use ($project) {
            return $q->where('project', strtolower($project));
        })->get();

        $issuesFound = 0;
        $checked = [];

        foreach ($documents as $doc1) {
            foreach ($documents as $doc2) {
                // Skip same document or already checked pair
                if ($doc1->id >= $doc2->id) continue;

                $pairKey = "{$doc1->id}-{$doc2->id}";
                if (isset($checked[$pairKey])) continue;
                $checked[$pairKey] = true;

                // Calculate similarity
                $similarity = $this->calculateSimilarity($doc1->embedding ?? [], $doc2->embedding ?? []);

                if ($similarity >= $this->duplicateThreshold) {
                    // Check if issue already exists
                    $existingIssue = DocIssue::where('issue_type', DocIssue::TYPE_DUPLICATE)
                        ->where('status', DocIssue::STATUS_OPEN)
                        ->whereJsonContains('affected_files', $doc1->file_path)
                        ->whereJsonContains('affected_files', $doc2->file_path)
                        ->first();

                    if (!$existingIssue) {
                        DocIssue::create([
                            'project' => $doc1->project === $doc2->project ? $doc1->project : null,
                            'issue_type' => DocIssue::TYPE_DUPLICATE,
                            'severity' => DocIssue::SEVERITY_MEDIUM,
                            'title' => "Duplicate content detected",
                            'details' => [
                                'similarity' => round($similarity * 100, 1) . '%',
                                'file1' => ['project' => $doc1->project, 'path' => $doc1->file_path],
                                'file2' => ['project' => $doc2->project, 'path' => $doc2->file_path],
                            ],
                            'affected_files' => [
                                "{$doc1->project}:{$doc1->file_path}",
                                "{$doc2->project}:{$doc2->file_path}",
                            ],
                            'suggested_action' => "Review both files and consolidate into one location.",
                        ]);

                        // Also create a relation
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

        return $issuesFound;
    }

    /**
     * Detect outdated documents
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
                    'severity' => $daysSinceUpdate > 180 ? DocIssue::SEVERITY_HIGH : DocIssue::SEVERITY_LOW,
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
            // Find markdown links in content
            preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $doc->content, $matches);
            preg_match_all('/\[\[([^\]]+)\]\]/', $doc->content, $wikiMatches); // Wiki-style links

            $links = array_merge($matches[2] ?? [], $wikiMatches[1] ?? []);

            foreach ($links as $link) {
                // Skip external URLs
                if (preg_match('/^https?:\/\//', $link)) continue;
                if (str_starts_with($link, '#')) continue; // Anchor links

                // Check if file exists
                $basePath = $this->getProjectPath($doc->project);
                $docDir = dirname($basePath . '/' . $doc->file_path);

                // Try relative path
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
     * Detect potential inconsistencies (same topic, different values)
     * This is a simplified heuristic-based detection
     */
    public function detectInconsistencies(?string $project = null): int
    {
        // Look for common patterns that might have inconsistent values
        $patterns = [
            'price' => '/\€\s*(\d+[,.]?\d*)/i',
            'version' => '/v?(\d+\.\d+(\.\d+)?)/i',
            'url' => '/(https?:\/\/[^\s\)]+)/i',
        ];

        $documents = DocEmbedding::when($project, function ($q) use ($project) {
            return $q->where('project', strtolower($project));
        })->get();

        $issuesFound = 0;
        $foundValues = [];

        // Collect all values per pattern per topic
        foreach ($documents as $doc) {
            foreach ($patterns as $patternName => $regex) {
                preg_match_all($regex, $doc->content, $matches);
                foreach ($matches[1] as $value) {
                    $key = "{$patternName}:{$value}";
                    $foundValues[$patternName][$value][] = [
                        'project' => $doc->project,
                        'file' => $doc->file_path,
                    ];
                }
            }
        }

        // For prices specifically, look for different prices in same project
        if (isset($foundValues['price'])) {
            $pricesByProject = [];
            foreach ($foundValues['price'] as $price => $locations) {
                foreach ($locations as $loc) {
                    $pricesByProject[$loc['project']][$price][] = $loc['file'];
                }
            }

            foreach ($pricesByProject as $proj => $prices) {
                if (count($prices) > 1) {
                    // Multiple different prices in same project - potential issue
                    $existingIssue = DocIssue::where('issue_type', DocIssue::TYPE_INCONSISTENT)
                        ->where('project', $proj)
                        ->where('status', DocIssue::STATUS_OPEN)
                        ->where('title', 'LIKE', '%price%')
                        ->first();

                    if (!$existingIssue) {
                        $affectedFiles = [];
                        foreach ($prices as $price => $files) {
                            foreach ($files as $file) {
                                $affectedFiles[] = "{$proj}:{$file}";
                            }
                        }

                        DocIssue::create([
                            'project' => $proj,
                            'issue_type' => DocIssue::TYPE_INCONSISTENT,
                            'severity' => DocIssue::SEVERITY_HIGH,
                            'title' => "Inconsistent prices found",
                            'details' => [
                                'prices_found' => array_keys($prices),
                                'files_per_price' => $prices,
                            ],
                            'affected_files' => array_unique($affectedFiles),
                            'suggested_action' => "Review and unify the prices across all documents.",
                        ]);

                        $issuesFound++;
                    }
                }
            }
        }

        return $issuesFound;
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

    /**
     * Calculate similarity between embeddings
     */
    protected function calculateSimilarity(array $embedding1, array $embedding2): float
    {
        if (empty($embedding1) || empty($embedding2)) {
            return 0.0;
        }

        $allKeys = array_unique(array_merge(array_keys($embedding1), array_keys($embedding2)));

        $dotProduct = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;

        foreach ($allKeys as $key) {
            $val1 = $embedding1[$key] ?? 0;
            $val2 = $embedding2[$key] ?? 0;

            $dotProduct += $val1 * $val2;
            $norm1 += $val1 * $val1;
            $norm2 += $val2 * $val2;
        }

        if ($norm1 == 0 || $norm2 == 0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($norm1) * sqrt($norm2));
    }

    /**
     * Get project base path
     */
    protected function getProjectPath(string $project): string
    {
        $paths = [
            'havuncore' => 'D:/GitHub/HavunCore',
            'havunadmin' => 'D:/GitHub/HavunAdmin',
            'herdenkingsportaal' => 'D:/GitHub/Herdenkingsportaal',
            'judotoernooi' => 'D:/GitHub/Judotoernooi',
            'infosyst' => 'D:/GitHub/infosyst',
            'studieplanner' => 'D:/GitHub/Studieplanner',
            'safehavun' => 'D:/GitHub/SafeHavun',
            'havun' => 'D:/GitHub/Havun',
            'vpdupdate' => 'D:/GitHub/VPDUpdate',
        ];

        return $paths[strtolower($project)] ?? "D:/GitHub/{$project}";
    }
}
