<?php

namespace App\Services;

use App\Mail\CoMakerApprovalRequestMail;
use App\Mail\CoMakerNotificationMail;
use App\Mail\LoanApplicationMail;
use App\Mail\LoanStatusMail;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\ActionRequiredNotification;
use App\Notifications\LoanStatusNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class LoanNotificationService
{
    /**
     * Send co-maker an approval request email with Approve/Decline links.
     * Used for SC loans where co-maker consent is required before receiver sees the loan.
     */
    public function notifyCoMakerApprovalRequest(Loan $loan): void
    {
        $loan->loadMissing(['user', 'coMaker', 'coMaker2']);

        // A loan may name up to two co-makers; each is asked for consent.
        foreach ([$loan->coMaker, $loan->coMaker2] as $coMaker) {
            if (!$coMaker) {
                continue;
            }

            Mail::to($coMaker->email)->send(new CoMakerApprovalRequestMail($loan));
            $coMaker->notify(new ActionRequiredNotification($loan, 'co_maker'));
        }
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
        $loan->coMaker->notify(new ActionRequiredNotification($loan, 'co_maker'));
    }

    /**
     * Notify admins that a loan exceeding the max amount needs their approval.
     */
    public function notifyAdminApprovalRequired(Loan $loan): void
    {
        $loan->loadMissing('user');

        $admins = User::where('role', 'admin')
            ->where('status', 'active')
            ->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new LoanApplicationMail($loan, 'admin'));
        }

        NotificationFacade::send($admins, new ActionRequiredNotification($loan, 'admin_approval'));
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

        // Approvers for this stage, plus admins who oversee every stage.
        $recipients = User::whereIn('role', [$targetRole, 'admin'])
            ->where('status', 'active')
            ->get()
            ->unique('id');

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->send(new LoanApplicationMail($loan, $targetRole));
        }

        NotificationFacade::send($recipients, new ActionRequiredNotification($loan, 'approval'));
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

        $loan->user->notify(new LoanStatusNotification($loan, $action, $level, $remarks));
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
