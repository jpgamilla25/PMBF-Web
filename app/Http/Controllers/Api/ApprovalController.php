<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApprovalActionRequest;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use App\Services\LoanNotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return $this->success(new LoanResource($loan), 'Loan details retrieved.');
    }

    public function approve(ApprovalActionRequest $request, Loan $loan): JsonResponse
    {
        $user = $request->user();

        if (!$loan->canBeApprovedBy($user)) {
            return $this->error('You are not authorized to approve this loan at the current stage.', 403);
        }

        $level = $this->getUserLevel($user);
        $loan->advanceApproval($user, 'approved', $request->validated('remarks'));
        $loan->refresh()->load(['user', 'coMaker', 'approvals.approver']);

        // Email: notify applicant + next level approvers
        $this->notificationService->onApproved($loan, $level, $request->validated('remarks'));

        return $this->success(new LoanResource($loan), 'Loan approved successfully.');
    }

    public function disapprove(ApprovalActionRequest $request, Loan $loan): JsonResponse
    {
        $user = $request->user();

        if (!$loan->canBeApprovedBy($user)) {
            return $this->error('You are not authorized to act on this loan at the current stage.', 403);
        }

        $level = $this->getUserLevel($user);
        $loan->advanceApproval($user, 'disapproved', $request->validated('remarks'));
        $loan->refresh()->load(['user', 'coMaker', 'approvals.approver']);

        // Email: notify applicant
        $this->notificationService->onDisapproved($loan, $level, $request->validated('remarks'));

        return $this->success(new LoanResource($loan), 'Loan has been disapproved.');
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

        $loan->advanceApproval($user, 'released', $request->validated('remarks'));
        $loan->refresh()->load(['user', 'coMaker', 'approvals.approver']);

        // Email: notify applicant that loan is released
        $this->notificationService->onReleased($loan);

        return $this->success(new LoanResource($loan), 'Loan has been released.');
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
