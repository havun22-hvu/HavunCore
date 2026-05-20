<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class HavunPackCommand extends Command
{
    protected $signature = 'havun:pack
                            {--project= : Project name (e.g. herdenkingsportaal, judotoernooi)}
                            {--format=text : Output format: text or json}';

    protected $description = 'Pack project context into a structured AI-ready payload';

    private array $projects = [
        'havuncore'         => 'D:/GitHub/HavunCore',
        'herdenkingsportaal' => 'D:/GitHub/Herdenkingsportaal',
        'judotoernooi'      => 'D:/GitHub/JudoToernooi',
        'studieplanner'     => 'D:/GitHub/Studieplanner',
        'havunadmin'        => 'D:/GitHub/HavunAdmin',
        'infosyst'          => 'D:/GitHub/Infosyst',
        'safehavun'         => 'D:/GitHub/SafeHavun',
        'munus'             => 'D:/GitHub/Munus',
        'aeterna'           => 'D:/GitHub/Aeterna',
        'havunclub'         => 'D:/GitHub/HavunClub',
        'havunity'          => 'D:/GitHub/Havunity',
    ];

    public function handle(): int
    {
        $projectKey = strtolower($this->option('project') ?? '');
        $format = $this->option('format');

        if (! $projectKey) {
            $this->error('Specify a project with --project=<name>');
            $this->line('Available: ' . implode(', ', array_keys($this->projects)));
            return Command::FAILURE;
        }

        if (! isset($this->projects[$projectKey])) {
            $this->error("Unknown project: {$projectKey}");
            $this->line('Available: ' . implode(', ', array_keys($this->projects)));
            return Command::FAILURE;
        }

        $projectPath = $this->projects[$projectKey];
        $payload = $this->buildPayload($projectKey, $projectPath);

        if ($format === 'json') {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderText($payload);
        }

        return Command::SUCCESS;
    }

    private function buildPayload(string $projectKey, string $projectPath): array
    {
        return [
            'project'    => $projectKey,
            'generated'  => now()->toIso8601String(),
            'claude_md'  => $this->readFile($projectPath . '/CLAUDE.md'),
            'contracts'  => $this->readFile($projectPath . '/CONTRACTS.md'),
            'kb_docs'    => $this->readKbDocs($projectKey),
            'git_log'    => $this->gitLog($projectPath),
            'open_files' => $this->listOpenFiles($projectPath),
        ];
    }

    private function readFile(string $path): ?string
    {
        $normalized = str_replace('/', DIRECTORY_SEPARATOR, $path);
        return file_exists($normalized) ? file_get_contents($normalized) : null;
    }

    private function readKbDocs(string $projectKey): array
    {
        $kbBase = 'D:/GitHub/HavunCore/docs/kb';
        $docs = [];

        // Always include global references
        $globalDocs = [
            'reference/authentication-methods.md',
            'reference/test-quality-policy.md',
            'reference/productie-deploy-eisen.md',
            'runbooks/claude-werkwijze.md',
        ];

        foreach ($globalDocs as $doc) {
            $content = $this->readFile($kbBase . '/' . $doc);
            if ($content) {
                $docs[$doc] = $content;
            }
        }

        // Project-specific docs
        $projectDoc = $this->readFile($kbBase . '/projects/' . $projectKey . '.md');
        if ($projectDoc) {
            $docs["projects/{$projectKey}.md"] = $projectDoc;
        }

        return $docs;
    }

    private function gitLog(string $projectPath): string
    {
        $path = str_replace('/', DIRECTORY_SEPARATOR, $projectPath);
        if (! is_dir($path . DIRECTORY_SEPARATOR . '.git')) {
            return '(no git repo)';
        }

        $output = shell_exec("git -C \"{$path}\" log --oneline -10 2>&1");
        return trim($output ?? '(git log failed)');
    }

    private function listOpenFiles(string $projectPath): array
    {
        $path = str_replace('/', DIRECTORY_SEPARATOR, $projectPath);
        $files = [];

        // Key files worth including if they exist
        $candidates = [
            'PLAN.md',
            'SPEC.md',
            'docs/INDEX.md',
            '.claude/context.md',
        ];

        foreach ($candidates as $candidate) {
            $full = $path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
            if (file_exists($full)) {
                $content = file_get_contents($full);
                // Truncate large files
                if (strlen($content) > 8000) {
                    $content = substr($content, 0, 8000) . "\n\n[... truncated ...]";
                }
                $files[$candidate] = $content;
            }
        }

        return $files;
    }

    private function renderText(array $payload): void
    {
        $this->line('');
        $this->info("=== HAVUN PACK: {$payload['project']} ===");
        $this->line("Generated: {$payload['generated']}");
        $this->line('');

        if ($payload['claude_md']) {
            $this->info('--- CLAUDE.md ---');
            $this->line($payload['claude_md']);
            $this->line('');
        }

        if ($payload['contracts']) {
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

        $this->info('--- GIT LOG (last 10) ---');
        $this->line($payload['git_log']);
        $this->line('');
    }
}
