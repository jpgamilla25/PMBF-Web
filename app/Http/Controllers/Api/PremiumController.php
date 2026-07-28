<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FmisShareContribution;
use App\Models\ShareCapital;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract of Service (COS) members contribute Premiums — non-refundable
 * benefit-fund coverage payments. Storage-wise these live in `share_capitals`
 * with type='premium' alongside Permanent members' shares (type='share'); the
 * discriminator was chosen so a member promoted from COS → Permanent keeps
 * their historical premium rows tagged correctly.
 *
 * Manual FMIS sync trigger is intentionally not duplicated here — the
 * `shares:sync-from-fmis` command feeds both types based on each user's
 * current employment_type, so ShareController@syncFromFmis is the single
 * trigger surface.
 */
class PremiumController extends Controller
{
    use ApiResponse;

    // ── Member endpoints ──────────────────────────────────

    /**
     * Get current user's premium contribution history. Any authenticated
     * user can hit this — a Permanent member who was previously COS will
     * still see their historical premium rows here.
     */
    public function myPremiums(Request $request): JsonResponse
    {
        $user = $request->user();
        $year = $request->input('year', now()->year);

        $rows = ShareCapital::where('user_id', $user->id)
            ->where('type', 'premium')
            ->where('year', $year)
            ->orderBy('month')
            ->get();

        $latest = ShareCapital::where('user_id', $user->id)
            ->where('type', 'premium')
            ->orderByDesc('year')->orderByDesc('month')
            ->first();

        $total = ShareCapital::where('user_id', $user->id)
            ->where('type', 'premium')
            ->sum('amount');

        return $this->success([
            'current_monthly' => $latest?->amount ?? 0,
            'total_premium'   => $total,
            'year'            => (int) $year,
            'history'         => $rows,
        ]);
    }

    // ── Admin endpoints ───────────────────────────────────

    /**
     * List all premium rows with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month');

        $query = ShareCapital::with('user:id,first_name,last_name,middle_name,employee_id,employment_type,department')
            ->where('type', 'premium')
            ->where('year', $year);

        if ($month) {
            $query->where('month', $month);
        }

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->whereHas('user', fn ($q) => $q->where('first_name', 'LIKE', "%{$s}%")
                ->orWhere('last_name', 'LIKE', "%{$s}%")
                ->orWhere('employee_id', 'LIKE', "%{$s}%"));
        }

        $rows = $query->orderBy('month')->get();

        $totalAmount = ShareCapital::where('type', 'premium')->where('year', $year)->sum('amount');

        $summary = ShareCapital::selectRaw('user_id, SUM(amount) as total, MAX(amount) as latest_amount')
            ->where('type', 'premium')
            ->where('year', $year)
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
            'year'         => (int) $year,
            'total_amount' => $totalAmount,
            'member_count' => $summary->count(),
            'members'      => $summary,
            'records'      => $rows,
        ]);
    }

    /**
     * Admin: Set premium amount for a user for a specific month.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount'  => 'required|numeric|min:0',
            'year'    => 'required|integer',
            'month'   => 'required|integer|min:1|max:12',
            'remarks' => 'nullable|string|max:500',
        ]);

        $row = ShareCapital::updateOrCreate(
            [
                'user_id' => $request->input('user_id'),
                'year'    => $request->input('year'),
                'month'   => $request->input('month'),
            ],
            [
                'amount'     => $request->input('amount'),
                'type'       => 'premium',
                'remarks'    => $request->input('remarks'),
                'updated_by' => $request->user()->id,
            ]
        );

        return $this->success($row->load('user'), 'Premium updated.');
    }

    /**
     * Admin: Bulk set premium for a user (same amount for remaining months).
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'    => 'required|exists:users,id',
            'amount'     => 'required|numeric|min:0',
            'year'       => 'required|integer',
            'from_month' => 'required|integer|min:1|max:12',
        ]);

        $userId    = $request->input('user_id');
        $amount    = $request->input('amount');
        $year      = $request->input('year');
        $fromMonth = $request->input('from_month');

        for ($m = $fromMonth; $m <= 12; $m++) {
            ShareCapital::updateOrCreate(
                ['user_id' => $userId, 'year' => $year, 'month' => $m],
                ['amount' => $amount, 'type' => 'premium', 'updated_by' => $request->user()->id]
            );
        }

        return $this->success(null, "Premium updated for months {$fromMonth}-12.");
    }

    /**
     * Admin: Per-member monthly premium breakdown for a year, combining the
     * curated premium rows with the raw FMIS rows for cross-check.
     */
    public function memberPremiums(\App\Models\User $user, Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);

        $curated = ShareCapital::where('user_id', $user->id)
            ->where('type', 'premium')
            ->where('year', $year)
            ->orderBy('month')
            ->get();

        $fmisRows = FmisShareContribution::where('employee_id', $user->employee_id)
            ->where('year', $year)
            ->orderBy('month')
            ->get();

        $curatedByMonth = $curated->keyBy('month');
        $fmisByMonth    = $fmisRows->keyBy('month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $c = $curatedByMonth->get($m);
            $f = $fmisByMonth->get($m);
            $months[] = [
                'month'          => $m,
                'curated_amount' => $c ? (float) $c->amount : null,
                'type'           => $c?->type,   // 'share' | 'premium' | null
                'fmis_amount'    => $f ? (float) $f->amount : null,
                'dv_number'      => $f?->dv_number,
                'dv_date'        => $f?->dv_date?->toDateString(),
                'fund'           => $f?->fund,
                'voided'         => (bool) ($f?->voided ?? false),
                'remarks'        => $c?->remarks,
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
                'id'              => $user->id,
                'employee_id'     => $user->employee_id,
                'first_name'      => $user->first_name,
                'last_name'       => $user->last_name,
                'middle_name'     => $user->middle_name,
                'employment_type' => $user->employment_type,
                'department'      => $user->department,
            ],
            'year'   => $year,
            'months' => $months,
            'totals' => [
                'curated'       => (float) $curated->sum('amount'),
                'fmis'          => (float) $fmisRows->sum('amount'),
                'lifetime_fmis' => (float) FmisShareContribution::where('employee_id', $user->employee_id)->sum('amount'),
            ],
            'years_with_data' => $yearTotals,
        ]);
    }

    /**
     * Admin: Analytics — how many COS employees contributed premiums this year,
     * split by whether their employee_id is registered in PMBF or not.
     */
    public function analytics(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);

        $rows = FmisShareContribution::query()
            ->leftJoin('users', 'users.employee_id', '=', 'fmis_share_contributions.employee_id')
            ->where('fmis_share_contributions.year', $year)
            ->where(function ($q) {
                $q->where('users.employment_type', 'Contract of Service')
                    ->orWhereNull('users.id'); // unregistered — can't know employment type
            })
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
                'employees'    => (int) ($rows->registered_employees ?? 0),
                'total_amount' => (float) ($rows->registered_total ?? 0),
            ],
            'unregistered' => [
                'employees'    => (int) ($rows->unregistered_employees ?? 0),
                'total_amount' => (float) ($rows->unregistered_total ?? 0),
            ],
            'last_synced_at' => $latestSync,
        ]);
    }
}
