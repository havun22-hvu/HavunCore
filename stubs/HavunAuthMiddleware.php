<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * HavunCore Auth Middleware
 *
 * Copy this file to your project: app/Http/Middleware/HavunAuthMiddleware.php
 * Then register it in your bootstrap/app.php or app/Http/Kernel.php
 */
class HavunAuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->getDeviceToken($request);

        if (!$token) {
            return $this->redirectToLogin($request);
        }

        // Verify token with HavunCore (with caching)
        $user = $this->verifyToken($token, $request->ip());

        if (!$user) {
            // Clear invalid token
            $this->clearDeviceToken();
            return $this->redirectToLogin($request);
        }

        // Store user in request for controllers
        $request->attributes->set('havun_user', $user);
        $request->attributes->set('havun_token', $token);

        return $next($request);
    }

    /**
     * Get device token from cookie or header
     */
    protected function getDeviceToken(Request $request): ?string
    {
        $cookieName = config('havun-auth.cookie_name', 'havun_device_token');

        // Check cookie first
        $token = $request->cookie($cookieName);

        // Fall back to Authorization header
        if (!$token) {
            $token = $request->bearerToken();
        }

        return $token;
    }

    /**
     * Verify token with HavunCore API
     */
    protected function verifyToken(string $token, ?string $ipAddress = null): ?array
    {
        // Cache verification for 5 minutes to reduce API calls
        $cacheKey = 'havun_auth_' . hash('sha256', $token);

        return Cache::remember($cacheKey, 300, function () use ($token, $ipAddress) {
            try {
                $apiUrl = config('havun-auth.api_url', 'https://havuncore.havun.nl');

                $response = Http::withToken($token)
                    ->timeout(10)
                    ->post("{$apiUrl}/api/auth/verify");

                if ($response->successful() && $response->json('valid')) {
                    return $response->json('user');
                }
            } catch (\Exception $e) {
                // Log error but don't fail - allow cached auth
                logger()->warning('HavunCore auth verification failed', [
                    'error' => $e->getMessage(),
                ]);
            }

            return null;
        });
    }

    /**
     * Clear device token cookie
     */
    protected function clearDeviceToken(): void
    {
        $cookieName = config('havun-auth.cookie_name', 'havun_device_token');
        cookie()->queue(cookie()->forget($cookieName));
    }

    /**
     * Redirect to login page
     */
    protected function redirectToLogin(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Please login to continue',
            ], 401);
        }

        $loginRoute = config('havun-auth.login_route', 'login');

        return redirect()->route($loginRoute)->with('intended', $request->url());
    }
}
