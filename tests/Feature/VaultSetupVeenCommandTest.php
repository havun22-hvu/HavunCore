<?php

namespace Tests\Feature;

use App\Models\VaultProject;
use App\Models\VaultSecret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultSetupVeenCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_secrets_encrypted_and_grants_project_access(): void
    {
        $this->artisan('vault:setup-veen')
            ->expectsQuestion('Secret key (leeg = stoppen)', 'veen_mollie_api')
            ->expectsQuestion('Omschrijving (zichtbaar, optioneel)', 'Mollie live key')
            ->expectsQuestion('Waarde (verborgen)', 'mollie-secret-xyz')
            ->expectsQuestion('Secret key (leeg = stoppen)', 'veen_transip')
            ->expectsQuestion('Omschrijving (zichtbaar, optioneel)', '')
            ->expectsQuestion('Waarde (verborgen)', 'transip-pw')
            ->expectsQuestion('Secret key (leeg = stoppen)', '')
            ->assertExitCode(0);

        $mollie = VaultSecret::where('key', 'veen_mollie_api')->first();
        $this->assertNotNull($mollie);
        $this->assertSame('veen', $mollie->category);
        $this->assertTrue((bool) $mollie->is_sensitive);
        // Stored value is encrypted, not plaintext, but decrypts back
        $this->assertNotSame('mollie-secret-xyz', $mollie->getAttributes()['value']);
        $this->assertSame('mollie-secret-xyz', $mollie->getDecryptedValue());

        $project = VaultProject::where('project', 'veen-ledenadministratie')->first();
        $this->assertNotNull($project);
        $this->assertTrue($project->is_active);
        $this->assertEqualsCanonicalizing(['veen_mollie_api', 'veen_transip'], $project->secrets);
        $this->assertStringStartsWith('hvn_', $project->api_token);
    }

    public function test_empty_value_is_skipped(): void
    {
        $this->artisan('vault:setup-veen')
            ->expectsQuestion('Secret key (leeg = stoppen)', 'veen_email')
            ->expectsQuestion('Omschrijving (zichtbaar, optioneel)', '')
            ->expectsQuestion('Waarde (verborgen)', '')
            ->expectsQuestion('Secret key (leeg = stoppen)', '')
            ->assertExitCode(0);

        $this->assertNull(VaultSecret::where('key', 'veen_email')->first());
        $this->assertNull(VaultProject::where('project', 'veen-ledenadministratie')->first());
    }

    public function test_existing_secret_is_updated_after_confirmation(): void
    {
        VaultSecret::create([
            'key' => 'veen_mollie_api',
            'value' => 'old-value',
            'category' => 'veen',
            'is_sensitive' => true,
        ]);

        $this->artisan('vault:setup-veen')
            ->expectsQuestion('Secret key (leeg = stoppen)', 'veen_mollie_api')
            ->expectsConfirmation(
                "Secret 'veen_mollie_api' bestaat al (mogelijk van een ander project). Overschrijven?",
                'yes'
            )
            ->expectsQuestion('Omschrijving (zichtbaar, optioneel)', '')
            ->expectsQuestion('Waarde (verborgen)', 'new-value')
            ->expectsQuestion('Secret key (leeg = stoppen)', '')
            ->assertExitCode(0);

        $this->assertSame('new-value', VaultSecret::where('key', 'veen_mollie_api')->first()->getDecryptedValue());
    }
}
