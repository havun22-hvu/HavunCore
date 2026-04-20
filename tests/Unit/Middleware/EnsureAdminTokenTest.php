<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureAdminToken;
use App\Services\DeviceTrustService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * The admin gate in front of the Vault routes. Regressions here are
 * direct privilege-escalation paths: every Vault read/write is gated
 * behind this middleware (see `critical-paths-havuncore.md` Pad 1).
 *
 * Four failure modes are pinned:
 *
 *   1. No Authorization header → 401 "Authentication required".
 *   2. Token that DeviceTrust rejects → 401 "Invalid or expired token"
 *      (same response as "deleted user" — prevents enumeration oracle).
 *   3. Valid token but user.is_admin = false → 403.
 *   4. Valid + admin → next() invoked with resolved user.
 */
class EnsureAdminTokenTest extends TestCase
{
    private function middleware(DeviceTrustService $deviceTrust): EnsureAdminToken
    {
        return new EnsureAdminToken($deviceTrust);
    }

    public function test_missing_bearer_token_returns_401(): void
    {
        $deviceTrust = $this->mock(DeviceTrustService::class);
        // Never called — middleware must short-circuit before service-lookup.
        $deviceTrust->shouldNotReceive('verifyToken');

        $response = $this->middleware($deviceTrust)->handle(
            Request::create('/admin'),
            fn () => throw new \RuntimeException('next() must not be called'),
        );

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Authentication required', $response->getData(true)['error']);
    }

    public function test_invalid_token_returns_401_with_neutral_message(): void
    {
        $deviceTrust = $this->mock(DeviceTrustService::class);
        $deviceTrust->shouldReceive('verifyToken')
            ->once()
            ->andReturn(['valid' => false]);

        $request = Request::create('/admin', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer bogus']);

        $response = $this->middleware($deviceTrust)->handle(
            $request,
            fn () => throw new \RuntimeException('next() must not be called'),
        );

        $this->assertSame(401, $response->getStatusCode());
        // Same message for "invalid" and "deleted-user" — prevents
        // enumeration (§docstring).
        $this->assertSame('Invalid or expired token', $response->getData(true)['error']);
    }

    public function test_deleted_user_returns_same_401_as_invalid_token(): void
    {
        $deviceTrust = $this->mock(DeviceTrustService::class);
        // verifyToken says "valid" but user_model missing (= deleted account).
        $deviceTrust->shouldReceive('verifyToken')
            ->once()
            ->andReturn(['valid' => true, 'user_model' => null]);

        $request = Request::create('/admin', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer stale']);

        $response = $this->middleware($deviceTrust)->handle(
            $request,
            fn () => throw new \RuntimeException('next() must not be called'),
        );

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(
            'Invalid or expired token',
            $response->getData(true)['error'],
            'Deleted-user must return the same body as invalid-token to avoid enumeration'
        );
    }

    public function test_non_admin_user_returns_403(): void
    {
        $nonAdmin = new class {
            public bool $is_admin = false;
        };

        $deviceTrust = $this->mock(DeviceTrustService::class);
        $deviceTrust->shouldReceive('verifyToken')
            ->once()
            ->andReturn(['valid' => true, 'user_model' => $nonAdmin]);

        $request = Request::create('/admin', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer ok']);

        $response = $this->middleware($deviceTrust)->handle(
            $request,
            fn () => throw new \RuntimeException('next() must not be called'),
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Admin privileges required', $response->getData(true)['error']);
    }

    public function test_admin_user_forwards_and_sets_user_resolver(): void
    {
        $admin = new class {
            public bool $is_admin = true;
            public string $email = 'henk@example.test';
        };

        $deviceTrust = $this->mock(DeviceTrustService::class);
        $deviceTrust->shouldReceive('verifyToken')
            ->once()
            ->andReturn(['valid' => true, 'user_model' => $admin]);

        $request = Request::create('/admin', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer good']);

        $called = false;
        $response = $this->middleware($deviceTrust)->handle(
            $request,
            function (Request $r) use (&$called, $admin) {
                $called = true;
                $this->assertSame($admin, $r->user());

                return new Response('ok');
            },
        );

        $this->assertTrue($called, 'next() must be invoked for an admin with a valid token');
        $this->assertSame(200, $response->getStatusCode());
    }
}
