<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FmisLoanPayment;
use App\Models\ScheduleRun;
use App\Models\User;
use App\Services\LinkFmisLoanPaymentsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $rows = FmisLoanPayment::where('employee_id', $user->employee_id)
            ->where('year', $year)
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $f = $rows->get($m);
            $months[] = [
                'month' => $m,
                'amount' => $f ? (float) $f->amount : null,
                'dv_number' => $f?->dv_number,
                'dv_date' => $f?->dv_date?->toDateString(),
                'fund' => $f?->fund,
                'voided' => (bool) ($f?->voided ?? false),
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
                'year' => (float) $rows->sum('amount'),
                'lifetime' => (float) FmisLoanPayment::where('employee_id', $user->employee_id)->sum('amount'),
            ],
            'years_with_data' => $yearTotals,
        ]);
    }

    /**
     * Pending FMIS rows that could not be auto-matched. Each row carries the
     * member's active loans and a suggested split when one exists.
     */
    public function pending(Request $request, LinkFmisLoanPaymentsService $linker): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $year = $request->filled('year') ? (int) $request->input('year') : null;
        $search = $request->input('search');

        $query = FmisLoanPayment::query()
            ->whereNull('linked_at')
            ->where('voided', false)
            // Restrict the queue to FMIS rows whose employee is a registered
            // PMBF member — unregistered rows have nothing to allocate to.
            ->whereExists(function ($q) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.employee_id', 'fmis_loan_payments.employee_id');
            })
            ->orderByDesc('dv_date');

        if ($year !== null) {
            $query->where('year', $year);
        }

        if (is_string($search) && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('employee_id', 'like', "%{$search}%")
                    ->orWhere('dv_number', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(function (FmisLoanPayment $row) use ($linker) {
            $info = $linker->suggestionFor($row);
            $user = $row->user()->first();

            return [
                'fmis_id' => $row->id,
                'employee_id' => $row->employee_id,
                'member' => $user ? [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'employment_type' => $user->employment_type,
                ] : null,
                'year' => $row->year,
                'month' => $row->month,
                'amount' => (float) $row->amount,
                'dv_number' => $row->dv_number,
                'dv_date' => $row->dv_date?->toDateString(),
                'fund' => $row->fund,
                'suggestion' => $info['suggestion'],
                'active_loans' => $info['active_loans'],
                'reason' => $info['reason'],
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Apply a chosen split to a pending FMIS row.
     */
    public function applyPending(Request $request, FmisLoanPayment $fmisLoanPayment, LinkFmisLoanPaymentsService $linker): JsonResponse
    {
        $validated = $request->validate([
            'allocations' => 'required|array|min:1',
            'allocations.*.loan_id' => 'required|integer|exists:loans,id',
            'allocations.*.amount' => 'required|numeric|min:0.01',
        ]);

        if ($fmisLoanPayment->linked_at !== null) {
            return $this->error('This FMIS row is already linked.', 422);
        }

        $sum = array_sum(array_column($validated['allocations'], 'amount'));
        if (round($sum, 2) > round((float) $fmisLoanPayment->amount + 0.01, 2)) {
            return $this->error('Allocation totals exceed the FMIS amount.', 422);
        }

        $linker->applyManual(
            $fmisLoanPayment,
            $validated['allocations'],
            $request->user()?->id
        );

        return $this->success(null, 'FMIS row applied to ' . count($validated['allocations']) . ' loan(s).');
    }

    /**
     * Run the linker now and report counts.
     */
    public function runLinker(LinkFmisLoanPaymentsService $linker): JsonResponse
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $startedAt = now();
        $runRow = ScheduleRun::create([
            'command' => 'loan-payments:link',
            'expression' => null,
            'status' => 'running',
            'started_at' => $startedAt,
            'manual' => true,
        ]);

        $startedMs = microtime(true);
        try {
            $result = $linker->linkPending();
            $durationMs = (int) round((microtime(true) - $startedMs) * 1000);

            $runRow->update([
                'status' => 'success',
                'exit_code' => 0,
                'finished_at' => now(),
                'duration_ms' => $durationMs,
                'output_excerpt' => sprintf(
                    'scanned=%d linked=%d queued=%d voided_reversed=%d',
                    $result['scanned'],
                    $result['linked'],
                    $result['queued'],
                    $result['voided_reversed']
                ),
            ]);
        } catch (\Throwable $e) {
            $runRow->update([
                'status' => 'failed',
                'exit_code' => 1,
                'finished_at' => now(),
                'output_excerpt' => mb_substr($e->getMessage(), -4000),
            ]);
            return $this->error('Linker failed: ' . $e->getMessage(), 500);
        }

        return $this->success($result, sprintf(
            'Linked %d, queued %d, voided-reversed %d (scanned %d).',
            $result['linked'],
            $result['queued'],
            $result['voided_reversed'],
            $result['scanned']
        ));
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
