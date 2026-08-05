<?php

namespace App\Providers;

use App\Support\Timing\Stopwatch;
use App\Support\Timing\SystemStopwatch;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Stateless, so one instance is enough.
        $this->app->singleton(Stopwatch::class, SystemStopwatch::class);
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Named rate-limiters used across api.php. Hit thresholds are deliberately
     * tight on auth endpoints (brute-force) and looser on general API write
     * endpoints. Per-IP scoping for unauthenticated routes; per-user for the
     * rest (falls back to IP if no user).
     */
    private function configureRateLimiting(): void
    {
        // Login + register + QR-generate: brute-force surface, very tight.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Token-bearing auth endpoints (verify, logout, approve-*) — moderate.
        RateLimiter::for('auth-session', fn (Request $request) => Limit::perMinute(30)->by($this->userOrIp($request)));

        // General API write traffic (Vault user-token, MCP, ClaudeTask, etc.) — loose.
        RateLimiter::for('api-write', fn (Request $request) => Limit::perMinute(60)->by($this->userOrIp($request)));
    }

    private function userOrIp(Request $request): string
    {
        return (string) ($request->user()?->id ?? $request->ip());
    }
}
