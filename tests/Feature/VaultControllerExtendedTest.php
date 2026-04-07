<?php

namespace Tests\Feature;

use App\Models\VaultAccessLog;
use App\Models\VaultConfig;
use App\Models\VaultProject;
use App\Models\VaultSecret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultControllerExtendedTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private VaultProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = VaultProject::generateToken();
        $this->project = VaultProject::create([
            'project' => 'testproject',
            'secrets' => ['allowed_secret', 'another_secret'],
            'configs' => ['allowed_config', 'another_config'],
            'api_token' => $this->token,
            'is_active' => true,
        ]);
    }

    private function authHeader(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ========================================
    // Authentication: X-Vault-Token header
    // ========================================

    public function test_auth_via_x_vault_token_header(): void
    {
        VaultSecret::create(['key' => 'allowed_secret', 'value' => 'val', 'category' => 'api']);

        $response = $this->getJson('/api/vault/secrets', [
            'X-Vault-Token' => $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('secrets.allowed_secret', 'val');
    }

    public function test_invalid_token_returns_401(): void
    {
        $response = $this->getJson('/api/vault/secrets', [
            'Authorization' => 'Bearer invalid-token-12345',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Unauthorized');
    }

    // ========================================
    // GET /api/vault/secrets/{key}
    // ========================================

    public function test_get_secret_requires_authentication(): void
    {
        $response = $this->getJson('/api/vault/secrets/some_key');

        $response->assertStatus(401);
    }

    public function test_get_secret_returns_secret_with_valid_access(): void
    {
        VaultSecret::create([
            'key' => 'allowed_secret',
            'value' => 'my-secret-value',
            'category' => 'api',
            'description' => 'Test secret',
        ]);

        $response = $this->getJson('/api/vault/secrets/allowed_secret', $this->authHeader());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('key', 'allowed_secret')
            ->assertJsonPath('value', 'my-secret-value')
            ->assertJsonPath('category', 'api');
    }

    public function test_get_secret_returns_404_for_nonexistent_key(): void
    {
        // Project has access to 'allowed_secret' but it doesn't exist in vault_secrets
        $response = $this->getJson('/api/vault/secrets/allowed_secret', $this->authHeader());

        $response->assertStatus(404)
            ->assertJsonPath('error', 'Secret not found');
    }

    // ========================================
    // GET /api/vault/configs
    // ========================================

    public function test_get_configs_requires_authentication(): void
    {
        $response = $this->getJson('/api/vault/configs');

        $response->assertStatus(401);
    }

    public function test_get_configs_returns_project_configs(): void
    {
        VaultConfig::create([
            'name' => 'allowed_config',
            'type' => 'laravel',
            'config' => ['db_host' => 'localhost', 'db_port' => 3306],
        ]);
        VaultConfig::create([
            'name' => 'another_config',
            'type' => 'shared',
            'config' => ['feature_flags' => ['dark_mode' => true]],
        ]);
        // Config not in project's access list
        VaultConfig::create([
            'name' => 'forbidden_config',
            'type' => 'laravel',
            'config' => ['secret' => 'data'],
        ]);

        $response = $this->getJson('/api/vault/configs', $this->authHeader());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('project', 'testproject')
            ->assertJsonPath('configs.allowed_config.db_host', 'localhost')
            ->assertJsonPath('configs.another_config.feature_flags.dark_mode', true);

        // Should NOT include forbidden config
        $this->assertArrayNotHasKey('forbidden_config', $response->json('configs'));
    }

    // ========================================
    // GET /api/vault/configs/{name}
    // ========================================

    public function test_get_config_requires_authentication(): void
    {
        $response = $this->getJson('/api/vault/configs/some_config');

        $response->assertStatus(401);
    }

    public function test_get_config_returns_config_with_valid_access(): void
    {
        VaultConfig::create([
            'name' => 'allowed_config',
            'type' => 'laravel',
            'config' => ['key' => 'value', 'nested' => ['a' => 1]],
        ]);

        $response = $this->getJson('/api/vault/configs/allowed_config', $this->authHeader());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('name', 'allowed_config')
            ->assertJsonPath('type', 'laravel')
            ->assertJsonPath('config.key', 'value')
            ->assertJsonPath('config.nested.a', 1);
    }

    public function test_get_config_denies_access_to_unauthorized_config(): void
    {
        VaultConfig::create([
            'name' => 'forbidden_config',
            'type' => 'laravel',
            'config' => ['secret' => 'data'],
        ]);

        $response = $this->getJson('/api/vault/configs/forbidden_config', $this->authHeader());

        $response->assertStatus(403)
            ->assertJsonPath('error', 'Access denied to this config');
    }

    public function test_get_config_returns_404_for_nonexistent_config(): void
    {
        // Project has access but config doesn't exist
        $response = $this->getJson('/api/vault/configs/allowed_config', $this->authHeader());

        $response->assertStatus(404)
            ->assertJsonPath('error', 'Config not found');
    }

    // ========================================
    // GET /api/vault/bootstrap
    // ========================================

    public function test_bootstrap_requires_authentication(): void
    {
        $response = $this->getJson('/api/vault/bootstrap');

        $response->assertStatus(401);
    }

    // ========================================
    // Admin: GET /api/vault/admin/secrets
    // ========================================

    public function test_admin_list_secrets_returns_masked_values(): void
    {
        VaultSecret::create([
            'key' => 'api_key_1',
            'value' => 'super-secret-value-1234',
            'category' => 'api',
            'description' => 'An API key',
            'is_sensitive' => true,
        ]);
        VaultSecret::create([
            'key' => 'db_password',
            'value' => 'dbpass123',
            'category' => 'database',
            'description' => 'DB password',
            'is_sensitive' => true,
        ]);

        $response = $this->getJson('/api/vault/admin/secrets');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'secrets');

        $secrets = $response->json('secrets');

        // Check structure of first secret
        $first = collect($secrets)->firstWhere('key', 'api_key_1');
        $this->assertNotNull($first);
        $this->assertEquals('api', $first['category']);
        $this->assertEquals('An API key', $first['description']);
        $this->assertTrue($first['is_sensitive']);
        // Masked value should contain bullets and end with last 4 chars
        $this->assertStringContainsString('•', $first['masked_value']);
        $this->assertStringEndsWith('1234', $first['masked_value']);
        // Should NOT contain actual value
        $this->assertArrayNotHasKey('value', $first);
    }

    // ========================================
    // Admin: POST /api/vault/admin/secrets
    // ========================================

    public function test_admin_create_secret_success(): void
    {
        $response = $this->postJson('/api/vault/admin/secrets', [
            'key' => 'new_api_key',
            'value' => 'new-secret-value',
            'category' => 'api',
            'description' => 'A new secret',
            'is_sensitive' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Secret created')
            ->assertJsonPath('secret.key', 'new_api_key')
            ->assertJsonPath('secret.category', 'api');

        // Verify it was actually stored encrypted
        $secret = VaultSecret::where('key', 'new_api_key')->first();
        $this->assertNotNull($secret);
        $this->assertEquals('new-secret-value', $secret->getDecryptedValue());
    }

    public function test_admin_create_secret_validation_requires_key_and_value(): void
    {
        $response = $this->postJson('/api/vault/admin/secrets', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['key', 'value']);
    }

    public function test_admin_create_secret_validation_rejects_duplicate_key(): void
    {
        VaultSecret::create(['key' => 'existing_key', 'value' => 'val', 'category' => 'api']);

        $response = $this->postJson('/api/vault/admin/secrets', [
            'key' => 'existing_key',
            'value' => 'another-value',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['key']);
    }

    public function test_admin_create_secret_with_defaults(): void
    {
        $response = $this->postJson('/api/vault/admin/secrets', [
            'key' => 'minimal_secret',
            'value' => 'minimal-value',
        ]);

        $response->assertStatus(201);

        $secret = VaultSecret::where('key', 'minimal_secret')->first();
        $this->assertNotNull($secret);
        $this->assertTrue($secret->is_sensitive); // default
        $this->assertNull($secret->category);
        $this->assertNull($secret->description);
    }

    // ========================================
    // Admin: PUT /api/vault/admin/secrets/{key}
    // ========================================

    public function test_admin_update_secret_value(): void
    {
        VaultSecret::create(['key' => 'update_me', 'value' => 'old-value', 'category' => 'api']);

        $response = $this->putJson('/api/vault/admin/secrets/update_me', [
            'value' => 'new-value',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Secret updated');

        $secret = VaultSecret::where('key', 'update_me')->first();
        $this->assertEquals('new-value', $secret->getDecryptedValue());
    }

    public function test_admin_update_secret_category_and_description(): void
    {
        VaultSecret::create([
            'key' => 'update_meta',
            'value' => 'val',
            'category' => 'old-category',
            'description' => 'old description',
        ]);

        $response = $this->putJson('/api/vault/admin/secrets/update_meta', [
            'category' => 'new-category',
            'description' => 'new description',
        ]);

        $response->assertStatus(200);

        $secret = VaultSecret::where('key', 'update_meta')->first();
        $this->assertEquals('new-category', $secret->category);
        $this->assertEquals('new description', $secret->description);
        // Value should remain unchanged
        $this->assertEquals('val', $secret->getDecryptedValue());
    }

    public function test_admin_update_secret_returns_404_for_nonexistent(): void
    {
        $response = $this->putJson('/api/vault/admin/secrets/nonexistent', [
            'value' => 'new-value',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('error', 'Secret not found');
    }

    // ========================================
    // Admin: DELETE /api/vault/admin/secrets/{key}
    // ========================================

    public function test_admin_delete_secret_success(): void
    {
        VaultSecret::create(['key' => 'delete_me', 'value' => 'val', 'category' => 'api']);

        $response = $this->deleteJson('/api/vault/admin/secrets/delete_me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Secret deleted');

        $this->assertNull(VaultSecret::where('key', 'delete_me')->first());
    }

    public function test_admin_delete_secret_returns_404_for_nonexistent(): void
    {
        $response = $this->deleteJson('/api/vault/admin/secrets/nonexistent');

        $response->assertStatus(404)
            ->assertJsonPath('error', 'Secret not found');
    }

    // ========================================
    // Admin: GET /api/vault/admin/projects
    // ========================================

    public function test_admin_list_projects(): void
    {
        // setUp already creates one project
        VaultProject::create([
            'project' => 'second_project',
            'secrets' => ['s1'],
            'configs' => ['c1'],
            'api_token' => VaultProject::generateToken(),
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/vault/admin/projects');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'projects');

        $projects = $response->json('projects');
        $second = collect($projects)->firstWhere('project', 'second_project');
        $this->assertNotNull($second);
        $this->assertFalse($second['is_active']);
        $this->assertEquals(['s1'], $second['secrets']);
        $this->assertEquals(['c1'], $second['configs']);
    }

    // ========================================
    // Admin: POST /api/vault/admin/projects
    // ========================================

    public function test_admin_create_project_success(): void
    {
        $response = $this->postJson('/api/vault/admin/projects', [
            'project' => 'new-project',
            'secrets' => ['secret1', 'secret2'],
            'configs' => ['config1'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Project created')
            ->assertJsonPath('project', 'new-project');

        // Token should be returned and start with hvn_
        $this->assertStringStartsWith('hvn_', $response->json('api_token'));

        // Verify in DB
        $project = VaultProject::where('project', 'new-project')->first();
        $this->assertNotNull($project);
        $this->assertTrue($project->is_active);
        $this->assertEquals(['secret1', 'secret2'], $project->secrets);
        $this->assertEquals(['config1'], $project->configs);
    }

    public function test_admin_create_project_validation_requires_name(): void
    {
        $response = $this->postJson('/api/vault/admin/projects', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project']);
    }

    public function test_admin_create_project_validation_rejects_duplicate_name(): void
    {
        $response = $this->postJson('/api/vault/admin/projects', [
            'project' => 'testproject', // already exists from setUp
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project']);
    }

    public function test_admin_create_project_with_defaults(): void
    {
        $response = $this->postJson('/api/vault/admin/projects', [
            'project' => 'minimal-project',
        ]);

        $response->assertStatus(201);

        $project = VaultProject::where('project', 'minimal-project')->first();
        $this->assertEquals([], $project->secrets);
        $this->assertEquals([], $project->configs);
    }

    // ========================================
    // Admin: PUT /api/vault/admin/projects/{project}
    // ========================================

    public function test_admin_update_project_permissions(): void
    {
        $response = $this->putJson('/api/vault/admin/projects/testproject', [
            'secrets' => ['new_secret_1', 'new_secret_2'],
            'configs' => ['new_config_1'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Project updated');

        $project = $this->project->fresh();
        $this->assertEquals(['new_secret_1', 'new_secret_2'], $project->secrets);
        $this->assertEquals(['new_config_1'], $project->configs);
    }

    public function test_admin_update_project_deactivate(): void
    {
        $response = $this->putJson('/api/vault/admin/projects/testproject', [
            'is_active' => false,
        ]);

        $response->assertStatus(200);

        $this->assertFalse($this->project->fresh()->is_active);
    }

    public function test_admin_update_project_returns_404_for_nonexistent(): void
    {
        $response = $this->putJson('/api/vault/admin/projects/nonexistent', [
            'secrets' => ['s1'],
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('error', 'Project not found');
    }

    // ========================================
    // Admin: POST /api/vault/admin/projects/{project}/regenerate-token
    // ========================================

    public function test_admin_regenerate_token_success(): void
    {
        $response = $this->postJson('/api/vault/admin/projects/testproject/regenerate-token');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Token regenerated');

        $newToken = $response->json('api_token');
        $this->assertStringStartsWith('hvn_', $newToken);
        $this->assertNotEquals($this->token, $newToken);

        // Old token should no longer work
        $oldResponse = $this->getJson('/api/vault/secrets', [
            'Authorization' => "Bearer {$this->token}",
        ]);
        $oldResponse->assertStatus(401);

        // New token should work
        $newResponse = $this->getJson('/api/vault/secrets', [
            'Authorization' => "Bearer {$newToken}",
        ]);
        $newResponse->assertStatus(200);
    }

    public function test_admin_regenerate_token_returns_404_for_nonexistent(): void
    {
        $response = $this->postJson('/api/vault/admin/projects/nonexistent/regenerate-token');

        $response->assertStatus(404)
            ->assertJsonPath('error', 'Project not found');
    }

    // ========================================
    // Admin: GET /api/vault/admin/logs
    // ========================================

    public function test_admin_get_logs_returns_recent_logs(): void
    {
        // Generate some logs via API calls
        VaultSecret::create(['key' => 'allowed_secret', 'value' => 'val', 'category' => 'api']);
        $this->getJson('/api/vault/secrets', $this->authHeader());

        $response = $this->getJson('/api/vault/admin/logs');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $logs = $response->json('logs');
        $this->assertNotEmpty($logs);
        $this->assertEquals('testproject', $logs[0]['project']);
        $this->assertEquals('read', $logs[0]['action']);
    }

    public function test_admin_get_logs_filters_by_project(): void
    {
        VaultAccessLog::log('project_a', 'read', 'secret', 'key1', '127.0.0.1');
        VaultAccessLog::log('project_b', 'read', 'secret', 'key2', '127.0.0.1');
        VaultAccessLog::log('project_a', 'read', 'config', 'cfg1', '127.0.0.1');

        $response = $this->getJson('/api/vault/admin/logs?project=project_a');

        $response->assertStatus(200);

        $logs = $response->json('logs');
        $this->assertCount(2, $logs);
        foreach ($logs as $log) {
            $this->assertEquals('project_a', $log['project']);
        }
    }

    public function test_admin_get_logs_filters_by_days(): void
    {
        // Recent log
        VaultAccessLog::log('proj', 'read', 'secret', 'key1', '127.0.0.1');

        // Old log (manually insert with old date)
        VaultAccessLog::create([
            'project' => 'proj',
            'action' => 'read',
            'resource_type' => 'secret',
            'resource_key' => 'old_key',
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDays(30),
        ]);

        $response = $this->getJson('/api/vault/admin/logs?days=7');

        $response->assertStatus(200);

        $logs = $response->json('logs');
        // Only the recent log should be returned
        $this->assertCount(1, $logs);
        $this->assertEquals('key1', $logs[0]['resource_key']);
    }

    // ========================================
    // Access logging: verify logs are created
    // ========================================

    public function test_get_secrets_creates_access_log(): void
    {
        VaultSecret::create(['key' => 'allowed_secret', 'value' => 'val', 'category' => 'api']);

        $this->getJson('/api/vault/secrets', $this->authHeader());

        $log = VaultAccessLog::where('project', 'testproject')
            ->where('resource_type', 'secret')
            ->where('resource_key', '*')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('read', $log->action);
    }

    public function test_get_single_secret_creates_access_log(): void
    {
        VaultSecret::create(['key' => 'allowed_secret', 'value' => 'val', 'category' => 'api']);

        $this->getJson('/api/vault/secrets/allowed_secret', $this->authHeader());

        $log = VaultAccessLog::where('resource_key', 'allowed_secret')
            ->where('resource_type', 'secret')
            ->first();

        $this->assertNotNull($log);
    }

    public function test_get_configs_creates_access_log(): void
    {
        $this->getJson('/api/vault/configs', $this->authHeader());

        $log = VaultAccessLog::where('project', 'testproject')
            ->where('resource_type', 'config')
            ->where('resource_key', '*')
            ->first();

        $this->assertNotNull($log);
    }

    public function test_bootstrap_creates_access_log(): void
    {
        $this->getJson('/api/vault/bootstrap', $this->authHeader());

        $log = VaultAccessLog::where('project', 'testproject')
            ->where('resource_type', 'bootstrap')
            ->first();

        $this->assertNotNull($log);
    }

    // ========================================
    // touchAccess: verify last_accessed_at
    // ========================================

    public function test_get_secrets_updates_last_accessed_at(): void
    {
        $this->assertNull($this->project->last_accessed_at);

        $this->getJson('/api/vault/secrets', $this->authHeader());

        $this->assertNotNull($this->project->fresh()->last_accessed_at);
    }

    public function test_get_config_updates_last_accessed_at(): void
    {
        VaultConfig::create([
            'name' => 'allowed_config',
            'type' => 'laravel',
            'config' => ['key' => 'value'],
        ]);

        $this->assertNull($this->project->last_accessed_at);

        $this->getJson('/api/vault/configs/allowed_config', $this->authHeader());

        $this->assertNotNull($this->project->fresh()->last_accessed_at);
    }
}
