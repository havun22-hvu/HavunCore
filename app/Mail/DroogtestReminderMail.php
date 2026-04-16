<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class DroogtestReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Carbon $date,
        public string $contact,
        public string $standby,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Droogtest noodprotocol over 1 week — {$this->contact}",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.droogtest-reminder',
            with: [
                'date' => $this->date,
                'contact' => $this->contact,
                'standby' => $this->standby,
            ],
        );
    }
}
