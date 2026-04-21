<?php

namespace App\Console\Commands;

use App\Enums\Severity;
use App\Models\DocIntelligence\DocIssue;
use App\Services\DocIntelligence\IssueDetector;
use Illuminate\Console\Command;

class DocIssuesCommand extends Command
{
    protected $signature = 'docs:issues
                            {project? : Filter by project}
                            {--severity= : Filter by severity (low, medium, high)}
                            {--type= : Filter by type (duplicate, outdated, broken_link, inconsistent)}
                            {--resolve= : Resolve issue by ID}
                            {--ignore= : Ignore issue by ID}
                            {--summary : Show summary per project instead of all issues}';

    protected $description = 'List and manage documentation issues';

    public function handle(IssueDetector $detector): int
    {
        // Handle resolve/ignore actions
        if ($resolveId = $this->option('resolve')) {
            return $this->resolveIssue($resolveId);
        }

        if ($ignoreId = $this->option('ignore')) {
            return $this->ignoreIssue($ignoreId);
        }

        // Summary mode
        if ($this->option('summary')) {
            return $this->showSummary($detector);
        }

        // List issues
        $project = $this->argument('project');
        $severity = $this->option('severity');
        $type = $this->option('type');

        $query = DocIssue::open();

        if ($project) {
            $query->where('project', strtolower($project));
        }

        if ($severity) {
            $query->where('severity', $severity);
        }

        if ($type) {
            $query->where('issue_type', $type);
        }

        // Order by severity weight (High=1, Medium=2, rest=3) — matches Severity::sortWeight()
        // DocIssue currently only persists low/medium/high, so critical/info get fallback weight 3.
        $issues = $query->orderByRaw(sprintf(
            "CASE severity WHEN '%s' THEN 1 WHEN '%s' THEN 2 ELSE 3 END",
            Severity::High->value,
            Severity::Medium->value
        ))
            ->orderBy('created_at', 'desc')
            ->get();

        if ($issues->isEmpty()) {
            $this->info('No open issues found!');
            return Command::SUCCESS;
        }

        $this->line('');
        $this->info("Open Issues (" . $issues->count() . "):");
        $this->line('');

        foreach ($issues as $issue) {
            $severityEnum = Severity::tryFrom((string) $issue->severity);
            $severityIcon = $severityEnum?->icon() ?? '⚪';

            $typeIcon = match($issue->issue_type) {
                'inconsistent' => '⚠️',
                'duplicate' => '📋',
                'outdated' => '📅',
                'broken_link' => '🔗',
                'missing' => '❓',
                default => '•',
            };

            $projectLabel = $issue->project ?? 'cross-project';

            $this->line("{$severityIcon} {$typeIcon} [{$issue->id}] {$issue->title}");
            $this->line("   Project: {$projectLabel}");
            $this->line("   Files: " . implode(', ', $issue->affected_files ?? []));
            if ($issue->suggested_action) {
                $this->line("   Action: {$issue->suggested_action}");
            }
            $this->line('');
        }

        $this->line('---');
        $this->line('Commands:');
        $this->line('  php artisan docs:issues --resolve=ID   Resolve an issue');
        $this->line('  php artisan docs:issues --ignore=ID    Ignore an issue');

        return Command::SUCCESS;
    }

    protected function showSummary(IssueDetector $detector): int
    {
        $summary = $detector->getIssueSummary();

        if (empty($summary)) {
            $this->info('No open issues found!');
            return Command::SUCCESS;
        }

        // Calculate totals
        $totalHigh = 0;
        $totalMedium = 0;
        $totalLow = 0;
        $totalAll = 0;

        foreach ($summary as $project => $data) {
            $totalHigh += $data['high'];
            $totalMedium += $data['medium'];
            $totalLow += $data['low'];
            $totalAll += $data['total'];
        }

        $this->line('');
        $this->info('📊 MD File Audit - ' . now()->format('Y-m-d'));
        $this->line('═══════════════════════════════════════');
        $this->line('');
        $this->line('SAMENVATTING');
        $this->line('────────────');
        $this->line("🔴 HIGH:   {$totalHigh} issues");
        $this->line("🟡 MEDIUM: {$totalMedium} issues");
        $this->line("🟢 LOW:    {$totalLow} issues");
        $this->line("   TOTAAL: {$totalAll} issues");
        $this->line('');
        $this->line('PER PROJECT');
        $this->line('───────────');

        foreach ($summary as $project => $data) {
            $icon = $data[Severity::High->value] > 0
                ? Severity::High->icon()
                : ($data[Severity::Medium->value] > 0 ? Severity::Medium->icon() : Severity::Low->icon());
            $this->line("{$icon} {$project}: {$data['total']} issues");

            // Show breakdown by type
            foreach ($data['by_type'] as $type => $count) {
                $typeLabel = match($type) {
                    'inconsistent' => 'Inconsistenties',
                    'duplicate' => 'Duplicaten',
                    'outdated' => 'Verouderd',
                    'broken_link' => 'Broken links',
                    'missing' => 'Ontbrekend',
                    default => $type,
                };
                $this->line("   - {$typeLabel}: {$count}");
            }
        }

        $this->line('');
        $this->line('───────────');
        $this->line('Gebruik `php artisan docs:issues [project]` voor details');
        $this->line('Gebruik `php artisan docs:issues --severity=high` voor alleen HIGH issues');

        return Command::SUCCESS;
    }

    protected function resolveIssue(int $id): int
    {
        $issue = DocIssue::find($id);

        if (!$issue) {
            $this->error("Issue #{$id} not found.");
            return Command::FAILURE;
        }

        $issue->resolve('user');
        $this->info("Issue #{$id} marked as resolved.");

        return Command::SUCCESS;
    }

    protected function ignoreIssue(int $id): int
    {
        $issue = DocIssue::find($id);

        if (!$issue) {
            $this->error("Issue #{$id} not found.");
            return Command::FAILURE;
        }

        $issue->ignore();
        $this->info("Issue #{$id} marked as ignored.");

        return Command::SUCCESS;
    }
}
