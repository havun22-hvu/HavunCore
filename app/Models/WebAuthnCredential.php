<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebAuthnCredential extends Model
{
    protected $fillable = [
        'user_id',
        'credential_id',
        'public_key',
        'name',
        'counter',
        'transports',
        'device_type',
        'last_used_at',
    ];

    protected $casts = [
        'transports' => 'array',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'public_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id');
    }

    public function incrementCounter(): void
    {
        $this->increment('counter');
        $this->update(['last_used_at' => now()]);
    }

    public static function findByCredentialId(string $credentialId): ?self
    {
        return static::where('credential_id', $credentialId)->first();
    }
}
