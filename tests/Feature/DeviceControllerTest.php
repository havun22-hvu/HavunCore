<?php

namespace Tests\Feature;

use App\Models\AuthAccessLog;
use App\Models\AuthDevice;
use App\Models\AuthUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceControllerTest extends TestCase
{
    use RefreshDatabase;

    private AuthUser $user;
    private AuthDevice $device;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = AuthUser::create([
            'name' => 'Test User',
            'email' => 'test@havun.nl',
            'password_hash' => null,
            'is_admin' => false,
        ]);

        $this->token = AuthDevice::generateToken();

        $this->device = AuthDevice::create([
            'user_id' => $this->user->id,
            'device_name' => 'Chrome Windows',
            'device_hash' => hash('sha256', 'test-fingerprint'),
            'token' => $this->token,
            'expires_at' => now()->addDays(30),
            'last_used_at' => now(),
            'ip_address' => '127.0.0.1',
            'is_active' => true,
        ]);
    }

    // -- Index --

    public function test_index_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/auth/devices');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Authentication required');
    }

    public function test_index_returns_user_devices(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/auth/devices');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'devices')
            ->assertJsonPath('devices.0.name', 'Chrome Windows');
    }

    public function test_index_marks_current_device(): void
    {
        // Create a second device for the same user
        $otherToken = AuthDevice::generateToken();
        AuthDevice::create([
            'user_id' => $this->user->id,
            'device_name' => 'Safari iPhone',
            'device_hash' => hash('sha256', 'other-fingerprint'),
            'token' => $otherToken,
            'expires_at' => now()->addDays(30),
            'last_used_at' => now()->subHour(),
            'ip_address' => '192.168.1.1',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/auth/devices');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'devices');

        $devices = $response->json('devices');
        $current = collect($devices)->firstWhere('id', $this->device->id);
        $other = collect($devices)->firstWhere('name', 'Safari iPhone');

        $this->assertTrue($current['is_current']);
        $this->assertFalse($other['is_current']);
    }

    // -- Destroy --

    public function test_destroy_revokes_a_device(): void
    {
        // Create another device to revoke
        $otherDevice = AuthDevice::create([
            'user_id' => $this->user->id,
            'device_name' => 'Firefox Linux',
            'device_hash' => hash('sha256', 'firefox-fingerprint'),
            'token' => AuthDevice::generateToken(),
            'expires_at' => now()->addDays(30),
            'last_used_at' => now(),
            'ip_address' => '10.0.0.1',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->deleteJson("/api/auth/devices/{$otherDevice->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Device revoked successfully');

        $this->assertFalse($otherDevice->fresh()->is_active);
    }

    public function test_destroy_cannot_revoke_current_device(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->deleteJson("/api/auth/devices/{$this->device->id}");

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot revoke current device. Use logout instead.');

        $this->assertTrue($this->device->fresh()->is_active);
    }

    // -- Revoke All --

    public function test_revoke_all_revokes_all_except_current(): void
    {
        // Create two extra devices
        $device2 = AuthDevice::create([
            'user_id' => $this->user->id,
            'device_name' => 'Safari iPhone',
            'device_hash' => hash('sha256', 'safari-fp'),
            'token' => AuthDevice::generateToken(),
            'expires_at' => now()->addDays(30),
            'last_used_at' => now(),
            'ip_address' => '10.0.0.2',
            'is_active' => true,
        ]);

        $device3 = AuthDevice::create([
            'user_id' => $this->user->id,
            'device_name' => 'Edge Windows',
            'device_hash' => hash('sha256', 'edge-fp'),
            'token' => AuthDevice::generateToken(),
            'expires_at' => now()->addDays(30),
            'last_used_at' => now(),
            'ip_address' => '10.0.0.3',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/auth/devices/revoke-all');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('revoked_count', 2);

        // Current device should still be active
        $this->assertTrue($this->device->fresh()->is_active);
        // Other devices should be revoked
        $this->assertFalse($device2->fresh()->is_active);
        $this->assertFalse($device3->fresh()->is_active);
    }

    // -- Logs --

    public function test_logs_returns_access_logs(): void
    {
        AuthAccessLog::log('login', $this->user->id, 'Chrome Windows', '127.0.0.1');
        AuthAccessLog::log('logout', $this->user->id, 'Chrome Windows', '127.0.0.1');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/auth/logs');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'logs');
    }

    public function test_logs_respects_limit_parameter(): void
    {
        // Create 5 log entries
        for ($i = 0; $i < 5; $i++) {
            AuthAccessLog::log('login', $this->user->id, 'Device ' . $i, '127.0.0.1');
        }

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/auth/logs?limit=3');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'logs');
    }

    public function test_logs_without_auth_returns_401(): void
    {
        $response = $this->getJson('/api/auth/logs');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Authentication required');
    }
}
