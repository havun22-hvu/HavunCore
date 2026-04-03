<?php

namespace App\Services\DocIntelligence;

use App\Models\DocIntelligence\DocEmbedding;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DocIndexer
{
    protected array $projectPaths = [];

    protected array $localPaths = [
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
        'havuncore-webapp' => 'D:/GitHub/HavunCore/webapp',
    ];

    protected array $serverPaths = [
        'havuncore' => '/var/www/havuncore/production',
        'havunadmin' => '/var/www/havunadmin/production',
        'herdenkingsportaal' => '/var/www/herdenkingsportaal/production',
        'judotoernooi' => '/var/www/judotoernooi/laravel',
        'infosyst' => '/var/www/infosyst/production',
        'studieplanner' => '/var/www/studieplanner/production',
        'studieplanner-api' => '/var/www/studieplanner/production',
        'safehavun' => '/var/www/safehavun/production',
        'havun' => '/var/www/havun.nl',
        'havunvet' => '/var/www/havunvet/staging',
    ];

    protected array $excludePaths = [
        'vendor',
        'node_modules',
        '.git',
        'storage',
        'bootstrap/cache',
        'public/build',
        'public/hot',
        '.idea',
        '.vscode',
        'offline',
        'staging',
    ];

    /**
     * Code file extensions to index (in addition to .md)
     */
    protected array $codeExtensions = [
        'php', 'js', 'ts', 'jsx', 'tsx', 'vue', 'blade.php',
    ];

    /**
     * Subdirectories to scan for code files (limits scope to relevant code)
     */
    protected array $codeDirectories = [
        'app/Models',
        'app/Http/Controllers',
        'app/Http/Middleware',
        'app/Services',
        'app/Contracts',
        'app/DTOs',
        'app/Enums',
        'app/Events',
        'app/Listeners',
        'app/Jobs',
        'app/Console/Commands',
        'app/Traits',
        'app/Exceptions',
        'config',
        'routes',
        'database/migrations',
        'src',
        'laravel/app/Models',
        'laravel/app/Http/Controllers',
        'laravel/app/Http/Middleware',
        'laravel/app/Services',
        'laravel/app/Contracts',
        'laravel/app/DTOs',
        'laravel/app/Enums',
        'laravel/app/Events',
        'laravel/app/Listeners',
        'laravel/app/Jobs',
        'laravel/app/Console/Commands',
        'laravel/app/Traits',
        'laravel/app/Exceptions',
        'laravel/config',
        'laravel/routes',
        'laravel/database/migrations',
    ];

    protected ?string $claudeApiKey = null;
    protected string $ollamaUrl = 'http://127.0.0.1:11434';
    protected string $embeddingModel = 'nomic-embed-text';

    public function __construct()
    {
        $this->claudeApiKey = config('services.claude.api_key') ?? env('CLAUDE_API_KEY');
        $this->ollamaUrl = env('OLLAMA_URL', 'http://127.0.0.1:11434');
        $this->embeddingModel = env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text');

        // WAL mode: lezen (webapp) en schrijven (indexer) kunnen tegelijk
        try {
            \DB::connection('doc_intelligence')->statement('PRAGMA journal_mode=WAL');
            \DB::connection('doc_intelligence')->statement('PRAGMA synchronous=NORMAL');
        } catch (\Exception $e) {
            // Niet kritiek — ga door
        }

        // Use server paths on Linux, local paths on Windows
        $this->projectPaths = PHP_OS_FAMILY === 'Windows'
            ? $this->localPaths
            : $this->serverPaths;
    }

    /**
     * Index all files (MD + code) in a specific project
     */
    public function indexProject(string $project, bool $forceReindex = false, bool $includeCode = true): array
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
            'indexed_md' => 0,
            'indexed_code' => 0,
        ];

        // Index MD files
        $mdFiles = $this->findMdFiles($basePath);
        foreach ($mdFiles as $filePath) {
            $relativePath = $this->toRelativePath($filePath, $basePath);
            try {
                $indexed = $this->indexFile($project, $relativePath, $filePath, $forceReindex);
                if ($indexed) {
                    $results['indexed']++;
                    $results['indexed_md']++;
                } else {
                    $results['skipped']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = "{$relativePath}: {$e->getMessage()}";
            }
        }

        // Index code files
        if ($includeCode) {
            $codeFiles = $this->findCodeFiles($basePath);
            foreach ($codeFiles as $filePath) {
                $relativePath = $this->toRelativePath($filePath, $basePath);
                try {
                    $indexed = $this->indexCodeFile($project, $relativePath, $filePath, $forceReindex);
                    if ($indexed) {
                        $results['indexed']++;
                        $results['indexed_code']++;
                    } else {
                        $results['skipped']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "{$relativePath}: {$e->getMessage()}";
                }
            }
        }

        return $results;
    }

    /**
     * Index all projects
     */
    public function indexAll(bool $forceReindex = false, bool $includeCode = true): array
    {
        $results = [];
        foreach (array_keys($this->projectPaths) as $project) {
            $results[$project] = $this->indexProject($project, $forceReindex, $includeCode);
        }
        return $results;
    }

    /**
     * Convert absolute path to relative path
     */
    protected function toRelativePath(string $filePath, string $basePath): string
    {
        $normalizedFile = str_replace('\\', '/', $filePath);
        $normalizedBase = str_replace('\\', '/', $basePath);
        return preg_replace('#^' . preg_quote($normalizedBase . '/', '#') . '#i', '', $normalizedFile);
    }

    /**
     * Find code files in relevant subdirectories
     */
    protected function findCodeFiles(string $basePath): array
    {
        $files = [];

        foreach ($this->codeDirectories as $subDir) {
            $dirPath = $basePath . '/' . $subDir;
            if (!is_dir($dirPath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());

                // Check excluded paths
                $excluded = false;
                foreach ($this->excludePaths as $excludePath) {
                    if (str_contains($path, "/{$excludePath}/")) {
                        $excluded = true;
                        break;
                    }
                }
                if ($excluded) {
                    continue;
                }

                // Check if extension matches (handle .blade.php specially)
                $filename = $file->getFilename();
                if (str_ends_with($filename, '.blade.php')) {
                    $files[] = $path;
                    continue;
                }

                $ext = strtolower($file->getExtension());
                if (in_array($ext, $this->codeExtensions) && $ext !== 'blade') {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    /**
     * Index a code file — extracts a structured summary before embedding
     */
    protected function indexCodeFile(string $project, string $relativePath, string $fullPath, bool $force = false): bool
    {
        if (!file_exists($fullPath)) {
            return false;
        }

        $rawContent = file_get_contents($fullPath);
        $contentHash = hash('sha256', $rawContent);
        $fileModified = filemtime($fullPath);

        // Check if already indexed and unchanged
        $existing = DocEmbedding::where('project', $project)
            ->where('file_path', $relativePath)
            ->first();

        if ($existing && !$force && $existing->content_hash === $contentHash) {
            return false;
        }

        // Extract a structured summary instead of embedding raw code
        $summary = $this->extractCodeSummary($relativePath, $rawContent);

        // Generate embedding from the summary
        $embedding = $this->generateEmbedding($summary);
        $tokenCount = $this->estimateTokenCount($summary);

        DocEmbedding::updateOrCreate(
            ['project' => $project, 'file_path' => $relativePath],
            [
                'content' => $summary,
                'content_hash' => $contentHash,
                'embedding' => $embedding,
                'embedding_model' => $embedding ? $this->embeddingModel : 'tfidf-fallback',
                'file_type' => $this->detectFileType($relativePath),
                'token_count' => $tokenCount,
                'file_modified_at' => date('Y-m-d H:i:s', $fileModified),
            ]
        );

        return true;
    }

    /**
     * Extract a structured summary from a code file for embedding.
     * Captures class name, methods, properties, use statements, routes — not raw code.
     */
    protected function extractCodeSummary(string $relativePath, string $content): string
    {
        $lines = explode("\n", $content);
        $summary = ["[FILE] {$relativePath}"];

        // Detect file type
        $isPhp = str_ends_with($relativePath, '.php');
        $isBlade = str_contains($relativePath, '.blade.php');
        $isJs = preg_match('/\.(js|ts|jsx|tsx|vue)$/', $relativePath);
        $isConfig = str_starts_with($relativePath, 'config/') || str_starts_with($relativePath, 'laravel/config/');
        $isRoute = str_starts_with($relativePath, 'routes/') || str_starts_with($relativePath, 'laravel/routes/');
        $isMigration = str_contains($relativePath, 'migrations/');

        if ($isBlade) {
            // Blade: extract components, sections, slots
            $summary[] = '[TYPE] Blade template';
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (preg_match('/@(extends|section|component|include|livewire|vite|push|slot)\s*\(/', $trimmed, $m)) {
                    $summary[] = $trimmed;
                }
                if (preg_match('/DO NOT REMOVE/', $trimmed)) {
                    $summary[] = $trimmed;
                }
            }
            return implode("\n", $summary);
        }

        if ($isRoute) {
            // Routes: extract all route definitions
            $summary[] = '[TYPE] Route definitions';
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (preg_match('/Route::(get|post|put|patch|delete|any|match|resource|apiResource)\s*\(/', $trimmed)) {
                    $summary[] = $trimmed;
                }
                if (preg_match('/->middleware\(/', $trimmed)) {
                    $summary[] = '  ' . $trimmed;
                }
                if (preg_match('/->name\(/', $trimmed)) {
                    $summary[] = '  ' . $trimmed;
                }
                // Route groups
                if (preg_match('/Route::(prefix|group|middleware)\s*\(/', $trimmed)) {
                    $summary[] = $trimmed;
                }
            }
            return implode("\n", $summary);
        }

        if ($isMigration) {
            // Migrations: extract table name, columns
            $summary[] = '[TYPE] Database migration';
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (preg_match('/Schema::(create|table)\s*\(\s*[\'"](\w+)[\'"]/', $trimmed, $m)) {
                    $summary[] = "[TABLE] {$m[2]} ({$m[1]})";
                }
                if (preg_match('/\$table->(\w+)\s*\(\s*[\'"](\w+)[\'"]/', $trimmed, $m)) {
                    $summary[] = "  {$m[1]} {$m[2]}";
                }
                if (preg_match('/(->nullable|->default|->unique|->index|->foreign)/', $trimmed, $m)) {
                    // Append modifier to previous line
                    $lastIdx = count($summary) - 1;
                    if ($lastIdx >= 0) {
                        $summary[$lastIdx] .= ' ' . $m[1];
                    }
                }
            }
            return implode("\n", $summary);
        }

        if ($isConfig) {
            // Config: extract keys and structure
            $summary[] = '[TYPE] Configuration';
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (preg_match("/^['\"](\w[\w.-]*)['\"]\\s*=>/", $trimmed, $m)) {
                    $summary[] = "  {$m[1]}";
                }
                // Comments often explain config
                if (preg_match('/^\/{2}\s*(.+)/', $trimmed, $m)) {
                    $summary[] = "  // {$m[1]}";
                }
            }
            return implode("\n", $summary);
        }

        if ($isPhp) {
            // PHP classes: namespace, class, methods, properties, use statements
            foreach ($lines as $line) {
                $trimmed = trim($line);

                // Namespace
                if (preg_match('/^namespace\s+(.+);/', $trimmed, $m)) {
                    $summary[] = "[NAMESPACE] {$m[1]}";
                }

                // Use statements (imports)
                if (preg_match('/^use\s+(.+);/', $trimmed, $m)) {
                    $summary[] = "[USE] {$m[1]}";
                }

                // Class/interface/trait/enum declaration
                if (preg_match('/^(abstract\s+|final\s+)?(class|interface|trait|enum)\s+(\w+)(.*)/', $trimmed, $m)) {
                    $summary[] = "[CLASS] {$m[2]} {$m[3]}{$m[4]}";
                }

                // Method signatures
                if (preg_match('/^\s*(public|protected|private)(\s+static)?\s+function\s+(\w+)\s*\(([^)]*)\)/', $trimmed, $m)) {
                    $visibility = $m[1];
                    $static = trim($m[2] ?? '');
                    $name = $m[3];
                    $params = $m[4];
                    $prefix = $static ? "{$visibility} static" : $visibility;
                    $summary[] = "[METHOD] {$prefix} {$name}({$params})";
                }

                // Properties
                if (preg_match('/^\s*(public|protected|private)(\s+static)?\s+(\??\w+\s+)?\$(\w+)/', $trimmed, $m)) {
                    $summary[] = "[PROPERTY] {$m[1]} \${$m[4]}";
                }

                // Constants
                if (preg_match('/^\s*(public|protected|private|)\s*const\s+(\w+)\s*=/', $trimmed, $m)) {
                    $summary[] = "[CONST] {$m[2]}";
                }

                // Laravel model casts, fillable, relations
                if (preg_match('/protected\s+\$fillable\s*=/', $trimmed)) {
                    $summary[] = '[FILLABLE] ' . $this->extractArrayValues($lines, $line);
                }
                if (preg_match('/protected\s+\$casts\s*=/', $trimmed)) {
                    $summary[] = '[CASTS] ' . $this->extractArrayValues($lines, $line);
                }

                // Eloquent relations
                if (preg_match('/return\s+\$this->(hasMany|hasOne|belongsTo|belongsToMany|morphMany|morphTo|morphOne)\s*\((.+?)\)/', $trimmed, $m)) {
                    $summary[] = "[RELATION] {$m[1]}({$m[2]})";
                }
            }
        }

        if ($isJs) {
            // JS/TS: exports, functions, classes, imports
            $summary[] = '[TYPE] JavaScript/TypeScript';
            foreach ($lines as $line) {
                $trimmed = trim($line);

                if (preg_match('/^import\s+/', $trimmed)) {
                    $summary[] = $trimmed;
                }
                if (preg_match('/^export\s+(default\s+)?(function|class|const|let|var|interface|type|enum)\s+(\w+)/', $trimmed, $m)) {
                    $summary[] = "[EXPORT] {$m[2]} {$m[3]}";
                }
                if (preg_match('/^(function|class)\s+(\w+)/', $trimmed, $m)) {
                    $summary[] = "[{$m[1]}] {$m[2]}";
                }
            }
        }

        // If summary is too short, include first part of raw content as fallback
        if (count($summary) <= 2) {
            $summary[] = mb_substr($content, 0, 2000);
        }

        return implode("\n", $summary);
    }

    /**
     * Extract inline array values from a PHP line (for $fillable, $casts, etc.)
     */
    protected function extractArrayValues(array $allLines, string $currentLine): string
    {
        // Try to find the array content on this and following lines
        $idx = array_search($currentLine, $allLines);
        if ($idx === false) {
            return '(...)';
        }

        $buffer = '';
        for ($i = $idx; $i < min($idx + 20, count($allLines)); $i++) {
            $buffer .= $allLines[$i];
            if (str_contains($allLines[$i], '];')) {
                break;
            }
        }

        // Extract quoted strings
        preg_match_all("/['\"]([^'\"]+)['\"]/", $buffer, $matches);
        return implode(', ', $matches[1] ?? ['(...)']);
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
                'embedding_model' => $embedding ? $this->embeddingModel : 'tfidf-fallback',
                'file_type' => $this->detectFileType($relativePath),
                'token_count' => $tokenCount,
                'file_modified_at' => date('Y-m-d H:i:s', $fileModified),
            ]
        );

        return true;
    }

    /**
     * Detect file type for categorization and filtering
     */
    protected function detectFileType(string $relativePath): string
    {
        // Structure files
        if (str_contains($relativePath, '_structure/')) {
            return 'structure';
        }

        // Documentation
        if (str_ends_with($relativePath, '.md')) {
            return 'docs';
        }

        // Models
        if (preg_match('#(^|/)app/Models/#i', $relativePath) || preg_match('#laravel/app/Models/#i', $relativePath)) {
            return 'model';
        }

        // Controllers
        if (preg_match('#(^|/)app/Http/Controllers/#i', $relativePath)) {
            return 'controller';
        }

        // Middleware
        if (preg_match('#(^|/)app/Http/Middleware/#i', $relativePath)) {
            return 'middleware';
        }

        // Services
        if (preg_match('#(^|/)app/Services/#i', $relativePath)) {
            return 'service';
        }

        // Commands
        if (preg_match('#(^|/)app/Console/Commands/#i', $relativePath)) {
            return 'command';
        }

        // Migrations
        if (preg_match('#(^|/)database/migrations/#i', $relativePath)) {
            return 'migration';
        }

        // Routes
        if (preg_match('#(^|/)routes/#i', $relativePath)) {
            return 'route';
        }

        // Config
        if (preg_match('#(^|/)config/#i', $relativePath)) {
            return 'config';
        }

        // Blade views
        if (str_contains($relativePath, '.blade.php')) {
            return 'view';
        }

        // Tests
        if (preg_match('#(^|/)tests/#i', $relativePath)) {
            return 'test';
        }

        // Enums, DTOs, Events, Jobs, etc.
        if (preg_match('#(^|/)app/(Enums|DTOs|Events|Jobs|Listeners|Traits|Exceptions|Contracts)/#i', $relativePath)) {
            return 'support';
        }

        return 'code';
    }

    /**
     * Public wrapper for generating embeddings (used by StructureIndexer)
     */
    public function generateEmbeddingPublic(string $content): ?array
    {
        return $this->generateEmbedding($content);
    }

    /**
     * Generate embedding using Ollama (nomic-embed-text).
     * Falls back to TF-IDF if Ollama is unavailable.
     */
    protected function generateEmbedding(string $content): ?array
    {
        // Truncate content to avoid token limits (nomic-embed-text: 8192 tokens)
        $truncated = mb_substr($content, 0, 8000);

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(30)->post(
                "{$this->ollamaUrl}/api/embeddings",
                [
                    'model' => $this->embeddingModel,
                    'prompt' => $truncated,
                ]
            );

            if ($response->successful()) {
                $embedding = $response->json('embedding');
                if (is_array($embedding) && count($embedding) > 0) {
                    return $embedding;
                }
            }
        } catch (\Exception $e) {
            Log::warning("Ollama embedding failed, falling back to TF-IDF: " . $e->getMessage());
        }

        // Fallback to TF-IDF if Ollama unavailable
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
    public function search(string $query, ?string $project = null, int $limit = 5, ?string $fileType = null): array
    {
        $queryEmbedding = $this->generateEmbedding($query);

        $documents = DocEmbedding::when($project, function ($q) use ($project) {
            return $q->where('project', strtolower($project));
        })->when($fileType, function ($q) use ($fileType) {
            return $q->where('file_type', $fileType);
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
     * Get the resolved project path for a given project
     */
    public function getProjectPath(string $project): ?string
    {
        return $this->projectPaths[strtolower($project)] ?? null;
    }

    /**
     * Get all configured project paths
     */
    public function getProjectPaths(): array
    {
        return $this->projectPaths;
    }

    /**
     * Calculate similarity between two embeddings
     */
    public function calculateSimilarity(array $embedding1, array $embedding2): float
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
