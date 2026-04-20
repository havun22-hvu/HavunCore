<?php

namespace Tests\Unit;

use App\Models\AuthDevice;
use App\Models\AuthUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage voor het AuthDevice model — token-generation, hash, lookup
 * scopes, trust-extension, revoke, createForUser. Toegevoegd 2026-04-20
 * om HavunCore Unit-coverage richting 80 % te tillen.
 */
class AuthDeviceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): AuthUser
    {
        return AuthUser::create([
            'email' => 'test-' . Str::random(8) . '@havun.nl',
            'name' => 'Test',
            'password' => bcrypt('x'),
            'is_admin' => false,
        ]);
    }

    public function test_generate_token_returns_prefixed_string(): void
    {
        $token = AuthDevice::generateToken();

        $this->assertStringStartsWith('dev_', $token);
        $this->assertSame(52, strlen($token), 'dev_ + 48-char random.');
    }

    public function test_create_hash_is_deterministic_per_input(): void
    {
        $a = AuthDevice::createHash(['ip' => '1.2.3.4', 'ua' => 'phpunit']);
        $b = AuthDevice::createHash(['ip' => '1.2.3.4', 'ua' => 'phpunit']);
        $c = AuthDevice::createHash(['ip' => '1.2.3.4', 'ua' => 'firefox']);

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertSame(64, strlen($a), 'sha256 hex digest.');
    }

    public function test_find_by_token_returns_active_unexpired_device(): void
    {
        $device = AuthDevice::createForUser($this->makeUser(), 'Phone', 'h1');
        $token = $device->token;

        $found = AuthDevice::findByToken($token);

        $this->assertNotNull($found);
        $this->assertSame($device->id, $found->id);
    }

    public function test_find_by_token_returns_null_for_expired_device(): void
    {
        $device = AuthDevice::createForUser($this->makeUser(), 'Old', 'h2');
        $device->update(['expires_at' => now()->subDay()]);

        $this->assertNull(AuthDevice::findByToken($device->token));
    }

    public function test_find_by_token_returns_null_for_revoked_device(): void
    {
        $device = AuthDevice::createForUser($this->makeUser(), 'Revoked', 'h3');
        $device->revoke();

        $this->assertNull(AuthDevice::findByToken($device->token));
    }

    public function test_find_by_hash_filters_to_user_id(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();
        AuthDevice::createForUser($userA, 'A', 'shared-hash');
        AuthDevice::createForUser($userB, 'B', 'shared-hash');

        $found = AuthDevice::findByHash($userA->id, 'shared-hash');

        $this->assertNotNull($found);
        $this->assertSame($userA->id, $found->user_id);
    }

    public function test_is_valid_true_for_active_unexpired(): void
    {
        $device = AuthDevice::createForUser($this->makeUser(), 'Live', 'h4');

        $this->assertTrue($device->isValid());
    }

    public function test_is_valid_false_for_expired_or_revoked(): void
    {
        $expired = AuthDevice::createForUser($this->makeUser(), 'Exp', 'h5');
        $expired->update(['expires_at' => now()->subSecond()]);
        $this->assertFalse($expired->fresh()->isValid());

        $revoked = AuthDevice::createForUser($this->makeUser(), 'Rev', 'h6');
        $revoked->revoke();
        $this->assertFalse($revoked->fresh()->isValid());
    }

    public function test_extend_trust_resets_expires_at_30_days_in_future(): void
    {
        $device = AuthDevice::createForUser($this->makeUser(), 'Ext', 'h7');
        $device->update(['expires_at' => now()->addDay()]); // close to expiry

        $device->extendTrust();

        $this->assertGreaterThan(now()->addDays(29), $device->fresh()->expires_at);
    }

    public function test_touch_used_updates_timestamp_and_optionally_ip(): void
    {
        $device = AuthDevice::createForUser($this->makeUser(), 'Touch', 'h8');
        $device->update(['last_used_at' => now()->subHours(2), 'ip_address' => '1.1.1.1']);

        $device->touchUsed('2.2.2.2');

        $fresh = $device->fresh();
        $this->assertSame('2.2.2.2', $fresh->ip_address);
        $this->assertGreaterThan(now()->subMinute(), $fresh->last_used_at);
    }

    public function test_create_for_user_sets_all_defaults(): void
    {
        $user = $this->makeUser();
        $device = AuthDevice::createForUser($user, 'iPhone 15', 'fp-hash', '127.0.0.1', 'Safari', 'iOS', 'mobile-ua');

        $this->assertSame($user->id, $device->user_id);
        $this->assertSame('iPhone 15', $device->device_name);
        $this->assertSame('fp-hash', $device->device_hash);
        $this->assertSame('Safari', $device->browser);
        $this->assertTrue((bool) $device->is_active);
        $this->assertGreaterThan(now()->addDays(29), $device->expires_at);
    }
}
