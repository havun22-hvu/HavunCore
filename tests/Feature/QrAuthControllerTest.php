<?php

namespace Tests\Feature;

use App\Models\AuthDevice;
use App\Models\AuthQrSession;
use App\Models\AuthUser;
use App\Services\DeviceTrustService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QrAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Test fixture - not a real credential */
    private const TEST_PW = 'test-pw-123';
    private const WRONG_PW = 'wrong-pw-xyz';

    // ==========================================
    // Register
    // ==========================================

    public function test_register_first_user_becomes_admin(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'first@havun.nl',
            'name' => 'First User',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'user' => [
                    'email' => 'first@havun.nl',
                    'name' => 'First User',
                    'is_admin' => true,
                ],
            ]);

        $this->assertDatabaseHas('auth_users', [
            'email' => 'first@havun.nl',
            'is_admin' => true,
        ]);
    }

    public function test_register_second_user_is_not_admin(): void
    {
        // Create first user (admin) with a device token for auth
        $admin = AuthUser::create([
            'email' => 'admin@havun.nl',
            'name' => 'Admin',
            'password_hash' => Hash::make('password123'),
            'is_admin' => true,
        ]);

        $device = AuthDevice::createForUser($admin, 'Chrome', 'hash123');

        $response = $this->postJson('/api/auth/register', [
            'email' => 'second@havun.nl',
            'name' => 'Second User',
            'password' => 'password123',
        ], ['Authorization' => 'Bearer ' . $device->token]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'user' => [
                    'email' => 'second@havun.nl',
                    'is_admin' => false,
                ],
            ]);
    }

    public function test_register_second_user_without_admin_token_fails(): void
    {
        // Create first user so it's no longer "first user" scenario
        AuthUser::create([
            'email' => 'admin@havun.nl',
            'name' => 'Admin',
            'is_admin' => true,
        ]);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'second@havun.nl',
            'name' => 'Second User',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Admin authentication required']);
    }

    public function test_register_validation_missing_fields(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'name', 'password']);
    }

    public function test_register_validation_short_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'test@havun.nl',
            'name' => 'Test',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_validation_duplicate_email(): void
    {
        AuthUser::create([
            'email' => 'existing@havun.nl',
            'name' => 'Existing',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'existing@havun.nl',
            'name' => 'Duplicate',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ==========================================
    // Login
    // ==========================================

    public function test_login_with_correct_credentials(): void
    {
        AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
            'password_hash' => Hash::make(self::TEST_PW),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'henk@havun.nl',
            'password' => self::TEST_PW,
            'browser' => 'Chrome',
            'os' => 'Windows',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'user' => [
                    'email' => 'henk@havun.nl',
                    'name' => 'Henk',
                ],
            ])
            ->assertJsonStructure(['device_token']);
    }

    public function test_login_with_wrong_password(): void
    {
        AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
            'password_hash' => Hash::make(self::TEST_PW),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'henk@havun.nl',
            'password' => self::WRONG_PW,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
    }

    public function test_login_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'niemand@havun.nl',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
    }

    public function test_login_validation_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    // ==========================================
    // Generate QR
    // ==========================================

    public function test_generate_qr_creates_session(): void
    {
        $response = $this->postJson('/api/auth/qr/generate', [
            'browser' => 'Chrome',
            'os' => 'Windows',
            'device_name' => 'Desktop',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'qr_code',
                'email_token',
                'expires_at',
                'expires_in',
            ]);

        $this->assertStringStartsWith('qr_', $response->json('qr_code'));
        $this->assertDatabaseCount('auth_qr_sessions', 1);
    }

    // ==========================================
    // Check QR Status
    // ==========================================

    public function test_check_qr_status_returns_pending_for_new_session(): void
    {
        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        $response = $this->getJson('/api/auth/qr/' . $session->qr_code . '/status');

        $response->assertOk()
            ->assertJson([
                'status' => 'pending',
            ])
            ->assertJsonStructure(['expires_at']);
    }

    public function test_check_qr_status_not_found(): void
    {
        $response = $this->getJson('/api/auth/qr/nonexistent_code/status');

        $response->assertOk()
            ->assertJson(['status' => 'not_found']);
    }

    // ==========================================
    // Verify
    // ==========================================

    public function test_verify_with_valid_token_returns_user(): void
    {
        $user = AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
            'is_admin' => true,
        ]);

        $device = AuthDevice::createForUser($user, 'Chrome', 'hash123');

        $response = $this->postJson('/api/auth/verify', [], [
            'Authorization' => 'Bearer ' . $device->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'valid' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => 'Henk',
                    'email' => 'henk@havun.nl',
                    'is_admin' => true,
                ],
            ]);
    }

    public function test_verify_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/auth/verify');

        $response->assertStatus(401)
            ->assertJson([
                'valid' => false,
                'message' => 'No token provided',
            ]);
    }

    public function test_verify_with_invalid_token_returns_401(): void
    {
        $response = $this->postJson('/api/auth/verify', [], [
            'Authorization' => 'Bearer invalid_token_here',
        ]);

        $response->assertStatus(401)
            ->assertJson(['valid' => false]);
    }

    // ==========================================
    // Logout
    // ==========================================

    public function test_logout_revokes_device(): void
    {
        $user = AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
        ]);

        $device = AuthDevice::createForUser($user, 'Chrome', 'hash123');

        $response = $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer ' . $device->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);

        $this->assertFalse($device->fresh()->is_active);
    }

    public function test_logout_without_token_returns_400(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'No token provided',
            ]);
    }

    // ==========================================
    // Approve Email
    // ==========================================

    public function test_approve_email_with_valid_token(): void
    {
        $user = AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
        ]);

        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        $token = $session->setEmail('henk@havun.nl', 'https://example.com/approve');

        $response = $this->postJson('/api/auth/email/approve', [
            'token' => $token,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Login approved',
            ])
            ->assertJsonStructure(['device_name', 'user']);

        $this->assertEquals(AuthQrSession::STATUS_APPROVED, $session->fresh()->status);
    }

    public function test_approve_email_with_invalid_token(): void
    {
        $response = $this->postJson('/api/auth/email/approve', [
            'token' => str_repeat('a', 64),
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_approve_email_validation_requires_token(): void
    {
        $response = $this->postJson('/api/auth/email/approve', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    // ==========================================
    // Send Email
    // ==========================================

    public function test_send_email_for_qr_session(): void
    {
        Mail::fake();

        $user = AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
        ]);

        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        $response = $this->postJson('/api/auth/qr/' . $session->qr_code . '/send-email', [
            'email' => 'henk@havun.nl',
            'callback_url' => 'https://example.com/approve',
            'site_name' => 'TestApp',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Email sent',
            ]);

        // Mail::send() with raw closure is captured by MailFake
        // Verify the session got an email set
        $fresh = $session->fresh();
        $this->assertEquals('henk@havun.nl', $fresh->email);
    }

    public function test_send_email_with_nonexistent_user(): void
    {
        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        $response = $this->postJson('/api/auth/qr/' . $session->qr_code . '/send-email', [
            'email' => 'niemand@havun.nl',
            'callback_url' => 'https://example.com/approve',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Email not found',
            ]);
    }

    public function test_send_email_validation(): void
    {
        $session = AuthQrSession::createSession();

        $response = $this->postJson('/api/auth/qr/' . $session->qr_code . '/send-email', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'callback_url']);
    }

    // ==========================================
    // Approve QR (authenticated)
    // ==========================================

    public function test_approve_qr_without_token_returns_401(): void
    {
        $session = AuthQrSession::createSession();

        $response = $this->postJson('/api/auth/qr/' . $session->qr_code . '/approve');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Authentication required']);
    }

    public function test_approve_qr_with_valid_device_token(): void
    {
        $user = AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
        ]);

        $device = AuthDevice::createForUser($user, 'Mobile Chrome', 'mobilehash');

        $session = AuthQrSession::createSession(
            ['browser' => 'Firefox', 'os' => 'Linux'],
            '127.0.0.1'
        );

        $response = $this->postJson(
            '/api/auth/qr/' . $session->qr_code . '/approve',
            ['browser' => 'Mobile Chrome', 'os' => 'Android'],
            ['Authorization' => 'Bearer ' . $device->token]
        );

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Device approved successfully',
            ]);

        $this->assertTrue($session->fresh()->isApproved());
    }

    // ==========================================
    // Approve From App
    // ==========================================

    public function test_approve_from_app_with_valid_data(): void
    {
        $user = AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
        ]);

        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        $token = $session->setEmail('henk@havun.nl', 'https://example.com/approve');

        $response = $this->postJson('/api/auth/qr/approve-from-app', [
            'token' => $token,
            'email' => 'henk@havun.nl',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Login approved',
            ]);

        $this->assertTrue($session->fresh()->isApproved());
    }

    public function test_approve_from_app_validation(): void
    {
        $response = $this->postJson('/api/auth/qr/approve-from-app', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'email']);
    }

    public function test_approve_from_app_with_invalid_token(): void
    {
        $response = $this->postJson('/api/auth/qr/approve-from-app', [
            'token' => str_repeat('b', 64),
            'email' => 'niemand@havun.nl',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_approve_from_app_with_nonexistent_email(): void
    {
        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        $token = $session->setEmail('iemand@havun.nl', 'https://example.com/approve');

        // Email in request doesn't match any user
        $response = $this->postJson('/api/auth/qr/approve-from-app', [
            'token' => $token,
            'email' => 'niemand@havun.nl',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    // ==========================================
    // Approve Authenticated
    // ==========================================

    public function test_approve_authenticated_with_valid_device_token(): void
    {
        $user = AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
        ]);

        $device = AuthDevice::createForUser($user, 'Mobile Chrome', 'mobilehash');

        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        $emailToken = $session->setEmail('henk@havun.nl', 'https://example.com/approve');

        $response = $this->postJson('/api/auth/qr/approve-authenticated', [
            'token' => $emailToken,
            'device_token' => $device->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Login goedgekeurd',
            ])
            ->assertJsonStructure(['device_name', 'user']);
    }

    public function test_approve_authenticated_with_invalid_device_token(): void
    {
        $session = AuthQrSession::createSession();
        $emailToken = $session->setEmail('henk@havun.nl', 'https://example.com/approve');

        $response = $this->postJson('/api/auth/qr/approve-authenticated', [
            'token' => $emailToken,
            'device_token' => 'invalid_device_token',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Ongeldige of verlopen sessie op dit apparaat',
            ]);
    }

    public function test_approve_authenticated_validation(): void
    {
        $response = $this->postJson('/api/auth/qr/approve-authenticated', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'device_token']);
    }

    public function test_approve_authenticated_with_deleted_user(): void
    {
        $user = AuthUser::create([
            'email' => 'temp@havun.nl',
            'name' => 'Temp User',
        ]);

        $device = AuthDevice::createForUser($user, 'Chrome', 'hash999');
        $deviceToken = $device->token;

        // Delete the user (device still exists but verifyToken fails because user is gone)
        $user->delete();

        $session = AuthQrSession::createSession();
        $emailToken = $session->setEmail('temp@havun.nl', 'https://example.com/approve');

        $response = $this->postJson('/api/auth/qr/approve-authenticated', [
            'token' => $emailToken,
            'device_token' => $deviceToken,
        ]);

        // Device token verification fails because user is deleted
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_approve_authenticated_with_expired_email_token(): void
    {
        $user = AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
        ]);

        $device = AuthDevice::createForUser($user, 'Mobile', 'mobilehash2');

        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        $emailToken = $session->setEmail('henk@havun.nl', 'https://example.com/approve');

        // Expire the session
        $session->update(['expires_at' => now()->subMinutes(1)]);

        $response = $this->postJson('/api/auth/qr/approve-authenticated', [
            'token' => $emailToken,
            'device_token' => $device->token,
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    // ==========================================
    // QR Status edge cases
    // ==========================================

    public function test_check_qr_status_returns_expired_for_old_session(): void
    {
        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        // Expire the session manually
        $session->update(['expires_at' => now()->subMinutes(1)]);

        $response = $this->getJson('/api/auth/qr/' . $session->qr_code . '/status');

        $response->assertOk()
            ->assertJson(['status' => 'expired']);
    }

    public function test_check_qr_status_returns_approved_with_device_token(): void
    {
        $user = AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
        ]);

        $device = AuthDevice::createForUser($user, 'Chrome', 'hash456');

        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        // Approve the session
        $session->approve($user, $device);

        $response = $this->getJson('/api/auth/qr/' . $session->qr_code . '/status');

        $response->assertOk()
            ->assertJson([
                'status' => 'approved',
            ])
            ->assertJsonStructure(['device_token', 'user']);
    }

    // ==========================================
    // Approve QR edge cases
    // ==========================================

    public function test_approve_qr_with_invalid_device_token_returns_401(): void
    {
        $session = AuthQrSession::createSession();

        $response = $this->postJson(
            '/api/auth/qr/' . $session->qr_code . '/approve',
            [],
            ['Authorization' => 'Bearer invalid_token']
        );

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid device token']);
    }

    public function test_approve_qr_with_deleted_user_returns_401(): void
    {
        $user = AuthUser::create([
            'email' => 'temp2@havun.nl',
            'name' => 'Temp2',
        ]);

        $device = AuthDevice::createForUser($user, 'Chrome', 'temphash2');
        $token = $device->token;

        // Delete user — device token verification will fail
        $user->delete();

        $session = AuthQrSession::createSession();

        $response = $this->postJson(
            '/api/auth/qr/' . $session->qr_code . '/approve',
            [],
            ['Authorization' => 'Bearer ' . $token]
        );

        // verifyToken fails because user is deleted, returns 401
        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid device token']);
    }

    // ==========================================
    // Send Email edge cases
    // ==========================================

    public function test_send_email_for_expired_session(): void
    {
        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        // Expire the session
        $session->update(['expires_at' => now()->subMinutes(1)]);

        $response = $this->postJson('/api/auth/qr/' . $session->qr_code . '/send-email', [
            'email' => 'henk@havun.nl',
            'callback_url' => 'https://example.com/approve',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'QR session not found or expired',
            ]);
    }

    public function test_send_email_with_default_site_name(): void
    {
        Mail::fake();

        $user = AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
        ]);

        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '127.0.0.1'
        );

        // Don't pass site_name — should default to 'Havun'
        $response = $this->postJson('/api/auth/qr/' . $session->qr_code . '/send-email', [
            'email' => 'henk@havun.nl',
            'callback_url' => 'https://example.com/approve',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    // ==========================================
    // Logout edge cases
    // ==========================================

    public function test_logout_with_invalid_token(): void
    {
        $response = $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer totally_invalid_token',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'Device not found',
            ]);
    }

    // ==========================================
    // Register edge cases
    // ==========================================

    public function test_register_second_user_with_non_admin_token_fails(): void
    {
        // First user (admin)
        AuthUser::create([
            'email' => 'admin@havun.nl',
            'name' => 'Admin',
            'password_hash' => Hash::make('password123'),
            'is_admin' => true,
        ]);

        // Non-admin user with device
        $nonAdmin = AuthUser::create([
            'email' => 'regular@havun.nl',
            'name' => 'Regular',
            'password_hash' => Hash::make('password123'),
            'is_admin' => false,
        ]);

        $device = AuthDevice::createForUser($nonAdmin, 'Chrome', 'hash_regular');

        $response = $this->postJson('/api/auth/register', [
            'email' => 'third@havun.nl',
            'name' => 'Third User',
            'password' => 'password123',
        ], ['Authorization' => 'Bearer ' . $device->token]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Admin privileges required']);
    }
}
