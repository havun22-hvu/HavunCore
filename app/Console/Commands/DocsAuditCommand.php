<?php

namespace App\Console\Commands;

use App\Services\DocsAudit\AuditReportRenderer;
use App\Services\DocsAudit\DocsAuditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DocsAuditCommand extends Command
{
    protected $signature = 'docs:audit
                            {--project= : Specifiek project uit config/quality-safety.php (of "current" voor HavunCore)}
                            {--json : JSON-output ipv markdown-rapport}
                            {--output= : Custom output-pad voor MD-rapport (alleen met current-project)}';

    protected $description = 'Audit markdown-docs op obsolete info, broken links, structuur en zombie-refs. Schrijft rapport in docs/kb/reference/kb-audit-latest.md.';

    public function handle(DocsAuditor $auditor, AuditReportRenderer $renderer): int
    {
        $projectArg = (string) ($this->option('project') ?? 'current');

        if ($projectArg === 'current') {
            return $this->auditCurrentProject($auditor, $renderer);
        }

        return $this->auditConfiguredProject($auditor, $renderer, $projectArg);
    }

    private function auditCurrentProject(DocsAuditor $auditor, AuditReportRenderer $renderer): int
    {
        $root = base_path();
        $result = $auditor->audit(
            $this->scanRootsFor($root),
            $root
        );

        if ($this->option('json')) {
            $this->output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->exitCodeFor($result);
        }

        $report = $renderer->render($result, 'havuncore', $root);
        $output = $this->option('output') ?: $root . '/docs/kb/reference/kb-audit-latest.md';
        File::ensureDirectoryExists(dirname($output));
        File::put($output, $report);

        $this->info(sprintf(
            'KB audit (havuncore): %d files, %d critical, %d high, %d medium, %d low',
            $result['scanned'],
            $result['totals']['critical'] ?? 0,
            $result['totals']['high'] ?? 0,
            $result['totals']['medium'] ?? 0,
            $result['totals']['low'] ?? 0,
        ));

        return $this->exitCodeFor($result);
    }

    private function auditConfiguredProject(DocsAuditor $auditor, AuditReportRenderer $renderer, string $projectSlug): int
    {
        $projects = (array) config('quality-safety.projects', []);
        $entry = $projects[$projectSlug] ?? null;
        if (! is_array($entry) || ($entry['enabled'] ?? false) !== true) {
            $this->error("Onbekend of uitgeschakeld project: {$projectSlug}");

            return self::FAILURE;
        }

        $root = (string) ($entry['path'] ?? '');
        if ($root === '' || ! is_dir($root)) {
            $this->error("Pad bestaat niet voor {$projectSlug}: {$root}");

            return self::FAILURE;
        }

        $result = $auditor->audit($this->scanRootsFor($root), $root);

        if ($this->option('json')) {
            $this->output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->exitCodeFor($result);
        }

        $report = $renderer->render($result, $projectSlug, $root);
        $output = $root . '/docs/kb/reference/kb-audit-latest.md';
        File::ensureDirectoryExists(dirname($output));
        File::put($output, $report);

        $this->info(sprintf(
            'KB audit (%s): %d files, %d critical, %d high',
            $projectSlug,
            $result['scanned'],
            $result['totals']['critical'] ?? 0,
            $result['totals']['high'] ?? 0,
        ));

        return $this->exitCodeFor($result);
    }

    /**
     * @return array<int,string>
     */
    private function scanRootsFor(string $projectRoot): array
    {
        $roots = [];
        foreach (['docs', '.claude'] as $sub) {
            $path = $projectRoot . DIRECTORY_SEPARATOR . $sub;
            if (is_dir($path)) {
                $roots[] = $path;
            }
        }

        return $roots;
    }

    private function exitCodeFor(array $result): int
    {
        return ($result['totals']['critical'] ?? 0) > 0 || ($result['totals']['high'] ?? 0) > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
