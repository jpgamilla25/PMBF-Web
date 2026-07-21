<?php

namespace App\Notifications;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app record of a loan status change for the applicant.
 *
 * Database channel only — LoanNotificationService already sends the matching
 * email through LoanStatusMail, and sending both from here would double up.
 */
class LoanStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Loan $loan,
        public string $action,          // approved | disapproved | released | cancelled
        public string $level,           // receiver | loan_committee | chairperson | admin | release
        public ?string $remarks = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $levelLabels = [
            'receiver' => 'the Receiver',
            'loan_committee' => 'the Loan Committee',
            'chairperson' => 'the Chairperson',
            'admin' => 'the Administrator',
            'release' => 'the Releasing Officer',
        ];

        $by = $levelLabels[$this->level] ?? 'PMBF';
        $ref = $this->loan->reference_no;
        $amount = '₱' . number_format((float) $this->loan->amount, 2);

        [$title, $message, $icon] = match ($this->action) {
            'approved' => [
                'Loan Approved',
                "{$ref} ({$amount}) was approved by {$by}.",
                'bi-check-circle',
            ],
            'disapproved' => [
                'Loan Disapproved',
                "{$ref} ({$amount}) was disapproved by {$by}."
                    . ($this->remarks ? " Reason: {$this->remarks}" : ''),
                'bi-x-circle',
            ],
            'released' => [
                'Loan Released',
                "{$ref} ({$amount}) has been released.",
                'bi-cash-coin',
            ],
            'cancelled' => [
                'Loan Cancelled',
                "{$ref} ({$amount}) was cancelled.",
                'bi-slash-circle',
            ],
            default => [
                'Loan Update',
                "{$ref} ({$amount}) was updated.",
                'bi-bell',
            ],
        };

        return [
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
            'url' => '/loans/' . $this->loan->id,
            'loan_id' => $this->loan->id,
            'action' => $this->action,
            'level' => $this->level,
        ];
    }
}
