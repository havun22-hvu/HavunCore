<?php

namespace Tests\Unit;

use App\Models\VaultProject;
use App\Models\VaultSecret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage voor VaultProject — token-generation, lookup, secret/config
 * access checks, last-accessed touch.
 */
class VaultProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_token_returns_hvn_prefixed_string(): void
    {
        $token = VaultProject::generateToken();

        $this->assertStringStartsWith('hvn_', $token);
        $this->assertSame(52, strlen($token), 'hvn_ + 48-char random.');
    }

    public function test_find_by_token_returns_active_project(): void
    {
        $project = VaultProject::create([
            'project' => 'havuncore',
            'api_token' => 'hvn_test_token',
            'is_active' => true,
            'secrets' => [],
            'configs' => [],
        ]);

        $found = VaultProject::findByToken('hvn_test_token');
        $this->assertNotNull($found);
        $this->assertSame($project->id, $found->id);
    }

    public function test_find_by_token_returns_null_for_inactive_project(): void
    {
        VaultProject::create([
            'project' => 'inactive',
            'api_token' => 'inactive_token',
            'is_active' => false,
            'secrets' => [],
            'configs' => [],
        ]);

        $this->assertNull(VaultProject::findByToken('inactive_token'));
    }

    public function test_has_secret_access_checks_array(): void
    {
        $project = VaultProject::create([
            'project' => 'p',
            'api_token' => 't',
            'is_active' => true,
            'secrets' => ['STRIPE_KEY', 'GROQ_KEY'],
            'configs' => [],
        ]);

        $this->assertTrue($project->hasSecretAccess('STRIPE_KEY'));
        $this->assertTrue($project->hasSecretAccess('GROQ_KEY'));
        $this->assertFalse($project->hasSecretAccess('AWS_SECRET'));
    }

    public function test_has_config_access_checks_array(): void
    {
        $project = VaultProject::create([
            'project' => 'p',
            'api_token' => 't',
            'is_active' => true,
            'secrets' => [],
            'configs' => ['db_main', 'redis'],
        ]);

        $this->assertTrue($project->hasConfigAccess('db_main'));
        $this->assertFalse($project->hasConfigAccess('memcached'));
    }

    public function test_get_secrets_returns_decrypted_values_for_allowed_keys(): void
    {
        VaultSecret::create(['key' => 'STRIPE_KEY', 'value' => 'sk_live_xyz']);
        VaultSecret::create(['key' => 'OTHER_KEY', 'value' => 'other_secret']);

        $project = VaultProject::create([
            'project' => 'p',
            'api_token' => 't',
            'is_active' => true,
            'secrets' => ['STRIPE_KEY'], // only one access
            'configs' => [],
        ]);

        $secrets = $project->getSecrets();

        $this->assertSame(['STRIPE_KEY' => 'sk_live_xyz'], $secrets,
            'Returns only the allowed key, decrypted.');
    }

    public function test_get_secrets_skips_unknown_keys_silently(): void
    {
        $project = VaultProject::create([
            'project' => 'p',
            'api_token' => 't',
            'is_active' => true,
            'secrets' => ['MISSING_KEY'],
            'configs' => [],
        ]);

        $this->assertSame([], $project->getSecrets());
    }

    public function test_api_token_is_hidden_from_array(): void
    {
        $project = VaultProject::create([
            'project' => 'p',
            'api_token' => 'super_secret_token',
            'is_active' => true,
            'secrets' => [],
            'configs' => [],
        ]);

        $this->assertArrayNotHasKey('api_token', $project->toArray());
    }

    public function test_touch_access_updates_last_accessed_at(): void
    {
        $project = VaultProject::create([
            'project' => 'p',
            'api_token' => 't',
            'is_active' => true,
            'secrets' => [],
            'configs' => [],
            'last_accessed_at' => now()->subDay(),
        ]);

        $project->touchAccess();

        $this->assertGreaterThan(now()->subMinute(), $project->fresh()->last_accessed_at);
    }
}
