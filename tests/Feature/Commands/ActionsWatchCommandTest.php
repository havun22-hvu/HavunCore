<?php

namespace Tests\Feature\Commands;

use App\Models\HealthAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Vusista's staging deploy failed for thirteen days. Every push turned the
 * Action red and put a broken page on a public URL, and it reached nobody —
 * the signal existed, the channel did not. These tests pin that channel.
 */
class ActionsWatchCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/actions-test-' . uniqid();
        mkdir($this->tmp, 0777, true);
        config(['havun-projects' => ['proj' => ['path' => $this->tmp]]]);
    }

    protected function tearDown(): void
    {
        @rmdir($this->tmp);
        parent::tearDown();
    }

    /**
     * The command passes its commands as ARRAYS, and Process::fake's pattern
     * map only matches strings — a ['gh auth*' => ...] map silently matches
     * nothing and every call falls through to a successful default. Hence a
     * closure that flattens the command first. Same trap as in
     * AutoCommitRegeneratedTest.
     *
     * @param  array<string,mixed>|null  $run  null = geen runs
     */
    private function fakeGh(?array $run, bool $ghIngelogd = true, bool $remote = true): void
    {
        Process::fake(function ($process) use ($run, $ghIngelogd, $remote) {
            $cmd = implode(' ', (array) $process->command);

            return match (true) {
                str_starts_with($cmd, 'gh auth status') => Process::result('', '', $ghIngelogd ? 0 : 1),
                str_starts_with($cmd, 'git remote get-url') => $remote
                    ? Process::result("https://github.com/havun22-hvu/Proj.git\n")
                    : Process::result('', 'no remote', 1),
                str_contains($cmd, '--jq .default_branch') => Process::result("main\n"),
                str_contains($cmd, '/actions/runs') => Process::result(
                    json_encode(['workflow_runs' => $run === null ? [] : [$run]])
                ),
                default => Process::result(''),
            };
        });
    }

    /**
     * @return array<string,mixed>
     */
    private function workflowRun(string $conclusion, string $updatedAt): array
    {
        return [
            'conclusion' => $conclusion,
            'name' => 'Tests',
            'updated_at' => $updatedAt,
            'html_url' => 'https://github.com/havun22-hvu/Proj/actions/runs/1',
        ];
    }

    public function test_a_failing_run_on_the_main_branch_opens_an_alert(): void
    {
        $this->fakeGh($this->workflowRun('failure', now()->subHours(2)->toIso8601String()));

        $this->artisan('actions:watch')->assertExitCode(0);

        $alert = HealthAlert::where('key', 'actions-proj')->first();
        $this->assertNotNull($alert, 'A red build must reach someone');
        $this->assertSame('open', $alert->status);
        $this->assertSame('proj', $alert->project);
        $this->assertStringContainsString('build faalt', $alert->title);
    }

    public function test_a_build_red_for_days_escalates_to_critical(): void
    {
        // Thirteen days of silence is the case this exists for: severity has to
        // grow with duration, or a long outage stays as quiet as a fresh one.
        $this->fakeGh($this->workflowRun('failure', now()->subDays(13)->toIso8601String()));

        $this->artisan('actions:watch')->assertExitCode(0);

        $this->assertSame('critical', HealthAlert::where('key', 'actions-proj')->first()->severity);
    }

    public function test_a_freshly_broken_build_is_only_a_warning(): void
    {
        $this->fakeGh($this->workflowRun('failure', now()->subHours(3)->toIso8601String()));

        $this->artisan('actions:watch')->assertExitCode(0);

        $this->assertSame('warning', HealthAlert::where('key', 'actions-proj')->first()->severity);
    }

    public function test_a_green_run_resolves_an_open_alert(): void
    {
        HealthAlert::create([
            'key' => 'actions-proj',
            'scope' => 'project',
            'project' => 'proj',
            'severity' => 'warning',
            'title' => 'proj: build faalt op de hoofdbranch',
            'status' => 'open',
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now()->subDay(),
        ]);

        $this->fakeGh($this->workflowRun('success', now()->toIso8601String()));

        $this->artisan('actions:watch')->assertExitCode(0);

        $this->assertSame('resolved', HealthAlert::where('key', 'actions-proj')->first()->status);
    }

    public function test_a_missing_gh_fails_loudly_instead_of_looking_clean(): void
    {
        // The whole point: not being able to measure is reported, never silently
        // passed off as "all green".
        $this->fakeGh(null, ghIngelogd: false);

        $this->artisan('actions:watch')
            ->expectsOutputToContain('NIET gecontroleerd')
            ->assertExitCode(1);

        $this->assertSame(0, HealthAlert::count());
    }

    public function test_dry_run_reports_without_writing_an_alert(): void
    {
        $this->fakeGh($this->workflowRun('failure', now()->subDays(5)->toIso8601String()));

        $this->artisan('actions:watch', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(0, HealthAlert::count(), 'A dry run must not touch the alert table');
    }

    public function test_a_project_without_a_github_remote_is_skipped(): void
    {
        $this->fakeGh(null, remote: false);

        $this->artisan('actions:watch')->assertExitCode(0);

        $this->assertSame(0, HealthAlert::count());
    }

    public function test_a_repo_without_any_runs_produces_no_alert(): void
    {
        // Vusista2 today: a repo with no workflows yet. Nothing to report, and
        // that is different from a red build.
        $this->fakeGh(null);

        $this->artisan('actions:watch')->assertExitCode(0);

        $this->assertSame(0, HealthAlert::count());
    }
}
