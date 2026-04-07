<?php

namespace Tests\Feature;

use App\Models\AuthDevice;
use App\Models\AuthUser;
use App\Models\WebAuthnCredential;
use App\Models\WebAuthnChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAuthnControllerTest extends TestCase
{
    use RefreshDatabase;

    private AuthUser $user;
    private AuthDevice $device;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = AuthUser::create([
            'name' => 'WebAuthn User',
            'email' => 'webauthn@havun.nl',
            'password_hash' => null,
            'is_admin' => false,
        ]);

        $this->token = AuthDevice::generateToken();

        $this->device = AuthDevice::create([
            'user_id' => $this->user->id,
            'device_name' => 'Chrome Windows',
            'device_hash' => hash('sha256', 'webauthn-test-fingerprint'),
            'token' => $this->token,
            'expires_at' => now()->addDays(30),
            'last_used_at' => now(),
            'ip_address' => '127.0.0.1',
            'is_active' => true,
        ]);
    }

    // -- Available --

    public function test_available_without_username_returns_response(): void
    {
        $response = $this->getJson('/api/auth/webauthn/available');

        $response->assertStatus(200)
            ->assertJsonStructure(['available', 'count'])
            ->assertJsonPath('available', false)
            ->assertJsonPath('count', 0);
    }

    public function test_available_with_username_checks_for_credentials(): void
    {
        WebAuthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => 'test-credential-id-abc',
            'public_key' => 'test-public-key',
            'name' => 'Test Passkey',
            'counter' => 0,
            'transports' => ['internal', 'hybrid'],
            'device_type' => 'Windows',
        ]);

        // Search by username (without @havun.nl suffix)
        $response = $this->getJson('/api/auth/webauthn/available?username=webauthn');

        $response->assertStatus(200)
            ->assertJsonPath('available', true)
            ->assertJsonPath('count', 1);
    }

    // -- Login Options --

    public function test_login_options_returns_challenge(): void
    {
        // Need at least one credential for login options to return 200
        WebAuthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => 'test-cred-login',
            'public_key' => 'test-key',
            'name' => 'Passkey',
            'counter' => 0,
            'transports' => ['internal'],
            'device_type' => 'Windows',
        ]);

        $response = $this->getJson('/api/auth/webauthn/login-options');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'challenge',
                'timeout',
                'rpId',
                'userVerification',
                'allowCredentials',
                'available',
            ])
            ->assertJsonPath('available', true)
            ->assertJsonPath('timeout', 60000);

        // Verify challenge was stored in DB
        $this->assertDatabaseCount('webauthn_challenges', 1);
        $this->assertDatabaseHas('webauthn_challenges', ['type' => 'login']);
    }

    public function test_login_options_with_username_returns_user_specific_options(): void
    {
        // Create credential for our user
        WebAuthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => 'user-specific-cred',
            'public_key' => 'test-key',
            'name' => 'Passkey',
            'counter' => 0,
            'transports' => ['internal', 'hybrid'],
            'device_type' => 'Windows',
        ]);

        // Create another user with a credential
        $otherUser = AuthUser::create([
            'name' => 'Other User',
            'email' => 'other@havun.nl',
            'password_hash' => null,
            'is_admin' => false,
        ]);
        WebAuthnCredential::create([
            'user_id' => $otherUser->id,
            'credential_id' => 'other-user-cred',
            'public_key' => 'other-key',
            'name' => 'Other Passkey',
            'counter' => 0,
            'transports' => ['internal'],
            'device_type' => 'macOS',
        ]);

        $response = $this->getJson('/api/auth/webauthn/login-options?username=webauthn');

        $response->assertStatus(200)
            ->assertJsonPath('available', true);

        // Should only contain the user's credential, not the other user's
        $allowCredentials = $response->json('allowCredentials');
        $this->assertCount(1, $allowCredentials);
        $this->assertEquals('user-specific-cred', $allowCredentials[0]['id']);
    }

    public function test_login_options_without_credentials_returns_404(): void
    {
        $response = $this->getJson('/api/auth/webauthn/login-options');

        $response->assertStatus(404)
            ->assertJsonPath('available', false)
            ->assertJsonPath('error', 'No passkeys registered');
    }

    // -- Register Options --

    public function test_register_options_without_auth_returns_401(): void
    {
        $response = $this->getJson('/api/auth/webauthn/register-options');

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Authentication required');
    }

    public function test_register_options_with_auth_returns_challenge(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/auth/webauthn/register-options');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'challenge',
                'rp' => ['name', 'id'],
                'user' => ['id', 'name', 'displayName'],
                'pubKeyCredParams',
                'timeout',
                'attestation',
                'authenticatorSelection',
                'excludeCredentials',
            ])
            ->assertJsonPath('rp.name', 'HavunCore')
            ->assertJsonPath('user.name', 'webauthn@havun.nl')
            ->assertJsonPath('user.displayName', 'WebAuthn User')
            ->assertJsonPath('timeout', 60000)
            ->assertJsonPath('attestation', 'none');

        // Verify challenge was stored
        $this->assertDatabaseCount('webauthn_challenges', 1);
        $this->assertDatabaseHas('webauthn_challenges', [
            'user_id' => $this->user->id,
            'type' => 'register',
        ]);
    }

    // -- Register --

    public function test_register_without_auth_returns_401(): void
    {
        $response = $this->postJson('/api/auth/webauthn/register');

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Authentication required');
    }

    // -- Credentials --

    public function test_credentials_without_auth_returns_401(): void
    {
        $response = $this->getJson('/api/auth/webauthn/credentials');

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Authentication required');
    }

    public function test_credentials_returns_user_passkeys(): void
    {
        WebAuthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => 'cred-1',
            'public_key' => 'key-1',
            'name' => 'Windows Passkey',
            'counter' => 5,
            'transports' => ['internal'],
            'device_type' => 'Windows',
        ]);

        WebAuthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => 'cred-2',
            'public_key' => 'key-2',
            'name' => 'macOS Passkey',
            'counter' => 3,
            'transports' => ['internal', 'hybrid'],
            'device_type' => 'macOS',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/auth/webauthn/credentials');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'credentials')
            ->assertJsonStructure([
                'credentials' => [
                    '*' => ['id', 'name', 'device_type', 'last_used_at', 'created_at'],
                ],
            ]);
    }

    // -- Delete Credential --

    public function test_delete_credential_without_auth_returns_401(): void
    {
        $response = $this->deleteJson('/api/auth/webauthn/credentials/1');

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Authentication required');
    }

    public function test_delete_credential_removes_credential(): void
    {
        $credential = WebAuthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => 'cred-to-delete',
            'public_key' => 'key-delete',
            'name' => 'Delete Me',
            'counter' => 0,
            'transports' => ['internal'],
            'device_type' => 'Windows',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->deleteJson("/api/auth/webauthn/credentials/{$credential->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Passkey verwijderd');

        $this->assertDatabaseMissing('webauthn_credentials', [
            'id' => $credential->id,
        ]);
    }

    public function test_delete_credential_returns_404_for_other_users_credential(): void
    {
        $otherUser = AuthUser::create([
            'name' => 'Other User',
            'email' => 'other@havun.nl',
            'password_hash' => null,
            'is_admin' => false,
        ]);

        $credential = WebAuthnCredential::create([
            'user_id' => $otherUser->id,
            'credential_id' => 'other-cred',
            'public_key' => 'other-key',
            'name' => 'Other Passkey',
            'counter' => 0,
            'transports' => ['internal'],
            'device_type' => 'macOS',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->deleteJson("/api/auth/webauthn/credentials/{$credential->id}");

        $response->assertStatus(404)
            ->assertJsonPath('error', 'Credential not found');

        // Credential should still exist
        $this->assertDatabaseHas('webauthn_credentials', [
            'id' => $credential->id,
        ]);
    }

    // -- Login --

    public function test_login_with_invalid_credential_returns_error(): void
    {
        // Create a valid challenge first
        $challenge = WebAuthnChallenge::createForLogin(null);

        // Build fake clientDataJSON with the correct challenge
        $clientData = json_encode([
            'type' => 'webauthn.get',
            'challenge' => rtrim(strtr(base64_encode($challenge->challenge), '+/', '-_'), '='),
            'origin' => 'https://havuncore.havun.nl',
        ]);
        $clientDataB64 = rtrim(strtr(base64_encode($clientData), '+/', '-_'), '=');

        // Build fake authenticatorData (at least 37 bytes with counter=1)
        $authenticatorData = str_repeat("\0", 33) . pack('N', 1) . "\0";
        $authDataB64 = rtrim(strtr(base64_encode($authenticatorData), '+/', '-_'), '=');

        $response = $this->postJson('/api/auth/webauthn/login', [
            'credential' => [
                'id' => 'non-existent-credential-id',
                'rawId' => rtrim(strtr(base64_encode('non-existent'), '+/', '-_'), '='),
                'type' => 'public-key',
                'response' => [
                    'authenticatorData' => $authDataB64,
                    'clientDataJSON' => $clientDataB64,
                    'signature' => rtrim(strtr(base64_encode('fake-sig'), '+/', '-_'), '='),
                ],
            ],
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Passkey niet gevonden');
    }

    // -- Login validation --

    public function test_login_validation_requires_credential(): void
    {
        $response = $this->postJson('/api/auth/webauthn/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['credential']);
    }

    // -- Login with valid credential but invalid challenge --

    public function test_login_with_valid_credential_but_invalid_challenge(): void
    {
        $credential = WebAuthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => 'valid-cred-for-login',
            'public_key' => 'test-key',
            'name' => 'Test Passkey',
            'counter' => 0,
            'transports' => ['internal'],
            'device_type' => 'Windows',
        ]);

        // Build clientDataJSON with a WRONG challenge (not in DB)
        $clientData = json_encode([
            'type' => 'webauthn.get',
            'challenge' => rtrim(strtr(base64_encode('invalid-challenge-not-in-db'), '+/', '-_'), '='),
            'origin' => 'https://havuncore.havun.nl',
        ]);
        $clientDataB64 = rtrim(strtr(base64_encode($clientData), '+/', '-_'), '=');

        $authenticatorData = str_repeat("\0", 33) . pack('N', 1) . "\0";
        $authDataB64 = rtrim(strtr(base64_encode($authenticatorData), '+/', '-_'), '=');

        $response = $this->postJson('/api/auth/webauthn/login', [
            'credential' => [
                'id' => 'valid-cred-for-login',
                'rawId' => rtrim(strtr(base64_encode('valid-cred'), '+/', '-_'), '='),
                'type' => 'public-key',
                'response' => [
                    'authenticatorData' => $authDataB64,
                    'clientDataJSON' => $clientDataB64,
                    'signature' => rtrim(strtr(base64_encode('fake-sig'), '+/', '-_'), '='),
                ],
            ],
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Ongeldige of verlopen challenge');
    }

    // -- Login with valid credential and valid challenge (full flow) --

    public function test_login_full_flow_with_valid_credential_and_challenge(): void
    {
        $credential = WebAuthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => 'full-flow-cred',
            'public_key' => 'test-key',
            'name' => 'Test Passkey',
            'counter' => 0,
            'transports' => ['internal'],
            'device_type' => 'Windows',
        ]);

        // Create a real challenge
        $challenge = WebAuthnChallenge::createForLogin($this->user->id);

        // Build clientDataJSON with the correct challenge
        $clientData = json_encode([
            'type' => 'webauthn.get',
            'challenge' => rtrim(strtr(base64_encode($challenge->challenge), '+/', '-_'), '='),
            'origin' => 'https://havuncore.havun.nl',
        ]);
        $clientDataB64 = rtrim(strtr(base64_encode($clientData), '+/', '-_'), '=');

        // Build authenticatorData with counter > 0
        $authenticatorData = str_repeat("\0", 33) . pack('N', 5) . "\0";
        $authDataB64 = rtrim(strtr(base64_encode($authenticatorData), '+/', '-_'), '=');

        $response = $this->postJson('/api/auth/webauthn/login', [
            'credential' => [
                'id' => 'full-flow-cred',
                'rawId' => rtrim(strtr(base64_encode('full-flow'), '+/', '-_'), '='),
                'type' => 'public-key',
                'response' => [
                    'authenticatorData' => $authDataB64,
                    'clientDataJSON' => $clientDataB64,
                    'signature' => rtrim(strtr(base64_encode('fake-sig'), '+/', '-_'), '='),
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Ingelogd met passkey')
            ->assertJsonStructure(['device_token', 'user'])
            ->assertJsonPath('user.email', 'webauthn@havun.nl');

        // Challenge should be deleted after use
        $this->assertDatabaseMissing('webauthn_challenges', ['id' => $challenge->id]);

        // Credential counter should be updated
        $this->assertEquals(5, $credential->fresh()->counter);
    }

    // -- Login with counter replay attack --

    public function test_login_rejects_counter_going_backwards(): void
    {
        $credential = WebAuthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => 'replay-cred',
            'public_key' => 'test-key',
            'name' => 'Replay Passkey',
            'counter' => 10, // Current counter is 10
            'transports' => ['internal'],
            'device_type' => 'Windows',
        ]);

        $challenge = WebAuthnChallenge::createForLogin($this->user->id);

        $clientData = json_encode([
            'type' => 'webauthn.get',
            'challenge' => rtrim(strtr(base64_encode($challenge->challenge), '+/', '-_'), '='),
            'origin' => 'https://havuncore.havun.nl',
        ]);
        $clientDataB64 = rtrim(strtr(base64_encode($clientData), '+/', '-_'), '=');

        // Counter value 5 < 10 (current) - should be rejected
        $authenticatorData = str_repeat("\0", 33) . pack('N', 5) . "\0";
        $authDataB64 = rtrim(strtr(base64_encode($authenticatorData), '+/', '-_'), '=');

        $response = $this->postJson('/api/auth/webauthn/login', [
            'credential' => [
                'id' => 'replay-cred',
                'rawId' => rtrim(strtr(base64_encode('replay'), '+/', '-_'), '='),
                'type' => 'public-key',
                'response' => [
                    'authenticatorData' => $authDataB64,
                    'clientDataJSON' => $clientDataB64,
                    'signature' => rtrim(strtr(base64_encode('fake-sig'), '+/', '-_'), '='),
                ],
            ],
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'Beveiligingsfout: counter mismatch');
    }

    // -- Login with invalid clientDataJSON --

    public function test_login_with_invalid_client_data_json(): void
    {
        $credential = WebAuthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => 'bad-client-cred',
            'public_key' => 'test-key',
            'name' => 'Bad Client',
            'counter' => 0,
            'transports' => ['internal'],
            'device_type' => 'Windows',
        ]);

        // clientDataJSON that is not valid JSON
        $clientDataB64 = rtrim(strtr(base64_encode('not-json'), '+/', '-_'), '=');

        $authenticatorData = str_repeat("\0", 33) . pack('N', 1) . "\0";
        $authDataB64 = rtrim(strtr(base64_encode($authenticatorData), '+/', '-_'), '=');

        $response = $this->postJson('/api/auth/webauthn/login', [
            'credential' => [
                'id' => 'bad-client-cred',
                'rawId' => rtrim(strtr(base64_encode('bad'), '+/', '-_'), '='),
                'type' => 'public-key',
                'response' => [
                    'authenticatorData' => $authDataB64,
                    'clientDataJSON' => $clientDataB64,
                    'signature' => rtrim(strtr(base64_encode('fake-sig'), '+/', '-_'), '='),
                ],
            ],
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Ongeldige client data');
    }

    // -- Register with invalid token --

    public function test_register_with_invalid_auth_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_token_here',
        ])->postJson('/api/auth/webauthn/register', [
            'credential' => [
                'id' => 'test',
                'rawId' => 'test',
                'type' => 'public-key',
                'response' => [
                    'clientDataJSON' => 'test',
                    'attestationObject' => 'test',
                ],
            ],
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Invalid token');
    }

    // -- Register options with invalid token --

    public function test_register_options_with_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_token',
        ])->getJson('/api/auth/webauthn/register-options');

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Invalid token');
    }

    // -- Register options excludes existing credentials --

    public function test_register_options_excludes_existing_credentials(): void
    {
        WebAuthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => 'existing-cred',
            'public_key' => 'existing-key',
            'name' => 'Existing Passkey',
            'counter' => 0,
            'transports' => ['internal', 'hybrid'],
            'device_type' => 'Windows',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/auth/webauthn/register-options');

        $response->assertOk();

        $excludeCredentials = $response->json('excludeCredentials');
        $this->assertCount(1, $excludeCredentials);
        $this->assertEquals('existing-cred', $excludeCredentials[0]['id']);
    }

    // -- Credentials with invalid token --

    public function test_credentials_with_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer bad_token',
        ])->getJson('/api/auth/webauthn/credentials');

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Invalid token');
    }

    // -- Delete credential with invalid token --

    public function test_delete_credential_with_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer bad_token',
        ])->deleteJson('/api/auth/webauthn/credentials/1');

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Invalid token');
    }

    // -- Delete nonexistent credential --

    public function test_delete_nonexistent_credential_returns_404(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->deleteJson('/api/auth/webauthn/credentials/99999');

        $response->assertStatus(404)
            ->assertJsonPath('error', 'Credential not found');
    }

    // -- Available with full email --

    public function test_available_with_full_email_as_username(): void
    {
        WebAuthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => 'email-cred',
            'public_key' => 'email-key',
            'name' => 'Email Passkey',
            'counter' => 0,
            'transports' => ['internal'],
            'device_type' => 'Windows',
        ]);

        // Using full email should also find the user
        $response = $this->getJson('/api/auth/webauthn/available?username=webauthn@havun.nl');

        $response->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('count', 1);
    }

    // -- Available falls back to total count --

    public function test_available_with_unknown_username_falls_back_to_total_count(): void
    {
        // Create a credential for another user
        $other = AuthUser::create([
            'name' => 'Other',
            'email' => 'other@havun.nl',
            'password_hash' => null,
            'is_admin' => false,
        ]);

        WebAuthnCredential::create([
            'user_id' => $other->id,
            'credential_id' => 'other-cred-avail',
            'public_key' => 'other-key',
            'name' => 'Other Passkey',
            'counter' => 0,
            'transports' => ['internal'],
            'device_type' => 'macOS',
        ]);

        // Query with unknown username — no user-specific creds, but total > 0
        $response = $this->getJson('/api/auth/webauthn/available?username=nobody');

        $response->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('count', 1);
    }

    // -- Login options with full email username --

    public function test_login_options_with_full_email(): void
    {
        WebAuthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => 'email-login-cred',
            'public_key' => 'test-key',
            'name' => 'Passkey',
            'counter' => 0,
            'transports' => ['internal'],
            'device_type' => 'Windows',
        ]);

        $response = $this->getJson('/api/auth/webauthn/login-options?username=webauthn@havun.nl');

        $response->assertOk()
            ->assertJsonPath('available', true);

        $allowCredentials = $response->json('allowCredentials');
        $this->assertCount(1, $allowCredentials);
        $this->assertEquals('email-login-cred', $allowCredentials[0]['id']);
    }

    // -- Login with short authenticator data --

    public function test_login_with_short_authenticator_data_returns_null_counter(): void
    {
        $credential = WebAuthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => 'short-auth-cred',
            'public_key' => 'test-key',
            'name' => 'Short Auth',
            'counter' => 0,
            'transports' => ['internal'],
            'device_type' => 'Windows',
        ]);

        $challenge = WebAuthnChallenge::createForLogin($this->user->id);

        $clientData = json_encode([
            'type' => 'webauthn.get',
            'challenge' => rtrim(strtr(base64_encode($challenge->challenge), '+/', '-_'), '='),
            'origin' => 'https://havuncore.havun.nl',
        ]);
        $clientDataB64 = rtrim(strtr(base64_encode($clientData), '+/', '-_'), '=');

        // Short authenticator data (< 37 bytes) - extractCounter returns null
        $authenticatorData = str_repeat("\0", 20);
        $authDataB64 = rtrim(strtr(base64_encode($authenticatorData), '+/', '-_'), '=');

        $response = $this->postJson('/api/auth/webauthn/login', [
            'credential' => [
                'id' => 'short-auth-cred',
                'rawId' => rtrim(strtr(base64_encode('short'), '+/', '-_'), '='),
                'type' => 'public-key',
                'response' => [
                    'authenticatorData' => $authDataB64,
                    'clientDataJSON' => $clientDataB64,
                    'signature' => rtrim(strtr(base64_encode('fake-sig'), '+/', '-_'), '='),
                ],
            ],
        ]);

        // Should succeed — null counter falls through to counter + 1
        $response->assertOk()
            ->assertJsonPath('success', true);

        // Counter should be incremented by 1 (fallback)
        $this->assertEquals(1, $credential->fresh()->counter);
    }
}
