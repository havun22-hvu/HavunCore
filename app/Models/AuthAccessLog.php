<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthAccessLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'device_name',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Action constants
     */
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_QR_GENERATE = 'qr_generate';
    public const ACTION_QR_SCAN = 'qr_scan';
    public const ACTION_QR_APPROVE = 'qr_approve';
    public const ACTION_DEVICE_REVOKE = 'device_revoke';
    public const ACTION_PASSWORD_LOGIN = 'password_login';
    public const ACTION_LOGIN_EMAIL_SENT = 'login_email_sent';
    public const ACTION_LOGIN_EMAIL_APPROVED = 'login_email_approved';

    /**
     * Get the user this log belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id');
    }

    /**
     * Log an action
     */
    public static function log(
        string $action,
        ?int $userId = null,
        ?string $deviceName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $metadata = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'action' => $action,
            'device_name' => $deviceName,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Get recent logs for user
     */
    public static function recentForUser(int $userId, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
