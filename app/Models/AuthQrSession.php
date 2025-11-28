<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuthQrSession extends Model
{
    protected $fillable = [
        'qr_code',
        'device_info',
        'ip_address',
        'status',
        'approved_by',
        'device_id',
        'expires_at',
    ];

    protected $casts = [
        'device_info' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * QR code expiration in minutes
     */
    public const EXPIRY_MINUTES = 5;

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SCANNED = 'scanned';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_EXPIRED = 'expired';

    /**
     * Get the user who approved this session
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'approved_by');
    }

    /**
     * Get the device created from this session
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(AuthDevice::class, 'device_id');
    }

    /**
     * Generate a new QR code
     */
    public static function generateQrCode(): string
    {
        return 'qr_' . Str::random(48);
    }

    /**
     * Find session by QR code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('qr_code', $code)->first();
    }

    /**
     * Find active session by QR code
     */
    public static function findActiveByCode(string $code): ?self
    {
        return static::where('qr_code', $code)
            ->where('status', self::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Check if session is still valid
     */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at->isFuture();
    }

    /**
     * Check if session is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Mark session as scanned
     */
    public function markScanned(): void
    {
        if ($this->status === self::STATUS_PENDING) {
            $this->update(['status' => self::STATUS_SCANNED]);
        }
    }

    /**
     * Approve session and create device
     */
    public function approve(AuthUser $user, AuthDevice $device): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $user->id,
            'device_id' => $device->id,
        ]);
    }

    /**
     * Mark session as expired
     */
    public function markExpired(): void
    {
        $this->update(['status' => self::STATUS_EXPIRED]);
    }

    /**
     * Create new QR session
     */
    public static function createSession(?array $deviceInfo = null, ?string $ipAddress = null): self
    {
        return static::create([
            'qr_code' => self::generateQrCode(),
            'device_info' => $deviceInfo,
            'ip_address' => $ipAddress,
            'status' => self::STATUS_PENDING,
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);
    }

    /**
     * Cleanup expired sessions
     */
    public static function cleanupExpired(): int
    {
        return static::where('status', self::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->update(['status' => self::STATUS_EXPIRED]);
    }
}
