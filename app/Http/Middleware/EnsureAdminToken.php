<?php

namespace App\Http\Middleware;

use App\Models\AuthUser;
use App\Services\DeviceTrustService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token-based admin gate for the custom AuthUser/DeviceTrustService stack.
 *
 * Why a middleware instead of FormRequest::authorize(): authorize() only fires for
 * write-routes that have a FormRequest. The Vault admin group also exposes GET
 * (list/logs) that need the same protection — middleware on the route-group covers
 * everything in one place.
 */
class EnsureAdminToken
{
    public function __construct(private DeviceTrustService $deviceTrust)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $verification = $this->deviceTrust->verifyToken($token, $request->ip());

        if (! ($verification['valid'] ?? false)) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        $user = AuthUser::find($verification['user']['id'] ?? null);

        if (! $user) {
            return response()->json(['error' => 'User not found'], 401);
        }

        if (! $user->is_admin) {
            return response()->json(['error' => 'Admin privileges required'], 403);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
