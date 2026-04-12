<?php

namespace App\Console\Commands;

use App\Models\ChaosResult;
use App\Services\Chaos\Experiments\ApiTimeoutExperiment;
use App\Services\Chaos\Experiments\DatabaseSlowExperiment;
use App\Services\Chaos\Experiments\EndpointProbeExperiment;
use App\Services\Chaos\Experiments\ErrorFloodExperiment;
use App\Services\Chaos\Experiments\HealthDeepExperiment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ChaosRunCommand extends Command
{
    protected $signature = 'chaos:run
                            {experiment? : Experiment to run (or "all")}
                            {--list : List available experiments}';

    protected $description = 'Run chaos engineering experiments to test system resilience';

    protected array $experiments = [
        'health-deep' => HealthDeepExperiment::class,
        'endpoint-probe' => EndpointProbeExperiment::class,
        'error-flood' => ErrorFloodExperiment::class,
        'db-slow' => DatabaseSlowExperiment::class,
        'api-timeout' => ApiTimeoutExperiment::class,
    ];

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listExperiments();
        }

        $name = $this->argument('experiment');

        if (! $name) {
            $this->error('Specify an experiment name or use --list');

            return self::FAILURE;
        }

        if ($name === 'all') {
            return $this->runAll();
        }

        if (! isset($this->experiments[$name])) {
            $this->error("Unknown experiment: {$name}");
            $this->listExperiments();

            return self::FAILURE;
        }

        return $this->runExperiment($name);
    }

    protected function runExperiment(string $name): int
    {
        $class = $this->experiments[$name];
        $experiment = app($class);

        $this->info("Running: {$experiment->name()}");
        $this->line("Hypothesis: {$experiment->hypothesis()}");
        $this->newLine();

        $report = $experiment->execute();

        $this->storeResult($name, $report);
        $this->renderReport($report);
        $this->alertOnFailure($name, $report);

        return self::SUCCESS;
    }

    protected function runAll(): int
    {
        $this->info('Running all chaos experiments...');
        $this->newLine();

        $allResults = [];
        $failures = [];

        foreach ($this->experiments as $name => $class) {
            $experiment = app($class);
            $this->info("=== {$experiment->name()} ===");

            $report = $experiment->execute();
            $allResults[$name] = $report;

            $this->storeResult($name, $report);
            $this->renderReport($report);
            $this->newLine();

            if (($report['results']['status'] ?? '') === 'fail') {
                $failures[$name] = $report;
            }
        }

        // Summary
        $this->info('=== SUMMARY ===');
        foreach ($allResults as $name => $report) {
            $status = $report['results']['status'] ?? 'unknown';
            $icon = match ($status) {
                'pass' => '<fg=green>PASS</>',
                'warn' => '<fg=yellow>WARN</>',
                'fail' => '<fg=red>FAIL</>',
                default => '<fg=gray>????</>',
            };
            $this->line("  [{$icon}] {$name} ({$report['duration_ms']}ms)");
        }

        if (! empty($failures)) {
            $this->alertOnBatchFailure($failures);
        }

        return self::SUCCESS;
    }

    /**
     * Store experiment result to database.
     */
    protected function storeResult(string $name, array $report): void
    {
        try {
            ChaosResult::create([
                'experiment' => $name,
                'status' => $report['results']['status'] ?? 'error',
                'duration_ms' => $report['duration_ms'],
                'checks' => $report['results']['checks'] ?? null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * Send alert email when a single experiment fails.
     */
    protected function alertOnFailure(string $name, array $report): void
    {
        $status = $report['results']['status'] ?? '';
        if ($status !== 'fail') {
            return;
        }

        $this->sendAlert("Chaos FAIL: {$name}", $this->formatAlertBody([$name => $report]));
    }

    /**
     * Send alert email for batch failures.
     */
    protected function alertOnBatchFailure(array $failures): void
    {
        $names = implode(', ', array_keys($failures));
        $this->sendAlert("Chaos FAIL: {$names}", $this->formatAlertBody($failures));
    }

    /**
     * Send alert email.
     */
    protected function sendAlert(string $subject, string $body): void
    {
        $to = config('chaos.alert_email', env('MAIL_FROM_ADDRESS'));
        if (empty($to)) {
            return;
        }

        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject("[HavunCore] {$subject}");
            });
            $this->warn("Alert email sent to {$to}");
        } catch (\Throwable $e) {
            $this->error("Failed to send alert: {$e->getMessage()}");
        }
    }

    /**
     * Format alert email body.
     */
    protected function formatAlertBody(array $failures): string
    {
        $lines = ["Chaos Engineering Alert — " . now()->toDateTimeString(), ""];

        foreach ($failures as $name => $report) {
            $lines[] = "EXPERIMENT: {$name}";
            $lines[] = "STATUS: " . strtoupper($report['results']['status'] ?? 'unknown');
            $lines[] = "DURATION: {$report['duration_ms']}ms";

            if (isset($report['results']['checks'])) {
                foreach ($report['results']['checks'] as $check => $detail) {
                    $status = strtoupper($detail['status'] ?? '??');
                    $lines[] = "  [{$status}] {$check}: " . ($detail['message'] ?? '');
                }
            }
            $lines[] = "";
        }

        $lines[] = "Server: " . gethostname();
        $lines[] = "URL: https://havuncore.havun.nl";

        return implode("\n", $lines);
    }

    protected function renderReport(array $report): void
    {
        $results = $report['results'];
        $status = $results['status'] ?? 'unknown';

        $statusColor = match ($status) {
            'pass' => 'green',
            'warn' => 'yellow',
            'fail' => 'red',
            default => 'gray',
        };

        $this->line("Status: <fg={$statusColor}>" . strtoupper($status) . '</> (' . $report['duration_ms'] . 'ms)');

        if (isset($results['checks'])) {
            foreach ($results['checks'] as $check => $detail) {
                $checkStatus = $detail['status'] ?? 'unknown';
                $icon = match ($checkStatus) {
                    'pass' => '<fg=green>OK</>',
                    'warn' => '<fg=yellow>!!<//>',
                    'fail' => '<fg=red>XX</>',
                    default => '<fg=gray>??</>',
                };
                $msg = $detail['message'] ?? '';
                $this->line("  [{$icon}] {$check}: {$msg}");
            }
        }

        if (isset($results['error'])) {
            $this->error("  Error: {$results['error']}");
        }
    }

    protected function listExperiments(): int
    {
        $this->info('Available chaos experiments:');
        foreach ($this->experiments as $name => $class) {
            $experiment = app($class);
            $this->line("  <fg=cyan>{$name}</> — {$experiment->hypothesis()}");
        }

        return self::SUCCESS;
    }
}
