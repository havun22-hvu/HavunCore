<?php

namespace Tests\Feature;

use App\Models\AuthDevice;
use App\Models\AuthQrSession;
use App\Models\AuthUser;
use App\Services\QrAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApproveControllerTest extends TestCase
{
    use RefreshDatabase;

    // ===================================================================
    // GET /approve — show
    // ===================================================================

    public function test_approve_page_shows_error_without_token(): void
    {
        $response = $this->get('/approve');

        $response->assertStatus(200);
        $response->assertSee('Ongeldige of verlopen link');
    }

    public function test_approve_page_shows_error_with_short_token(): void
    {
        $response = $this->get('/approve?token=tooshort');

        $response->assertStatus(200);
        $response->assertSee('Ongeldige of verlopen link');
    }

    public function test_approve_page_shows_form_with_valid_token(): void
    {
        $token = str_repeat('a', 64);

        // Create a matching session so device info is found
        AuthQrSession::create([
            'qr_code' => 'qr_test123',
            'email_token' => $token,
            'device_info' => ['browser' => 'Chrome', 'os' => 'Windows'],
            'status' => AuthQrSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->get('/approve?token=' . $token);

        $response->assertStatus(200);
        $response->assertSee('Login Goedkeuren');
        $response->assertSee('Ja, log mij in');
        $response->assertSee('Chrome op Windows');
    }

    public function test_approve_page_shows_form_without_session(): void
    {
        // Valid length token but no session in DB
        $token = str_repeat('b', 64);

        $response = $this->get('/approve?token=' . $token);

        $response->assertStatus(200);
        $response->assertSee('Login Goedkeuren');
    }

    // ===================================================================
    // POST /approve — process
    // ===================================================================

    public function test_approve_process_rejects_invalid_token(): void
    {
        $response = $this->post('/approve', [
            'token' => 'invalid',
        ]);

        $response->assertStatus(200);
        $response->assertSee('Ongeldige of verlopen link');
    }

    public function test_approve_process_requires_email(): void
    {
        $token = str_repeat('c', 64);

        $response = $this->post('/approve', [
            'token' => $token,
        ]);

        $response->assertStatus(200);
        $response->assertSee('Vul je email adres in');
    }

    public function test_approve_process_shows_error_for_unknown_email(): void
    {
        $token = str_repeat('d', 64);

        // Create a valid pending session
        AuthQrSession::create([
            'qr_code' => 'qr_approve_test',
            'email_token' => $token,
            'device_info' => ['browser' => 'Firefox', 'os' => 'Linux'],
            'status' => AuthQrSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->post('/approve', [
            'token' => $token,
            'email' => 'unknown@example.com',
        ]);

        $response->assertStatus(200);
        // Error is shown inline in the form (not the error page) because token is still valid
        $response->assertSee('Email niet gevonden');
    }

    public function test_approve_process_success_with_valid_data(): void
    {
        $token = str_repeat('e', 64);

        // Create user
        $user = AuthUser::create([
            'email' => 'henk@havun.nl',
            'name' => 'Henk',
            'password_hash' => Hash::make('password123'),
            'is_admin' => true,
        ]);

        // Create a valid pending session
        AuthQrSession::create([
            'qr_code' => 'qr_success_test',
            'email_token' => $token,
            'device_info' => ['browser' => 'Chrome', 'os' => 'Windows'],
            'status' => AuthQrSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->post('/approve', [
            'token' => $token,
            'email' => 'henk@havun.nl',
        ]);

        $response->assertStatus(200);
        $response->assertSee('Ingelogd!');
        $response->assertSee('Henk');
    }

    // ===================================================================
    // HTML rendering
    // ===================================================================

    public function test_approve_page_contains_tailwind(): void
    {
        $response = $this->get('/approve');

        $response->assertStatus(200);
        $response->assertSee('tailwindcss');
        $response->assertSee('HavunCore');
    }

    public function test_approve_page_contains_csrf_token_in_form(): void
    {
        $token = str_repeat('f', 64);

        $response = $this->get('/approve?token=' . $token);

        $response->assertStatus(200);
        $response->assertSee('name="_token"', false);
    }
}
