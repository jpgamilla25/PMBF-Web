<?php

namespace App\Mail;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanApplicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Loan $loan,
        public string $recipientRole,
    ) {}

    public function envelope(): Envelope
    {
        $status = match ($this->recipientRole) {
            'receiver' => 'New Loan Application Pending Review',
            'loan_committee' => 'Loan Application Awaiting Committee Review',
            'chairperson' => 'Loan Application Awaiting Final Approval',
            default => 'Loan Application Update',
        };

        return new Envelope(subject: "PMBF — {$status}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.loan-application');
    }
}
