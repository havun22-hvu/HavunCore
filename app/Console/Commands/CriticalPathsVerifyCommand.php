<?php

namespace App\Console\Commands;

use App\Services\CriticalPaths\DocParser;
use App\Services\CriticalPaths\ReferenceChecker;
use App\Services\CriticalPaths\TestRunner;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CriticalPathsVerifyCommand extends Command
{
    protected $signature = 'critical-paths:verify
        {--project= : Slug of a single project (default: havuncore)}
        {--all : Verify every docs/kb/reference/critical-paths-*.md file}
        {--run : Also execute the referenced tests}
        {--json : Emit machine-readable JSON instead of text}';

    protected $description = 'Verify that each test reference in critical-paths-{project}.md exists (and optionally runs green)';

    public function handle(DocParser $parser, TestRunner $runner): int
    {
        if ($this->option('project') && $this->option('all')) {
            $this->error('--project and --all are mutually exclusive.');

            return 2;
        }

        $projects = $this->option('all')
            ? $this->discoverAllProjects()
            : [$this->option('project') ?: 'havuncore'];

        $reports = [];
        $worstExit = 0;

        foreach ($projects as $project) {
            $docPath = base_path("docs/kb/reference/critical-paths-{$project}.md");

            if (! is_file($docPath)) {
                $worstExit = max($worstExit, 2);
                $reports[] = [
                    'project' => $project,
                    'doc' => $docPath,
                    'error' => 'doc missing',
                    'paths' => [],
                    'totals' => ['paths' => 0, 'references' => 0, 'ok' => 0, 'missing' => 0, 'failed' => 0],
                ];
                continue;
            }

            $report = $this->verifyProject($project, $docPath, $parser, $runner);
            $reports[] = $report;

            if ($report['totals']['missing'] > 0 || $report['totals']['failed'] > 0) {
                $worstExit = max($worstExit, 1);
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode(
                count($reports) === 1 ? $reports[0] : $reports,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ));
        } else {
            $this->renderText($reports);
        }

        return $worstExit === 0 ? SymfonyCommand::SUCCESS : $worstExit;
    }

    /**
     * @return array{
     *     project: string,
     *     doc: string,
     *     paths: list<array{name: string, references: list<array<string, mixed>>}>,
     *     totals: array{paths: int, references: int, ok: int, missing: int, failed: int}
     * }
     */
    private function verifyProject(string $project, string $docPath, DocParser $parser, TestRunner $runner): array
    {
        $paths = $parser->parseFile($docPath);
        $checker = new ReferenceChecker(base_path());

        $pathReports = [];
        $totalRefs = $ok = $missing = $failed = 0;

        foreach ($paths as $path) {
            $refReports = [];
            foreach ($checker->checkAll($path['references']) as $ref) {
                $entry = $ref;
                $entry['tests_passed'] = null;
                $entry['tests_duration_ms'] = null;

                $totalRefs++;
                if ($ref['exists']) {
                    $ok++;

                    if ($this->option('run') && $ref['matches']) {
                        foreach ($ref['matches'] as $matchedPath) {
                            $result = $runner->run(TestRunner::filterFromPath($matchedPath));
                            $entry['tests_passed'] = ($entry['tests_passed'] ?? true) && $result['passed'];
                            $entry['tests_duration_ms'] = ($entry['tests_duration_ms'] ?? 0) + $result['duration_ms'];
                            if (! $result['passed']) {
                                $failed++;
                            }
                        }
                    }
                } else {
                    $missing++;
                }

                $refReports[] = $entry;
            }

            $pathReports[] = [
                'name' => $path['name'],
                'references' => $refReports,
            ];
        }

        return [
            'project' => $project,
            'doc' => str_replace('\\', '/', $docPath),
            'paths' => $pathReports,
            'totals' => [
                'paths' => count($paths),
                'references' => $totalRefs,
                'ok' => $ok,
                'missing' => $missing,
                'failed' => $failed,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $reports
     */
    private function renderText(array $reports): void
    {
        foreach ($reports as $report) {
            $this->line("[{$report['project']}] {$report['doc']}");

            if (isset($report['error'])) {
                $this->error("  Error: {$report['error']}");
                $this->line('');
                continue;
            }

            foreach ($report['paths'] as $path) {
                $this->line("  Pad: {$path['name']}");
                foreach ($path['references'] as $ref) {
                    $mark = $ref['exists'] ? '<info>✓</info>' : '<error>✗</error>';
                    $suffix = $ref['exists'] ? '' : " ({$ref['error']})";
                    if ($ref['exists'] && isset($ref['tests_passed'])) {
                        $suffix = $ref['tests_passed']
                            ? " [ran OK in {$ref['tests_duration_ms']}ms]"
                            : " [RAN FAILED]";
                    }
                    $this->line("    {$mark} {$ref['path']}{$suffix}");
                }
            }

            $t = $report['totals'];
            $this->line("  Summary: {$t['paths']} paths / {$t['references']} refs / {$t['ok']} ok / {$t['missing']} missing / {$t['failed']} failed");
            $this->line('');
        }
    }

    /**
     * @return list<string>
     */
    private function discoverAllProjects(): array
    {
        $pattern = base_path('docs/kb/reference/critical-paths-*.md');
        $projects = [];
        foreach (glob($pattern) ?: [] as $file) {
            if (preg_match('/critical-paths-(.+)\.md$/', $file, $m)) {
                $projects[] = $m[1];
            }
        }
        sort($projects);

        return $projects;
    }
}
