<?php

namespace App\Mail;

use App\Models\LoanExemptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExemptionRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LoanExemptionRequest $exemptionRequest
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'PMBF - New Loan Exemption Request',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.exemption-request',
            with: [
                'exemption' => $this->exemptionRequest,
                'employee' => $this->exemptionRequest->user,
            ],
        );
    }
}
