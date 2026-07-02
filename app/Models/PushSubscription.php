<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A browser Web Push subscription (PWA). One row per browser endpoint; deduped
 * on endpoint_hash. Rows are removed when the push service reports the endpoint
 * is gone (404/410) — see WebPushService.
 */
class PushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'p256dh',
        'auth',
        'user_agent',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public static function hashFor(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
