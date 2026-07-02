<?php

namespace App\Console\Commands;

use App\Models\HealthAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Record an in-app health alert (replaces the old email alerting).
 *
 * Called by /usr/local/bin/havun-health-check.sh. A "down" status upserts an
 * open alert keyed by {key}; an "up" status resolves any open alert for that
 * key. After writing, it pings the webapp backend so the notification panel
 * updates in real time via Socket.io (best-effort, never blocks).
 */
class HealthAlertCommand extends Command
{
    protected $signature = 'health:alert
        {key : Unique source key, e.g. reverb / JudoToernooi / disk}
        {--scope=server : server|project}
        {--project= : Project name when scope=project}
        {--status=down : down|up (down=open alert, up=resolve)}
        {--severity=warning : info|warning|critical}
        {--title= : Human-readable title (defaults from key/status)}
        {--body= : Optional detail body}';

    protected $description = 'Record an in-app health alert and notify the webapp';

    public function handle(): int
    {
        $key = $this->argument('key');
        $status = $this->option('status') === 'up' ? 'up' : 'down';

        if ($status === 'up') {
            $alert = HealthAlert::where('key', $key)->where('status', 'open')->first();
            if (! $alert) {
                return self::SUCCESS; // already healthy — nothing to do, no ping
            }
            $alert->update(['status' => 'resolved', 'resolved_at' => now()]);
            $this->notifyWebapp(['event' => 'health-alert', 'key' => $key, 'status' => 'resolved']);
            $this->info("Resolved alert: {$key}");

            return self::SUCCESS;
        }

        // status = down → upsert an open alert
        $existing = HealthAlert::where('key', $key)->first();
        $wasOpen = $existing && $existing->status === 'open';
        $title = $this->option('title') ?: "{$key} is niet bereikbaar";

        HealthAlert::updateOrCreate(
            ['key' => $key],
            [
                'scope' => $this->option('scope') === 'project' ? 'project' : 'server',
                'project' => $this->option('project') ?: null,
                'severity' => $this->option('severity') ?: 'warning',
                'title' => $title,
                'body' => $this->option('body') ?: null,
                'status' => 'open',
                'first_seen_at' => ($existing && $existing->status === 'open' && $existing->first_seen_at)
                    ? $existing->first_seen_at
                    : now(),
                'last_seen_at' => now(),
                'resolved_at' => null,
            ]
        );

        $this->notifyWebapp(['event' => 'health-alert', 'key' => $key, 'status' => 'open']);

        // Push only on a *fresh* critical outage — not every 5 min while it stays
        // down. This is the active channel the reverb outage (23 jun–2 jul) lacked.
        if (! $wasOpen && $this->option('severity') === 'critical') {
            $this->pushCritical($key, $title, $this->option('body'));
        }

        $this->info("Recorded alert: {$key}");

        return self::SUCCESS;
    }

    /**
     * Best-effort PWA push for a new critical alert. Never throws — a push
     * failure must not break the health check.
     */
    private function pushCritical(string $key, string $title, ?string $body): void
    {
        try {
            app(\App\Services\WebPushService::class)->send(
                $title,
                $body ?: $title,
                ['key' => $key, 'url' => 'https://havuncore.havun.nl']
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('health:alert push failed: '.$e->getMessage());
        }
    }

    /**
     * Best-effort real-time ping to the webapp backend (localhost). Never throws.
     */
    private function notifyWebapp(array $payload): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $url = config('services.webapp_notify_url');
        if (! $url) {
            return;
        }

        try {
            Http::timeout(2)->post($url, $payload);
        } catch (\Throwable $e) {
            // Webapp down? The alert is already persisted; the panel picks it
            // up on next load. Swallow so the health check never fails on this.
        }
    }
}
