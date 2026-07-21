<?php

namespace App\Notifications;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app record that something is waiting on the recipient — a loan needing
 * their approval, or a co-maker consent request.
 *
 * Database channel only; the matching emails are already sent by
 * LoanNotificationService.
 */
class ActionRequiredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Loan $loan,
        public string $kind, // approval | co_maker | admin_approval
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->loan->loadMissing('user');

        $applicant = $this->loan->user?->full_name ?? 'A member';
        $ref = $this->loan->reference_no;
        $amount = '₱' . number_format((float) $this->loan->amount, 2);

        [$title, $message, $icon, $url] = match ($this->kind) {
            'approval' => [
                'Loan Awaiting Your Approval',
                "{$applicant} — {$ref} ({$amount}) is waiting for your review.",
                'bi-clipboard-check',
                '/approvals/' . $this->loan->id,
            ],
            'admin_approval' => [
                'Loan Needs Admin Approval',
                "{$ref} ({$amount}) from {$applicant} exceeds the standard limit and needs admin approval.",
                'bi-shield-exclamation',
                '/approvals/' . $this->loan->id,
            ],
            'co_maker' => [
                'Co-Maker Consent Requested',
                "{$applicant} named you as co-maker on {$ref} ({$amount}).",
                'bi-people',
                '/loans',
            ],
            default => [
                'Action Required',
                "{$ref} needs your attention.",
                'bi-bell',
                '/loans/' . $this->loan->id,
            ],
        };

        return [
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
            'url' => $url,
            'loan_id' => $this->loan->id,
            'kind' => $this->kind,
        ];
    }
}
