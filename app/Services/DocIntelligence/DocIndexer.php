<?php

namespace App\Services\DocIntelligence;

use App\Models\DocIntelligence\DocEmbedding;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DocIndexer
{
    protected array $projectPaths = [];



    protected array $excludePaths = [
        'vendor',
        'node_modules',
        '.git',
        'storage',
        'bootstrap/cache',
        'public/build',
        'public/hot',
        'dist',
        'build',
        'playwright-report',
        'test-results',
        'coverage',
        '.idea',
        '.vscode',
        'offline',
        'staging',
        '.claude/worktrees',
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
    /** Marks a row that was embedded with the local word-frequency fallback. */
    public const FALLBACK_MODEL = 'tfidf-fallback';

    /** Successively smaller inputs to try when Ollama rejects the text as too long. */
    private const EMBEDDING_CHAR_LIMITS = [8000, 4000, 2000];

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

        // Use server paths on Linux, local paths on Windows.
        // Source of truth is config/havun-projects.php — er was hier jarenlang een tweede,
        // hardcoded lijst en dan vergeet je er altijd één: JudoScoreBoard (prioriteit 1),
        // Aeterna en LastMatch stonden tot 15-07-2026 niet in de index, waardoor 190 docs
        // onvindbaar waren terwijl CLAUDE.md voorschrijft elke taak met docs:search te beginnen.
        $sleutel = PHP_OS_FAMILY === 'Windows' ? 'path' : 'server_path';

        $this->projectPaths = collect(config('havun-projects', []))
            ->map(fn (array $project) => $project[$sleutel] ?? null)
            ->filter()   // projecten zonder pad voor deze omgeving (native apps) slaan we over
            ->all();
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
     * Base paths of other configured projects that are nested *inside* the given
     * project's path. Files under these belong to the nested project and must not
     * be indexed again under the parent (e.g. havuncore-webapp lives inside havuncore).
     *
     * @return string[] normalized (forward-slash) base paths, each ending without trailing slash
     */
    protected function nestedProjectPaths(string $basePath): array
    {
        $normalizedBase = rtrim(str_replace('\\', '/', $basePath), '/');
        $nested = [];

        foreach ($this->projectPaths as $otherPath) {
            $normalizedOther = rtrim(str_replace('\\', '/', $otherPath), '/');
            // Strictly nested below the base (not the base itself)
            if ($normalizedOther !== $normalizedBase
                && str_starts_with($normalizedOther . '/', $normalizedBase . '/')) {
                $nested[] = $normalizedOther;
            }
        }

        return $nested;
    }

    /**
     * Whether an absolute file path falls under one of the nested project paths.
     */
    protected function isUnderNestedProject(string $absolutePath, array $nestedPaths): bool
    {
        $normalized = str_replace('\\', '/', $absolutePath);
        foreach ($nestedPaths as $nestedPath) {
            if (str_starts_with($normalized, $nestedPath . '/')) {
                return true;
            }
        }
        return false;
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
        $nestedPaths = $this->nestedProjectPaths($basePath);

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

                // Skip files that belong to a nested configured project (indexed separately)
                if ($this->isUnderNestedProject($path, $nestedPaths)) {
                    continue;
                }

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

        if ($existing && !$force && $existing->content_hash === $contentHash
            && !$this->needsEmbeddingUpgrade($existing)) {
            return false;
        }

        // Extract a structured summary instead of embedding raw code
        $summary = $this->extractCodeSummary($relativePath, $rawContent);

        // Generate embedding from the summary
        $ollamaEmbedding = $this->generateOllamaEmbedding($summary);
        $embedding = $ollamaEmbedding ?? $this->generateLocalEmbedding($summary);
        $tokenCount = $this->estimateTokenCount($summary);

        DocEmbedding::updateOrCreate(
            ['project' => $project, 'file_path' => $relativePath],
            [
                'content' => $summary,
                'content_hash' => $contentHash,
                'embedding' => $embedding,
                'embedding_model' => $this->embeddingModelFor($ollamaEmbedding),
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
        $nestedPaths = $this->nestedProjectPaths($basePath);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
                $path = $file->getPathname();
                $relativePath = str_replace($basePath, '', $path);

                // Skip files that belong to a nested configured project (indexed separately)
                if ($this->isUnderNestedProject($path, $nestedPaths)) {
                    continue;
                }

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

        if ($existing && !$force && $existing->content_hash === $contentHash
            && !$this->needsEmbeddingUpgrade($existing)) {
            return false; // No changes and the embedding is real, skip
        }

        // Generate embedding
        $ollamaEmbedding = $this->generateOllamaEmbedding($content);
        $embedding = $ollamaEmbedding ?? $this->generateLocalEmbedding($content);
        $tokenCount = $this->estimateTokenCount($content);

        // Create or update record
        DocEmbedding::updateOrCreate(
            ['project' => $project, 'file_path' => $relativePath],
            [
                'content' => $content,
                'content_hash' => $contentHash,
                'embedding' => $embedding,
                'embedding_model' => $this->embeddingModelFor($ollamaEmbedding),
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
        return $this->generateOllamaEmbedding($content) ?? $this->generateLocalEmbedding($content);
    }

    /**
     * Ask Ollama for a real semantic vector. Returns null when unavailable, so the
     * caller can tell a genuine embedding from the word-frequency fallback — the two
     * are not interchangeable and must never be labelled the same.
     */
    protected function generateOllamaEmbedding(string $content): ?array
    {
        // Ollama serves nomic-embed-text with a ~2048 token context, not the 8192 the
        // model itself supports, and it rejects (500) anything longer instead of
        // truncating. Tokens-per-character varies with the content — dense code hits the
        // ceiling far sooner than prose — so rather than guess one safe cut-off, start
        // generous and halve on rejection. A real embedding of the first half beats the
        // word-frequency fallback of the whole.
        foreach (self::EMBEDDING_CHAR_LIMITS as $limiet) {
            $truncated = mb_substr($content, 0, $limiet);

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

                // Too long is the one failure worth retrying smaller; anything else
                // (model missing, Ollama down) will fail identically at every size.
                if (!$this->isContextLengthError($response->body())) {
                    Log::warning('Ollama embedding failed, falling back to TF: HTTP ' . $response->status());

                    return null;
                }
            } catch (\Exception $e) {
                Log::warning('Ollama embedding failed, falling back to TF: ' . $e->getMessage());

                return null;
            }
        }

        Log::warning('Ollama embedding failed, falling back to TF: still too long at ' . end(self::EMBEDDING_CHAR_LIMITS) . ' chars');

        return null;
    }

    protected function isContextLengthError(string $body): bool
    {
        return str_contains($body, 'exceeds the context length');
    }

    /**
     * Was the last stored embedding a real Ollama vector, or the local fallback?
     * Anything indexed while Ollama was down keeps working, but is marked so it can
     * be found and re-indexed later — see needsEmbeddingUpgrade().
     */
    protected function embeddingModelFor(?array $ollamaEmbedding): string
    {
        return $ollamaEmbedding ? $this->embeddingModel : self::FALLBACK_MODEL;
    }

    /**
     * A row indexed with the TF fallback is degraded: it only matches on literal
     * words. Once Ollama is reachable again it must be re-embedded, even though the
     * file itself never changed — otherwise it stays degraded forever.
     *
     * The label alone is not enough to go on: until 15-07-2026 every fallback was
     * mislabelled as a real model, so rows are judged on their shape as well.
     */
    protected function needsEmbeddingUpgrade(?DocEmbedding $existing): bool
    {
        if ($existing === null) {
            return false;
        }

        return $existing->embedding_model === self::FALLBACK_MODEL
            || self::isFallbackEmbedding($existing->embedding);
    }

    /**
     * A real Ollama embedding is a flat list of floats. The local fallback is a
     * map of word => weight, so string keys give it away regardless of its label.
     */
    public static function isFallbackEmbedding(mixed $embedding): bool
    {
        return is_array($embedding) && $embedding !== [] && !array_is_list($embedding);
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
