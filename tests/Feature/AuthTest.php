<?php

namespace Tests\Feature;

use App\Models\AuthAccessLog;
use App\Models\AuthDevice;
use App\Models\AuthQrSession;
use App\Models\AuthUser;
use App\Services\DeviceTrustService;
use App\Services\QrAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // AuthUser Model
    // ==========================================

    public function test_user_password_verification(): void
    {
        $user = AuthUser::create([
            'email' => 'test@havun.nl',
            'name' => 'Test User',
            'password_hash' => Hash::make('correct-password'),
        ]);

        $this->assertTrue($user->verifyPassword('correct-password'));
        $this->assertFalse($user->verifyPassword('wrong-password'));
    }

    public function test_user_without_password_always_fails_verification(): void
    {
        $user = AuthUser::create([
            'email' => 'test@havun.nl',
            'name' => 'No Password User',
        ]);

        $this->assertFalse($user->verifyPassword('anything'));
    }

    public function test_user_set_password(): void
    {
        $user = AuthUser::create([
            'email' => 'test@havun.nl',
            'name' => 'Test User',
        ]);

        $user->setPassword('new-password');

        $this->assertTrue($user->fresh()->verifyPassword('new-password'));
    }

    public function test_user_find_by_email(): void
    {
        AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
        ]);

        $this->assertNotNull(AuthUser::findByEmail('henk@havun.nl'));
        $this->assertNull(AuthUser::findByEmail('nobody@havun.nl'));
    }

    public function test_user_touch_login(): void
    {
        $user = AuthUser::create([
            'email' => 'test@havun.nl',
            'name' => 'Test',
        ]);

        $this->assertNull($user->last_login_at);

        $user->touchLogin();

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_user_revoke_all_devices(): void
    {
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);

        AuthDevice::createForUser($user, 'Chrome', 'hash1');
        AuthDevice::createForUser($user, 'Firefox', 'hash2');

        $this->assertCount(2, $user->activeDevices()->get());

        $user->revokeAllDevices();

        $this->assertCount(0, $user->fresh()->activeDevices()->get());
    }

    // ==========================================
    // AuthDevice Model
    // ==========================================

    public function test_device_token_starts_with_prefix(): void
    {
        $token = AuthDevice::generateToken();
        $this->assertStringStartsWith('dev_', $token);
    }

    public function test_device_create_for_user(): void
    {
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);

        $device = AuthDevice::createForUser($user, 'Chrome Windows', 'hash123', '192.168.1.1');

        $this->assertEquals($user->id, $device->user_id);
        $this->assertEquals('Chrome Windows', $device->device_name);
        $this->assertTrue($device->is_active);
        $this->assertNotNull($device->expires_at);
        $this->assertTrue($device->expires_at->isFuture());
    }

    public function test_device_find_by_token(): void
    {
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);
        $device = AuthDevice::createForUser($user, 'Chrome', 'hash');

        $found = AuthDevice::findByToken($device->token);
        $this->assertNotNull($found);
        $this->assertEquals($device->id, $found->id);
    }

    public function test_device_find_by_token_ignores_inactive(): void
    {
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);
        $device = AuthDevice::createForUser($user, 'Chrome', 'hash');

        $device->revoke();

        $this->assertNull(AuthDevice::findByToken($device->token));
    }

    public function test_device_find_by_token_ignores_expired(): void
    {
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);
        $device = AuthDevice::createForUser($user, 'Chrome', 'hash');

        // Manually expire the device
        $device->update(['expires_at' => now()->subDay()]);

        $this->assertNull(AuthDevice::findByToken($device->token));
    }

    public function test_device_is_valid(): void
    {
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);
        $device = AuthDevice::createForUser($user, 'Chrome', 'hash');

        $this->assertTrue($device->isValid());

        $device->revoke();
        $this->assertFalse($device->fresh()->isValid());
    }

    public function test_device_extend_trust(): void
    {
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);
        $device = AuthDevice::createForUser($user, 'Chrome', 'hash');

        $originalExpiry = $device->expires_at;
        $device->extendTrust();

        $this->assertTrue($device->fresh()->expires_at->greaterThanOrEqualTo($originalExpiry));
    }

    public function test_device_hash_is_deterministic(): void
    {
        $data = ['browser' => 'Chrome', 'os' => 'Windows'];

        $hash1 = AuthDevice::createHash($data);
        $hash2 = AuthDevice::createHash($data);

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1)); // SHA-256
    }

    // ==========================================
    // AuthQrSession Model
    // ==========================================

    public function test_qr_session_creation(): void
    {
        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '192.168.1.1'
        );

        $this->assertStringStartsWith('qr_', $session->qr_code);
        $this->assertEquals(AuthQrSession::STATUS_PENDING, $session->status);
        $this->assertTrue($session->expires_at->isFuture());
        $this->assertTrue($session->isValid());
    }

    public function test_qr_session_find_by_code(): void
    {
        $session = AuthQrSession::createSession();

        $found = AuthQrSession::findByCode($session->qr_code);
        $this->assertNotNull($found);
        $this->assertEquals($session->id, $found->id);
    }

    public function test_qr_session_find_active_by_code_excludes_expired(): void
    {
        $session = AuthQrSession::createSession();
        $session->update(['expires_at' => now()->subMinute()]);

        $this->assertNull(AuthQrSession::findActiveByCode($session->qr_code));
    }

    public function test_qr_session_mark_expired(): void
    {
        $session = AuthQrSession::createSession();
        $session->markExpired();

        $this->assertEquals(AuthQrSession::STATUS_EXPIRED, $session->fresh()->status);
        $this->assertFalse($session->fresh()->isValid());
    }

    public function test_qr_session_approve(): void
    {
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);
        $device = AuthDevice::createForUser($user, 'Chrome', 'hash');
        $session = AuthQrSession::createSession();

        $session->approve($user, $device);

        $fresh = $session->fresh();
        $this->assertEquals(AuthQrSession::STATUS_APPROVED, $fresh->status);
        $this->assertTrue($fresh->isApproved());
        $this->assertEquals($user->id, $fresh->approved_by);
        $this->assertEquals($device->id, $fresh->device_id);
    }

    public function test_qr_session_mark_scanned(): void
    {
        $session = AuthQrSession::createSession();
        $session->markScanned();

        $this->assertEquals(AuthQrSession::STATUS_SCANNED, $session->fresh()->status);
    }

    public function test_qr_session_mark_scanned_only_from_pending(): void
    {
        $session = AuthQrSession::createSession();
        $session->markExpired();
        $session->markScanned();

        // Should still be expired since markScanned only works from pending
        $this->assertEquals(AuthQrSession::STATUS_EXPIRED, $session->fresh()->status);
    }

    public function test_qr_session_set_email_extends_expiry(): void
    {
        $session = AuthQrSession::createSession();
        $originalExpiry = $session->expires_at;

        $token = $session->setEmail('test@havun.nl', 'https://example.com/approve');

        $fresh = $session->fresh();
        $this->assertNotEmpty($token);
        $this->assertEquals('test@havun.nl', $fresh->email);
        // Email flow extends to 15 minutes
        $this->assertTrue($fresh->expires_at->greaterThan($originalExpiry));
    }

    public function test_qr_session_find_by_email_token(): void
    {
        $session = AuthQrSession::createSession();
        $token = $session->setEmail('test@havun.nl', 'https://example.com');

        $found = AuthQrSession::findByEmailToken($token);
        $this->assertNotNull($found);
        $this->assertEquals($session->id, $found->id);
    }

    public function test_qr_session_cleanup_expired(): void
    {
        // Create expired session
        $expired = AuthQrSession::createSession();
        $expired->update(['expires_at' => now()->subMinute()]);

        // Create active session
        AuthQrSession::createSession();

        $cleaned = AuthQrSession::cleanupExpired();
        $this->assertEquals(1, $cleaned);

        $this->assertEquals(AuthQrSession::STATUS_EXPIRED, $expired->fresh()->status);
    }

    // ==========================================
    // AuthAccessLog Model
    // ==========================================

    public function test_access_log_creation(): void
    {
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);

        $log = AuthAccessLog::log(
            AuthAccessLog::ACTION_LOGIN,
            $user->id,
            'Chrome Windows',
            '192.168.1.1',
            'Mozilla/5.0',
            ['extra' => 'data']
        );

        $this->assertEquals(AuthAccessLog::ACTION_LOGIN, $log->action);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals('Chrome Windows', $log->device_name);
    }

    public function test_access_log_recent_for_user(): void
    {
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);

        AuthAccessLog::log(AuthAccessLog::ACTION_LOGIN, $user->id);
        AuthAccessLog::log(AuthAccessLog::ACTION_LOGOUT, $user->id);
        AuthAccessLog::log(AuthAccessLog::ACTION_QR_GENERATE, null); // No user

        $logs = AuthAccessLog::recentForUser($user->id);
        $this->assertCount(2, $logs);
    }

    // ==========================================
    // QrAuthService
    // ==========================================

    public function test_qr_auth_generate_session(): void
    {
        $service = new QrAuthService();

        $result = $service->generateQrSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '10.0.0.1'
        );

        $this->assertArrayHasKey('qr_code', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertStringStartsWith('qr_', $result['qr_code']);
    }

    public function test_qr_auth_check_status_not_found(): void
    {
        $service = new QrAuthService();

        $result = $service->checkQrStatus('nonexistent_code');

        $this->assertEquals('not_found', $result['status']);
    }

    public function test_qr_auth_check_status_pending(): void
    {
        $service = new QrAuthService();
        $session = AuthQrSession::createSession();

        $result = $service->checkQrStatus($session->qr_code);

        $this->assertEquals('pending', $result['status']);
    }

    public function test_qr_auth_check_status_marks_expired(): void
    {
        $service = new QrAuthService();
        $session = AuthQrSession::createSession();
        $session->update(['expires_at' => now()->subMinute()]);

        $result = $service->checkQrStatus($session->qr_code);

        $this->assertEquals('expired', $result['status']);
    }

    public function test_qr_auth_login_with_password_success(): void
    {
        $service = new QrAuthService();

        AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
            'password_hash' => Hash::make('secret123'),
        ]);

        $result = $service->loginWithPassword(
            'henk@havun.nl',
            'secret123',
            ['browser' => 'Chrome'],
            '10.0.0.1'
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('device_token', $result);
        $this->assertEquals('Henk', $result['user']['name']);
    }

    public function test_qr_auth_login_with_wrong_password(): void
    {
        $service = new QrAuthService();

        AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
            'password_hash' => Hash::make('correct'),
        ]);

        $result = $service->loginWithPassword('henk@havun.nl', 'wrong');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid credentials', $result['message']);
    }

    public function test_qr_auth_login_with_nonexistent_user(): void
    {
        $service = new QrAuthService();

        $result = $service->loginWithPassword('nobody@havun.nl', 'password');

        $this->assertFalse($result['success']);
    }

    public function test_qr_auth_approve_session(): void
    {
        $service = new QrAuthService();

        $user = AuthUser::create([
            'email' => 'approver@havun.nl',
            'name' => 'Approver',
        ]);

        $session = AuthQrSession::createSession(
            ['browser' => 'Firefox', 'os' => 'Linux'],
            '10.0.0.1'
        );

        $result = $service->approveQrSession(
            $session->qr_code,
            $user,
            ['browser' => 'Chrome Mobile'],
            '10.0.0.2'
        );

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('approved', $result['message']);

        // Session should be approved
        $this->assertTrue($session->fresh()->isApproved());
    }

    public function test_qr_auth_approve_expired_session(): void
    {
        $service = new QrAuthService();

        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);
        $session = AuthQrSession::createSession();
        $session->update(['expires_at' => now()->subMinute()]);

        $result = $service->approveQrSession($session->qr_code, $user);

        $this->assertFalse($result['success']);
    }

    // ==========================================
    // DeviceTrustService
    // ==========================================

    public function test_device_trust_verify_valid_token(): void
    {
        $service = new DeviceTrustService();
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);
        $device = AuthDevice::createForUser($user, 'Chrome', 'hash');

        $result = $service->verifyToken($device->token, '10.0.0.1');

        $this->assertTrue($result['valid']);
        $this->assertEquals($user->id, $result['user']['id']);
        $this->assertEquals('Chrome', $result['device']['name']);
    }

    public function test_device_trust_verify_invalid_token(): void
    {
        $service = new DeviceTrustService();

        $result = $service->verifyToken('invalid_token');

        $this->assertFalse($result['valid']);
    }

    public function test_device_trust_revoke_device(): void
    {
        $service = new DeviceTrustService();
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);
        $device = AuthDevice::createForUser($user, 'Chrome', 'hash');

        $result = $service->revokeDevice($user, $device->id);

        $this->assertTrue($result['success']);
        $this->assertFalse($device->fresh()->is_active);
    }

    public function test_device_trust_revoke_nonexistent_device(): void
    {
        $service = new DeviceTrustService();
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);

        $result = $service->revokeDevice($user, 99999);

        $this->assertFalse($result['success']);
    }

    public function test_device_trust_revoke_all_except_current(): void
    {
        $service = new DeviceTrustService();
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);
        $device1 = AuthDevice::createForUser($user, 'Chrome', 'hash1');
        $device2 = AuthDevice::createForUser($user, 'Firefox', 'hash2');
        $device3 = AuthDevice::createForUser($user, 'Safari', 'hash3');

        $result = $service->revokeAllDevices($user, $device1->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['revoked_count']);

        // Device 1 should still be active
        $this->assertTrue($device1->fresh()->is_active);
        $this->assertFalse($device2->fresh()->is_active);
        $this->assertFalse($device3->fresh()->is_active);
    }

    public function test_device_trust_logout(): void
    {
        $service = new DeviceTrustService();
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);
        $device = AuthDevice::createForUser($user, 'Chrome', 'hash');

        $result = $service->logout($device->token);

        $this->assertTrue($result['success']);
        $this->assertFalse($device->fresh()->is_active);
    }

    public function test_device_trust_logout_invalid_token(): void
    {
        $service = new DeviceTrustService();

        $result = $service->logout('nonexistent');

        $this->assertFalse($result['success']);
    }

    public function test_device_trust_get_user_devices(): void
    {
        $service = new DeviceTrustService();
        $user = AuthUser::create(['email' => 'test@havun.nl', 'name' => 'Test']);

        AuthDevice::createForUser($user, 'Chrome', 'hash1');
        AuthDevice::createForUser($user, 'Firefox', 'hash2');

        $devices = $service->getUserDevices($user);

        $this->assertCount(2, $devices);
        $this->assertArrayHasKey('name', $devices[0]);
        $this->assertArrayHasKey('expires_at', $devices[0]);
    }
}
