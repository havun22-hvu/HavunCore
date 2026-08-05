<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token gate for the Claude task queue.
 *
 * The queue hands instructions to an agent that runs commands on another
 * machine, so this is the lock on a remote-code-execution path — not on a
 * data endpoint. Reads are gated too: a task body carries server paths and
 * project layout.
 *
 * Deliberately not EnsureAdminToken: that one authenticates a human through
 * the device-trust stack. A poller is a machine with one shared secret.
 *
 * The configured value is the SHA-256 of the token, never the token itself,
 * so reading the server's .env does not hand anyone the key. SHA-256 without a
 * work factor is the right choice here: the secret is high-entropy and machine
 * generated, so there is no dictionary to run against it.
 */
class EnsureTaskQueueToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.claude_tasks.token_hash');

        // No hash configured closes the queue rather than opening it. An
        // unconfigured gate that waves everyone through is how this endpoint
        // came to sit open on the internet in the first place.
        if (! is_string($expected) || $expected === '') {
            return $this->deny();
        }

        $provided = $request->bearerToken();

        if (! is_string($provided) || $provided === '') {
            return $this->deny();
        }

        if (! hash_equals($expected, hash('sha256', $provided))) {
            return $this->deny();
        }

        return $next($request);
    }

    /**
     * One response for every rejection — a missing token, a wrong token and an
     * unconfigured server must be indistinguishable from the outside.
     */
    private function deny(): Response
    {
        return response()->json(['error' => 'Authentication required'], 401);
    }
}
