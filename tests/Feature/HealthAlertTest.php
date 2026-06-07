<?php

namespace Tests\Feature;

use App\Models\HealthAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_down_command_creates_open_alert(): void
    {
        $this->artisan('health:alert', [
            'key' => 'reverb',
            '--scope' => 'project',
            '--project' => 'JudoToernooi',
            '--status' => 'down',
            '--severity' => 'critical',
            '--title' => 'reverb FATAL',
        ])->assertSuccessful();

        $this->assertDatabaseHas('health_alerts', [
            'key' => 'reverb',
            'scope' => 'project',
            'project' => 'JudoToernooi',
            'severity' => 'critical',
            'status' => 'open',
        ]);

        $alert = HealthAlert::where('key', 'reverb')->first();
        $this->assertNotNull($alert->first_seen_at);
        $this->assertNotNull($alert->last_seen_at);
    }

    public function test_repeated_down_keeps_first_seen_and_single_row(): void
    {
        $this->artisan('health:alert', ['key' => 'disk', '--status' => 'down'])->assertSuccessful();
        $first = HealthAlert::where('key', 'disk')->first();

        $this->artisan('health:alert', ['key' => 'disk', '--status' => 'down'])->assertSuccessful();

        $this->assertSame(1, HealthAlert::where('key', 'disk')->count());
        $this->assertEquals(
            $first->first_seen_at->timestamp,
            HealthAlert::where('key', 'disk')->first()->first_seen_at->timestamp
        );
    }

    public function test_up_command_resolves_open_alert(): void
    {
        $this->artisan('health:alert', ['key' => 'reverb', '--status' => 'down'])->assertSuccessful();
        $this->artisan('health:alert', ['key' => 'reverb', '--status' => 'up'])->assertSuccessful();

        $this->assertDatabaseHas('health_alerts', [
            'key' => 'reverb',
            'status' => 'resolved',
        ]);
        $this->assertNotNull(HealthAlert::where('key', 'reverb')->first()->resolved_at);
    }

    public function test_up_on_healthy_key_is_noop(): void
    {
        $this->artisan('health:alert', ['key' => 'nginx', '--status' => 'up'])->assertSuccessful();
        $this->assertDatabaseMissing('health_alerts', ['key' => 'nginx']);
    }

    public function test_index_returns_only_open_by_default(): void
    {
        $this->artisan('health:alert', ['key' => 'reverb', '--status' => 'down']);
        $this->artisan('health:alert', ['key' => 'mysql', '--status' => 'down']);
        $this->artisan('health:alert', ['key' => 'mysql', '--status' => 'up']);

        $res = $this->getJson('/api/health-alerts');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('open_count', 1);
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('reverb', $res->json('data.0.key'));
    }

    public function test_dismiss_resolves_alert(): void
    {
        $this->artisan('health:alert', ['key' => 'reverb', '--status' => 'down']);
        $id = HealthAlert::where('key', 'reverb')->first()->id;

        $this->postJson("/api/health-alerts/{$id}/dismiss")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('health_alerts', ['id' => $id, 'status' => 'resolved']);
    }
}
