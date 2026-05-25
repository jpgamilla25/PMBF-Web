<?php

namespace App\Mail;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CoMakerNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Loan $loan,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'PMBF — You Have Been Named as a Co-Maker',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.co-maker-notification');
    }
}
