<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShareCapital;
use App\Models\ShareUpdateRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShareController extends Controller
{
    use ApiResponse;

    // ── Member endpoints ──────────────────────────────────

    /**
     * Get current user's share capital history.
     */
    public function myShares(Request $request): JsonResponse
    {
        $user = $request->user();
        $year = $request->input('year', now()->year);

        $shares = ShareCapital::where('user_id', $user->id)
            ->where('year', $year)
            ->orderBy('month')
            ->get();

        $currentShare = ShareCapital::where('user_id', $user->id)
            ->orderByDesc('year')->orderByDesc('month')
            ->first();

        $total = ShareCapital::where('user_id', $user->id)->sum('amount');

        return $this->success([
            'current_monthly' => $currentShare?->amount ?? 0,
            'total_shares' => $total,
            'year' => (int) $year,
            'history' => $shares,
            'pending_request' => ShareUpdateRequest::where('user_id', $user->id)
                ->where('status', 'pending')
                ->first(),
        ]);
    }

    /**
     * Member requests to update their share capital amount.
     */
    public function requestUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'requested_amount' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        // Check for existing pending request
        $hasPending = ShareUpdateRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return $this->error('You already have a pending share update request.', 422);
        }

        $currentShare = ShareCapital::where('user_id', $user->id)
            ->orderByDesc('year')->orderByDesc('month')
            ->first();

        $req = ShareUpdateRequest::create([
            'user_id' => $user->id,
            'current_amount' => $currentShare?->amount ?? 0,
            'requested_amount' => $request->input('requested_amount'),
            'reason' => $request->input('reason'),
        ]);

        return $this->success($req, 'Share update request submitted. Changes will reflect next month.', 201);
    }

    // ── Admin endpoints ───────────────────────────────────

    /**
     * List all share capitals with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month');

        $query = ShareCapital::with('user:id,first_name,last_name,middle_name,employee_id,employment_type,department')
            ->where('year', $year);

        if ($month) {
            $query->where('month', $month);
        }

        if ($request->filled('employment_type') && $request->input('employment_type') !== 'all') {
            $query->whereHas('user', fn ($q) => $q->where('employment_type', $request->input('employment_type')));
        }

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->whereHas('user', fn ($q) => $q->where('first_name', 'LIKE', "%{$s}%")
                ->orWhere('last_name', 'LIKE', "%{$s}%")
                ->orWhere('employee_id', 'LIKE', "%{$s}%"));
        }

        $shares = $query->orderBy('month')->get();

        // Get totals
        $totalQuery = ShareCapital::where('year', $year);
        if ($request->filled('employment_type') && $request->input('employment_type') !== 'all') {
            $totalQuery->whereHas('user', fn ($q) => $q->where('employment_type', $request->input('employment_type')));
        }
        $totalAmount = $totalQuery->sum('amount');

        // Group by user for summary
        $summary = ShareCapital::selectRaw('user_id, SUM(amount) as total, MAX(amount) as latest_amount')
            ->where('year', $year)
            ->when($request->filled('employment_type') && $request->input('employment_type') !== 'all', function ($q) use ($request) {
                $q->whereHas('user', fn ($q2) => $q2->where('employment_type', $request->input('employment_type')));
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->input('search');
                $q->whereHas('user', fn ($q2) => $q2->where('first_name', 'LIKE', "%{$s}%")
                    ->orWhere('last_name', 'LIKE', "%{$s}%")
                    ->orWhere('employee_id', 'LIKE', "%{$s}%"));
            })
            ->groupBy('user_id')
            ->with('user:id,first_name,last_name,middle_name,employee_id,employment_type,department')
            ->get();

        return $this->success([
            'year' => (int) $year,
            'total_amount' => $totalAmount,
            'member_count' => $summary->count(),
            'members' => $summary,
            'records' => $shares,
        ]);
    }

    /**
     * Admin: Set share capital for a user for a specific month.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
            'remarks' => 'nullable|string|max:500',
        ]);

        $share = ShareCapital::updateOrCreate(
            [
                'user_id' => $request->input('user_id'),
                'year' => $request->input('year'),
                'month' => $request->input('month'),
            ],
            [
                'amount' => $request->input('amount'),
                'remarks' => $request->input('remarks'),
                'updated_by' => $request->user()->id,
            ]
        );

        return $this->success($share->load('user'), 'Share capital updated.');
    }

    /**
     * Admin: Bulk set share capital for a user (same amount for remaining months).
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'year' => 'required|integer',
            'from_month' => 'required|integer|min:1|max:12',
        ]);

        $userId = $request->input('user_id');
        $amount = $request->input('amount');
        $year = $request->input('year');
        $fromMonth = $request->input('from_month');

        for ($m = $fromMonth; $m <= 12; $m++) {
            ShareCapital::updateOrCreate(
                ['user_id' => $userId, 'year' => $year, 'month' => $m],
                ['amount' => $amount, 'updated_by' => $request->user()->id]
            );
        }

        return $this->success(null, 'Share capital updated for months ' . $fromMonth . '-12.');
    }

    /**
     * Admin: List pending share update requests.
     */
    public function pendingRequests(Request $request): JsonResponse
    {
        $requests = ShareUpdateRequest::where('status', 'pending')
            ->with('user:id,first_name,last_name,middle_name,employee_id,employment_type')
            ->latest()
            ->get();

        return $this->success($requests);
    }

    /**
     * Admin: Approve share update request.
     */
    public function approveRequest(Request $request, ShareUpdateRequest $shareUpdateRequest): JsonResponse
    {
        if ($shareUpdateRequest->status !== 'pending') {
            return $this->error('Already processed.', 422);
        }

        $shareUpdateRequest->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'reviewer_remarks' => $request->input('remarks'),
        ]);

        // Apply starting next month
        $nextMonth = now()->addMonth();
        ShareCapital::updateOrCreate(
            [
                'user_id' => $shareUpdateRequest->user_id,
                'year' => $nextMonth->year,
                'month' => $nextMonth->month,
            ],
            [
                'amount' => $shareUpdateRequest->requested_amount,
                'updated_by' => $request->user()->id,
                'remarks' => 'Updated via approved request #' . $shareUpdateRequest->id,
            ]
        );

        return $this->success(null, 'Share update approved. Will reflect next month.');
    }

    /**
     * Admin: Reject share update request.
     */
    public function rejectRequest(Request $request, ShareUpdateRequest $shareUpdateRequest): JsonResponse
    {
        $request->validate(['remarks' => 'required|string|max:500']);

        if ($shareUpdateRequest->status !== 'pending') {
            return $this->error('Already processed.', 422);
        }

        $shareUpdateRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'reviewer_remarks' => $request->input('remarks'),
        ]);

        return $this->success(null, 'Share update request rejected.');
    }
}
