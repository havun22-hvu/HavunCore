<?php

namespace Tests\Unit;

use App\Models\AuthUser;
use App\Models\WebAuthnChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAuthnChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_expired_returns_true_for_past_date(): void
    {
        $challenge = WebAuthnChallenge::create([
            'user_id' => null,
            'challenge' => 'test-challenge',
            'type' => 'login',
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertTrue($challenge->isExpired());
    }

    public function test_is_expired_returns_false_for_future_date(): void
    {
        $challenge = WebAuthnChallenge::create([
            'user_id' => null,
            'challenge' => 'test-challenge',
            'type' => 'login',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->assertFalse($challenge->isExpired());
    }

    public function test_user_relationship(): void
    {
        $user = AuthUser::create([
            'name' => 'Test', 'email' => 'test@havun.nl',
            'password_hash' => null, 'is_admin' => false,
        ]);

        $challenge = WebAuthnChallenge::createForRegistration($user->id);

        $this->assertNotNull($challenge->user);
        $this->assertEquals($user->id, $challenge->user->id);
    }
}
