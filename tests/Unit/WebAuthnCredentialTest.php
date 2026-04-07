<?php

namespace Tests\Unit;

use App\Models\AuthUser;
use App\Models\WebAuthnCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAuthnCredentialTest extends TestCase
{
    use RefreshDatabase;

    private AuthUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = AuthUser::create([
            'name' => 'Cred User', 'email' => 'cred@havun.nl',
            'password_hash' => null, 'is_admin' => false,
        ]);
    }

    public function test_increment_counter(): void
    {
        $credential = WebAuthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => 'inc-test',
            'public_key' => 'key',
            'name' => 'Test',
            'counter' => 5,
            'transports' => ['internal'],
            'device_type' => 'Windows',
        ]);

        $credential->incrementCounter();

        $fresh = $credential->fresh();
        $this->assertEquals(6, $fresh->counter);
        $this->assertNotNull($fresh->last_used_at);
    }
}
