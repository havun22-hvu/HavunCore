<?php

namespace Tests\Unit;

use App\Models\AuthUser;
use App\Models\AuthDevice;
use App\Models\AuthAccessLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthUserExtendedTest extends TestCase
{
    use RefreshDatabase;

    private AuthUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = AuthUser::create([
            'name' => 'Extended User', 'email' => 'ext@havun.nl',
            'password_hash' => null, 'is_admin' => false,
        ]);
    }

    public function test_active_devices_only_returns_active_non_expired(): void
    {
        AuthDevice::create([
            'user_id' => $this->user->id, 'device_name' => 'Active',
            'device_hash' => hash('sha256', 'active'), 'token' => AuthDevice::generateToken(),
            'expires_at' => now()->addDays(30), 'last_used_at' => now(),
            'ip_address' => '1.2.3.4', 'is_active' => true,
        ]);
        AuthDevice::create([
            'user_id' => $this->user->id, 'device_name' => 'Inactive',
            'device_hash' => hash('sha256', 'inactive'), 'token' => AuthDevice::generateToken(),
            'expires_at' => now()->addDays(30), 'last_used_at' => now(),
            'ip_address' => '1.2.3.5', 'is_active' => false,
        ]);
        AuthDevice::create([
            'user_id' => $this->user->id, 'device_name' => 'Expired',
            'device_hash' => hash('sha256', 'expired'), 'token' => AuthDevice::generateToken(),
            'expires_at' => now()->subDay(), 'last_used_at' => now(),
            'ip_address' => '1.2.3.6', 'is_active' => true,
        ]);

        $this->assertCount(1, $this->user->activeDevices);
        $this->assertEquals('Active', $this->user->activeDevices->first()->device_name);
    }

    public function test_access_logs_relationship(): void
    {
        AuthAccessLog::log(AuthAccessLog::ACTION_LOGIN, $this->user->id);
        AuthAccessLog::log(AuthAccessLog::ACTION_QR_APPROVE, $this->user->id);

        $this->assertCount(2, $this->user->accessLogs);
    }

    public function test_revoke_all_devices(): void
    {
        for ($i = 0; $i < 3; $i++) {
            AuthDevice::create([
                'user_id' => $this->user->id, 'device_name' => "Device {$i}",
                'device_hash' => hash('sha256', "device-{$i}"), 'token' => AuthDevice::generateToken(),
                'expires_at' => now()->addDays(30), 'last_used_at' => now(),
                'ip_address' => '1.2.3.4', 'is_active' => true,
            ]);
        }

        $count = $this->user->revokeAllDevices();
        $this->assertEquals(3, $count);
        $this->assertCount(0, $this->user->activeDevices()->get());
    }
}
