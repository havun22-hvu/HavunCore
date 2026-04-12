<?php

namespace App\Console\Commands;

use App\Services\Chaos\Experiments\ApiTimeoutExperiment;
use App\Services\Chaos\Experiments\DatabaseSlowExperiment;
use App\Services\Chaos\Experiments\EndpointProbeExperiment;
use App\Services\Chaos\Experiments\ErrorFloodExperiment;
use App\Services\Chaos\Experiments\HealthDeepExperiment;
use Illuminate\Console\Command;

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

        $this->renderReport($report);

        return self::SUCCESS;
    }

    protected function runAll(): int
    {
        $this->info('Running all chaos experiments...');
        $this->newLine();

        $allResults = [];

        foreach ($this->experiments as $name => $class) {
            $experiment = app($class);
            $this->info("=== {$experiment->name()} ===");

            $report = $experiment->execute();
            $allResults[$name] = $report;

            $this->renderReport($report);
            $this->newLine();
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

        return self::SUCCESS;
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
