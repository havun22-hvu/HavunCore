<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthUser;
use App\Services\DeviceTrustService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(
        private DeviceTrustService $deviceTrustService
    ) {}

    /**
     * Authenticate user from device token
     */
    private function authenticateUser(Request $request): ?array
    {
        $token = $request->bearerToken();

        if (!$token) {
            return null;
        }

        $result = $this->deviceTrustService->verifyToken($token, $request->ip());

        if (!$result['valid']) {
            return null;
        }

        return $result;
    }

    /**
     * GET /api/auth/devices
     * Get all devices for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $auth = $this->authenticateUser($request);

        if (!$auth) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $user = AuthUser::find($auth['user']['id']);
        $currentDeviceId = $auth['device']['id'];

        $devices = $this->deviceTrustService->getUserDevices($user);

        // Mark current device
        $devices = array_map(function ($device) use ($currentDeviceId) {
            $device['is_current'] = $device['id'] === $currentDeviceId;
            return $device;
        }, $devices);

        return response()->json([
            'success' => true,
            'devices' => $devices,
        ]);
    }

    /**
     * DELETE /api/auth/devices/{id}
     * Revoke a specific device
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $auth = $this->authenticateUser($request);

        if (!$auth) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        // Prevent revoking current device via this endpoint
        if ($auth['device']['id'] === $id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot revoke current device. Use logout instead.',
            ], 400);
        }

        $user = AuthUser::find($auth['user']['id']);

        $result = $this->deviceTrustService->revokeDevice(
            $user,
            $id,
            $request->ip()
        );

        if (!$result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    /**
     * POST /api/auth/devices/revoke-all
     * Revoke all devices except current
     */
    public function revokeAll(Request $request): JsonResponse
    {
        $auth = $this->authenticateUser($request);

        if (!$auth) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $user = AuthUser::find($auth['user']['id']);
        $currentDeviceId = $auth['device']['id'];

        $result = $this->deviceTrustService->revokeAllDevices(
            $user,
            $currentDeviceId,
            $request->ip()
        );

        return response()->json($result);
    }

    /**
     * GET /api/auth/logs
     * Get access logs for the authenticated user
     */
    public function logs(Request $request): JsonResponse
    {
        $auth = $this->authenticateUser($request);

        if (!$auth) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $user = AuthUser::find($auth['user']['id']);
        $limit = min($request->input('limit', 20), 100);

        $logs = $this->deviceTrustService->getAccessLogs($user, $limit);

        return response()->json([
            'success' => true,
            'logs' => $logs,
        ]);
    }
}
