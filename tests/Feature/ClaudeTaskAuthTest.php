<?php

namespace Tests\Feature;

use App\Models\ClaudeTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The task queue hands work to an agent that runs commands on another machine.
 * Until 2026-08-06 it had no authentication at all: a curl from the open
 * internet returned "Task created successfully". Verified from outside the
 * network on 2026-08-05, probe deleted straight after.
 *
 * These tests exist so that can never quietly come back.
 */
class ClaudeTaskAuthTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-queue-token-not-a-real-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.claude_tasks.token_hash', hash('sha256', self::TOKEN));
    }

    /**
     * Every route in the group, including the read-only ones: the task bodies
     * carry server paths and project layout, so listing is not harmless either.
     *
     * @return iterable<string, array{method: string, uri: string}>
     */
    public static function everyRoute(): iterable
    {
        yield 'list' => ['method' => 'getJson', 'uri' => '/api/claude/tasks'];
        yield 'pending per project' => ['method' => 'getJson', 'uri' => '/api/claude/tasks/pending/havuncore'];
        yield 'show' => ['method' => 'getJson', 'uri' => '/api/claude/tasks/1'];
        yield 'create' => ['method' => 'postJson', 'uri' => '/api/claude/tasks'];
        yield 'start' => ['method' => 'postJson', 'uri' => '/api/claude/tasks/1/start'];
        yield 'complete' => ['method' => 'postJson', 'uri' => '/api/claude/tasks/1/complete'];
        yield 'fail' => ['method' => 'postJson', 'uri' => '/api/claude/tasks/1/fail'];
        yield 'delete' => ['method' => 'deleteJson', 'uri' => '/api/claude/tasks/1'];
    }

    #[DataProvider('everyRoute')]
    public function test_the_queue_refuses_callers_without_a_token(string $method, string $uri): void
    {
        $this->$method($uri)->assertUnauthorized();
    }

    #[DataProvider('everyRoute')]
    public function test_the_queue_refuses_a_wrong_token(string $method, string $uri): void
    {
        $this->withHeader('Authorization', 'Bearer wrong-token')
            ->$method($uri)
            ->assertUnauthorized();
    }

    public function test_a_valid_token_still_gets_through(): void
    {
        ClaudeTask::create(['project' => 'havuncore', 'task' => 'Something', 'status' => 'pending']);

        $this->withHeader('Authorization', 'Bearer ' . self::TOKEN)
            ->getJson('/api/claude/tasks')
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_creating_a_task_works_with_a_token(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . self::TOKEN)
            ->postJson('/api/claude/tasks', [
                'project' => 'havuncore',
                'task' => 'Do the thing',
                'priority' => 'normal',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('claude_tasks', ['task' => 'Do the thing']);
    }

    public function test_an_unconfigured_token_locks_the_queue_rather_than_opening_it(): void
    {
        // A missing hash must not read as "no gate configured, let everyone in" —
        // that is exactly how the queue ended up open in the first place.
        config()->set('services.claude_tasks.token_hash', null);

        $this->withHeader('Authorization', 'Bearer ' . self::TOKEN)
            ->getJson('/api/claude/tasks')
            ->assertUnauthorized();
    }
}
