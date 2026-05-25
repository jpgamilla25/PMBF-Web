<?php

namespace App\Mail;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Loan $loan,
        public string $cancelledBy, // 'applicant' or 'admin'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'PMBF — Loan Application Cancelled',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.loan-cancelled');
    }
}
