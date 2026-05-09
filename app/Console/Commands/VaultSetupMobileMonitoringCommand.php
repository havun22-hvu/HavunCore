<?php

namespace App\Console\Commands;

use App\Models\VaultProject;
use App\Models\VaultSecret;
use Illuminate\Console\Command;

class VaultSetupMobileMonitoringCommand extends Command
{
    protected $signature = 'vault:setup-mobile-monitoring
        {--project=havuncore-webapp : Vault project name}
        {--secret=github_pat_ro : Secret key}
        {--rotate-token : Force generation of a fresh project token}';

    protected $description = 'Idempotent setup for the PWA mobile-monitoring Vault entry. Prompts for the GitHub PAT (hidden input), creates/updates the secret, ensures the webapp project has access, and prints the Bearer token for VAULT_PROJECT_TOKEN.';

    public function handle(): int
    {
        $projectName = (string) $this->option('project');
        $secretKey = (string) $this->option('secret');
        $rotate = (bool) $this->option('rotate-token');

        $pat = $this->secret('GitHub Personal Access Token (read-only, scopes: public_repo, repo:status)');
        if (! is_string($pat) || trim($pat) === '') {
            $this->error('PAT was empty — aborting.');

            return 1;
        }
        if (! str_starts_with($pat, 'ghp_') && ! str_starts_with($pat, 'github_pat_')) {
            if (! $this->confirm('PAT does not start with ghp_ or github_pat_. Continue anyway?', false)) {
                return 1;
            }
        }

        $secret = VaultSecret::where('key', $secretKey)->first();
        if ($secret) {
            $secret->value = $pat;
            $secret->save();
            $this->info("✓ Updated existing secret '{$secretKey}'");
        } else {
            VaultSecret::create([
                'key' => $secretKey,
                'value' => $pat,
                'category' => 'github',
                'description' => 'Read-only GitHub PAT used by havuncore-webapp PWA mobile-project monitoring',
                'is_sensitive' => true,
            ]);
            $this->info("✓ Created secret '{$secretKey}'");
        }

        $project = VaultProject::where('project', $projectName)->first();
        if ($project) {
            $secrets = $project->secrets ?? [];
            if (! in_array($secretKey, $secrets, true)) {
                $secrets[] = $secretKey;
                $project->secrets = $secrets;
            }
            if ($rotate) {
                $project->api_token = VaultProject::generateToken();
                $this->warn('Project token was rotated — old token invalid.');
            }
            $project->is_active = true;
            $project->save();
            $this->info("✓ Updated existing project '{$projectName}'");
        } else {
            $project = VaultProject::create([
                'project' => $projectName,
                'secrets' => [$secretKey],
                'configs' => [],
                'api_token' => VaultProject::generateToken(),
                'is_active' => true,
            ]);
            $this->info("✓ Created project '{$projectName}'");
        }

        $this->newLine();
        $this->line('───────────────────────────────────────────────────────────');
        $this->line(' Vault project token (use as VAULT_PROJECT_TOKEN in PWA env):');
        $this->line('');
        $this->line('   ' . $project->api_token);
        $this->line('');
        $this->line(' Add to /var/www/havuncore/webapp/backend/.env.production:');
        $this->line('   VAULT_PROJECT_TOKEN=' . $project->api_token);
        $this->line('   HAVUNCORE_API_URL=https://havuncore.havun.nl');
        $this->line('───────────────────────────────────────────────────────────');
        $this->newLine();
        $this->comment('Reminder: rotate the PAT every ~90 days; rerun this command to update.');

        return 0;
    }
}
