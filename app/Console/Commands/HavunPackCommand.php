<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\NormalizesPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class HavunPackCommand extends Command
{
    use NormalizesPath;

    protected $signature = 'havun:pack
                            {--project= : Project name (e.g. herdenkingsportaal, judotoernooi)}
                            {--format=text : Output format: text or json}
                            {--include-source : Include PHP/JS/TS source files + live API samples}';

    protected $description = 'Pack project context into a structured AI-ready payload';

    private const SKIP_DIRS = ['vendor', 'node_modules', '.git', 'storage', 'bootstrap'];

    private const SOURCE_DIRS = [
        'app'              => ['php'],
        'routes'           => ['php'],
        'database'         => ['php'],
        'config'           => ['php'],
        'resources/js'     => ['js', 'ts', 'vue', 'jsx', 'tsx'],
        'resources/views'  => ['php'],
        'src'              => ['js', 'ts', 'vue', 'jsx', 'tsx'],
        'tests'            => ['php'],
    ];

    public function handle(): int
    {
        $projects = config('havun-projects');
        $projectKey = strtolower($this->option('project') ?? '');
        $format = $this->option('format');
        $includeSource = (bool) $this->option('include-source');

        if (! $projectKey) {
            $projectKey = $this->detectProjectFromCwd($projects);
            if (! $projectKey) {
                $this->error('Kan project niet detecteren. Gebruik --project=<name>');
                $this->line('Available: ' . implode(', ', array_keys($projects)));
                return Command::FAILURE;
            }
            $this->line("Auto-detected project: {$projectKey}");
        }

        if (! isset($projects[$projectKey])) {
            $this->error("Unknown project: {$projectKey}");
            $this->line('Available: ' . implode(', ', array_keys($projects)));
            return Command::FAILURE;
        }

        $config = $projects[$projectKey];
        $payload = $this->buildPayload($projectKey, $config, $includeSource);

        if ($format === 'json') {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderText($payload);
        }

        return Command::SUCCESS;
    }

    private function detectProjectFromCwd(array $projects): ?string
    {
        $cwd = str_replace('\\', '/', getcwd() ?: '');
        if (! $cwd) {
            return null;
        }

        foreach ($projects as $key => $config) {
            if (str_starts_with($cwd, $config['path'])) {
                return $key;
            }
        }
        return null;
    }

    private function buildPayload(string $projectKey, array $config, bool $includeSource): array
    {
        $projectPath = $config['path'];
        $gitDepth = $includeSource ? 50 : 10;

        $payload = [
            'project'       => $projectKey,
            'generated'     => now()->toIso8601String(),
            'mode'          => $includeSource ? 'full (broncode + live API)' : 'docs-only',
            'git_log_depth' => $gitDepth,
            'claude_md'     => $this->readFile($projectPath . '/CLAUDE.md'),
            'contracts'     => $this->readFile($projectPath . '/CONTRACTS.md'),
            'kb_docs'       => $this->readKbDocs($projectKey),
            'git_log'       => $this->gitLog($projectPath, $gitDepth),
            'open_files'    => $this->listDocFiles($projectPath),
        ];

        if ($includeSource) {
            $payload['composer_json'] = $this->readFile($projectPath . '/composer.json');
            $payload['package_json']  = $this->readFile($projectPath . '/package.json');
            $payload['source_files']  = $this->listSourceFiles($projectPath);
            $payload['api_samples']   = $this->fetchApiSamples(
                $config['local_url'] ?? null,
                $config['endpoints'] ?? []
            );
        }

        return $payload;
    }

    private function fetchApiSamples(?string $baseUrl, array $endpoints): array
    {
        if (! $baseUrl || empty($endpoints)) {
            return [];
        }

        $samples = [];
        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::timeout(3)->get($baseUrl . $endpoint);
                $samples[$endpoint] = $response->successful()
                    ? $response->body()
                    : 'API_UNAVAILABLE (HTTP ' . $response->status() . ')';
            } catch (\Throwable) {
                $samples[$endpoint] = 'API_UNAVAILABLE (timeout of connectiefout)';
            }
        }

        return $samples;
    }

    private function readFile(string $path): ?string
    {
        $normalized = $this->normalizePath($path);
        $content = @file_get_contents($normalized);
        return $content !== false ? $content : null;
    }

    private function readKbDocs(string $projectKey): array
    {
        $kbBase = base_path('docs/kb');
        $docs = [];

        $globalDocs = [
            'reference/authentication-methods.md',
            'reference/test-quality-policy.md',
            'reference/productie-deploy-eisen.md',
            'runbooks/claude-werkwijze.md',
            'runbooks/gemini-claude-workflow.md',
            'decisions/008-gemini-claude-hybrid-workflow.md',
        ];

        foreach ($globalDocs as $doc) {
            $content = $this->readFile($kbBase . '/' . $doc);
            if ($content !== null) {
                $docs[$doc] = $content;
            }
        }

        $projectDoc = $this->readFile($kbBase . '/projects/' . $projectKey . '.md');
        if ($projectDoc !== null) {
            $docs["projects/{$projectKey}.md"] = $projectDoc;
        }

        return $docs;
    }

    private function gitLog(string $projectPath, int $depth): string
    {
        $path = $this->normalizePath($projectPath);

        $process = new Process(['git', '-C', $path, 'log', '--oneline', "-{$depth}"]);
        $process->run();

        return $process->isSuccessful()
            ? trim($process->getOutput())
            : '(git log failed)';
    }

    private function listDocFiles(string $projectPath): array
    {
        $files = $this->scanFiles($projectPath, null, ['md']);
        ksort($files);
        return $files;
    }

    private function listSourceFiles(string $projectPath): array
    {
        $path = $this->normalizePath($projectPath);
        $chunks = [];

        foreach (self::SOURCE_DIRS as $subDir => $extensions) {
            $dirPath = $path . DIRECTORY_SEPARATOR . $this->normalizePath($subDir);
            if (! is_dir($dirPath)) {
                continue;
            }
            $chunks[] = $this->scanFiles($dirPath, $path, $extensions);
        }

        $files = $chunks ? array_merge(...$chunks) : [];
        ksort($files);
        return $files;
    }

    private function scanFiles(string $scanPath, ?string $basePath, array $extensions): array
    {
        $path = $this->normalizePath($scanPath);
        $base = $basePath ? $this->normalizePath($basePath) : $path;
        $files = [];

        $dir = new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS);
        $filtered = new \RecursiveCallbackFilterIterator($dir, function ($current) use ($extensions) {
            if ($current->isDir()) {
                return ! in_array($current->getFilename(), self::SKIP_DIRS, true);
            }
            return in_array($current->getExtension(), $extensions, true);
        });

        foreach (new \RecursiveIteratorIterator($filtered) as $file) {
            $relative = str_replace([$base . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $file->getPathname());
            $content = file_get_contents($file->getPathname());
            if ($content !== false && trim($content) !== '') {
                $files[$relative] = $content;
            }
        }

        return $files;
    }

    private function renderText(array $payload): void
    {
        $this->line('');
        $this->info("=== HAVUN PACK: {$payload['project']} ({$payload['mode']}) ===");
        $this->line("Generated: {$payload['generated']}");
        $this->line('');

        if ($payload['claude_md'] !== null) {
            $this->info('--- CLAUDE.md ---');
            $this->line($payload['claude_md']);
            $this->line('');
        }

        if ($payload['contracts'] !== null) {
            $this->info('--- CONTRACTS.md ---');
            $this->line($payload['contracts']);
            $this->line('');
        }

        if (! empty($payload['kb_docs'])) {
            $this->info('--- KB DOCS ---');
            foreach ($payload['kb_docs'] as $path => $content) {
                $this->line("### {$path}");
                $this->line($content);
                $this->line('');
            }
        }

        if (! empty($payload['open_files'])) {
            $this->info('--- PROJECT DOCS ---');
            foreach ($payload['open_files'] as $path => $content) {
                $this->line("### {$path}");
                $this->line($content);
                $this->line('');
            }
        }

        if (isset($payload['composer_json']) && $payload['composer_json'] !== null) {
            $this->info('--- composer.json ---');
            $this->line($payload['composer_json']);
            $this->line('');
        }

        if (isset($payload['package_json']) && $payload['package_json'] !== null) {
            $this->info('--- package.json ---');
            $this->line($payload['package_json']);
            $this->line('');
        }

        if (! empty($payload['api_samples'])) {
            $this->info('--- LIVE API SAMPLES (' . count($payload['api_samples']) . ' endpoints) ---');
            foreach ($payload['api_samples'] as $endpoint => $body) {
                $this->line("### {$endpoint}");
                $this->line($body);
                $this->line('');
            }
        }

        if (! empty($payload['source_files'])) {
            $this->info('--- SOURCE FILES (' . count($payload['source_files']) . ' bestanden) ---');
            foreach ($payload['source_files'] as $path => $content) {
                $this->line("### {$path}");
                $this->line($content);
                $this->line('');
            }
        }

        $this->info("--- GIT LOG (last {$payload['git_log_depth']}) ---");
        $this->line($payload['git_log']);
        $this->line('');
    }
}
