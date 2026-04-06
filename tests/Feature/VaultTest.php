<?php

namespace Tests\Feature;

use App\Models\VaultConfig;
use App\Models\VaultProject;
use App\Models\VaultSecret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultTest extends TestCase
{
    use RefreshDatabase;

    // -- VaultSecret Model --

    public function test_secret_value_is_encrypted_on_save(): void
    {
        $secret = VaultSecret::create([
            'key' => 'test_api_key',
            'value' => 'my-secret-value-123',
            'category' => 'api',
        ]);

        // Raw DB value should NOT equal the original
        $raw = $secret->getAttributes()['value'];
        $this->assertNotEquals('my-secret-value-123', $raw);

        // Decrypted value should match
        $this->assertEquals('my-secret-value-123', $secret->getDecryptedValue());
    }

    public function test_secret_masked_value_hides_most_characters(): void
    {
        $secret = VaultSecret::create([
            'key' => 'test_key',
            'value' => 'abcdefghij',
            'category' => 'api',
        ]);

        $masked = $secret->getMaskedValue();

        // Should end with last 4 chars
        $this->assertStringEndsWith('ghij', $masked);
        // Should contain bullets
        $this->assertStringContainsString('•', $masked);
        // Length should match original (use mb_strlen since bullet is multi-byte)
        $this->assertEquals(mb_strlen('abcdefghij'), mb_strlen($masked));
    }

    public function test_secret_masked_value_fully_hides_short_values(): void
    {
        $secret = VaultSecret::create([
            'key' => 'short_key',
            'value' => 'abc',
            'category' => 'api',
        ]);

        $masked = $secret->getMaskedValue();
        $this->assertEquals('•••', $masked);
    }

    public function test_secret_value_is_hidden_in_json(): void
    {
        $secret = VaultSecret::create([
            'key' => 'hidden_key',
            'value' => 'should-not-appear',
            'category' => 'api',
        ]);

        $json = $secret->toArray();
        $this->assertArrayNotHasKey('value', $json);
    }

    public function test_secret_category_scope(): void
    {
        VaultSecret::create(['key' => 'api_key_1', 'value' => 'v1', 'category' => 'api']);
        VaultSecret::create(['key' => 'api_key_2', 'value' => 'v2', 'category' => 'api']);
        VaultSecret::create(['key' => 'db_pass', 'value' => 'v3', 'category' => 'database']);

        $apiSecrets = VaultSecret::category('api')->get();
        $this->assertCount(2, $apiSecrets);
    }

    // -- VaultConfig Model --

    public function test_config_stores_and_retrieves_json(): void
    {
        $configData = ['host' => 'localhost', 'port' => 3306, 'charset' => 'utf8mb4'];

        $config = VaultConfig::create([
            'name' => 'mysql_defaults',
            'type' => 'shared',
            'config' => $configData,
        ]);

        $this->assertEquals($configData, $config->fresh()->config);
    }

    public function test_config_get_by_dot_notation(): void
    {
        $config = VaultConfig::create([
            'name' => 'nested_config',
            'type' => 'laravel',
            'config' => ['database' => ['host' => '127.0.0.1', 'port' => 5432]],
        ]);

        $this->assertEquals('127.0.0.1', $config->get('database.host'));
        $this->assertEquals(5432, $config->get('database.port'));
        $this->assertEquals('default', $config->get('database.missing', 'default'));
    }

    public function test_config_merge_with_secrets(): void
    {
        VaultSecret::create(['key' => 'db_password', 'value' => 'secret123', 'category' => 'database']);

        $config = VaultConfig::create([
            'name' => 'db_config',
            'type' => 'laravel',
            'config' => ['host' => 'localhost', 'db_password' => 'placeholder'],
        ]);

        $merged = $config->mergeWithSecrets(['db_password']);

        $this->assertEquals('secret123', $merged['db_password']);
        $this->assertEquals('localhost', $merged['host']);
    }

    // -- VaultProject Model --

    public function test_project_token_generation(): void
    {
        $token = VaultProject::generateToken();

        $this->assertStringStartsWith('hvn_', $token);
        $this->assertEquals(52, strlen($token)); // hvn_ + 48 random
    }

    public function test_project_find_by_token(): void
    {
        $token = VaultProject::generateToken();

        $project = VaultProject::create([
            'project' => 'testproject',
            'secrets' => ['key1', 'key2'],
            'configs' => ['config1'],
            'api_token' => $token,
            'is_active' => true,
        ]);

        $found = VaultProject::findByToken($token);
        $this->assertNotNull($found);
        $this->assertEquals('testproject', $found->project);
    }

    public function test_project_find_by_token_ignores_inactive(): void
    {
        $token = VaultProject::generateToken();

        VaultProject::create([
            'project' => 'inactive',
            'secrets' => [],
            'configs' => [],
            'api_token' => $token,
            'is_active' => false,
        ]);

        $this->assertNull(VaultProject::findByToken($token));
    }

    public function test_project_secret_access_check(): void
    {
        $project = VaultProject::create([
            'project' => 'testproj',
            'secrets' => ['allowed_key', 'another_key'],
            'configs' => [],
            'api_token' => VaultProject::generateToken(),
            'is_active' => true,
        ]);

        $this->assertTrue($project->hasSecretAccess('allowed_key'));
        $this->assertTrue($project->hasSecretAccess('another_key'));
        $this->assertFalse($project->hasSecretAccess('forbidden_key'));
    }

    public function test_project_config_access_check(): void
    {
        $project = VaultProject::create([
            'project' => 'testproj',
            'secrets' => [],
            'configs' => ['mysql_config'],
            'api_token' => VaultProject::generateToken(),
            'is_active' => true,
        ]);

        $this->assertTrue($project->hasConfigAccess('mysql_config'));
        $this->assertFalse($project->hasConfigAccess('redis_config'));
    }

    public function test_project_get_secrets_returns_decrypted_values(): void
    {
        VaultSecret::create(['key' => 'api_key', 'value' => 'secret-api-key', 'category' => 'api']);
        VaultSecret::create(['key' => 'db_pass', 'value' => 'secret-db-pass', 'category' => 'database']);

        $project = VaultProject::create([
            'project' => 'myproject',
            'secrets' => ['api_key', 'db_pass'],
            'configs' => [],
            'api_token' => VaultProject::generateToken(),
            'is_active' => true,
        ]);

        $secrets = $project->getSecrets();

        $this->assertEquals('secret-api-key', $secrets['api_key']);
        $this->assertEquals('secret-db-pass', $secrets['db_pass']);
    }

    public function test_project_get_secrets_skips_missing_keys(): void
    {
        VaultSecret::create(['key' => 'existing_key', 'value' => 'val', 'category' => 'api']);

        $project = VaultProject::create([
            'project' => 'myproject',
            'secrets' => ['existing_key', 'nonexistent_key'],
            'configs' => [],
            'api_token' => VaultProject::generateToken(),
            'is_active' => true,
        ]);

        $secrets = $project->getSecrets();

        $this->assertCount(1, $secrets);
        $this->assertArrayHasKey('existing_key', $secrets);
        $this->assertArrayNotHasKey('nonexistent_key', $secrets);
    }

    // -- Vault API Endpoints --

    public function test_vault_secrets_requires_authentication(): void
    {
        $response = $this->getJson('/api/vault/secrets');

        $response->assertStatus(401);
    }

    public function test_vault_secrets_returns_secrets_with_valid_token(): void
    {
        VaultSecret::create(['key' => 'my_secret', 'value' => 'secret_value', 'category' => 'api']);

        $token = VaultProject::generateToken();
        VaultProject::create([
            'project' => 'testproj',
            'secrets' => ['my_secret'],
            'configs' => [],
            'api_token' => $token,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/vault/secrets', [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('secrets.my_secret', 'secret_value');
    }

    public function test_vault_specific_secret_denies_access_to_unauthorized_key(): void
    {
        VaultSecret::create(['key' => 'restricted', 'value' => 'val', 'category' => 'api']);

        $token = VaultProject::generateToken();
        VaultProject::create([
            'project' => 'testproj',
            'secrets' => ['other_key'],
            'configs' => [],
            'api_token' => $token,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/vault/secrets/restricted', [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(403);
    }

    public function test_vault_bootstrap_returns_all_project_data(): void
    {
        VaultSecret::create(['key' => 'sk', 'value' => 'sv', 'category' => 'api']);
        VaultConfig::create([
            'name' => 'cfg',
            'type' => 'laravel',
            'config' => ['key' => 'value'],
        ]);

        $token = VaultProject::generateToken();
        VaultProject::create([
            'project' => 'testproj',
            'secrets' => ['sk'],
            'configs' => ['cfg'],
            'api_token' => $token,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/vault/bootstrap', [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('secrets.sk', 'sv')
            ->assertJsonPath('configs.cfg.key', 'value')
            ->assertJsonStructure(['timestamp']);
    }
}
