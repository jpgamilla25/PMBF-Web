<?php

namespace App\Mail;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Loan $loan,
        public string $action, // 'approved', 'disapproved', 'released', 'co_maker_approved', 'co_maker_declined'
        public string $level,  // 'receiver', 'loan_committee', 'chairperson', 'co_maker'
        public ?string $remarks = null,
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'approved' => "Your Loan Application Has Been Approved ({$this->level})",
            'disapproved' => 'Your Loan Application Has Been Disapproved',
            'released' => 'Your Loan Has Been Released!',
            'co_maker_approved' => 'Your Co-Maker Has Agreed — Loan Is Now Under Review',
            'co_maker_declined' => 'Your Co-Maker Has Declined — Loan Application Stopped',
        ];

        return new Envelope(
            subject: 'PMBF — ' . ($subjects[$this->action] ?? 'Loan Update'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.loan-status');
    }
}
