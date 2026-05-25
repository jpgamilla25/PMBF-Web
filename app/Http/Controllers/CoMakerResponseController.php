<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Services\LoanNotificationService;
use Illuminate\Http\Request;

class CoMakerResponseController extends Controller
{
    public function __construct(
        private readonly LoanNotificationService $notificationService,
    ) {}

    /**
     * Handle co-maker approve or decline via email link.
     * GET /co-maker/respond/{token}/{action}
     */
    public function respond(string $token, string $action)
    {
        $loan = Loan::where('co_maker_token', $token)
            ->where('status', 'co_maker_pending')
            ->with(['user', 'coMaker'])
            ->first();

        if (!$loan) {
            return view('co-maker.response', [
                'status' => 'invalid',
                'message' => 'This link is invalid or has already been used.',
            ]);
        }

        if (!in_array($action, ['approve', 'decline'])) {
            abort(404);
        }

        if ($action === 'approve') {
            $nextStatus = $loan->requires_admin_approval ? 'admin_pending' : 'pending';

            $loan->update([
                'co_maker_status' => 'approved',
                'co_maker_acted_at' => now(),
                'co_maker_token' => null,
                'status' => $nextStatus,
            ]);

            $fresh = $loan->fresh(['user', 'coMaker']);
            if ($loan->requires_admin_approval) {
                $this->notificationService->notifyAdminApprovalRequired($fresh);
            } else {
                $this->notificationService->notifyNextApprovers($fresh);
            }
            $this->notificationService->notifyApplicant($loan, 'co_maker_approved', 'co_maker');

            return view('co-maker.response', [
                'status' => 'approved',
                'loan' => $loan,
                'message' => 'Thank you! You have agreed to be the co-maker. The loan application will now proceed to the approvers.',
            ]);
        }

        // Decline
        $loan->update([
            'co_maker_status' => 'declined',
            'co_maker_acted_at' => now(),
            'co_maker_token' => null,
            'status' => 'co_maker_declined',
        ]);

        // Notify the applicant their co-maker declined
        $this->notificationService->notifyApplicant($loan, 'co_maker_declined', 'co_maker');

        return view('co-maker.response', [
            'status' => 'declined',
            'loan' => $loan,
            'message' => 'You have declined to be the co-maker. The applicant has been notified.',
        ]);
    }
}
