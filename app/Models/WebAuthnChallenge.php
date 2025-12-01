<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WebAuthnChallenge extends Model
{
    protected $fillable = [
        'user_id',
        'challenge',
        'type',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public static function createForRegistration(?int $userId = null): self
    {
        // Clean up expired challenges
        static::where('expires_at', '<', now())->delete();

        return static::create([
            'user_id' => $userId,
            'challenge' => Str::random(64),
            'type' => 'register',
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    public static function createForLogin(?int $userId = null): self
    {
        // Clean up expired challenges
        static::where('expires_at', '<', now())->delete();

        return static::create([
            'user_id' => $userId,
            'challenge' => Str::random(64),
            'type' => 'login',
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    public static function findValidChallenge(string $challenge, string $type): ?self
    {
        return static::where('challenge', $challenge)
            ->where('type', $type)
            ->where('expires_at', '>', now())
            ->first();
    }
}
