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
        $this->info("Recorded alert: {$key}");

        return self::SUCCESS;
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
