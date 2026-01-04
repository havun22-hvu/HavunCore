<?php

namespace App\Services\DocIntelligence;

use App\Models\DocIntelligence\DocEmbedding;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DocIndexer
{
    protected array $projectPaths = [
        'havuncore' => 'D:/GitHub/HavunCore',
        'havunadmin' => 'D:/GitHub/HavunAdmin',
        'herdenkingsportaal' => 'D:/GitHub/Herdenkingsportaal',
        'judotoernooi' => 'D:/GitHub/JudoToernooi',
        'infosyst' => 'D:/GitHub/Infosyst',
        'studieplanner' => 'D:/GitHub/Studieplanner',
        'studieplanner-api' => 'D:/GitHub/Studieplanner-api',
        'safehavun' => 'D:/GitHub/SafeHavun',
        'havun' => 'D:/GitHub/Havun',
        'vpdupdate' => 'D:/GitHub/VPDUpdate',
        'idsee' => 'D:/GitHub/IDSee',
        'havunvet' => 'D:/GitHub/HavunVet',
        'havuncore-webapp' => 'D:/GitHub/havuncore-webapp',
    ];

    protected array $excludePaths = [
        'vendor',
        'node_modules',
        '.git',
        'storage',
        'bootstrap/cache',
    ];

    protected ?string $claudeApiKey = null;

    public function __construct()
    {
        // Optional: For future Claude API embedding support
        $this->claudeApiKey = config('services.claude.api_key') ?? env('CLAUDE_API_KEY');
    }

    /**
     * Index all MD files in a specific project
     */
    public function indexProject(string $project, bool $forceReindex = false): array
    {
        $project = strtolower($project);
        $basePath = $this->projectPaths[$project] ?? null;

        if (!$basePath || !is_dir($basePath)) {
            return ['error' => "Project path not found: {$project}"];
        }

        $results = [
            'project' => $project,
            'indexed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $mdFiles = $this->findMdFiles($basePath);

        foreach ($mdFiles as $filePath) {
            $relativePath = str_replace($basePath . '/', '', $filePath);
            $relativePath = str_replace('\\', '/', $relativePath);

            try {
                $indexed = $this->indexFile($project, $relativePath, $filePath, $forceReindex);
                if ($indexed) {
                    $results['indexed']++;
                } else {
                    $results['skipped']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = "{$relativePath}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Index all projects
     */
    public function indexAll(bool $forceReindex = false): array
    {
        $results = [];
        foreach (array_keys($this->projectPaths) as $project) {
            $results[$project] = $this->indexProject($project, $forceReindex);
        }
        return $results;
    }

    /**
     * Find all MD files in a directory, excluding vendor/node_modules
     */
    protected function findMdFiles(string $basePath): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
                $path = $file->getPathname();
                $relativePath = str_replace($basePath, '', $path);

                // Check if path contains excluded directories
                $excluded = false;
                foreach ($this->excludePaths as $excludePath) {
                    if (str_contains($relativePath, "/{$excludePath}/") ||
                        str_contains($relativePath, "\\{$excludePath}\\")) {
                        $excluded = true;
                        break;
                    }
                }

                if (!$excluded) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    /**
     * Index a single file
     */
    protected function indexFile(string $project, string $relativePath, string $fullPath, bool $force = false): bool
    {
        if (!file_exists($fullPath)) {
            return false;
        }

        $content = file_get_contents($fullPath);
        $contentHash = hash('sha256', $content);
        $fileModified = filemtime($fullPath);

        // Check if already indexed and unchanged
        $existing = DocEmbedding::where('project', $project)
            ->where('file_path', $relativePath)
            ->first();

        if ($existing && !$force && $existing->content_hash === $contentHash) {
            return false; // No changes, skip
        }

        // Generate embedding
        $embedding = $this->generateEmbedding($content);
        $tokenCount = $this->estimateTokenCount($content);

        // Create or update record
        DocEmbedding::updateOrCreate(
            ['project' => $project, 'file_path' => $relativePath],
            [
                'content' => $content,
                'content_hash' => $contentHash,
                'embedding' => $embedding,
                'token_count' => $tokenCount,
                'file_modified_at' => date('Y-m-d H:i:s', $fileModified),
            ]
        );

        return true;
    }

    /**
     * Generate embedding using Claude API
     *
     * Note: Claude doesn't have a native embedding endpoint, so we use a workaround
     * by asking Claude to create a semantic summary and then using that for comparison.
     * For production, consider using OpenAI's embedding API or a dedicated embedding service.
     */
    protected function generateEmbedding(string $content): ?array
    {
        // For now, we'll use a simple TF-IDF-like approach locally
        // This avoids API costs and works offline
        return $this->generateLocalEmbedding($content);
    }

    /**
     * Generate a simple local embedding using word frequencies
     * This is a simplified approach - not as good as neural embeddings but works offline
     */
    protected function generateLocalEmbedding(string $content): array
    {
        // Normalize text
        $text = strtolower($content);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Count word frequencies
        $wordCounts = array_count_values($words);

        // Remove very common words (simple stopwords)
        $stopwords = ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been',
                      'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will',
                      'would', 'could', 'should', 'may', 'might', 'must', 'shall',
                      'can', 'need', 'dare', 'ought', 'used', 'to', 'of', 'in',
                      'for', 'on', 'with', 'at', 'by', 'from', 'as', 'into',
                      'through', 'during', 'before', 'after', 'above', 'below',
                      'between', 'under', 'again', 'further', 'then', 'once',
                      'here', 'there', 'when', 'where', 'why', 'how', 'all',
                      'each', 'few', 'more', 'most', 'other', 'some', 'such',
                      'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than',
                      'too', 'very', 'just', 'and', 'but', 'if', 'or', 'because',
                      'until', 'while', 'this', 'that', 'these', 'those', 'it'];

        foreach ($stopwords as $word) {
            unset($wordCounts[$word]);
        }

        // Get top 100 words as feature vector
        arsort($wordCounts);
        $topWords = array_slice($wordCounts, 0, 100, true);

        // Normalize to create embedding
        $total = array_sum($topWords) ?: 1;
        $embedding = [];
        foreach ($topWords as $word => $count) {
            $embedding[$word] = $count / $total;
        }

        return $embedding;
    }

    /**
     * Estimate token count (rough approximation)
     */
    protected function estimateTokenCount(string $content): int
    {
        // Rough estimation: ~4 characters per token for English
        return (int) ceil(strlen($content) / 4);
    }

    /**
     * Search documents by query
     */
    public function search(string $query, ?string $project = null, int $limit = 5): array
    {
        $queryEmbedding = $this->generateLocalEmbedding($query);

        $documents = DocEmbedding::when($project, function ($q) use ($project) {
            return $q->where('project', strtolower($project));
        })->get();

        $results = [];
        foreach ($documents as $doc) {
            $similarity = $this->calculateSimilarity($queryEmbedding, $doc->embedding ?? []);
            $results[] = [
                'project' => $doc->project,
                'file_path' => $doc->file_path,
                'similarity' => $similarity,
                'snippet' => substr($doc->content, 0, 200) . '...',
                'file_modified_at' => $doc->file_modified_at?->format('Y-m-d H:i'),
                'indexed_at' => $doc->updated_at?->format('Y-m-d H:i'),
            ];
        }

        // Sort by similarity descending
        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Calculate similarity between two embeddings
     */
    protected function calculateSimilarity(array $embedding1, array $embedding2): float
    {
        if (empty($embedding1) || empty($embedding2)) {
            return 0.0;
        }

        // Get all unique keys
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
     * Remove documents for files that no longer exist
     */
    public function cleanupOrphaned(string $project): int
    {
        $project = strtolower($project);
        $basePath = $this->projectPaths[$project] ?? null;

        if (!$basePath) {
            return 0;
        }

        $removed = 0;
        $documents = DocEmbedding::where('project', $project)->get();

        foreach ($documents as $doc) {
            $fullPath = $basePath . '/' . $doc->file_path;
            if (!file_exists($fullPath)) {
                $doc->delete();
                $removed++;
            }
        }

        return $removed;
    }
}
