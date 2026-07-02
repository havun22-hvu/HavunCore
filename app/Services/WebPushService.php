<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\VaultSecret;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends PWA Web Push notifications for critical health-alerts.
 *
 * VAPID keys are read at runtime from the Vault (not config, so config:cache
 * stays DB-free). Generate/store them with `php artisan vapid:setup`. Endpoints
 * the push service reports as gone (404/410) are pruned automatically.
 */
class WebPushService
{
    /** Public VAPID key for the frontend to subscribe with (safe to expose). */
    public function vapidPublicKey(): ?string
    {
        return $this->secret('vapid_public_key');
    }

    /** Push to every stored subscription. Returns the number delivered. */
    public function send(string $title, string $body, array $data = []): int
    {
        $public = $this->secret('vapid_public_key');
        $private = $this->secret('vapid_private_key');
        if (! $public || ! $private) {
            Log::warning('WebPush: VAPID keys missing in Vault — run `php artisan vapid:setup`.');

            return 0;
        }

        $subs = PushSubscription::all();
        if ($subs->isEmpty()) {
            return 0;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => config('services.vapid.subject'),
            'publicKey' => $public,
            'privateKey' => $private,
        ]]);

        $payload = json_encode(array_merge(
            ['title' => $title, 'body' => $body],
            $data ? ['data' => $data] : []
        ));

        foreach ($subs as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->p256dh,
                    'authToken' => $sub->auth,
                ]),
                $payload
            );
        }

        $sent = 0;
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getEndpoint();
            if ($report->isSuccess()) {
                $sent++;
                PushSubscription::where('endpoint_hash', PushSubscription::hashFor($endpoint))
                    ->update(['last_used_at' => now()]);
            } elseif ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint_hash', PushSubscription::hashFor($endpoint))->delete();
            } else {
                Log::warning('WebPush delivery failed', [
                    'endpoint' => $endpoint,
                    'reason' => $report->getReason(),
                ]);
            }
        }

        return $sent;
    }

    private function secret(string $key): ?string
    {
        $secret = VaultSecret::where('key', $key)->first();

        return $secret ? $secret->getDecryptedValue() : null;
    }
}
