<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regression tests for the auto-commit cron.
 *
 * The command commits regenerated docs and pushes them. On production the deploy key is
 * read-only by design, so the push always failed — and the command left the commit in
 * place ("handmatige push nodig"). Nobody could push from there, so every daily run added
 * another unreachable commit and the checkout diverged: every deploy then broke on
 * --ff-only. Seen again 15-07-2026.
 */
#[Group('doc-intelligence')]
class AutoCommitRegeneratedTest extends TestCase
{
    /**
     * The dry-run push probe is what decides whether we commit at all.
     *
     * Note: the command passes its git commands as ARRAYS, and Process::fake's pattern
     * matching only works on strings — a ['git diff*' => ...] map silently matches nothing.
     * Hence a closure that flattens the command first.
     */
    private function fakeGit(bool $pushMogelijk, bool $pushSlaagt = true): void
    {
        Process::fake(function ($process) use ($pushMogelijk, $pushSlaagt) {
            $cmd = implode(' ', (array) $process->command);

            return match (true) {
                str_starts_with($cmd, 'git diff') => Process::result(output: "docs/handover.md\n"),
                str_starts_with($cmd, 'git push --dry-run') => $pushMogelijk
                    ? Process::result(output: '')
                    : Process::result(output: '', errorOutput: 'ERROR: You are not allowed to push code to this project.', exitCode: 128),
                $cmd === 'git push' => $pushSlaagt
                    ? Process::result(output: 'ok')
                    : Process::result(output: '', errorOutput: 'remote: read-only', exitCode: 128),
                str_starts_with($cmd, 'git commit') => Process::result(output: '1 file changed'),
                default => Process::result(output: ''),
            };
        });
    }

    public function test_it_does_not_commit_when_the_remote_is_read_only(): void
    {
        $this->fakeGit(pushMogelijk: false);

        $this->artisan('auto:commit-regenerated')
            ->expectsOutputToContain('Push is hier niet mogelijk')
            ->assertExitCode(0);

        // The whole point: no commit is created, so the checkout cannot diverge.
        Process::assertNotRan(fn ($p) => str_contains(implode(' ', (array) $p->command), 'git commit'));
    }

    public function test_it_commits_and_pushes_when_the_remote_accepts_writes(): void
    {
        $this->fakeGit(pushMogelijk: true);

        $this->artisan('auto:commit-regenerated')->assertExitCode(0);

        Process::assertRan(fn ($p) => str_contains(implode(' ', (array) $p->command), 'git commit'));
        Process::assertRan(fn ($p) => implode(' ', (array) $p->command) === 'git push');
    }

    /** Belt and braces: if the push still fails, the commit must not survive. */
    public function test_it_rolls_the_commit_back_when_the_push_fails_anyway(): void
    {
        $this->fakeGit(pushMogelijk: true, pushSlaagt: false);

        $this->artisan('auto:commit-regenerated')
            ->expectsOutputToContain('teruggedraaid')
            ->assertExitCode(2);

        Process::assertRan(fn ($p) => str_contains(implode(' ', (array) $p->command), 'reset --soft HEAD~1')
            || implode(' ', (array) $p->command) === 'git reset --soft HEAD~1');
    }

    public function test_dry_run_touches_nothing(): void
    {
        $this->fakeGit(pushMogelijk: true);

        $this->artisan('auto:commit-regenerated', ['--dry-run' => true])->assertExitCode(0);

        Process::assertNotRan(fn ($p) => str_contains(implode(' ', (array) $p->command), 'git commit'));
    }
}
