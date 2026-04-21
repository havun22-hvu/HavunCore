<?php

namespace App\Services;

use App\Models\AuthUser;
use App\Models\AuthDevice;
use App\Models\AuthAccessLog;

class DeviceTrustService
{
    /**
     * Verify device token and get user
     */
    public function verifyToken(string $token, ?string $ipAddress = null): array
    {
        $device = AuthDevice::findByToken($token);

        if (!$device) {
            return [
                'valid' => false,
                'message' => 'Device not found or expired',
            ];
        }

        // Update last used
        $device->touchUsed($ipAddress);

        // Extend trust if close to expiry (within 7 days).
        // NOTE (2026-04-21, mutation-run pad 4): de vorige versie gebruikte
        // `$device->expires_at->diffInDays(now()) < 7` — voor niet-verlopen
        // devices retourneert Carbon daar een negatief getal (expires_at is
        // na nu), dus de conditie was altijd true en elke verify extendde
        // trust onvoorwaardelijk. Contract is "alleen als binnen 7 dagen
        // tot expiry", dus we vergelijken de expiry direct met now+7d.
        if ($device->expires_at->lt(now()->addDays(7))) {
            $device->extendTrust();
        }

        $user = $device->user;

        return [
            'valid' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
            ],
            // Eloquent instance for callers that need the model (e.g. middleware
            // that sets the user resolver) — skips a duplicate AuthUser::find().
            'user_model' => $user,
            'device' => [
                'id' => $device->id,
                'name' => $device->device_name,
                'expires_at' => $device->expires_at->toISOString(),
            ],
        ];
    }

    /**
     * Get all devices for a user
     */
    public function getUserDevices(AuthUser $user): array
    {
        return $user->activeDevices()
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(fn($device) => [
                'id' => $device->id,
                'name' => $device->device_name,
                'ip_address' => $device->ip_address,
                'last_used_at' => $device->last_used_at?->toISOString(),
                'expires_at' => $device->expires_at->toISOString(),
                'is_current' => false, // Will be set by controller
            ])
            ->toArray();
    }

    /**
     * Revoke a specific device
     */
    public function revokeDevice(AuthUser $user, int $deviceId, ?string $ipAddress = null): array
    {
        $device = $user->devices()->find($deviceId);

        if (!$device) {
            return [
                'success' => false,
                'message' => 'Device not found',
            ];
        }

        $deviceName = $device->device_name;
        $device->revoke();

        // Log the action
        AuthAccessLog::log(
            AuthAccessLog::ACTION_DEVICE_REVOKE,
            $user->id,
            $deviceName,
            $ipAddress,
            null,
            ['revoked_device_id' => $deviceId]
        );

        return [
            'success' => true,
            'message' => 'Device revoked successfully',
        ];
    }

    /**
     * Revoke all devices except current
     */
    public function revokeAllDevices(AuthUser $user, ?int $exceptDeviceId = null, ?string $ipAddress = null): array
    {
        $query = $user->devices()->where('is_active', true);

        if ($exceptDeviceId) {
            $query->where('id', '!=', $exceptDeviceId);
        }

        $count = $query->update(['is_active' => false]);

        // Log the action
        AuthAccessLog::log(
            AuthAccessLog::ACTION_DEVICE_REVOKE,
            $user->id,
            'All devices',
            $ipAddress,
            null,
            ['revoked_count' => $count, 'except_device_id' => $exceptDeviceId]
        );

        return [
            'success' => true,
            'revoked_count' => $count,
        ];
    }

    /**
     * Logout (revoke current device)
     */
    public function logout(string $token, ?string $ipAddress = null): array
    {
        $device = AuthDevice::findByToken($token);

        if (!$device) {
            return [
                'success' => false,
                'message' => 'Device not found',
            ];
        }

        $user = $device->user;
        $deviceName = $device->device_name;

        $device->revoke();

        // Log the action
        AuthAccessLog::log(
            AuthAccessLog::ACTION_LOGOUT,
            $user->id,
            $deviceName,
            $ipAddress
        );

        return [
            'success' => true,
            'message' => 'Logged out successfully',
        ];
    }

    /**
     * Get access logs for user
     */
    public function getAccessLogs(AuthUser $user, int $limit = 20): array
    {
        return AuthAccessLog::recentForUser($user->id, $limit)
            ->map(fn($log) => [
                'action' => $log->action,
                'device_name' => $log->device_name,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->toISOString(),
            ])
            ->toArray();
    }
}
