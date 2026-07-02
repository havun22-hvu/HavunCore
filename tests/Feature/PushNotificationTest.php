<?php

namespace Tests\Feature;

use App\Models\HealthAlert;
use App\Models\PushSubscription;
use App\Models\VaultSecret;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function subscribePayload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc'): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'BPtestpublickey', 'auth' => 'authsecret123'],
        ];
    }

    public function test_subscribe_stores_a_subscription(): void
    {
        $this->postJson('/api/push/subscribe', $this->subscribePayload())
            ->assertCreated()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', ['p256dh' => 'BPtestpublickey']);
    }

    public function test_subscribe_is_idempotent_per_endpoint(): void
    {
        $this->postJson('/api/push/subscribe', $this->subscribePayload())->assertCreated();
        $this->postJson('/api/push/subscribe', $this->subscribePayload())->assertCreated();

        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    public function test_subscribe_validates_input(): void
    {
        $this->postJson('/api/push/subscribe', ['endpoint' => 'not-a-url'])
            ->assertStatus(422);
    }

    public function test_unsubscribe_removes_the_subscription(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/xyz';
        $this->postJson('/api/push/subscribe', $this->subscribePayload($endpoint))->assertCreated();

        $this->postJson('/api/push/unsubscribe', ['endpoint' => $endpoint])->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_vapid_public_key_is_503_when_unconfigured(): void
    {
        $this->getJson('/api/push/vapid-public-key')->assertStatus(503);
    }

    public function test_vapid_public_key_is_returned_when_configured(): void
    {
        VaultSecret::create([
            'key' => 'vapid_public_key',
            'value' => 'BPublicKeyFromVault',
            'category' => 'webpush',
            'is_sensitive' => false,
        ]);

        $this->getJson('/api/push/vapid-public-key')
            ->assertOk()
            ->assertJson(['publicKey' => 'BPublicKeyFromVault']);
    }

    public function test_send_is_a_noop_without_vapid_keys(): void
    {
        PushSubscription::create([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'endpoint_hash' => PushSubscription::hashFor('https://fcm.googleapis.com/fcm/send/abc'),
            'p256dh' => 'BPtestpublickey',
            'auth' => 'authsecret123',
        ]);

        $this->assertSame(0, app(WebPushService::class)->send('t', 'b'));
    }

    public function test_fresh_critical_down_triggers_a_push(): void
    {
        $mock = Mockery::mock(WebPushService::class);
        $mock->shouldReceive('send')->once();
        $this->app->instance(WebPushService::class, $mock);

        $this->artisan('health:alert', [
            'key' => 'reverb', '--status' => 'down', '--severity' => 'critical',
        ])->assertSuccessful();
    }

    public function test_repeated_critical_down_does_not_push_again(): void
    {
        // First down opens the alert (push allowed).
        HealthAlert::create([
            'key' => 'reverb', 'scope' => 'server', 'severity' => 'critical',
            'title' => 'reverb down', 'status' => 'open', 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        $mock = Mockery::mock(WebPushService::class);
        $mock->shouldReceive('send')->never();
        $this->app->instance(WebPushService::class, $mock);

        $this->artisan('health:alert', [
            'key' => 'reverb', '--status' => 'down', '--severity' => 'critical',
        ])->assertSuccessful();
    }

    public function test_warning_down_does_not_push(): void
    {
        $mock = Mockery::mock(WebPushService::class);
        $mock->shouldReceive('send')->never();
        $this->app->instance(WebPushService::class, $mock);

        $this->artisan('health:alert', [
            'key' => 'SafeHavun', '--status' => 'down', '--severity' => 'warning',
        ])->assertSuccessful();
    }
}
