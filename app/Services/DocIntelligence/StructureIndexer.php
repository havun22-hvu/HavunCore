<?php

namespace App\Services\DocIntelligence;

use App\Models\DocIntelligence\DocEmbedding;
use Illuminate\Support\Facades\Log;

class StructureIndexer
{
    protected DocIndexer $docIndexer;
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
        'havuncore-webapp' => 'D:/GitHub/havuncore-webapp',
    ];

    protected array $serverPaths = [
        'havuncore' => '/var/www/development/HavunCore',
        'havunadmin' => '/var/www/havunadmin/production',
        'herdenkingsportaal' => '/var/www/herdenkingsportaal/production',
        'judotoernooi' => '/var/www/judotoernooi/laravel',
        'infosyst' => '/var/www/infosyst/production',
        'studieplanner' => '/var/www/studieplanner/production',
        'safehavun' => '/var/www/safehavun/production',
    ];

    public function __construct(DocIndexer $docIndexer)
    {
        $this->docIndexer = $docIndexer;
        $this->projectPaths = PHP_OS_FAMILY === 'Windows'
            ? $this->localPaths
            : $this->serverPaths;
    }

    public function indexAll(bool $force = false): array
    {
        $results = [];
        foreach (array_keys($this->projectPaths) as $project) {
            $results[$project] = $this->indexProject($project, $force);
        }
        return $results;
    }

    public function indexProject(string $project, bool $force = false): array
    {
        $project = strtolower($project);
        $basePath = $this->projectPaths[$project] ?? null;

        if (!$basePath || !is_dir($basePath)) {
            return ['error' => "Project path not found: {$project}"];
        }

        // Detect project type
        $isLaravel = $this->isLaravel($basePath);
        $isNode = $this->isNode($basePath);

        $structure = [
            'project' => $project,
            'path' => $basePath,
            'type' => $isLaravel ? 'Laravel' : ($isNode ? 'Node.js' : 'Other'),
            'models' => [],
            'controllers' => [],
            'services' => [],
            'middleware' => [],
            'enums' => [],
            'migrations' => 0,
            'routes' => [],
            'config_keys' => [],
            'commands' => [],
            'jobs' => [],
            'events' => [],
            'traits' => [],
        ];

        if ($isLaravel) {
            $structure = $this->analyzeLaravel($basePath, $structure);
        }

        if ($isNode) {
            $structure = $this->analyzeNode($basePath, $structure);
        }

        // Generate summary text
        $summary = $this->generateSummary($project, $structure);
        $summaryHash = hash('sha256', $summary);

        // Check if unchanged
        $filePath = "_structure/{$project}.structure";
        $existing = DocEmbedding::where('project', $project)
            ->where('file_path', $filePath)
            ->first();

        if ($existing && !$force && $existing->content_hash === $summaryHash) {
            return array_merge($this->getCounts($structure), [
                'summary_preview' => mb_substr($summary, 0, 500),
                'status' => 'unchanged',
            ]);
        }

        // Generate embedding and store
        $embedding = $this->docIndexer->generateEmbeddingPublic($summary);

        DocEmbedding::updateOrCreate(
            ['project' => $project, 'file_path' => $filePath],
            [
                'content' => $summary,
                'content_hash' => $summaryHash,
                'embedding' => $embedding,
                'embedding_model' => $embedding ? (env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text')) : 'tfidf-fallback',
                'file_type' => 'structure',
                'token_count' => (int) ceil(strlen($summary) / 4),
                'file_modified_at' => now(),
            ]
        );

        return array_merge($this->getCounts($structure), [
            'summary_preview' => mb_substr($summary, 0, 500),
            'status' => 'indexed',
        ]);
    }

    protected function isLaravel(string $basePath): bool
    {
        return file_exists($basePath . '/artisan') || file_exists($basePath . '/laravel/artisan');
    }

    protected function isNode(string $basePath): bool
    {
        return file_exists($basePath . '/package.json') && !$this->isLaravel($basePath);
    }

    protected function analyzeLaravel(string $basePath, array $structure): array
    {
        // Support standard and nested (laravel/) layout
        $appBase = is_dir($basePath . '/laravel/app') ? $basePath . '/laravel' : $basePath;

        // Models
        $structure['models'] = $this->scanPhpClasses($appBase . '/app/Models');

        // Controllers
        $structure['controllers'] = $this->scanPhpClasses($appBase . '/app/Http/Controllers');

        // Services
        $structure['services'] = $this->scanPhpClasses($appBase . '/app/Services');

        // Middleware
        $structure['middleware'] = $this->scanPhpClasses($appBase . '/app/Http/Middleware');

        // Enums
        $structure['enums'] = $this->scanPhpClasses($appBase . '/app/Enums');

        // Commands
        $structure['commands'] = $this->scanPhpClasses($appBase . '/app/Console/Commands');

        // Jobs
        $structure['jobs'] = $this->scanPhpClasses($appBase . '/app/Jobs');

        // Events
        $structure['events'] = $this->scanPhpClasses($appBase . '/app/Events');

        // Traits
        $structure['traits'] = $this->scanPhpClasses($appBase . '/app/Traits');

        // Migrations count
        $migrationDir = $appBase . '/database/migrations';
        if (is_dir($migrationDir)) {
            $structure['migrations'] = count(glob($migrationDir . '/*.php'));
        }

        // Routes
        $structure['routes'] = $this->extractRoutes($appBase . '/routes');

        // Config keys
        $structure['config_keys'] = $this->extractConfigKeys($appBase . '/config');

        // Composer info
        $composerFile = $appBase . '/composer.json';
        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
            $structure['php_version'] = $composer['require']['php'] ?? 'unknown';
            $structure['laravel_version'] = $composer['require']['laravel/framework'] ?? 'unknown';
            $structure['packages'] = array_keys($composer['require'] ?? []);
        }

        // Package.json info
        $packageFile = $appBase . '/package.json';
        if (file_exists($packageFile)) {
            $package = json_decode(file_get_contents($packageFile), true);
            $structure['npm_packages'] = array_keys(array_merge(
                $package['dependencies'] ?? [],
                $package['devDependencies'] ?? []
            ));
        }

        return $structure;
    }

    protected function analyzeNode(string $basePath, array $structure): array
    {
        $packageFile = $basePath . '/package.json';
        if (file_exists($packageFile)) {
            $package = json_decode(file_get_contents($packageFile), true);
            $structure['npm_packages'] = array_keys(array_merge(
                $package['dependencies'] ?? [],
                $package['devDependencies'] ?? []
            ));
        }

        // Scan src/ for components/services
        if (is_dir($basePath . '/src')) {
            $structure['services'] = $this->scanJsModules($basePath . '/src');
        }
        if (is_dir($basePath . '/frontend/src')) {
            $structure['services'] = array_merge(
                $structure['services'],
                $this->scanJsModules($basePath . '/frontend/src')
            );
        }

        return $structure;
    }

    /**
     * Scan a directory for PHP class files, return class info
     */
    protected function scanPhpClasses(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $classes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $info = ['file' => str_replace('\\', '/', $file->getPathname())];

            // Extract class name
            if (preg_match('/^(abstract\s+|final\s+)?(class|interface|trait|enum)\s+(\w+)/m', $content, $m)) {
                $info['name'] = $m[3];
                $info['type'] = $m[2];
            } else {
                continue; // Skip files without a class
            }

            // Extract extends/implements
            if (preg_match('/class\s+\w+\s+extends\s+(\w+)/', $content, $m)) {
                $info['extends'] = $m[1];
            }
            if (preg_match('/implements\s+([\w,\s\\\\]+)/', $content, $m)) {
                $info['implements'] = trim($m[1]);
            }

            // Count methods
            $info['methods'] = preg_match_all('/\b(public|protected|private)\s+function\s+(\w+)/', $content, $methodMatches);
            $info['method_names'] = $methodMatches[2] ?? [];

            // Count lines
            $info['lines'] = substr_count($content, "\n") + 1;

            // For models: extract relations and fillable
            if (str_contains($dir, 'Models')) {
                preg_match_all('/\$this->(hasMany|hasOne|belongsTo|belongsToMany|morphMany|morphTo)\s*\(/', $content, $relMatches);
                $info['relations'] = count($relMatches[0]);
                $info['relation_types'] = $relMatches[1] ?? [];
            }

            $classes[] = $info;
        }

        // Sort by name
        usort($classes, fn($a, $b) => ($a['name'] ?? '') <=> ($b['name'] ?? ''));

        return $classes;
    }

    /**
     * Scan JS/TS modules
     */
    protected function scanJsModules(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $modules = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['js', 'ts', 'jsx', 'tsx', 'vue'])) continue;

            $content = file_get_contents($file->getPathname());
            $info = [
                'file' => str_replace('\\', '/', $file->getPathname()),
                'name' => pathinfo($file->getFilename(), PATHINFO_FILENAME),
                'lines' => substr_count($content, "\n") + 1,
            ];

            // Count exports
            $info['exports'] = preg_match_all('/export\s+(default\s+)?(function|class|const|interface|type)/', $content);

            $modules[] = $info;
        }

        return $modules;
    }

    /**
     * Extract route definitions
     */
    protected function extractRoutes(string $routesDir): array
    {
        if (!is_dir($routesDir)) {
            return [];
        }

        $routes = [];
        foreach (glob($routesDir . '/*.php') as $file) {
            $content = file_get_contents($file);
            $filename = basename($file);

            preg_match_all('/Route::(get|post|put|patch|delete|resource|apiResource)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $routes[] = [
                    'method' => strtoupper($match[1]),
                    'uri' => $match[2],
                    'file' => $filename,
                ];
            }
        }

        return $routes;
    }

    /**
     * Extract config file keys
     */
    protected function extractConfigKeys(string $configDir): array
    {
        if (!is_dir($configDir)) {
            return [];
        }

        $keys = [];
        foreach (glob($configDir . '/*.php') as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            // Skip framework defaults
            if (in_array($name, ['app', 'auth', 'cache', 'database', 'filesystems', 'logging', 'mail', 'queue', 'session'])) {
                continue;
            }
            $keys[] = $name;
        }

        return $keys;
    }

    /**
     * Generate a readable summary for embedding
     */
    protected function generateSummary(string $project, array $structure): string
    {
        $lines = [];
        $lines[] = "# Structure: {$project}";
        $lines[] = "Type: {$structure['type']}";
        $lines[] = "Generated: " . now()->format('Y-m-d H:i');
        $lines[] = '';

        if (isset($structure['laravel_version'])) {
            $lines[] = "Laravel: {$structure['laravel_version']} | PHP: {$structure['php_version']}";
        }

        // Models
        if (!empty($structure['models'])) {
            $lines[] = '';
            $lines[] = "## Models (" . count($structure['models']) . ")";
            foreach ($structure['models'] as $model) {
                $extra = [];
                if (isset($model['extends'])) $extra[] = "extends {$model['extends']}";
                if (isset($model['relations'])) $extra[] = "{$model['relations']} relations";
                $extra[] = "{$model['methods']} methods";
                $extra[] = "{$model['lines']} lines";
                $lines[] = "- {$model['name']} (" . implode(', ', $extra) . ")";
            }
        }

        // Controllers
        if (!empty($structure['controllers'])) {
            $lines[] = '';
            $lines[] = "## Controllers (" . count($structure['controllers']) . ")";
            foreach ($structure['controllers'] as $ctrl) {
                $lines[] = "- {$ctrl['name']} ({$ctrl['methods']} methods, {$ctrl['lines']} lines)";
            }
        }

        // Services
        if (!empty($structure['services'])) {
            $lines[] = '';
            $lines[] = "## Services (" . count($structure['services']) . ")";
            foreach ($structure['services'] as $svc) {
                $methods = $svc['methods'] ?? 0;
                $svcLines = $svc['lines'] ?? 0;
                $lines[] = "- {$svc['name']} ({$methods} methods, {$svcLines} lines)";
            }
        }

        // Middleware
        if (!empty($structure['middleware'])) {
            $lines[] = '';
            $lines[] = "## Middleware (" . count($structure['middleware']) . ")";
            foreach ($structure['middleware'] as $mw) {
                $lines[] = "- {$mw['name']}";
            }
        }

        // Enums
        if (!empty($structure['enums'])) {
            $lines[] = '';
            $lines[] = "## Enums (" . count($structure['enums']) . ")";
            foreach ($structure['enums'] as $enum) {
                $lines[] = "- {$enum['name']}";
            }
        }

        // Commands
        if (!empty($structure['commands'])) {
            $lines[] = '';
            $lines[] = "## Commands (" . count($structure['commands']) . ")";
            foreach ($structure['commands'] as $cmd) {
                $lines[] = "- {$cmd['name']}";
            }
        }

        // Migrations
        if ($structure['migrations'] > 0) {
            $lines[] = '';
            $lines[] = "## Migrations: {$structure['migrations']}";
        }

        // Routes
        if (!empty($structure['routes'])) {
            $lines[] = '';
            $lines[] = "## Routes (" . count($structure['routes']) . ")";
            foreach ($structure['routes'] as $route) {
                $lines[] = "- {$route['method']} {$route['uri']} ({$route['file']})";
            }
        }

        // Config
        if (!empty($structure['config_keys'])) {
            $lines[] = '';
            $lines[] = "## Custom Config: " . implode(', ', $structure['config_keys']);
        }

        // Packages
        if (!empty($structure['packages'])) {
            $lines[] = '';
            $lines[] = "## Composer Packages (" . count($structure['packages']) . ")";
            // Only show non-laravel packages
            $interesting = array_filter($structure['packages'], fn($p) => !str_starts_with($p, 'laravel/') && $p !== 'php');
            foreach ($interesting as $pkg) {
                $lines[] = "- {$pkg}";
            }
        }

        if (!empty($structure['npm_packages'])) {
            $lines[] = '';
            $lines[] = "## NPM Packages (" . count($structure['npm_packages']) . ")";
            foreach ($structure['npm_packages'] as $pkg) {
                $lines[] = "- {$pkg}";
            }
        }

        return implode("\n", $lines);
    }

    protected function getCounts(array $structure): array
    {
        return [
            'models' => count($structure['models']),
            'controllers' => count($structure['controllers']),
            'services' => count($structure['services']),
            'middleware' => count($structure['middleware']),
            'enums' => count($structure['enums']),
            'migrations' => $structure['migrations'],
            'routes' => count($structure['routes']),
            'commands' => count($structure['commands']),
        ];
    }
}
