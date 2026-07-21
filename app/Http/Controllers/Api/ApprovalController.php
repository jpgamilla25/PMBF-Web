<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApprovalActionRequest;
use App\Http\Requests\BulkApprovalActionRequest;
use App\Http\Resources\LoanResource;
use App\Models\Configuration;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanNotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApprovalController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly LoanNotificationService $notificationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $statusMap = [
            'receiver' => 'pending',
            'loan_committee' => 'receiver_approved',
            'chairperson' => 'committee_approved',
            'admin' => null,
        ];

        $query = Loan::with(['user', 'coMaker', 'approvals.approver'])
            ->withCount('payments');

        // Filter by tab/status
        $statusFilter = $request->input('status', 'pending');

        if ($statusFilter === 'all') {
            // No status filter — show everything
        } elseif ($statusFilter === 'pending') {
            if ($user->isAdmin()) {
                $query->whereIn('status', ['admin_pending', 'pending', 'receiver_approved', 'committee_approved', 'chairperson_approved']);
            } elseif ($user->isReceiver()) {
                $query->whereIn('status', ['pending', 'chairperson_approved']);
            } else {
                $pendingStatus = $statusMap[$user->role] ?? null;
                if ($pendingStatus) {
                    $query->where('status', $pendingStatus);
                }
            }
        } elseif ($statusFilter === 'approved') {
            $query->whereIn('status', ['chairperson_approved', 'approved']);
        } elseif ($statusFilter === 'cancelled') {
            $query->where('status', 'cancelled');
        } else {
            $query->where('status', $statusFilter);
        }

        // Return available filter options (years, loan types, employment types)
        if ($request->boolean('filters_only')) {
            return $this->success([
                'years' => Loan::selectRaw('DISTINCT YEAR(created_at) as year')
                    ->orderByDesc('year')->pluck('year'),
                'loan_types' => Loan::distinct()->pluck('loan_type')->sort()->values(),
                'employment_types' => Loan::join('users', 'loans.user_id', '=', 'users.id')
                    ->distinct()->pluck('users.employment_type')->sort()->values(),
            ]);
        }

        // Year filter
        if ($request->filled('year')) {
            $query->whereYear('created_at', $request->input('year'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('employee_id', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('loan_type')) {
            $query->where('loan_type', $request->input('loan_type'));
        }

        if ($request->filled('employment_type')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('employment_type', $request->input('employment_type'));
            });
        }

        $loans = $query->latest()->paginate($request->input('per_page', 15));

        return $this->paginated($loans, LoanResource::class);
    }

    public function show(Request $request, Loan $loan): JsonResponse
    {
        $loan->load(['user', 'coMaker', 'approvals.approver', 'payments']);

        // Detail view: approvers decide against take-home pay, which changes
        // every payroll, so this one reads live rather than the snapshot.
        return $this->success((new LoanResource($loan))->withHris(), 'Loan details retrieved.');
    }

    public function approve(ApprovalActionRequest $request, Loan $loan): JsonResponse
    {
        $user = $request->user();

        if (!$loan->canBeApprovedBy($user)) {
            return $this->error('You are not authorized to approve this loan at the current stage.', 403);
        }

        $this->performAction($loan, $user, 'approved', $request->validated('remarks'));

        return $this->success(new LoanResource($loan), 'Loan approved successfully.');
    }

    public function disapprove(ApprovalActionRequest $request, Loan $loan): JsonResponse
    {
        $user = $request->user();

        if (!$loan->canBeApprovedBy($user)) {
            return $this->error('You are not authorized to act on this loan at the current stage.', 403);
        }

        $this->performAction($loan, $user, 'disapproved', $request->validated('remarks'));

        return $this->success(new LoanResource($loan), 'Loan has been disapproved.');
    }

    /**
     * Approve many loans in one request.
     *
     * Each loan is processed independently so a single failure does not abort
     * the rest of the batch.
     */
    public function bulkApprove(BulkApprovalActionRequest $request): JsonResponse
    {
        return $this->processBulk($request, 'approved');
    }

    /**
     * Disapprove many loans in one request. Remarks are required, matching the
     * single-loan disapproval flow.
     */
    public function bulkDisapprove(BulkApprovalActionRequest $request): JsonResponse
    {
        if (blank($request->validated('remarks'))) {
            return $this->error('Remarks are required when disapproving.', 422, [
                'remarks' => ['Remarks are required when disapproving.'],
            ]);
        }

        return $this->processBulk($request, 'disapproved');
    }

    public function release(ApprovalActionRequest $request, Loan $loan): JsonResponse
    {
        $user = $request->user();

        if ($loan->status !== 'chairperson_approved') {
            return $this->error('Loan must be fully approved before release.', 422);
        }

        if (!$user->isAdmin() && !$user->isReceiver()) {
            return $this->error('Only receivers or admins can release loans.', 403);
        }

        $this->performAction($loan, $user, 'released', $request->validated('remarks'));

        return $this->success(new LoanResource($loan), 'Loan has been released.');
    }

    /**
     * Release many fully-approved loans at once.
     */
    public function bulkRelease(BulkApprovalActionRequest $request): JsonResponse
    {
        return $this->processBulk($request, 'released');
    }

    /**
     * Run a single approve/disapprove/release action. This is the ONE place the
     * state transition + notification happens — the single and bulk endpoints
     * both funnel through it so business rules never diverge.
     */
    private function performAction(Loan $loan, User $user, string $action, ?string $remarks): void
    {
        $level = $this->getUserLevel($user);

        $loan->advanceApproval($user, $action, $remarks);
        $loan->refresh()->load(['user', 'coMaker', 'approvals.approver']);

        match ($action) {
            // Notify applicant + next level approvers
            'approved' => $this->notificationService->onApproved($loan, $level, $remarks),
            // Notify applicant only
            'released' => $this->notificationService->onReleased($loan),
            default => $this->notificationService->onDisapproved($loan, $level, $remarks),
        };

        $this->autoReleaseIfConfigured($loan, $user, $action);
    }

    /**
     * Release straight after the final approval when the administrator has
     * enabled auto_release_after_approval, instead of parking the loan in
     * "Chairperson Approved" waiting for a manual release.
     *
     * Recursion is bounded: this only fires for an 'approved' action, and the
     * nested call passes 'released'.
     */
    private function autoReleaseIfConfigured(Loan $loan, User $user, string $action): void
    {
        if ($action !== 'approved' || $loan->status !== 'chairperson_approved') {
            return;
        }

        if (!Configuration::getBool('auto_release_after_approval', false)) {
            return;
        }

        $this->performAction($loan, $user, 'released', 'Released automatically after final approval.');
    }

    /**
     * Whether this user may take this action on this loan right now.
     *
     * Releasing is not part of the approval chain: it needs a fully-approved
     * loan and a receiver or admin, which is why it can't reuse
     * canBeApprovedBy().
     *
     * @return string|null Null when allowed, otherwise the reason it was skipped.
     */
    private function ineligibleReason(Loan $loan, User $user, string $action): ?string
    {
        if ($action === 'released') {
            if ($loan->status !== 'chairperson_approved') {
                return 'Not fully approved yet, so it cannot be released.';
            }

            if (!$user->isAdmin() && !$user->isReceiver()) {
                return 'Only receivers or admins can release loans.';
            }

            return null;
        }

        return $loan->canBeApprovedBy($user)
            ? null
            : 'Not awaiting your approval at the current stage.';
    }

    /**
     * Shared driver for the bulk endpoints.
     */
    private function processBulk(BulkApprovalActionRequest $request, string $action): JsonResponse
    {
        $user = $request->user();
        $remarks = $request->validated('remarks');
        $loanIds = array_values(array_unique($request->validated('loan_ids')));

        $loans = Loan::whereIn('id', $loanIds)->get()->keyBy('id');

        $processed = [];
        $failed = [];

        foreach ($loanIds as $loanId) {
            $loan = $loans->get($loanId);

            if (!$loan) {
                $failed[] = ['loan_id' => $loanId, 'reason' => 'Loan not found.'];
                continue;
            }

            if ($reason = $this->ineligibleReason($loan, $user, $action)) {
                $failed[] = ['loan_id' => $loanId, 'reason' => $reason];
                continue;
            }

            try {
                $this->performAction($loan, $user, $action, $remarks);
                $processed[] = $loanId;
            } catch (Throwable $e) {
                Log::error('Bulk approval action failed.', [
                    'loan_id' => $loanId,
                    'action' => $action,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                $failed[] = ['loan_id' => $loanId, 'reason' => 'Unexpected error while processing.'];
            }
        }

        $verb = match ($action) {
            'approved' => 'approved',
            'released' => 'released',
            default => 'disapproved',
        };

        $message = count($failed) > 0
            ? sprintf('%d %s, %d skipped.', count($processed), $verb, count($failed))
            : sprintf('%d %s.', count($processed), $verb);

        return $this->success([
            'approved' => $processed,
            'failed' => $failed,
            'total' => count($loanIds),
        ], $message);
    }

    private function getUserLevel($user): string
    {
        return match ($user->role) {
            'receiver' => 'receiver',
            'loan_committee' => 'loan_committee',
            'chairperson' => 'chairperson',
            default => 'admin',
        };
    }
}
