<?php

namespace Tests\Unit;

use App\Models\VaultConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_config_value_by_dot_notation(): void
    {
        $config = VaultConfig::create([
            'name' => 'test-config',
            'type' => 'api',
            'config' => ['database' => ['host' => 'localhost', 'port' => 3306]],
            'description' => 'Test',
        ]);

        $this->assertEquals('localhost', $config->get('database.host'));
        $this->assertEquals(3306, $config->get('database.port'));
    }

    public function test_get_returns_default_when_key_missing(): void
    {
        $config = VaultConfig::create([
            'name' => 'test-config',
            'type' => 'api',
            'config' => ['key' => 'value'],
            'description' => 'Test',
        ]);

        $this->assertEquals('fallback', $config->get('nonexistent', 'fallback'));
    }

    public function test_scope_type(): void
    {
        VaultConfig::create(['name' => 'a', 'type' => 'api', 'config' => [], 'description' => 'A']);
        VaultConfig::create(['name' => 'b', 'type' => 'database', 'config' => [], 'description' => 'B']);

        $this->assertCount(1, VaultConfig::type('api')->get());
        $this->assertCount(1, VaultConfig::type('database')->get());
    }
}
