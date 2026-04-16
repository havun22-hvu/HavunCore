<?php

namespace App\Console\Commands;

use App\Mail\DroogtestReminderMail;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Send a reminder email 7 days before a scheduled emergency-protocol dry run.
 *
 * Schedule lives in docs/kb/runbooks/droogtest-schema-2026-2027.md.
 * Run daily; the command checks itself whether today is exactly 7 days
 * before any scheduled date, and only sends in that case.
 */
class SendDroogtestReminderCommand extends Command
{
    protected $signature = 'droogtest:reminder';

    protected $description = 'Send a reminder email 7 days before each scheduled emergency-protocol dry run';

    /**
     * Schedule of upcoming dry runs.
     *
     * Source of truth is docs/kb/runbooks/droogtest-schema-2026-2027.md.
     * Update both when rotating the roster.
     */
    private const SCHEDULE = [
        ['date' => '2026-07-19', 'contact' => 'Thiemo',  'standby' => 'Mawin'],
        ['date' => '2026-10-18', 'contact' => 'Mawin',   'standby' => 'Thiemo'],
        ['date' => '2027-01-18', 'contact' => 'Thiemo',  'standby' => 'Mawin'],
        ['date' => '2027-04-19', 'contact' => 'Mawin',   'standby' => 'Thiemo'],
    ];

    public function handle(): int
    {
        $today = Carbon::today();
        $sent = 0;

        foreach (self::SCHEDULE as $entry) {
            $date = Carbon::parse($entry['date']);
            $reminderDay = $date->copy()->subDays(7);

            if (!$today->isSameDay($reminderDay)) {
                continue;
            }

            $this->sendReminder($date, $entry['contact'], $entry['standby']);
            $sent++;
        }

        $this->info($sent === 0
            ? 'No dry-run reminders scheduled for today.'
            : "Sent {$sent} dry-run reminder(s).");

        return self::SUCCESS;
    }

    private function sendReminder(Carbon $date, string $contact, string $standby): void
    {
        Mail::to('henkvu@gmail.com')
            ->send(new DroogtestReminderMail($date, $contact, $standby));
    }
}
