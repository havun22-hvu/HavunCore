<?php

namespace App\Services;

use App\Models\AuthUser;
use App\Models\AuthDevice;
use App\Models\AuthQrSession;
use App\Models\AuthAccessLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

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

    /**
     * Send login email for QR session
     */
    public function sendLoginEmail(
        string $qrCode,
        string $email,
        string $callbackUrl,
        ?string $siteName = null
    ): array {
        // Find active session
        $session = AuthQrSession::findActiveByCode($qrCode);

        if (!$session) {
            return [
                'success' => false,
                'message' => 'QR session not found or expired',
            ];
        }

        // Check if user exists
        $user = AuthUser::findByEmail($email);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Email not found',
            ];
        }

        // Generate email token and update session
        $token = $session->setEmail($email, $callbackUrl);

        // Build approve URL
        $approveUrl = rtrim($callbackUrl, '/') . '?token=' . $token;

        // Get device info for email
        $deviceInfo = $session->device_info ?? [];
        $deviceName = $this->formatDeviceName($deviceInfo);

        // Send email with branded FROM name
        $siteName = $siteName ?? 'Havun';
        $fromAddress = config('mail.from.address');

        Mail::send([], [], function ($message) use ($email, $user, $approveUrl, $deviceName, $siteName, $fromAddress) {
            $message->from($fromAddress, $siteName)
                ->to($email, $user->name)
                ->subject("Inloggen op {$siteName}")
                ->html($this->buildLoginEmailHtml($user->name, $approveUrl, $deviceName, $siteName));
        });

        // Log the action
        AuthAccessLog::log(
            AuthAccessLog::ACTION_LOGIN_EMAIL_SENT,
            $user->id,
            null,
            $session->ip_address,
            null,
            ['qr_session_id' => $session->id, 'email' => $email]
        );

        return [
            'success' => true,
            'message' => 'Email sent',
            'expires_at' => $session->fresh()->expires_at->toISOString(),
        ];
    }

    /**
     * Approve QR session via email token
     */
    public function approveViaEmailToken(string $token, ?string $ipAddress = null): array
    {
        $session = AuthQrSession::findByEmailToken($token);

        if (!$session) {
            return [
                'success' => false,
                'message' => 'Invalid or expired token',
            ];
        }

        // Get user by email
        $user = AuthUser::findByEmail($session->email);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found',
            ];
        }

        // Create device for the desktop
        $deviceName = $this->formatDeviceName($session->device_info ?? []);
        $deviceHash = AuthDevice::createHash($session->device_info ?? ['qr_code' => $session->qr_code]);

        // Check if device already exists
        $existingDevice = AuthDevice::findByHash($user->id, $deviceHash);

        if ($existingDevice) {
            $existingDevice->update([
                'is_active' => true,
                'expires_at' => now()->addDays(AuthDevice::TRUST_DAYS),
                'last_used_at' => now(),
                'ip_address' => $session->ip_address,
            ]);
            $device = $existingDevice;
        } else {
            $device = AuthDevice::createForUser(
                $user,
                $deviceName,
                $deviceHash,
                $session->ip_address
            );
        }

        // Approve the session
        $session->approve($user, $device);

        // Log the action
        AuthAccessLog::log(
            AuthAccessLog::ACTION_LOGIN_EMAIL_APPROVED,
            $user->id,
            $deviceName,
            $ipAddress,
            null,
            ['qr_session_id' => $session->id]
        );

        // Update user last login
        $user->touchLogin();

        return [
            'success' => true,
            'message' => 'Login approved',
            'device_name' => $deviceName,
            'user' => [
                'name' => $user->name,
            ],
        ];
    }

    /**
     * Build HTML for login email
     */
    protected function buildLoginEmailHtml(
        string $userName,
        string $approveUrl,
        string $deviceName,
        string $siteName
    ): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 20px; background: #f5f5f5;">
    <div style="max-width: 400px; margin: 0 auto; background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h2 style="margin: 0 0 20px; color: #333;">Hallo {$userName},</h2>

        <p style="color: #666; line-height: 1.6;">
            Er is een inlogverzoek voor <strong>{$siteName}</strong> vanaf:
        </p>

        <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
            <strong style="color: #333;">{$deviceName}</strong>
        </div>

        <p style="color: #666; line-height: 1.6;">
            Klik op de knop hieronder om in te loggen:
        </p>

        <a href="{$approveUrl}" style="display: block; background: #2563eb; color: white; text-align: center; padding: 15px 25px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 25px 0;">
            Ja, log mij in
        </a>

        <p style="color: #999; font-size: 13px; margin-top: 30px;">
            Was jij dit niet? Negeer dan deze email. De link verloopt over 15 minuten.
        </p>
    </div>
</body>
</html>
HTML;
    }
}
