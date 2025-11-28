<?php

namespace App\Services;

use App\Models\AuthUser;
use App\Models\AuthDevice;
use App\Models\AuthQrSession;
use App\Models\AuthAccessLog;
use Illuminate\Support\Facades\Hash;

class QrAuthService
{
    /**
     * Generate a new QR session for login
     */
    public function generateQrSession(?array $deviceInfo = null, ?string $ipAddress = null): array
    {
        // Cleanup old expired sessions
        AuthQrSession::cleanupExpired();

        // Create new session
        $session = AuthQrSession::createSession($deviceInfo, $ipAddress);

        // Log the action
        AuthAccessLog::log(
            AuthAccessLog::ACTION_QR_GENERATE,
            null,
            $deviceInfo['device_name'] ?? null,
            $ipAddress,
            $deviceInfo['user_agent'] ?? null,
            ['qr_session_id' => $session->id]
        );

        return [
            'qr_code' => $session->qr_code,
            'expires_at' => $session->expires_at->toISOString(),
            'expires_in' => AuthQrSession::EXPIRY_MINUTES * 60, // seconds
        ];
    }

    /**
     * Check status of QR session
     */
    public function checkQrStatus(string $qrCode): array
    {
        $session = AuthQrSession::findByCode($qrCode);

        if (!$session) {
            return [
                'status' => 'not_found',
                'message' => 'QR session not found',
            ];
        }

        // Check if expired
        if ($session->expires_at->isPast() && $session->status === AuthQrSession::STATUS_PENDING) {
            $session->markExpired();
        }

        $response = [
            'status' => $session->status,
            'expires_at' => $session->expires_at->toISOString(),
        ];

        // If approved, include device token
        if ($session->isApproved() && $session->device) {
            $response['device_token'] = $session->device->token;
            $response['user'] = [
                'id' => $session->approver->id,
                'name' => $session->approver->name,
                'email' => $session->approver->email,
            ];
        }

        return $response;
    }

    /**
     * Approve QR session from mobile device
     */
    public function approveQrSession(
        string $qrCode,
        AuthUser $approver,
        ?array $deviceInfo = null,
        ?string $ipAddress = null
    ): array {
        $session = AuthQrSession::findActiveByCode($qrCode);

        if (!$session) {
            return [
                'success' => false,
                'message' => 'QR session not found or expired',
            ];
        }

        // Create new device for the desktop
        $deviceName = $this->formatDeviceName($session->device_info ?? []);
        $deviceHash = AuthDevice::createHash($session->device_info ?? ['qr_code' => $qrCode]);

        // Check if device already exists
        $existingDevice = AuthDevice::findByHash($approver->id, $deviceHash);

        if ($existingDevice) {
            // Reactivate and extend existing device
            $existingDevice->update([
                'is_active' => true,
                'expires_at' => now()->addDays(AuthDevice::TRUST_DAYS),
                'last_used_at' => now(),
                'ip_address' => $session->ip_address,
            ]);
            $device = $existingDevice;
        } else {
            // Create new device
            $device = AuthDevice::createForUser(
                $approver,
                $deviceName,
                $deviceHash,
                $session->ip_address
            );
        }

        // Approve the session
        $session->approve($approver, $device);

        // Log the action
        AuthAccessLog::log(
            AuthAccessLog::ACTION_QR_APPROVE,
            $approver->id,
            $deviceName,
            $ipAddress,
            $deviceInfo['user_agent'] ?? null,
            [
                'qr_session_id' => $session->id,
                'new_device_id' => $device->id,
            ]
        );

        // Update user last login
        $approver->touchLogin();

        return [
            'success' => true,
            'message' => 'Device approved successfully',
            'device_name' => $deviceName,
        ];
    }

    /**
     * Login with password (fallback)
     */
    public function loginWithPassword(
        string $email,
        string $password,
        ?array $deviceInfo = null,
        ?string $ipAddress = null
    ): array {
        $user = AuthUser::findByEmail($email);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Invalid credentials',
            ];
        }

        if (!$user->verifyPassword($password)) {
            return [
                'success' => false,
                'message' => 'Invalid credentials',
            ];
        }

        // Create device for this login
        $deviceName = $this->formatDeviceName($deviceInfo ?? []);
        $deviceHash = AuthDevice::createHash($deviceInfo ?? ['email' => $email, 'time' => now()->timestamp]);

        $device = AuthDevice::createForUser($user, $deviceName, $deviceHash, $ipAddress);

        // Log the action
        AuthAccessLog::log(
            AuthAccessLog::ACTION_PASSWORD_LOGIN,
            $user->id,
            $deviceName,
            $ipAddress,
            $deviceInfo['user_agent'] ?? null
        );

        // Update user last login
        $user->touchLogin();

        return [
            'success' => true,
            'device_token' => $device->token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
            ],
        ];
    }

    /**
     * Format device name from device info
     */
    protected function formatDeviceName(array $deviceInfo): string
    {
        $browser = $deviceInfo['browser'] ?? 'Unknown Browser';
        $os = $deviceInfo['os'] ?? 'Unknown OS';

        return "{$browser} on {$os}";
    }
}
