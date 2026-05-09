<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;

class AutoCommitRegeneratedCommand extends Command
{
    protected $signature = 'auto:commit-regenerated
        {--dry-run : Show what would be committed/pushed but do not run git}';

    protected $description = 'Stage + commit + push the auto-regenerated docs (handover, kb-audit, qv-scan, security-findings). Idempotent — exits cleanly when nothing has changed.';

    /**
     * Files this command is allowed to commit. Anything else stays untouched.
     * Keep this list minimal — the whole point is "we own these regenerations
     * and will push them automatically; everything else is a human commit".
     */
    private const REGENERATED_PATHS = [
        'docs/handover.md',
        'docs/kb/reference/kb-audit-latest.md',
        'docs/kb/reference/qv-scan-latest.md',
        'docs/kb/reference/security-findings-log.md',
    ];

    public function handle(): int
    {
        $root = base_path();
        $dryRun = (bool) $this->option('dry-run');

        $existing = array_values(array_filter(
            self::REGENERATED_PATHS,
            fn ($p) => file_exists($root . DIRECTORY_SEPARATOR . $p),
        ));

        if (empty($existing)) {
            $this->info('Geen regenerated files gevonden — niets te doen.');

            return 0;
        }

        // Determine which of those files have actual changes vs HEAD.
        $diff = Process::path($root)->run(array_merge(
            ['git', 'diff', '--name-only', 'HEAD', '--'],
            $existing
        ));
        if (! $diff->successful()) {
            $this->error('git diff faalde: ' . trim($diff->errorOutput() ?: $diff->output()));

            return 2;
        }

        $changed = array_values(array_filter(preg_split('/\R/', $diff->output()) ?: []));
        if (empty($changed)) {
            $this->info('Niets gewijzigd in regenerated paths — schoon.');

            return 0;
        }

        $this->line('Wijzigingen in:');
        foreach ($changed as $f) {
            $this->line("  - {$f}");
        }

        if ($dryRun) {
            $this->comment('[dry-run] commit + push overgeslagen.');

            return 0;
        }

        $stamp = Carbon::now()->toIso8601String();
        $message = 'chore(auto): refresh ' . implode(', ', array_map(
            fn ($p) => basename($p, '.md'),
            $changed,
        )) . " ({$stamp})";

        $add = Process::path($root)->run(array_merge(['git', 'add', '--'], $changed));
        if (! $add->successful()) {
            $this->error('git add faalde: ' . trim($add->errorOutput() ?: $add->output()));

            return 2;
        }

        $commit = Process::path($root)->run(['git', 'commit', '-m', $message]);
        if (! $commit->successful()) {
            $this->error('git commit faalde: ' . trim($commit->errorOutput() ?: $commit->output()));

            return 2;
        }

        $push = Process::path($root)->timeout(30)->run(['git', 'push']);
        if (! $push->successful()) {
            $this->error('git push faalde: ' . trim($push->errorOutput() ?: $push->output()));
            $this->warn('Commit staat lokaal — handmatige push nodig.');

            return 2;
        }

        $this->info("✓ Auto-commit + push: {$message}");

        return 0;
    }
}
