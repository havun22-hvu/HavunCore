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

    public function test_no_reminder_sent_on_arbitrary_day(): void
    {
        Carbon::setTestNow('2026-04-16');

        $this->artisan('droogtest:reminder')
            ->expectsOutputToContain('No dry-run reminders scheduled for today')
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_reminder_sent_seven_days_before_q3_2026(): void
    {
        // Q3 2026 dry run is scheduled for 2026-07-19 → reminder on 2026-07-12
        Carbon::setTestNow('2026-07-12');

        $this->artisan('droogtest:reminder')
            ->expectsOutputToContain('Sent 1 dry-run reminder')
            ->assertSuccessful();

        Mail::assertSentCount(1);
    }

    public function test_reminder_sent_seven_days_before_q4_2026(): void
    {
        // Q4 2026 dry run is scheduled for 2026-10-18 → reminder on 2026-10-11
        Carbon::setTestNow('2026-10-11');

        $this->artisan('droogtest:reminder')->assertSuccessful();

        Mail::assertSentCount(1);
    }

    public function test_reminder_addressed_to_owner_email(): void
    {
        Carbon::setTestNow('2026-07-12');

        $this->artisan('droogtest:reminder')->assertSuccessful();

        Mail::assertSent(DroogtestReminderMail::class, function ($mail) {
            return $mail->hasTo('henkvu@gmail.com');
        });
    }

    public function test_reminder_carries_contact_name_in_payload(): void
    {
        Carbon::setTestNow('2026-07-12');

        $this->artisan('droogtest:reminder')->assertSuccessful();

        Mail::assertSent(DroogtestReminderMail::class, function ($mail) {
            return $mail->contact === 'Thiemo';
        });
    }
}
