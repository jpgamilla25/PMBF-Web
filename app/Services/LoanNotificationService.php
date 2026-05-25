<?php

namespace App\Services;

use App\Mail\CoMakerApprovalRequestMail;
use App\Mail\CoMakerNotificationMail;
use App\Mail\LoanApplicationMail;
use App\Mail\LoanStatusMail;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class LoanNotificationService
{
    /**
     * Send co-maker an approval request email with Approve/Decline links.
     * Used for SC loans where co-maker consent is required before receiver sees the loan.
     */
    public function notifyCoMakerApprovalRequest(Loan $loan): void
    {
        $loan->loadMissing(['user', 'coMaker']);

        if (!$loan->coMaker) {
            return;
        }

        Mail::to($loan->coMaker->email)->send(new CoMakerApprovalRequestMail($loan));
    }

    /**
     * Notify co-maker that they've been named on a loan.
     */
    public function notifyCoMaker(Loan $loan): void
    {
        $loan->loadMissing(['user', 'coMaker']);

        if (!$loan->coMaker) {
            return;
        }

        Mail::to($loan->coMaker->email)->send(new CoMakerNotificationMail($loan));
    }

    /**
     * Notify admins that a loan exceeding the max amount needs their approval.
     */
    public function notifyAdminApprovalRequired(Loan $loan): void
    {
        $loan->loadMissing('user');

        $admins = User::where('role', 'admin')
            ->where('status', 'active')
            ->pluck('email')
            ->toArray();

        foreach ($admins as $email) {
            Mail::to($email)->send(new LoanApplicationMail($loan, 'admin'));
        }
    }

    /**
     * Notify the next approvers when a loan is submitted or advanced.
     */
    public function notifyNextApprovers(Loan $loan): void
    {
        $loan->loadMissing('user');

        $targetRole = match ($loan->status) {
            'pending' => 'receiver',
            'receiver_approved' => 'loan_committee',
            'committee_approved' => 'chairperson',
            default => null,
        };

        if (!$targetRole) {
            return;
        }

        $approvers = User::where('role', $targetRole)
            ->where('status', 'active')
            ->pluck('email')
            ->toArray();

        // Also notify admins
        $admins = User::where('role', 'admin')
            ->where('status', 'active')
            ->pluck('email')
            ->toArray();

        $recipients = array_unique(array_merge($approvers, $admins));

        foreach ($recipients as $email) {
            Mail::to($email)->send(new LoanApplicationMail($loan, $targetRole));
        }
    }

    /**
     * Notify the applicant when their loan status changes.
     */
    public function notifyApplicant(Loan $loan, string $action, string $level, ?string $remarks = null): void
    {
        $loan->loadMissing('user');

        Mail::to($loan->user->email)->send(
            new LoanStatusMail($loan, $action, $level, $remarks)
        );
    }

    /**
     * Notify on approval — both applicant + next level approvers.
     */
    public function onApproved(Loan $loan, string $level, ?string $remarks = null): void
    {
        $this->notifyApplicant($loan, 'approved', $level, $remarks);
        $this->notifyNextApprovers($loan);
    }

    /**
     * Notify on disapproval — applicant only.
     */
    public function onDisapproved(Loan $loan, string $level, ?string $remarks = null): void
    {
        $this->notifyApplicant($loan, 'disapproved', $level, $remarks);
    }

    /**
     * Notify on release — applicant only.
     */
    public function onReleased(Loan $loan): void
    {
        $this->notifyApplicant($loan, 'released', 'release');
    }
}
