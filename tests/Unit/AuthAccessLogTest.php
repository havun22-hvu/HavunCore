<?php

namespace Tests\Unit;

use App\Models\AuthAccessLog;
use App\Models\AuthUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAccessLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_for_user(): void
    {
        $user = AuthUser::create([
            'name' => 'Log User', 'email' => 'log@havun.nl',
            'password_hash' => null, 'is_admin' => false,
        ]);

        AuthAccessLog::log(AuthAccessLog::ACTION_LOGIN, $user->id, 'Chrome', '1.2.3.4');
        AuthAccessLog::log(AuthAccessLog::ACTION_QR_APPROVE, $user->id, 'Firefox', '1.2.3.5');
        AuthAccessLog::log(AuthAccessLog::ACTION_LOGIN, null, 'Safari', '5.6.7.8'); // other user

        $logs = AuthAccessLog::recentForUser($user->id);
        $this->assertCount(2, $logs);
    }

    public function test_recent_for_user_respects_limit(): void
    {
        $user = AuthUser::create([
            'name' => 'Limit User', 'email' => 'limit@havun.nl',
            'password_hash' => null, 'is_admin' => false,
        ]);

        for ($i = 0; $i < 5; $i++) {
            AuthAccessLog::log(AuthAccessLog::ACTION_LOGIN, $user->id);
        }

        $logs = AuthAccessLog::recentForUser($user->id, 3);
        $this->assertCount(3, $logs);
    }

    public function test_user_relationship(): void
    {
        $user = AuthUser::create([
            'name' => 'Rel User', 'email' => 'rel@havun.nl',
            'password_hash' => null, 'is_admin' => false,
        ]);

        $log = AuthAccessLog::log(AuthAccessLog::ACTION_LOGIN, $user->id);

        $this->assertNotNull($log->user);
        $this->assertEquals($user->id, $log->user->id);
    }
}
