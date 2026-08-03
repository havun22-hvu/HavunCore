<?php

namespace Tests\Feature\QualitySafety;

use App\Services\QualitySafety\QualitySafetyScanner;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Drie checks vragen de server iets via SSH naar `root@188.245.159.115`:
 * `server`, `residu` en (tot 03-08) `backup-coverage`. Draait de scan óp die
 * server — en dat doet hij, elke minuut vanuit roots crontab — dan is dat een
 * verbinding naar de eigen machine, waarvoor geen sleutel bestaat en ook geen
 * sleutel hoort te bestaan.
 *
 * Gevolg, gemeten 03-08-2026: `serverHealth` meldde elke nacht
 * `Permission denied (publickey)` en mat dus nooit iets — terwijl hij
 * schijfvulling en omgevallen systemd-units bewaakt.
 *
 * De backupcheck kreeg daar een eigen oplossing voor (een manifest). Dit is de
 * algemene: een commando voor de machine waar je al op staat, draai je gewoon.
 */
class LokaleUitvoeringTest extends TestCase
{
    public function test_commando_voor_de_eigen_machine_gaat_niet_via_ssh(): void
    {
        Process::fake(['*' => Process::result(output: "Filesystem 1024-blocks Used Available Capacity Mounted on\n", exitCode: 0)]);

        // Loopback is per definitie deze machine, wat de testrunner ook is.
        $projects = ['server-prod' => ['host' => '127.0.0.1', 'user' => 'root']];

        (new QualitySafetyScanner)->scan($projects, ['server']);

        Process::assertRan(function ($process): bool {
            $commando = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;

            return ! str_contains($commando, 'ssh ') && ! str_contains($commando, 'root@');
        });
    }

    public function test_een_andere_host_gaat_nog_steeds_via_ssh(): void
    {
        Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

        $projects = ['elders' => ['host' => '203.0.113.9', 'user' => 'root']];

        (new QualitySafetyScanner)->scan($projects, ['server']);

        Process::assertRan(function ($process): bool {
            $commando = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;

            return str_contains($commando, 'root@203.0.113.9');
        });
    }
}
