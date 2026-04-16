<?php

namespace App\Console\Commands;

use App\Mail\DroogtestReminderMail;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendDroogtestReminderCommand extends Command
{
    protected $signature = 'droogtest:reminder';

    protected $description = 'Send a reminder email N days before each scheduled emergency-protocol dry run';

    public function handle(): int
    {
        $tz = config('droogtest.timezone');
        $today = Carbon::today($tz);
        $offset = (int) config('droogtest.reminder_days_before');
        $schedule = config('droogtest.schedule');

        $this->warnIfScheduleExpired($schedule, $today);

        $sent = 0;

        foreach ($schedule as $entry) {
            $reminderDay = Carbon::parse($entry['date'], $tz)->subDays($offset);

            if (!$today->isSameDay($reminderDay)) {
                continue;
            }

            $this->sendReminder(
                Carbon::parse($entry['date'], $tz),
                $entry['contact'],
                $entry['standby'],
            );
            $sent++;
        }

        $this->info($sent === 0
            ? 'No dry-run reminders scheduled for today.'
            : "Sent {$sent} dry-run reminder(s).");

        return self::SUCCESS;
    }

    private function warnIfScheduleExpired(array $schedule, Carbon $today): void
    {
        $latest = collect($schedule)
            ->map(fn ($e) => Carbon::parse($e['date'], config('droogtest.timezone')))
            ->max();

        if ($latest && $today->greaterThan($latest)) {
            $this->warn(
                "Droogtest schedule is verlopen (laatste datum: {$latest->toDateString()}). "
                . 'Voeg nieuwe entries toe in config/droogtest.php én docs/kb/runbooks/droogtest-schema-2026-2027.md.'
            );
        }
    }

    private function sendReminder(Carbon $date, string $contact, string $standby): void
    {
        Mail::to(config('droogtest.recipient'))
            ->send(new DroogtestReminderMail($date, $contact, $standby));
    }
}
