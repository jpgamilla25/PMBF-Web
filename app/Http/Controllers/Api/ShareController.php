<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FmisShareContribution;
use App\Models\ScheduleRun;
use App\Models\ShareCapital;
use App\Models\ShareUpdateRequest;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

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

        // Filter by type='share' so a member promoted from Contract of Service
        // sees only their post-promotion share rows here; their historical
        // premium rows show up on /premiums/my instead.
        $shares = ShareCapital::where('user_id', $user->id)
            ->where('type', 'share')
            ->where('year', $year)
            ->orderBy('month')
            ->get();

        $currentShare = ShareCapital::where('user_id', $user->id)
            ->where('type', 'share')
            ->orderByDesc('year')->orderByDesc('month')
            ->first();

        $total = ShareCapital::where('user_id', $user->id)
            ->where('type', 'share')
            ->sum('amount');

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

        // Share update requests only make sense for Permanent employees;
        // COS members' contributions are premiums (non-refundable coverage).
        if ($user->employment_type !== 'Permanent') {
            return $this->error('Share capital is only available to Permanent employees.', 403);
        }

        // Check for existing pending request
        $hasPending = ShareUpdateRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return $this->error('You already have a pending share update request.', 422);
        }

        $currentShare = ShareCapital::where('user_id', $user->id)
            ->where('type', 'share')
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
            ->where('type', 'share')
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
        $totalQuery = ShareCapital::where('type', 'share')->where('year', $year);
        if ($request->filled('employment_type') && $request->input('employment_type') !== 'all') {
            $totalQuery->whereHas('user', fn ($q) => $q->where('employment_type', $request->input('employment_type')));
        }
        $totalAmount = $totalQuery->sum('amount');

        // Group by user for summary
        $summary = ShareCapital::selectRaw('user_id, SUM(amount) as total, MAX(amount) as latest_amount')
            ->where('type', 'share')
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
                'type' => 'share',
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
                ['amount' => $amount, 'type' => 'share', 'updated_by' => $request->user()->id]
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
                'type' => 'share',
                'updated_by' => $request->user()->id,
                'remarks' => 'Updated via approved request #' . $shareUpdateRequest->id,
            ]
        );

        return $this->success(null, 'Share update approved. Will reflect next month.');
    }

    /**
     * Admin: Per-member monthly breakdown for the requested year, combining
     * curated share_capitals values with the raw FMIS rows for cross-check.
     */
    public function memberShares(\App\Models\User $user, Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);

        $shareRows = ShareCapital::where('user_id', $user->id)
            ->where('type', 'share')
            ->where('year', $year)
            ->orderBy('month')
            ->get();

        $fmisRows = FmisShareContribution::where('employee_id', $user->employee_id)
            ->where('year', $year)
            ->orderBy('month')
            ->orderBy('dv_date')
            ->get();

        $shareByMonth = $shareRows->keyBy('month');
        $fmisByMonth  = $fmisRows->groupBy('month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $s = $shareByMonth->get($m);
            $fs = $fmisByMonth->get($m) ?? collect();
            $fmisSum = $fs->sum(fn ($f) => (float) $f->amount);
            $months[] = [
                'month'          => $m,
                'curated_amount' => $s ? (float) $s->amount : null,
                'type'           => $s?->type,
                // Sum across every DV that fed this month (semi-monthly cutoffs).
                'fmis_amount'    => $fs->isNotEmpty() ? $fmisSum : null,
                'dv_count'       => $fs->count(),
                'dvs'            => $fs->map(fn ($f) => [
                    'dv_number' => $f->dv_number,
                    'dv_date'   => $f->dv_date?->toDateString(),
                    'fund'      => $f->fund,
                    'amount'    => (float) $f->amount,
                    'voided'    => (bool) $f->voided,
                ])->values(),
                'remarks'        => $s?->remarks,
            ];
        }

        $yearTotals = FmisShareContribution::where('employee_id', $user->employee_id)
            ->selectRaw('year, SUM(amount) AS total')
            ->groupBy('year')
            ->orderBy('year', 'desc')
            ->get()
            ->map(fn ($row) => ['year' => (int) $row->year, 'total' => (float) $row->total]);

        return $this->success([
            'user' => [
                'id' => $user->id,
                'employee_id' => $user->employee_id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'middle_name' => $user->middle_name,
                'employment_type' => $user->employment_type,
                'department' => $user->department,
            ],
            'year' => $year,
            'months' => $months,
            'totals' => [
                'curated' => (float) $shareRows->sum('amount'),
                'fmis' => (float) $fmisRows->sum('amount'),
                'lifetime_fmis' => (float) FmisShareContribution::where('employee_id', $user->employee_id)->sum('amount'),
            ],
            'years_with_data' => $yearTotals,
        ]);
    }

    /**
     * Admin: Aggregate counters split between FMIS contributions whose
     * employee_id is registered in PMBF and those that aren't.
     */
    public function analytics(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);

        $rows = FmisShareContribution::query()
            ->leftJoin('users', 'users.employee_id', '=', 'fmis_share_contributions.employee_id')
            ->where('fmis_share_contributions.year', $year)
            ->selectRaw('
                COUNT(DISTINCT CASE WHEN users.id IS NOT NULL THEN fmis_share_contributions.employee_id END) AS registered_employees,
                COALESCE(SUM(CASE WHEN users.id IS NOT NULL THEN fmis_share_contributions.amount END), 0) AS registered_total,
                COUNT(DISTINCT CASE WHEN users.id IS NULL THEN fmis_share_contributions.employee_id END) AS unregistered_employees,
                COALESCE(SUM(CASE WHEN users.id IS NULL THEN fmis_share_contributions.amount END), 0) AS unregistered_total
            ')
            ->first();

        $latestSync = FmisShareContribution::max('updated_at');

        return $this->success([
            'year' => $year,
            'registered' => [
                'employees' => (int) ($rows->registered_employees ?? 0),
                'total_amount' => (float) ($rows->registered_total ?? 0),
            ],
            'unregistered' => [
                'employees' => (int) ($rows->unregistered_employees ?? 0),
                'total_amount' => (float) ($rows->unregistered_total ?? 0),
            ],
            'last_synced_at' => $latestSync,
        ]);
    }

    /**
     * Admin: Trigger a synchronous sync from the FMIS api-center.
     * Defaults to the current calendar year; pass `year` to scope to a specific one.
     * Does NOT touch the nightly since-cursor — this is a manual refresh, not a delta pull.
     */
    public function syncFromFmis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:2020|max:2100',
        ]);

        // Full-year syncs against the live FMIS API can run several minutes
        // when paginating thousands of rows — well past php.ini's default
        // 60s max_execution_time. Lift the cap for this request only and
        // keep processing if the admin closes the tab mid-run.
        @set_time_limit(0);
        @ignore_user_abort(true);

        $year = $validated['year'] ?? (int) now()->year;

        $startedAt = now();
        $runRow = ScheduleRun::create([
            'command' => "shares:sync-from-fmis --year={$year}",
            'expression' => null,
            'status' => 'running',
            'started_at' => $startedAt,
            'manual' => true,
        ]);

        $startedMs = microtime(true);
        $exitCode = Artisan::call('shares:sync-from-fmis', [
            '--year' => $year,
            '--no-update-cursor' => true,
        ]);
        $output = Artisan::output();
        $durationMs = (int) round((microtime(true) - $startedMs) * 1000);

        $runRow->update([
            'status' => $exitCode === 0 ? 'success' : 'failed',
            'exit_code' => $exitCode,
            'finished_at' => now(),
            'duration_ms' => $durationMs,
            'output_excerpt' => mb_substr($output, -4000),
        ]);

        if ($exitCode !== 0) {
            return $this->error('Sync failed. See logs for details.', 502);
        }

        // Surface the row count summary from the new bulk output, fall back to the
        // old format for compatibility with any callers running an older binary.
        $summary = '';
        if (preg_match('/Done — (\d+) raw rows, (\d+) registered, (\d+) unregistered/u', $output, $m)) {
            $summary = "{$m[1]} rows synced — {$m[2]} registered, {$m[3]} unregistered";
        } elseif (preg_match('/Done — (\d+) upserted, (\d+) skipped/u', $output, $m)) {
            $summary = "{$m[1]} rows synced, {$m[2]} skipped (unknown employee)";
        }

        return $this->success([
            'year' => $year,
            'started_at' => $startedAt->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'duration_seconds' => (int) $startedAt->diffInSeconds(now()),
            'summary' => $summary,
            'schedule_run_id' => $runRow->id,
        ], $summary !== '' ? "FMIS sync complete: {$summary}." : 'FMIS sync complete.');
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
