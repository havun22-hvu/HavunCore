<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscribePushRequest;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PWA Web Push subscription endpoints. The webapp requests the public VAPID key,
 * subscribes the browser, and unsubscribes on logout/permission-revoke.
 */
class PushController extends Controller
{
    public function vapidPublicKey(WebPushService $push): JsonResponse
    {
        $key = $push->vapidPublicKey();
        if (! $key) {
            return response()->json(['error' => 'push not configured'], 503);
        }

        return response()->json(['publicKey' => $key]);
    }

    public function subscribe(SubscribePushRequest $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint');

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashFor($endpoint)],
            [
                'endpoint' => $endpoint,
                'p256dh' => $request->input('keys.p256dh'),
                'auth' => $request->input('keys.auth'),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'last_used_at' => now(),
            ]
        );

        return response()->json(['ok' => true], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint');
        if ($endpoint !== '') {
            PushSubscription::where('endpoint_hash', PushSubscription::hashFor($endpoint))->delete();
        }

        return response()->json(['ok' => true]);
    }
}
