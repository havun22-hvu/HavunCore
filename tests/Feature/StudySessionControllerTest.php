<?php

namespace Tests\Feature;

use App\Events\StudySessionUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StudySessionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'started',
            'student_id' => 1,
            'student_name' => 'Jan',
            'subject_name' => 'Wiskunde',
            'task_description' => 'Hoofdstuk 3 oefeningen',
            'minutes_planned' => 30,
            'minutes_actual' => null,
            'started_at' => '2024-12-27T14:00:00+01:00',
            'stopped_at' => null,
        ], $overrides);
    }

    private function broadcastWithKey(?string $key, array $payload = []): \Illuminate\Testing\TestResponse
    {
        $headers = $key !== null ? ['X-Api-Key' => $key] : [];

        return $this->postJson('/api/studieplanner/session/broadcast', $payload ?: $this->validPayload(), $headers);
    }

    // -- broadcast: authentication --

    public function test_broadcast_with_valid_api_key_returns_200(): void
    {
        Event::fake();
        config(['services.studieplanner.api_key' => 'test-secret-key']);

        $response = $this->broadcastWithKey('test-secret-key');

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Event broadcasted']);
    }

    public function test_broadcast_without_api_key_returns_401(): void
    {
        config(['services.studieplanner.api_key' => 'test-secret-key']);

        $response = $this->broadcastWithKey(null);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_broadcast_with_invalid_api_key_returns_401(): void
    {
        config(['services.studieplanner.api_key' => 'test-secret-key']);

        $response = $this->broadcastWithKey('wrong-key');

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    // -- broadcast: validation --

    public function test_broadcast_requires_type_field(): void
    {
        config(['services.studieplanner.api_key' => 'test-key']);

        $payload = $this->validPayload();
        unset($payload['type']);

        $response = $this->broadcastWithKey('test-key', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_broadcast_rejects_invalid_type_value(): void
    {
        config(['services.studieplanner.api_key' => 'test-key']);

        $response = $this->broadcastWithKey('test-key', $this->validPayload(['type' => 'invalid']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_broadcast_requires_student_id_and_name(): void
    {
        config(['services.studieplanner.api_key' => 'test-key']);

        $payload = $this->validPayload();
        unset($payload['student_id'], $payload['student_name']);

        $response = $this->broadcastWithKey('test-key', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['student_id', 'student_name']);
    }

    // -- broadcast: event dispatching --

    public function test_broadcast_fires_study_session_updated_event(): void
    {
        Event::fake();
        config(['services.studieplanner.api_key' => 'test-key']);

        $this->broadcastWithKey('test-key', $this->validPayload([
            'type' => 'completed',
            'student_id' => 42,
            'student_name' => 'Piet',
            'minutes_actual' => 25,
            'stopped_at' => '2024-12-27T14:25:00+01:00',
        ]));

        Event::assertDispatched(StudySessionUpdated::class, function ($event) {
            return $event->type === 'completed'
                && $event->studentId === 42
                && $event->studentName === 'Piet'
                && $event->minutesActual === 25;
        });
    }

    // -- credentials endpoint --

    public function test_credentials_returns_reverb_config(): void
    {
        config([
            'reverb.apps.apps.0.key' => 'reverb-test-key',
            'reverb.apps.apps.0.options.host' => 'localhost',
            'reverb.apps.apps.0.options.port' => 8080,
            'reverb.apps.apps.0.options.scheme' => 'https',
        ]);

        $response = $this->getJson('/api/studieplanner/reverb/credentials');

        $response->assertStatus(200)
            ->assertJson([
                'app_key' => 'reverb-test-key',
                'host' => 'localhost',
                'port' => 8080,
                'scheme' => 'https',
            ]);
    }
}
