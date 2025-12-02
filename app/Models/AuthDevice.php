<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuthDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_name',
        'device_hash',
        'token',
        'expires_at',
        'last_used_at',
        'ip_address',
        'is_active',
        'browser',
        'os',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'token',
    ];

    /**
     * Device trust duration in days
     */
    public const TRUST_DAYS = 30;

    /**
     * Get the user this device belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id');
    }

    /**
     * Generate a new device token
     */
    public static function generateToken(): string
    {
        return 'dev_' . Str::random(48);
    }

    /**
     * Create device hash from fingerprint data
     */
    public static function createHash(array $fingerprintData): string
    {
        return hash('sha256', json_encode($fingerprintData));
    }

    /**
     * Find device by token
     */
    public static function findByToken(string $token): ?self
    {
        return static::where('token', $token)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Find device by hash for user
     */
    public static function findByHash(int $userId, string $hash): ?self
    {
        return static::where('user_id', $userId)
            ->where('device_hash', $hash)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if device is valid/active
     */
    public function isValid(): bool
    {
        return $this->is_active && $this->expires_at->isFuture();
    }

    /**
     * Extend device trust period
     */
    public function extendTrust(): void
    {
        $this->update([
            'expires_at' => now()->addDays(self::TRUST_DAYS),
            'last_used_at' => now(),
        ]);
    }

    /**
     * Touch last used timestamp
     */
    public function touchUsed(?string $ipAddress = null): void
    {
        $data = ['last_used_at' => now()];

        if ($ipAddress) {
            $data['ip_address'] = $ipAddress;
        }

        $this->update($data);
    }

    /**
     * Revoke this device
     */
    public function revoke(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Create a new trusted device for user
     */
    public static function createForUser(
        AuthUser $user,
        string $deviceName,
        string $deviceHash,
        ?string $ipAddress = null
    ): self {
        return static::create([
            'user_id' => $user->id,
            'device_name' => $deviceName,
            'device_hash' => $deviceHash,
            'token' => self::generateToken(),
            'expires_at' => now()->addDays(self::TRUST_DAYS),
            'last_used_at' => now(),
            'ip_address' => $ipAddress,
            'is_active' => true,
        ]);
    }
}
