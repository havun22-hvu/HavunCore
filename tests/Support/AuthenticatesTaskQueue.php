<?php

namespace Tests\Support;

/**
 * Gives a test a working token for the task queue.
 *
 * The behaviour of the gate itself lives in ClaudeTaskAuthTest; tests that use
 * this trait are about what the queue does once you are through it.
 */
trait AuthenticatesTaskQueue
{
    /** Not a real secret — the gate compares a hash of whatever is configured. */
    protected string $taskQueueToken = 'test-queue-token-not-a-real-secret';

    protected function authenticateTaskQueue(): void
    {
        config()->set('services.claude_tasks.token_hash', hash('sha256', $this->taskQueueToken));
        $this->withHeader('Authorization', 'Bearer ' . $this->taskQueueToken);
    }
}
