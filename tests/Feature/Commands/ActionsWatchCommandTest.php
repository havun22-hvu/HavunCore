<?php

namespace Tests\Feature\Commands;

use App\Models\HealthAlert;
use App\Models\VaultSecret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
     * Twee bronnen: de git-remote komt uit een subproces (Process), de
     * Actions-status uit de GitHub-API (Http). Sinds 04-08-2026 is er geen
     * `gh` meer bij betrokken — die stond niet op de server, waar de cron
     * draait.
     *
     * Let op bij Process::fake: het commando is een ARRAY en de patroon-map
     * matcht alleen strings, dus een `['git remote*' => ...]`-map matcht stil
     * niets en alles valt door naar een geslaagde default. Vandaar de closure.
     *
     * @param  array<string,mixed>|null  $run  null = geen runs
     */
    private function fakeGh(?array $run, bool $patAanwezig = true, bool $remote = true): void
    {
        if ($patAanwezig) {
            VaultSecret::create([
                'key' => 'github_pat_ro',
                'value' => 'ghp_' . str_repeat('x', 36),
                'category' => 'github',
                'description' => 'test',
                'is_sensitive' => true,
            ]);
        }

        Process::fake(function ($process) use ($remote) {
            $cmd = implode(' ', (array) $process->command);

            return str_starts_with($cmd, 'git remote get-url') && $remote
                ? Process::result("https://github.com/havun22-hvu/Proj.git\n")
                : Process::result('', 'no remote', 1);
        });

        Http::fake([
            'api.github.com/repos/havun22-hvu/Proj' => Http::response(['default_branch' => 'main']),
            'api.github.com/repos/havun22-hvu/Proj/actions/runs*' => Http::response([
                'workflow_runs' => $run === null ? [] : [$run],
            ]),
        ]);
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

    public function test_a_missing_credential_fails_loudly_instead_of_looking_clean(): void
    {
        // The whole point: not being able to measure is reported, never silently
        // passed off as "all green".
        $this->fakeGh(null, patAanwezig: false);

        $this->artisan('actions:watch')
            ->expectsOutputToContain('NIET gecontroleerd')
            ->assertExitCode(1);

        $this->assertSame(0, HealthAlert::count());
    }

    /**
     * Op de server staat `gh` niet geïnstalleerd, en installeren zou een extra
     * binary zijn om iets te doen wat een HTTP-call ook kan. De crons van
     * 07:00 en 19:00 hebben daardoor sinds hun bestaan niets gecontroleerd —
     * vier rode builds, waarvan HavunAdmin al drie maanden, vond niemand.
     *
     * De read-only PAT staat al in de Vault (`github_pat_ro`); daarmee is `gh`
     * overbodig.
     */
    public function test_werkt_zonder_gh_via_de_pat_uit_de_vault(): void
    {
        VaultSecret::create([
            'key' => 'github_pat_ro',
            'value' => 'ghp_' . str_repeat('x', 36),
            'category' => 'github',
            'description' => 'test',
            'is_sensitive' => true,
        ]);

        // `gh` bestaat hier niet: elk procesaanroep behalve git faalt.
        Process::fake(function ($process) {
            $cmd = implode(' ', (array) $process->command);

            return str_starts_with($cmd, 'git remote get-url')
                ? Process::result("https://github.com/havun22-hvu/Proj.git
")
                : Process::result('', 'command not found: gh', 127);
        });

        Http::fake([
            'api.github.com/repos/havun22-hvu/Proj' => Http::response(['default_branch' => 'main']),
            'api.github.com/repos/havun22-hvu/Proj/actions/runs*' => Http::response([
                'workflow_runs' => [$this->workflowRun('failure', now()->subDays(4)->toIso8601String())],
            ]),
        ]);

        $this->artisan('actions:watch')->assertExitCode(0);

        $alert = HealthAlert::where('key', 'actions-proj')->first();
        $this->assertNotNull($alert, 'de rode build hoort een alert op te leveren, ook zonder gh');
        $this->assertSame('critical', $alert->severity);
    }

    /**
     * Zonder PAT valt er niets te meten — en dan meld je dat, in plaats van een
     * lege ronde als "alles groen" te laten lezen.
     */
    public function test_zonder_pat_faalt_het_hardop(): void
    {
        Process::fake(fn () => Process::result('', 'command not found: gh', 127));

        $this->artisan('actions:watch')
            ->expectsOutputToContain('NIET gecontroleerd')
            ->assertExitCode(1);

        $this->assertSame(0, HealthAlert::count());
    }

    /**
     * Op de server bestaat `D:/GitHub/...` niet, dus vond `repoVoor()` daar
     * nooit een git-remote en controleerde het commando nul repo's — met
     * exitcode 0, wat leest als "alles goed". De checkouts staan er wél, onder
     * `/var/www/...`.
     */
    public function test_valt_terug_op_het_serverpad_voor_de_git_remote(): void
    {
        config(['havun-projects' => ['proj' => [
            'path' => 'D:/GitHub/BestaatHierNiet',
            'server_path' => $this->tmp,
        ]]]);

        $this->fakeGh($this->workflowRun('failure', now()->subDays(4)->toIso8601String()));

        $this->artisan('actions:watch')->assertExitCode(0);

        $this->assertNotNull(HealthAlert::where('key', 'actions-proj')->first());
    }

    /**
     * Nul repo's gecontroleerd is geen schone ronde: dan is er niet gekeken.
     * Precies zo bleef vier maanden onopgemerkt dat de cron niets deed.
     */
    public function test_nul_gecontroleerde_repos_is_een_luide_fout(): void
    {
        config(['havun-projects' => ['proj' => ['path' => 'D:/GitHub/BestaatHierNiet']]]);

        $this->fakeGh(null);

        $this->artisan('actions:watch')
            ->expectsOutputToContain('geen enkele repo')
            ->assertExitCode(1);
    }

    public function test_dry_run_reports_without_writing_an_alert(): void
    {
        $this->fakeGh($this->workflowRun('failure', now()->subDays(5)->toIso8601String()));

        $this->artisan('actions:watch', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(0, HealthAlert::count(), 'A dry run must not touch the alert table');
    }

    /**
     * Eén project zonder GitHub-remote levert geen alert op. Is het meteen het
     * énige project, dan is er ook niets gecontroleerd — en dát meldt het
     * commando sinds 04-08 hardop, want op de server was "nul repo's" jarenlang
     * de stille werkelijkheid.
     */
    public function test_a_project_without_a_github_remote_is_skipped(): void
    {
        $this->fakeGh(null, remote: false);

        $this->artisan('actions:watch')
            ->expectsOutputToContain('geen enkele repo')
            ->assertExitCode(1);

        $this->assertSame(0, HealthAlert::count());
    }

    /**
     * De prod-checkouts gebruiken per repo een eigen SSH-host-alias
     * (`git@github-judotoernooi:havun22-hvu/Judotoernooi.git`) zodat elke
     * deploy-key maar één project opent. De regex matchte alleen letterlijk
     * `github.com`, dus op de server viel zes van de zeven checkouts weg —
     * de beveiligingsmaatregel maakte de bewaking blind.
     */
    public function test_herkent_een_ssh_host_alias_als_github_repo(): void
    {
        VaultSecret::create([
            'key' => 'github_pat_ro',
            'value' => 'ghp_' . str_repeat('x', 36),
            'category' => 'github',
            'description' => 'test',
            'is_sensitive' => true,
        ]);

        Process::fake(fn () => Process::result("git@github-proj:havun22-hvu/Proj.git
"));

        Http::fake([
            'api.github.com/repos/havun22-hvu/Proj' => Http::response(['default_branch' => 'main']),
            'api.github.com/repos/havun22-hvu/Proj/actions/runs*' => Http::response([
                'workflow_runs' => [$this->workflowRun('failure', now()->subDays(4)->toIso8601String())],
            ]),
        ]);

        $this->artisan('actions:watch')->assertExitCode(0);

        $this->assertNotNull(HealthAlert::where('key', 'actions-proj')->first());
    }

    /**
     * De read-only PAT is ooit gemaakt voor de mobiele monitoring en heeft geen
     * toegang tot alle repo's; GitHub antwoordt dan met 404, niet 403. Gemeten
     * 04-08-2026 op de server: HavunAdmin, Herdenkingsportaal, VPDUpdate en
     * havuncore-webapp gaven alle vier 404 — en verdwenen zonder een woord uit
     * de telling. Een repo die je niet kunt bevragen is geen repo zonder
     * problemen.
     */
    public function test_een_onbereikbare_repo_verdwijnt_niet_stil(): void
    {
        VaultSecret::create([
            'key' => 'github_pat_ro',
            'value' => 'ghp_' . str_repeat('x', 36),
            'category' => 'github',
            'description' => 'test',
            'is_sensitive' => true,
        ]);

        Process::fake(fn () => Process::result("https://github.com/havun22-hvu/Proj.git
"));
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

        $this->artisan('actions:watch')
            ->expectsOutputToContain('Niet op te vragen')
            ->assertExitCode(1);

        // De cron stuurt stdout naar /dev/null, dus een regel op het scherm is
        // geen melding. Dit moet dezelfde weg nemen als een rode build.
        $alert = HealthAlert::where('key', 'actions-bewaking')->first();
        $this->assertNotNull($alert, 'een onbereikbare repo hoort een alert op te leveren');
        $this->assertSame('open', $alert->status);
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
