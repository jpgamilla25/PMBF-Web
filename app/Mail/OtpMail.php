<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $type = 'registration'
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'registration' => 'PMBF - Registration OTP Verification',
            'login' => 'PMBF - Login OTP Verification',
            'loan_application' => 'PMBF - Loan Application OTP Verification',
        ];

        return new Envelope(
            subject: $subjects[$this->type] ?? 'PMBF - OTP Verification',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
        );
    }
}
