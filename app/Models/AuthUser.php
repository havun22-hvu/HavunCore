<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class AuthUser extends Model
{
    protected $fillable = [
        'email',
        'name',
        'password_hash',
        'is_admin',
        'last_login_at',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    protected $hidden = [
        'password_hash',
    ];

    /**
     * Get all devices for this user
     */
    public function devices(): HasMany
    {
        return $this->hasMany(AuthDevice::class, 'user_id');
    }

    /**
     * Get active devices for this user
     */
    public function activeDevices(): HasMany
    {
        return $this->hasMany(AuthDevice::class, 'user_id')
            ->where('is_active', true)
            ->where('expires_at', '>', now());
    }

    /**
     * Get QR sessions approved by this user
     */
    public function approvedSessions(): HasMany
    {
        return $this->hasMany(AuthQrSession::class, 'approved_by');
    }

    /**
     * Get access logs for this user
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(AuthAccessLog::class, 'user_id');
    }

    /**
     * Get WebAuthn credentials for this user
     */
    public function webauthnCredentials(): HasMany
    {
        return $this->hasMany(WebAuthnCredential::class, 'user_id');
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool
    {
        if (!$this->password_hash) {
            return false;
        }

        return Hash::check($password, $this->password_hash);
    }

    /**
     * Set password
     */
    public function setPassword(string $password): void
    {
        $this->update(['password_hash' => Hash::make($password)]);
    }

    /**
     * Update last login timestamp
     */
    public function touchLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Revoke all devices
     */
    public function revokeAllDevices(): int
    {
        return $this->devices()->update(['is_active' => false]);
    }

    /**
     * Find user by email
     */
    public static function findByEmail(string $email): ?self
    {
        return static::where('email', $email)->first();
    }
}
