<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FmisLoanPayment;
use App\Models\ScheduleRun;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

class LoanPaymentController extends Controller
{
    use ApiResponse;

    /**
     * Per-member yearly summary, mirroring the Share Capital page.
     */
    public function index(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);
        $month = $request->input('month');

        $query = FmisLoanPayment::query()->where('year', $year);
        if ($month) {
            $query->where('month', (int) $month);
        }

        if ($request->filled('employment_type') && $request->input('employment_type') !== 'all') {
            $query->whereHas('user', fn ($q) => $q->where('employment_type', $request->input('employment_type')));
        }

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('employee_id', 'like', "%{$s}%")
                    ->orWhereHas('user', fn ($q2) => $q2->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name', 'like', "%{$s}%"));
            });
        }

        $totalAmount = (clone $query)->sum('amount');

        $summary = FmisLoanPayment::query()
            ->selectRaw('fmis_loan_payments.employee_id, SUM(fmis_loan_payments.amount) AS total, MAX(fmis_loan_payments.amount) AS latest_amount, MAX(fmis_loan_payments.month) AS latest_month')
            ->leftJoin('users', 'users.employee_id', '=', 'fmis_loan_payments.employee_id')
            ->where('fmis_loan_payments.year', $year)
            ->when($request->filled('employment_type') && $request->input('employment_type') !== 'all', fn ($q) =>
                $q->where('users.employment_type', $request->input('employment_type')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->input('search');
                $q->where(function ($q2) use ($s) {
                    $q2->where('fmis_loan_payments.employee_id', 'like', "%{$s}%")
                        ->orWhere('users.first_name', 'like', "%{$s}%")
                        ->orWhere('users.last_name', 'like', "%{$s}%");
                });
            })
            ->groupBy('fmis_loan_payments.employee_id')
            ->orderByDesc('total')
            ->with('user:id,first_name,last_name,middle_name,employee_id,employment_type,department')
            ->get();

        return $this->success([
            'year' => $year,
            'total_amount' => (float) $totalAmount,
            'member_count' => $summary->count(),
            'members' => $summary,
        ]);
    }

    /**
     * Counters split between registered PMBF members and unregistered employees.
     */
    public function analytics(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);

        $rows = FmisLoanPayment::query()
            ->leftJoin('users', 'users.employee_id', '=', 'fmis_loan_payments.employee_id')
            ->where('fmis_loan_payments.year', $year)
            ->selectRaw('
                COUNT(DISTINCT CASE WHEN users.id IS NOT NULL THEN fmis_loan_payments.employee_id END) AS registered_employees,
                COALESCE(SUM(CASE WHEN users.id IS NOT NULL THEN fmis_loan_payments.amount END), 0) AS registered_total,
                COUNT(DISTINCT CASE WHEN users.id IS NULL THEN fmis_loan_payments.employee_id END) AS unregistered_employees,
                COALESCE(SUM(CASE WHEN users.id IS NULL THEN fmis_loan_payments.amount END), 0) AS unregistered_total
            ')
            ->first();

        $latestSync = FmisLoanPayment::max('updated_at');

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
     * Per-member monthly breakdown for the requested year.
     */
    public function memberPayments(User $user, Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);

        // FMIS now sends one row per DV — a member can have multiple DVs in a
        // single month. Group them and surface the monthly total plus the
        // individual DV breakdown.
        $allRows = FmisLoanPayment::where('employee_id', $user->employee_id)
            ->where('year', $year)
            ->orderBy('month')->orderBy('dv_date')
            ->get();

        $byMonth = $allRows->groupBy('month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthRows = $byMonth->get($m, collect());

            $months[] = [
                'month' => $m,
                'amount' => $monthRows->isEmpty() ? null : (float) $monthRows->sum(fn ($r) => (float) $r->amount),
                'dv_count' => $monthRows->count(),
                'dvs' => $monthRows->map(fn ($r) => [
                    'dv_number' => $r->dv_number,
                    'dv_date' => $r->dv_date?->toDateString(),
                    'amount' => (float) $r->amount,
                    'fund' => $r->fund,
                    'voided' => (bool) $r->voided,
                ])->values()->all(),
                'voided' => $monthRows->where('voided', true)->isNotEmpty() && $monthRows->where('voided', false)->isEmpty(),
                'method' => 'Payroll Deduction',
            ];
        }

        $yearTotals = FmisLoanPayment::where('employee_id', $user->employee_id)
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
                'year' => (float) $allRows->sum('amount'),
                'lifetime' => (float) FmisLoanPayment::where('employee_id', $user->employee_id)->sum('amount'),
            ],
            'years_with_data' => $yearTotals,
        ]);
    }

    /**
     * Tabular list of FMIS payroll-deduction payments grouped per
     * (employee, year, month) inside a date range. Each row carries the
     * summed amount and the list of DVs that contributed.
     */
    public function byMonth(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:200'],
            'employment_type' => ['nullable', 'string', 'max:50'],
            'registered_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : null;
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : null;
        $search = $validated['search'] ?? null;
        $employmentType = $validated['employment_type'] ?? null;
        $registeredOnly = (bool) ($validated['registered_only'] ?? true);
        $perPage = (int) ($validated['per_page'] ?? 20);
        $page = (int) ($validated['page'] ?? 1);

        $query = FmisLoanPayment::query()
            ->where('voided', false)
            ->when($from, fn ($q) => $q->where('dv_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('dv_date', '<=', $to))
            ->when($registeredOnly || $employmentType, function ($q) use ($employmentType) {
                $q->whereExists(function ($sub) use ($employmentType) {
                    $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('users')
                        ->whereColumn('users.employee_id', 'fmis_loan_payments.employee_id');
                    if ($employmentType) {
                        $sub->where('users.employment_type', $employmentType);
                    }
                });
            })
            ->when(is_string($search) && $search !== '', function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('fmis_loan_payments.employee_id', 'like', "%{$search}%")
                        ->orWhere('fmis_loan_payments.dv_number', 'like', "%{$search}%")
                        ->orWhereExists(function ($sub) use ($search) {
                            $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                                ->from('users')
                                ->whereColumn('users.employee_id', 'fmis_loan_payments.employee_id')
                                ->where(function ($u) use ($search) {
                                    $u->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->orderByDesc('year')->orderByDesc('month')->orderBy('employee_id');

        $rows = $query->get();

        $buckets = $rows->groupBy(fn (FmisLoanPayment $r) => "{$r->employee_id}|{$r->year}|{$r->month}")->values();

        $total = $buckets->count();
        $totalAmount = (float) $rows->sum(fn (FmisLoanPayment $r) => (float) $r->amount);
        $offset = ($page - 1) * $perPage;
        $sliced = $buckets->slice($offset, $perPage)->values();
        $lastPage = max(1, (int) ceil($total / $perPage));

        $items = $sliced->map(function (\Illuminate\Support\Collection $bucketRows) {
            $first = $bucketRows->first();
            $user = User::where('employee_id', $first->employee_id)->first();

            return [
                'bucket_key' => "{$first->employee_id}|{$first->year}|{$first->month}",
                'employee_id' => $first->employee_id,
                'member' => $user ? [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'employment_type' => $user->employment_type,
                ] : null,
                'year' => (int) $first->year,
                'month' => (int) $first->month,
                'total_amount' => (float) $bucketRows->sum(fn (FmisLoanPayment $r) => (float) $r->amount),
                'dv_count' => $bucketRows->count(),
                'dvs' => $bucketRows->sortBy('dv_date')->values()->map(fn (FmisLoanPayment $r) => [
                    'dv_number' => $r->dv_number,
                    'dv_date' => $r->dv_date?->toDateString(),
                    'amount' => (float) $r->amount,
                    'fund' => $r->fund,
                ])->all(),
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'total_amount' => $totalAmount,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
        ]);
    }

    /**
     * Synchronous sync trigger. Lifts max_execution_time, logs to schedule_runs.
     */
    public function syncFromFmis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:2020|max:2100',
        ]);

        @set_time_limit(0);
        @ignore_user_abort(true);

        $year = $validated['year'] ?? (int) now()->year;

        $startedAt = now();
        $runRow = ScheduleRun::create([
            'command' => "loan-payments:sync-from-fmis --year={$year}",
            'expression' => null,
            'status' => 'running',
            'started_at' => $startedAt,
            'manual' => true,
        ]);

        $startedMs = microtime(true);
        $exitCode = Artisan::call('loan-payments:sync-from-fmis', [
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

        $summary = '';
        if (preg_match('/Done — (\d+) raw rows, (\d+) registered, (\d+) unregistered/u', $output, $m)) {
            $summary = "{$m[1]} rows synced — {$m[2]} registered, {$m[3]} unregistered";
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
}
