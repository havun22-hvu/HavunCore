<?php

namespace Tests\Unit;

use App\Models\AuthAccessLog;
use App\Models\AuthDevice;
use App\Models\AuthQrSession;
use App\Models\AuthUser;
use App\Services\QrAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QrAuthServiceCoverageTest extends TestCase
{
    use RefreshDatabase;

    private QrAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QrAuthService();
    }

    // ===================================================================
    // sendLoginEmail
    // ===================================================================

    public function test_send_login_email_success(): void
    {
        Mail::fake();

        $user = AuthUser::create([
            'email' => 'havun22@gmail.com',
            'name' => 'Henk',
        ]);

        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        $result = $this->service->sendLoginEmail(
            $session->qr_code,
            'havun22@gmail.com',
            'https://example.com/approve',
            'TestApp'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('Email sent', $result['message']);
        $this->assertArrayHasKey('expires_at', $result);
    }

    public function test_send_login_email_expired_session(): void
    {
        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );
        $session->update(['expires_at' => now()->subMinutes(1)]);

        $result = $this->service->sendLoginEmail(
            $session->qr_code,
            'havun22@gmail.com',
            'https://example.com/approve'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('QR session not found or expired', $result['message']);
    }

    public function test_send_login_email_nonexistent_user(): void
    {
        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        $result = $this->service->sendLoginEmail(
            $session->qr_code,
            'nobody@havun.nl',
            'https://example.com/approve'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('Email not found', $result['message']);
    }

    public function test_send_login_email_default_site_name(): void
    {
        Mail::fake();

        $user = AuthUser::create([
            'email' => 'havun22@gmail.com',
            'name' => 'Henk',
        ]);

        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        // No site_name passed — should default to 'Havun'
        $result = $this->service->sendLoginEmail(
            $session->qr_code,
            'havun22@gmail.com',
            'https://example.com/approve'
        );

        $this->assertTrue($result['success']);
    }

    // ===================================================================
    // approveViaEmailToken
    // ===================================================================

    public function test_approve_via_email_token_success(): void
    {
        $user = AuthUser::create([
            'email' => 'havun22@gmail.com',
            'name' => 'Henk',
        ]);

        $session = AuthQrSession::createSession(
            ['browser' => 'Firefox', 'os' => 'Linux'],
            '10.0.0.1'
        );

        $token = $session->setEmail('havun22@gmail.com', 'https://example.com/approve');

        $result = $this->service->approveViaEmailToken($token, '10.0.0.2');

        $this->assertTrue($result['success']);
        $this->assertEquals('Login approved', $result['message']);
        $this->assertEquals('Henk', $result['user']['name']);
        $this->assertArrayHasKey('device_name', $result);
        $this->assertTrue($session->fresh()->isApproved());
    }

    public function test_approve_via_email_token_invalid_token(): void
    {
        $result = $this->service->approveViaEmailToken(str_repeat('x', 64));

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid or expired token', $result['message']);
    }

    public function test_approve_via_email_token_user_not_found(): void
    {
        // Create session with email that has no user
        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        $token = $session->setEmail('nobody@havun.nl', 'https://example.com/approve');

        $result = $this->service->approveViaEmailToken($token);

        $this->assertFalse($result['success']);
        $this->assertEquals('User not found', $result['message']);
    }

    public function test_approve_via_email_token_reactivates_existing_device(): void
    {
        $user = AuthUser::create([
            'email' => 'havun22@gmail.com',
            'name' => 'Henk',
        ]);

        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '10.0.0.1'
        );

        // Create an existing device with matching hash
        $deviceHash = AuthDevice::createHash($session->device_info ?? ['qr_code' => $session->qr_code]);
        $existingDevice = AuthDevice::create([
            'user_id' => $user->id,
            'device_name' => 'Old Device',
            'device_hash' => $deviceHash,
            'token' => AuthDevice::generateToken(),
            'expires_at' => now()->subDays(1), // Expired
            'last_used_at' => now()->subDays(30),
            'ip_address' => '1.2.3.4',
            'is_active' => true,
        ]);

        $token = $session->setEmail('havun22@gmail.com', 'https://example.com/approve');

        $result = $this->service->approveViaEmailToken($token, '10.0.0.2');

        $this->assertTrue($result['success']);
        // Existing device should be reactivated
        $refreshed = $existingDevice->fresh();
        $this->assertTrue($refreshed->is_active);
        $this->assertTrue($refreshed->expires_at->isFuture());
    }

    // ===================================================================
    // approveViaQrScan
    // ===================================================================

    public function test_approve_via_qr_scan_success(): void
    {
        $user = AuthUser::create([
            'email' => 'havun22@gmail.com',
            'name' => 'Henk',
        ]);

        $session = AuthQrSession::createSession(
            ['browser' => 'Firefox', 'os' => 'Linux'],
            '10.0.0.1'
        );

        $token = $session->setEmail('havun22@gmail.com', 'https://example.com/approve');

        $result = $this->service->approveViaQrScan($token, 'havun22@gmail.com', '10.0.0.2');

        $this->assertTrue($result['success']);
        $this->assertEquals('Login approved', $result['message']);
        $this->assertEquals('Henk', $result['user']['name']);
        $this->assertArrayHasKey('device_name', $result);
        $this->assertTrue($session->fresh()->isApproved());
    }

    public function test_approve_via_qr_scan_invalid_token(): void
    {
        $result = $this->service->approveViaQrScan(str_repeat('y', 64), 'havun22@gmail.com');

        $this->assertFalse($result['success']);
        $this->assertEquals('Ongeldige of verlopen sessie', $result['message']);
    }

    public function test_approve_via_qr_scan_user_not_found(): void
    {
        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        $token = $session->setEmail('somebody@havun.nl', 'https://example.com/approve');

        $result = $this->service->approveViaQrScan($token, 'nobody@havun.nl');

        $this->assertFalse($result['success']);
        $this->assertEquals('Email niet gevonden', $result['message']);
    }

    public function test_approve_via_qr_scan_reactivates_existing_device(): void
    {
        $user = AuthUser::create([
            'email' => 'havun22@gmail.com',
            'name' => 'Henk',
        ]);

        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '10.0.0.1'
        );

        // Create existing device with matching hash
        $deviceHash = AuthDevice::createHash($session->device_info ?? ['qr_code' => $session->qr_code]);
        $existingDevice = AuthDevice::create([
            'user_id' => $user->id,
            'device_name' => 'Old Device',
            'device_hash' => $deviceHash,
            'token' => AuthDevice::generateToken(),
            'expires_at' => now()->subDays(1),
            'last_used_at' => now()->subDays(30),
            'ip_address' => '1.2.3.4',
            'is_active' => true,
        ]);

        $token = $session->setEmail('havun22@gmail.com', 'https://example.com/approve');

        $result = $this->service->approveViaQrScan($token, 'havun22@gmail.com', '10.0.0.2');

        $this->assertTrue($result['success']);
        $refreshed = $existingDevice->fresh();
        $this->assertTrue($refreshed->is_active);
        $this->assertTrue($refreshed->expires_at->isFuture());
    }

    // ===================================================================
    // formatDeviceName (protected, tested via public methods)
    // ===================================================================

    public function test_format_device_name_with_browser_and_os(): void
    {
        // loginWithPassword uses formatDeviceName internally
        $user = AuthUser::create([
            'email' => 'havun22@gmail.com',
            'name' => 'Henk',
            'password_hash' => Hash::make('secret123'),
        ]);

        $result = $this->service->loginWithPassword(
            'havun22@gmail.com',
            'secret123',
            ['browser' => 'Safari', 'os' => 'macOS'],
            '10.0.0.1'
        );

        $this->assertTrue($result['success']);
        // Device was created with formatted name
        $device = AuthDevice::where('user_id', $user->id)->first();
        $this->assertNotNull($device);
        $this->assertEquals('Safari on macOS', $device->device_name);
    }

    public function test_format_device_name_with_empty_device_info(): void
    {
        $user = AuthUser::create([
            'email' => 'havun22@gmail.com',
            'name' => 'Henk',
            'password_hash' => Hash::make('secret123'),
        ]);

        $result = $this->service->loginWithPassword(
            'havun22@gmail.com',
            'secret123',
            [], // Empty device info
            '10.0.0.1'
        );

        $this->assertTrue($result['success']);
        $device = AuthDevice::where('user_id', $user->id)->first();
        $this->assertEquals('Unknown Browser on Unknown OS', $device->device_name);
    }

    // ===================================================================
    // buildLoginEmailHtml (protected, tested via sendLoginEmail)
    // ===================================================================

    public function test_build_login_email_html_contains_expected_content(): void
    {
        // Use reflection to test the protected method directly
        $method = new \ReflectionMethod(QrAuthService::class, 'buildLoginEmailHtml');
        $method->setAccessible(true);

        $html = $method->invoke(
            $this->service,
            'Henk',
            'https://example.com/approve?token=abc',
            'Chrome on Windows',
            'TestApp'
        );

        $this->assertStringContainsString('Henk', $html);
        $this->assertStringContainsString('TestApp', $html);
        $this->assertStringContainsString('Chrome on Windows', $html);
        $this->assertStringContainsString('https://example.com/approve?token=abc', $html);
        $this->assertStringContainsString('Ja, log mij in', $html);
    }

    // ===================================================================
    // approveQrSession — existing device branch
    // ===================================================================

    public function test_approve_qr_session_reactivates_existing_device(): void
    {
        $user = AuthUser::create([
            'email' => 'havun22@gmail.com',
            'name' => 'Henk',
        ]);

        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '10.0.0.1'
        );

        // Create existing device with matching hash
        $deviceHash = AuthDevice::createHash($session->device_info ?? ['qr_code' => $session->qr_code]);
        $existingDevice = AuthDevice::create([
            'user_id' => $user->id,
            'device_name' => 'Old Device',
            'device_hash' => $deviceHash,
            'token' => AuthDevice::generateToken(),
            'expires_at' => now()->subDays(1),
            'last_used_at' => now()->subDays(30),
            'ip_address' => '1.2.3.4',
            'is_active' => true,
        ]);

        $result = $this->service->approveQrSession(
            $session->qr_code,
            $user,
            ['browser' => 'Mobile Chrome', 'os' => 'Android'],
            '10.0.0.2'
        );

        $this->assertTrue($result['success']);
        $refreshed = $existingDevice->fresh();
        $this->assertTrue($refreshed->is_active);
        $this->assertTrue($refreshed->expires_at->isFuture());
    }

    // ===================================================================
    // checkQrStatus — approved with device branch
    // ===================================================================

    public function test_check_qr_status_approved_includes_device_token(): void
    {
        $user = AuthUser::create([
            'email' => 'havun22@gmail.com',
            'name' => 'Henk',
        ]);

        $device = AuthDevice::createForUser($user, 'Chrome', 'hash123');
        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        $session->approve($user, $device);

        $result = $this->service->checkQrStatus($session->qr_code);

        $this->assertEquals('approved', $result['status']);
        $this->assertArrayHasKey('device_token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals('Henk', $result['user']['name']);
        $this->assertEquals('havun22@gmail.com', $result['user']['email']);
    }
}
