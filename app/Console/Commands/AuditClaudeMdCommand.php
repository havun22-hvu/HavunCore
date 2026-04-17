<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AuditClaudeMdCommand extends Command
{
    protected $signature = 'docs:audit-claude-md
                            {--max=60 : Maximum allowed line count per CLAUDE.md}
                            {--root=D:/GitHub : Root directory containing all project folders}';

    protected $description = 'VP-03.3 — Audit CLAUDE.md size across all Havun projects.';

    private const PROJECTS = [
        'HavunCore', 'HavunAdmin', 'Herdenkingsportaal', 'JudoToernooi',
        'SafeHavun', 'Studieplanner', 'infosyst', 'HavunVet', 'JudoScoreBoard',
    ];

    public function handle(): int
    {
        $max = (int) $this->option('max');
        $root = rtrim($this->option('root'), '/\\');

        $rows = [];
        $exitCode = Command::SUCCESS;

        foreach (self::PROJECTS as $project) {
            $path = "{$root}/{$project}/CLAUDE.md";

            if (! is_file($path)) {
                $rows[] = [$project, '—', 'MISSING'];
                $exitCode = Command::FAILURE;
                continue;
            }

            $lines = count(file($path, FILE_IGNORE_NEW_LINES));
            $status = $lines <= $max ? 'OK' : 'OVER (+'.($lines - $max).')';

            if ($lines > $max) {
                $exitCode = Command::FAILURE;
            }

            $rows[] = [$project, $lines, $status];
        }

        $this->table(['Project', 'Lines', "Status (max {$max})"], $rows);

        if ($exitCode !== Command::SUCCESS) {
            $this->warn('Eén of meer CLAUDE.md bestanden overschrijden de norm. Splits in CONTRACTS.md / context.md.');
        } else {
            $this->info('Alle CLAUDE.md bestanden voldoen aan de norm.');
        }

        return $exitCode;
    }
}
