<?php

namespace App\Console\Commands;

use App\Models\VaultProject;
use App\Models\VaultSecret;
use Illuminate\Console\Command;

class VaultSetupVeenCommand extends Command
{
    protected $signature = 'vault:setup-veen
        {--project=veen-ledenadministratie : Vault project name}
        {--rotate-token : Force generation of a fresh project token}';

    protected $description = 'Interactive, idempotent setup for the veen-ledenadministratie Vault entry. Prompts per secret for a visible key + description and a hidden value; never echoes the value. Creates/updates secrets, grants the project access and prints the Bearer token.';

    public function handle(): int
    {
        $projectName = (string) $this->option('project');

        $this->info("Vault-secrets toevoegen aan project '{$projectName}'.");
        $this->line('Key-naam en omschrijving zijn zichtbaar; de waarde voer je verborgen in.');
        $this->line('Laat de key-naam leeg om te stoppen.');
        $this->newLine();

        $addedKeys = [];

        while (true) {
            $key = trim((string) $this->ask('Secret key (leeg = stoppen)'));
            if ($key === '') {
                break;
            }

            if (! preg_match('/^[a-z0-9_]+$/', $key)) {
                $this->error('Alleen kleine letters, cijfers en underscores toegestaan.');
                continue;
            }

            if (! str_starts_with($key, 'veen_')
                && ! $this->confirm("Key '{$key}' begint niet met 'veen_'. Toch gebruiken?", false)) {
                continue;
            }

            $existing = VaultSecret::where('key', $key)->first();
            if ($existing
                && ! $this->confirm("Secret '{$key}' bestaat al (mogelijk van een ander project). Overschrijven?", false)) {
                continue;
            }

            $description = trim((string) $this->ask('Omschrijving (zichtbaar, optioneel)', ''));

            $value = (string) $this->secret('Waarde (verborgen)');
            if (trim($value) === '') {
                $this->warn("Lege waarde — '{$key}' overgeslagen.");
                continue;
            }

            if ($existing) {
                $existing->value = $value;
                if ($description !== '') {
                    $existing->description = $description;
                }
                $existing->category = 'veen';
                $existing->is_sensitive = true;
                $existing->save();
                $this->info("✓ Bijgewerkt: {$key}");
            } else {
                VaultSecret::create([
                    'key' => $key,
                    'value' => $value,
                    'category' => 'veen',
                    'description' => $description !== '' ? $description : null,
                    'is_sensitive' => true,
                ]);
                $this->info("✓ Aangemaakt: {$key}");
            }

            $addedKeys[] = $key;
            $this->newLine();
        }

        if ($addedKeys === []) {
            $this->warn('Geen secrets toegevoegd — project ongewijzigd.');

            return 0;
        }

        $project = VaultProject::where('project', $projectName)->first();
        if ($project) {
            $secrets = $project->secrets ?? [];
            foreach ($addedKeys as $k) {
                if (! in_array($k, $secrets, true)) {
                    $secrets[] = $k;
                }
            }
            $project->secrets = $secrets;
            if ($this->option('rotate-token')) {
                $project->api_token = VaultProject::generateToken();
                $this->warn('Project-token geroteerd — oude token is ongeldig.');
            }
            $project->is_active = true;
            $project->save();
            $this->info("✓ Project '{$projectName}' bijgewerkt.");
        } else {
            $project = VaultProject::create([
                'project' => $projectName,
                'secrets' => $addedKeys,
                'configs' => [],
                'api_token' => VaultProject::generateToken(),
                'is_active' => true,
            ]);
            $this->info("✓ Project '{$projectName}' aangemaakt.");
        }

        $this->newLine();
        $this->line('───────────────────────────────────────────────');
        $this->line(" Secrets in project '{$projectName}' (waarde gemaskeerd):");
        foreach ($project->secrets as $k) {
            $secret = VaultSecret::where('key', $k)->first();
            $masked = $secret ? $secret->getMaskedValue() : '(ontbreekt)';
            $this->line(sprintf('   %-26s %s', $k, $masked));
        }
        $this->newLine();
        $this->line(' Project-token (Bearer — als VAULT_PROJECT_TOKEN):');
        $this->line('   ' . $project->api_token);
        $this->line('───────────────────────────────────────────────');

        return 0;
    }
}
