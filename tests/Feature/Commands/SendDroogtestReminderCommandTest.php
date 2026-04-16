<?php

namespace Tests\Feature\Commands;

use App\Mail\DroogtestReminderMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendDroogtestReminderCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function reminderDayFor(int $scheduleIndex): Carbon
    {
        $schedule = config('droogtest.schedule');
        $tz = config('droogtest.timezone');
        $offset = (int) config('droogtest.reminder_days_before');

        return Carbon::parse($schedule[$scheduleIndex]['date'], $tz)->subDays($offset);
    }

    public function test_no_reminder_sent_on_arbitrary_day(): void
    {
        Carbon::setTestNow('2026-04-16');

        $this->artisan('droogtest:reminder')
            ->expectsOutputToContain('No dry-run reminders scheduled for today')
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_reminder_sent_on_first_scheduled_reminder_day(): void
    {
        Carbon::setTestNow($this->reminderDayFor(0));

        $this->artisan('droogtest:reminder')
            ->expectsOutputToContain('Sent 1 dry-run reminder')
            ->assertSuccessful();

        Mail::assertSentCount(1);
    }

    public function test_reminder_sent_on_second_scheduled_reminder_day(): void
    {
        Carbon::setTestNow($this->reminderDayFor(1));

        $this->artisan('droogtest:reminder')->assertSuccessful();

        Mail::assertSentCount(1);
    }

    public function test_reminder_addressed_to_configured_recipient(): void
    {
        Carbon::setTestNow($this->reminderDayFor(0));
        $expectedRecipient = config('droogtest.recipient');

        $this->artisan('droogtest:reminder')->assertSuccessful();

        Mail::assertSent(DroogtestReminderMail::class, function ($mail) use ($expectedRecipient) {
            return $mail->hasTo($expectedRecipient);
        });
    }

    public function test_reminder_carries_first_contact_in_payload(): void
    {
        Carbon::setTestNow($this->reminderDayFor(0));
        $expectedContact = config('droogtest.schedule.0.contact');

        $this->artisan('droogtest:reminder')->assertSuccessful();

        Mail::assertSent(DroogtestReminderMail::class, function ($mail) use ($expectedContact) {
            return $mail->contact === $expectedContact;
        });
    }

    public function test_warning_when_schedule_expired(): void
    {
        $schedule = config('droogtest.schedule');
        $latestDate = Carbon::parse(end($schedule)['date'], config('droogtest.timezone'));
        Carbon::setTestNow($latestDate->copy()->addDay());

        $this->artisan('droogtest:reminder')
            ->expectsOutputToContain('Droogtest schedule is verlopen')
            ->assertSuccessful();
    }

    public function test_runbook_markdown_matches_config_schedule(): void
    {
        $runbook = file_get_contents(base_path('docs/kb/runbooks/droogtest-schema-2026-2027.md'));
        $this->assertNotFalse($runbook, 'Runbook bestaat niet');

        foreach (config('droogtest.schedule') as $entry) {
            $this->assertStringContainsString(
                $entry['date'],
                $runbook,
                "Schedule-datum {$entry['date']} ontbreekt in runbook (drift tussen config en docs)"
            );
            $this->assertStringContainsString(
                $entry['contact'],
                $runbook,
                "Contactpersoon {$entry['contact']} ontbreekt in runbook"
            );
        }
    }
}
