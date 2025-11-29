<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthUser;
use App\Models\AuthDevice;
use App\Services\QrAuthService;
use App\Services\DeviceTrustService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrAuthController extends Controller
{
    public function __construct(
        private QrAuthService $qrAuthService,
        private DeviceTrustService $deviceTrustService
    ) {}

    /**
     * POST /api/auth/qr/generate
     * Generate a new QR code for login
     */
    public function generateQr(Request $request): JsonResponse
    {
        $deviceInfo = [
            'browser' => $request->input('browser', 'Unknown'),
            'os' => $request->input('os', 'Unknown'),
            'device_name' => $request->input('device_name'),
            'user_agent' => $request->userAgent(),
        ];

        $result = $this->qrAuthService->generateQrSession(
            $deviceInfo,
            $request->ip()
        );

        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }

    /**
     * GET /api/auth/qr/{code}/status
     * Check the status of a QR session
     */
    public function checkQrStatus(string $code): JsonResponse
    {
        $result = $this->qrAuthService->checkQrStatus($code);

        return response()->json($result);
    }

    /**
     * POST /api/auth/qr/{code}/approve
     * Approve a QR session from mobile device (requires auth)
     */
    public function approveQr(Request $request, string $code): JsonResponse
    {
        // Get authenticated user from device token
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $verification = $this->deviceTrustService->verifyToken($token, $request->ip());

        if (!$verification['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid device token',
            ], 401);
        }

        $user = AuthUser::find($verification['user']['id']);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $deviceInfo = [
            'browser' => $request->input('browser'),
            'os' => $request->input('os'),
            'user_agent' => $request->userAgent(),
        ];

        $result = $this->qrAuthService->approveQrSession(
            $code,
            $user,
            $deviceInfo,
            $request->ip()
        );

        return response()->json($result);
    }

    /**
     * POST /api/auth/login
     * Login with email and password (fallback)
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $deviceInfo = [
            'browser' => $request->input('browser', 'Unknown'),
            'os' => $request->input('os', 'Unknown'),
            'user_agent' => $request->userAgent(),
        ];

        $result = $this->qrAuthService->loginWithPassword(
            $request->input('email'),
            $request->input('password'),
            $deviceInfo,
            $request->ip()
        );

        if (!$result['success']) {
            return response()->json($result, 401);
        }

        return response()->json($result);
    }

    /**
     * POST /api/auth/logout
     * Logout (revoke current device)
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'No token provided',
            ], 400);
        }

        $result = $this->deviceTrustService->logout($token, $request->ip());

        return response()->json($result);
    }

    /**
     * POST /api/auth/verify
     * Verify device token and get user info
     */
    public function verify(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'valid' => false,
                'message' => 'No token provided',
            ], 401);
        }

        $result = $this->deviceTrustService->verifyToken($token, $request->ip());

        if (!$result['valid']) {
            return response()->json($result, 401);
        }

        return response()->json($result);
    }

    /**
     * POST /api/auth/qr/{code}/send-email
     * Send login email for QR session
     */
    public function sendEmail(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'callback_url' => 'required|url',
            'site_name' => 'nullable|string|max:100',
        ]);

        $result = $this->qrAuthService->sendLoginEmail(
            $code,
            $request->input('email'),
            $request->input('callback_url'),
            $request->input('site_name')
        );

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * POST /api/auth/email/approve
     * Approve login via email token
     */
    public function approveEmail(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:64',
        ]);

        $result = $this->qrAuthService->approveViaEmailToken(
            $request->input('token'),
            $request->ip()
        );

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * POST /api/auth/qr/approve-authenticated
     * Approve QR session using device token from mobile app
     */
    public function approveAuthenticated(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:64',
            'device_token' => 'required|string',
        ]);

        $deviceToken = $request->input('device_token');

        // Verify the device token
        $verification = $this->deviceTrustService->verifyToken($deviceToken, $request->ip());

        if (!$verification['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Ongeldige of verlopen sessie op dit apparaat',
            ], 401);
        }

        $user = AuthUser::find($verification['user']['id']);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Gebruiker niet gevonden',
            ], 404);
        }

        // Approve the QR session using the email token
        $result = $this->qrAuthService->approveViaEmailToken(
            $request->input('token'),
            $request->ip()
        );

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login goedgekeurd',
            'device_name' => $result['device_name'] ?? 'Onbekend apparaat',
            'user' => [
                'name' => $user->name,
            ],
        ]);
    }

    /**
     * POST /api/auth/register
     * Register a new user (admin only or first user)
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|unique:auth_users,email',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8',
        ]);

        // Check if this is the first user (make them admin)
        $isFirstUser = AuthUser::count() === 0;

        // If not first user, require admin authentication
        if (!$isFirstUser) {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin authentication required',
                ], 401);
            }

            $verification = $this->deviceTrustService->verifyToken($token);

            if (!$verification['valid'] || !$verification['user']['is_admin']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin privileges required',
                ], 403);
            }
        }

        $user = AuthUser::create([
            'email' => $request->input('email'),
            'name' => $request->input('name'),
            'password_hash' => bcrypt($request->input('password')),
            'is_admin' => $isFirstUser || $request->input('is_admin', false),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'is_admin' => $user->is_admin,
            ],
        ], 201);
    }
}
