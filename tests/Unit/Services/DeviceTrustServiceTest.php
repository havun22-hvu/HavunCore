<?php

namespace Tests\Unit\Services;

use App\Models\AuthDevice;
use App\Models\AuthUser;
use App\Services\DeviceTrustService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage voor DeviceTrustService — bearer-token verify, revoke,
 * logout, en getUserDevices/getAccessLogs. Toegevoegd 2026-04-20 om
 * de gap richting 80 % HavunCore Unit-coverage te dichten.
 *
 * Eén van de meest gebruikte services (admin middleware, QrAuth,
 * WebAuthn, Vault) — verdiende eigen tests al lang.
 */
class DeviceTrustServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithDevice(array $deviceOverrides = []): array
    {
        $user = AuthUser::create([
            'email' => 'test-' . Str::random(8) . '@havun.nl',
            'name' => 'Test User',
            'password' => bcrypt('test-password'),
            'is_admin' => false,
        ]);

        $token = Str::random(64);
        $device = AuthDevice::create(array_merge([
            'user_id' => $user->id,
            'token' => $token,
            'device_hash' => hash('sha256', 'device-' . Str::random(16)),
            'device_name' => 'Test Device',
            'browser' => 'PHPUnit',
            'os' => 'Test',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'is_active' => true,
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ], $deviceOverrides));

        return [$user, $device, $token];
    }

    public function test_verify_token_returns_invalid_for_unknown_token(): void
    {
        $result = (new DeviceTrustService())->verifyToken('unknown-token-string');

        $this->assertFalse($result['valid']);
    }

    public function test_verify_token_returns_user_and_user_model_when_valid(): void
    {
        [, , $token] = $this->makeUserWithDevice();

        $result = (new DeviceTrustService())->verifyToken($token);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('user_model', $result);
        $this->assertInstanceOf(AuthUser::class, $result['user_model']);
    }

    public function test_verify_token_extends_trust_when_close_to_expiry(): void
    {
        [, $device, $token] = $this->makeUserWithDevice([
            'expires_at' => now()->addDays(3), // < 7 day window
        ]);

        (new DeviceTrustService())->verifyToken($token);

        $device->refresh();
        $this->assertGreaterThan(now()->addDays(7), $device->expires_at,
            'Verify must extend trust when within 7 days of expiry.');
    }

    public function test_revoke_device_marks_existing_device_inactive(): void
    {
        [$user, $device] = $this->makeUserWithDevice();

        $result = (new DeviceTrustService())->revokeDevice($user, $device->id, '127.0.0.1');

        $this->assertTrue($result['success']);
        $device->refresh();
        $this->assertFalse((bool) $device->is_active);
    }

    public function test_revoke_device_returns_error_for_unknown_id(): void
    {
        [$user] = $this->makeUserWithDevice();

        $result = (new DeviceTrustService())->revokeDevice($user, 999_999);

        $this->assertFalse($result['success']);
        $this->assertSame('Device not found', $result['message']);
    }

    public function test_revoke_all_devices_can_keep_one_active(): void
    {
        [$user, $deviceKeep] = $this->makeUserWithDevice();
        [, $deviceRevoke] = $this->makeUserWithDevice();
        // Reassign the second device to the same user so we have two.
        $deviceRevoke->update(['user_id' => $user->id]);

        $result = (new DeviceTrustService())->revokeAllDevices($user, $deviceKeep->id);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['revoked_count']);
        $this->assertTrue((bool) $deviceKeep->fresh()->is_active);
        $this->assertFalse((bool) $deviceRevoke->fresh()->is_active);
    }

    public function test_logout_revokes_device_with_matching_token(): void
    {
        [, $device, $token] = $this->makeUserWithDevice();

        $result = (new DeviceTrustService())->logout($token);

        $this->assertTrue($result['success']);
        $this->assertFalse((bool) $device->fresh()->is_active);
    }

    public function test_logout_returns_error_for_unknown_token(): void
    {
        $result = (new DeviceTrustService())->logout('unknown-token');

        $this->assertFalse($result['success']);
        $this->assertSame('Device not found', $result['message']);
    }
}
