<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * HavunCore Auth Controller
 *
 * Copy this file to your project: app/Http/Controllers/HavunAuthController.php
 * Then add routes in routes/web.php
 */
class HavunAuthController extends Controller
{
    protected string $apiUrl;
    protected string $cookieName;
    protected int $trustDays;

    public function __construct()
    {
        $this->apiUrl = config('havun-auth.api_url', 'https://havuncore.havun.nl');
        $this->cookieName = config('havun-auth.cookie_name', 'havun_device_token');
        $this->trustDays = config('havun-auth.trust_days', 30);
    }

    /**
     * Show login page
     */
    public function showLogin()
    {
        // Check if already logged in
        $token = request()->cookie($this->cookieName);
        if ($token && $this->verifyToken($token)) {
            return redirect(config('havun-auth.redirect_after_login', '/dashboard'));
        }

        return view('auth.login', [
            'qr_enabled' => config('havun-auth.qr_enabled', true),
            'password_enabled' => config('havun-auth.password_enabled', true),
            'havuncore_api' => $this->apiUrl,
        ]);
    }

    /**
     * Generate QR code for login
     */
    public function generateQr(Request $request)
    {
        try {
            $response = Http::post("{$this->apiUrl}/api/auth/qr/generate", [
                'browser' => $request->input('browser', 'Unknown'),
                'os' => $request->input('os', 'Unknown'),
            ]);

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate QR code',
            ], 500);
        }
    }

    /**
     * Check QR status
     */
    public function checkQrStatus(string $code)
    {
        try {
            $response = Http::get("{$this->apiUrl}/api/auth/qr/{$code}/status");
            $data = $response->json();

            // If approved, set cookie
            if (($data['status'] ?? '') === 'approved' && isset($data['device_token'])) {
                $cookie = cookie(
                    $this->cookieName,
                    $data['device_token'],
                    $this->trustDays * 24 * 60, // minutes
                    '/',
                    config('havun-auth.cookie_domain'),
                    true, // secure
                    true  // httpOnly
                );

                return response()->json($data)->withCookie($cookie);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check QR status',
            ], 500);
        }
    }

    /**
     * Handle password login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        try {
            $response = Http::post("{$this->apiUrl}/api/auth/login", [
                'email' => $request->input('email'),
                'password' => $request->input('password'),
                'browser' => $request->input('browser', 'Unknown'),
                'os' => $request->input('os', 'Unknown'),
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['success'] ?? false)) {
                $cookie = cookie(
                    $this->cookieName,
                    $data['device_token'],
                    $this->trustDays * 24 * 60,
                    '/',
                    config('havun-auth.cookie_domain'),
                    true,
                    true
                );

                if ($request->expectsJson()) {
                    return response()->json($data)->withCookie($cookie);
                }

                return redirect(config('havun-auth.redirect_after_login', '/dashboard'))
                    ->withCookie($cookie);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $data['message'] ?? 'Login failed',
                ], 401);
            }

            return back()->withErrors(['email' => $data['message'] ?? 'Invalid credentials']);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Login service unavailable',
                ], 500);
            }

            return back()->withErrors(['email' => 'Login service unavailable']);
        }
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        $token = $request->cookie($this->cookieName);

        if ($token) {
            try {
                Http::withToken($token)->post("{$this->apiUrl}/api/auth/logout");
            } catch (\Exception $e) {
                // Ignore logout errors
            }
        }

        $cookie = cookie()->forget($this->cookieName);

        if ($request->expectsJson()) {
            return response()->json(['success' => true])->withCookie($cookie);
        }

        return redirect('/')->withCookie($cookie);
    }

    /**
     * Verify token
     */
    protected function verifyToken(string $token): bool
    {
        try {
            $response = Http::withToken($token)
                ->post("{$this->apiUrl}/api/auth/verify");

            return $response->successful() && $response->json('valid');
        } catch (\Exception $e) {
            return false;
        }
    }
}
