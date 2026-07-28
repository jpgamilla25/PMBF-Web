<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\ShareCapital;
use App\Models\User;
use App\Traits\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ApiResponse;

    // ── Loans Report ──────────────────────────────────────────────

    public function loans(Request $request): JsonResponse
    {
        $query = $this->buildLoansQuery($request);
        $loans = $query->get();

        $summary = [
            'total_count'      => $loans->count(),
            'total_amount'     => $loans->sum('amount'),
            'total_paid'       => $loans->sum(fn($l) => $l->payments->sum('amount')),
            'total_released'   => $loans->where('status', 'released')->sum('amount'),
            'total_completed'  => $loans->where('status', 'completed')->sum('amount'),
        ];

        return $this->success([
            'loans'   => $loans->map(fn($l) => $this->formatLoan($l)),
            'summary' => $summary,
        ]);
    }

    public function loansCsv(Request $request): StreamedResponse
    {
        $loans = $this->buildLoansQuery($request)->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="loans-report-' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($loans) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Loan #', 'Applicant', 'Employee ID', 'Type', 'Employment Type', 'Amount', 'Interest Rate', 'Term (mos)', 'Monthly Amortization', 'Total Payable', 'Status', 'Applied At', 'Released At', 'Total Paid', 'Remaining Balance']);
            foreach ($loans as $l) {
                $totalPayable = $l->amount + ($l->amount * ($l->interest_rate / 100) * $l->term_months);
                $totalPaid    = $l->payments->sum('amount');
                fputcsv($file, [
                    $l->id,
                    $l->user->last_name . ', ' . $l->user->first_name . ' ' . $l->user->middle_name,
                    $l->user->employee_id,
                    $l->loan_type,
                    $l->user->employment_type,
                    $l->amount,
                    $l->interest_rate . '%',
                    $l->term_months,
                    $l->monthly_amortization,
                    round($totalPayable, 2),
                    $l->status,
                    $l->applied_at?->format('Y-m-d'),
                    $l->released_at?->format('Y-m-d'),
                    $totalPaid,
                    round($totalPayable - $totalPaid, 2),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function loansPdf(Request $request)
    {
        $loans = $this->buildLoansQuery($request)->get();

        $summary = [
            'total_count'     => $loans->count(),
            'total_amount'    => $loans->sum('amount'),
            'total_paid'      => $loans->sum(fn($l) => $l->payments->sum('amount')),
            'total_released'  => $loans->where('status', 'released')->sum('amount'),
            'total_completed' => $loans->where('status', 'completed')->sum('amount'),
        ];

        $filters = $this->describeFilters($request, ['from', 'to', 'status', 'loan_type', 'employment_type']);

        $pdf = Pdf::loadView('pdf.report-loans', compact('loans', 'summary', 'filters'));
        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('loans-report-' . now()->format('Y-m-d') . '.pdf');
    }

    private function buildLoansQuery(Request $request)
    {
        $query = Loan::with(['user', 'payments'])->latest();

        if ($request->filled('status'))          $query->where('status', $request->status);
        if ($request->filled('loan_type'))       $query->where('loan_type', $request->loan_type);
        if ($request->filled('employment_type')) $query->whereHas('user', fn($q) => $q->where('employment_type', $request->employment_type));
        if ($request->filled('from'))            $query->whereDate('applied_at', '>=', $request->from);
        if ($request->filled('to'))              $query->whereDate('applied_at', '<=', $request->to);

        return $query;
    }

    // ── Payments Report ───────────────────────────────────────────

    public function payments(Request $request): JsonResponse
    {
        $query = $this->buildPaymentsQuery($request);
        $payments = $query->get();

        $summary = [
            'total_count'  => $payments->count(),
            'total_amount' => $payments->sum('amount'),
            'by_method'    => $payments->groupBy('payment_method')->map(fn($g) => [
                'count'  => $g->count(),
                'amount' => $g->sum('amount'),
            ]),
        ];

        return $this->success([
            'payments' => $payments->map(fn($p) => $this->formatPayment($p)),
            'summary'  => $summary,
        ]);
    }

    public function paymentsCsv(Request $request): StreamedResponse
    {
        $payments = $this->buildPaymentsQuery($request)->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="payments-report-' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($payments) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['OR Number', 'Date', 'Borrower', 'Employee ID', 'Loan #', 'Loan Type', 'Employment Type', 'Amount', 'Method', 'Recorded By']);
            foreach ($payments as $p) {
                fputcsv($file, [
                    $p->or_number ?? '-',
                    $p->payment_date->format('Y-m-d'),
                    $p->loan->user->last_name . ', ' . $p->loan->user->first_name,
                    $p->loan->user->employee_id,
                    $p->loan_id,
                    $p->loan->loan_type,
                    $p->loan->user->employment_type,
                    $p->amount,
                    $p->payment_method,
                    $p->recorder?->full_name ?? 'System',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function paymentsPdf(Request $request)
    {
        $payments = $this->buildPaymentsQuery($request)->get();

        $summary = [
            'total_count'  => $payments->count(),
            'total_amount' => $payments->sum('amount'),
            'by_method'    => $payments->groupBy('payment_method')->map(fn($g) => [
                'count'  => $g->count(),
                'amount' => $g->sum('amount'),
            ]),
        ];

        $filters = $this->describeFilters($request, ['from', 'to', 'payment_method', 'employment_type']);

        $pdf = Pdf::loadView('pdf.report-payments', compact('payments', 'summary', 'filters'));
        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('payments-report-' . now()->format('Y-m-d') . '.pdf');
    }

    private function buildPaymentsQuery(Request $request)
    {
        $query = Payment::with(['loan.user', 'recorder'])->latest('payment_date');

        if ($request->filled('payment_method')) $query->where('payment_method', $request->payment_method);
        if ($request->filled('employment_type')) $query->whereHas('loan.user', fn($q) => $q->where('employment_type', $request->employment_type));
        if ($request->filled('from'))           $query->whereDate('payment_date', '>=', $request->from);
        if ($request->filled('to'))             $query->whereDate('payment_date', '<=', $request->to);

        return $query;
    }

    // ── Members Report ────────────────────────────────────────────

    public function members(Request $request): JsonResponse
    {
        $query = $this->buildMembersQuery($request);
        $members = $query->get();

        $summary = [
            'total_count'   => $members->count(),
            'by_type'       => $members->groupBy('employment_type')->map->count(),
            'active_loans'  => $members->filter(fn($m) => $m->loans->whereNotIn('status', ['completed', 'disapproved'])->count() > 0)->count(),
        ];

        return $this->success([
            'members' => $members->map(fn($m) => $this->formatMember($m)),
            'summary' => $summary,
        ]);
    }

    public function membersCsv(Request $request): StreamedResponse
    {
        $members = $this->buildMembersQuery($request)->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="members-report-' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($members) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Employee ID', 'Last Name', 'First Name', 'Middle Name', 'Email', 'Employment Type', 'Department', 'Role', 'Status', 'Total Loans', 'Active Loans', 'Joined']);
            foreach ($members as $m) {
                $activeLoans = $m->loans->whereNotIn('status', ['completed', 'disapproved'])->count();
                fputcsv($file, [
                    $m->employee_id,
                    $m->last_name,
                    $m->first_name,
                    $m->middle_name,
                    $m->email,
                    $m->employment_type,
                    $m->department,
                    $m->role,
                    $m->status ?? 'active',
                    $m->loans->count(),
                    $activeLoans,
                    $m->created_at->format('Y-m-d'),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function membersPdf(Request $request)
    {
        $members = $this->buildMembersQuery($request)->get();

        $summary = [
            'total_count'  => $members->count(),
            'by_type'      => $members->groupBy('employment_type')->map->count(),
            'active_loans' => $members->filter(fn($m) => $m->loans->whereNotIn('status', ['completed', 'disapproved'])->count() > 0)->count(),
        ];

        $filters = $this->describeFilters($request, ['employment_type', 'status', 'department']);

        $pdf = Pdf::loadView('pdf.report-members', compact('members', 'summary', 'filters'));
        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('members-report-' . now()->format('Y-m-d') . '.pdf');
    }

    private function buildMembersQuery(Request $request)
    {
        $query = User::withCount('loans')->with(['loans'])->orderBy('last_name');

        if ($request->filled('employment_type')) $query->where('employment_type', $request->employment_type);
        if ($request->filled('department'))      $query->where('department', $request->department);
        if ($request->filled('status'))          $query->where('status', $request->status);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('first_name', 'like', "%$s%")
                ->orWhere('last_name', 'like', "%$s%")
                ->orWhere('employee_id', 'like', "%$s%"));
        }

        return $query;
    }

    // ── Shares Report ─────────────────────────────────────────────

    public function shares(Request $request): JsonResponse
    {
        $year  = $request->input('year', now()->year);
        $query = $this->buildSharesQuery($request, $year);
        $rows  = $query->get();

        // Group by user
        $byUser = $rows->groupBy('user_id');
        $data   = $byUser->map(function ($entries) {
            $user  = $entries->first()->user;
            $total = $entries->sum('amount');
            return [
                'user_id'          => $userId,
                'employee_id'      => $user->employee_id,
                'name'             => $user->last_name . ', ' . $user->first_name,
                'employment_type'  => $user->employment_type,
                'department'       => $user->department,
                'monthly'          => $entries->sortBy('month')->map(fn($e) => ['month' => $e->month, 'amount' => $e->amount]),
                'total'            => $total,
            ];
        })->values();

        $summary = [
            'total_amount'  => $data->sum('total'),
            'member_count'  => $data->count(),
            'year'          => $year,
        ];

        return $this->success([
            'shares'  => $data,
            'summary' => $summary,
        ]);
    }

    public function sharesCsv(Request $request): StreamedResponse
    {
        $year  = $request->input('year', now()->year);
        $rows  = $this->buildSharesQuery($request, $year)->get();
        $byUser = $rows->groupBy('user_id');

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="shares-report-' . $year . '.csv"',
        ];

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $callback = function () use ($byUser, $months, $year) {
            $file = fopen('php://output', 'w');
            $header = ['Employee ID', 'Name', 'Employment Type', 'Department'];
            foreach ($months as $m) $header[] = "$m $year";
            $header[] = 'Total';
            fputcsv($file, $header);

            foreach ($byUser as $userId => $entries) {
                $user = $entries->first()->user;
                $row  = [$user->employee_id, $user->last_name . ', ' . $user->first_name, $user->employment_type, $user->department];
                $monthMap = $entries->keyBy('month');
                for ($m = 1; $m <= 12; $m++) {
                    $row[] = $monthMap->has($m) ? $monthMap[$m]->amount : '';
                }
                $row[] = $entries->sum('amount');
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function sharesPdf(Request $request)
    {
        $year   = $request->input('year', now()->year);
        $rows   = $this->buildSharesQuery($request, $year)->get();
        $byUser = $rows->groupBy('user_id');

        $data = $byUser->map(function ($entries) {
            $user = $entries->first()->user;
            return [
                'user'     => $user,
                'monthly'  => $entries->keyBy('month'),
                'total'    => $entries->sum('amount'),
            ];
        })->values();

        $summary = [
            'total_amount' => $data->sum('total'),
            'member_count' => $data->count(),
            'year'         => $year,
        ];

        $filters = $this->describeFilters($request, ['employment_type', 'year']);

        $pdf = Pdf::loadView('pdf.report-shares', compact('data', 'summary', 'filters', 'year'));
        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('shares-report-' . $year . '.pdf');
    }

    // ── Premium Contributions Report (COS-only) ───────────────────

    public function premiums(Request $request): JsonResponse
    {
        $year  = (int) $request->input('year', now()->year);
        $rows  = $this->buildPremiumsQuery($request, $year)->get();
        $byUser = $rows->groupBy('user_id');

        $data = $byUser->map(function ($entries) {
            $user  = $entries->first()->user;
            $total = $entries->sum('amount');
            return [
                'user_id'         => $user->id,
                'employee_id'     => $user->employee_id,
                'name'            => $user->last_name . ', ' . $user->first_name,
                'employment_type' => $user->employment_type,
                'department'      => $user->department,
                'monthly'         => $entries->sortBy('month')->map(fn($e) => ['month' => $e->month, 'amount' => $e->amount]),
                'total'           => $total,
            ];
        })->values();

        $summary = [
            'total_amount' => $data->sum('total'),
            'member_count' => $data->count(),
            'year'         => $year,
        ];

        return $this->success([
            'premiums' => $data,
            'summary'  => $summary,
        ]);
    }

    public function premiumsCsv(Request $request): StreamedResponse
    {
        $year   = (int) $request->input('year', now()->year);
        $rows   = $this->buildPremiumsQuery($request, $year)->get();
        $byUser = $rows->groupBy('user_id');

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="premiums-report-' . $year . '.csv"',
        ];

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $callback = function () use ($byUser, $months, $year) {
            $file = fopen('php://output', 'w');
            $header = ['Employee ID', 'Name', 'Department'];
            foreach ($months as $m) $header[] = "$m $year";
            $header[] = 'Total';
            fputcsv($file, $header);

            foreach ($byUser as $entries) {
                $user = $entries->first()->user;
                $row  = [$user->employee_id, $user->last_name . ', ' . $user->first_name, $user->department];
                $monthMap = $entries->keyBy('month');
                for ($m = 1; $m <= 12; $m++) {
                    $row[] = $monthMap->has($m) ? $monthMap[$m]->amount : '';
                }
                $row[] = $entries->sum('amount');
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function premiumsPdf(Request $request)
    {
        $year   = (int) $request->input('year', now()->year);
        $rows   = $this->buildPremiumsQuery($request, $year)->get();
        $byUser = $rows->groupBy('user_id');

        $data = $byUser->map(function ($entries) {
            $user = $entries->first()->user;
            return [
                'user'    => $user,
                'monthly' => $entries->keyBy('month'),
                'total'   => $entries->sum('amount'),
            ];
        })->values();

        $summary = [
            'total_amount' => $data->sum('total'),
            'member_count' => $data->count(),
            'year'         => $year,
        ];

        $filters = $this->describeFilters($request, ['year']);

        $pdf = Pdf::loadView('pdf.report-premiums', compact('data', 'summary', 'filters', 'year'));
        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('premiums-report-' . $year . '.pdf');
    }

    /**
     * Premium rows in share_capitals (type='premium') — historically tagged
     * at write time. A promoted Permanent member's old premium rows still
     * show up here; their new share rows do not.
     */
    private function buildPremiumsQuery(Request $request, int $year)
    {
        return ShareCapital::with('user')
            ->where('type', 'premium')
            ->where('year', $year)
            ->orderBy('month');
    }

    /**
     * Share rows in share_capitals (type='share') — the row's historical
     * tag, not the user's current employment_type.
     */
    private function buildSharesQuery(Request $request, int $year)
    {
        return ShareCapital::with('user')
            ->where('type', 'share')
            ->where('year', $year)
            ->orderBy('month');
    }

    // ── Loan Ledger Report ─────────────────────────────────────────

    /**
     * Per-member loan ledger: each member's loans, each with its payment
     * schedule and a running balance — mirrors the cooperative's SC ledger.
     * Filter by employment_type (Contract of Service / Permanent) and/or user.
     */
    public function ledger(Request $request): JsonResponse
    {
        $excluded = ['cancelled', 'disapproved', 'co_maker_declined'];

        $query = User::query()
            ->whereHas('loans', fn ($q) => $q->whereNotIn('status', $excluded))
            ->with(['loans' => function ($q) use ($excluded) {
                $q->whereNotIn('status', $excluded)
                    ->with(['coMaker', 'payments'])
                    ->orderBy('created_at');
            }]);

        if ($request->filled('employment_type')) {
            $query->where('employment_type', $request->input('employment_type'));
        }
        if ($request->filled('user_id')) {
            $query->where('id', $request->input('user_id'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(fn ($q) => $q->where('first_name', 'LIKE', "%{$s}%")
                ->orWhere('last_name', 'LIKE', "%{$s}%")
                ->orWhere('employee_id', 'LIKE', "%{$s}%"));
        }

        $members = $query->orderBy('last_name')->orderBy('first_name')->get()
            ->map(function (User $u) {
                // Loans are ordered oldest → newest (query orderBy created_at).
                $loans = $u->loans->map(fn ($l) => $this->formatLedgerLoan($l))->values();

                // AA SUMMARY columns: BALANCE = latest outstanding loan's remaining;
                // PREVIOUS LOAN BAL = remaining on any older still-unpaid loans.
                $outstanding = $loans->filter(fn ($l) => $l['remaining'] > 0.01)->values();
                $balance = $outstanding->isNotEmpty() ? $outstanding->last()['remaining'] : 0.0;
                $previous = $outstanding->isNotEmpty()
                    ? round($outstanding->slice(0, -1)->sum('remaining'), 2)
                    : 0.0;

                return [
                    'employee_id'           => $u->employee_id,
                    'full_name'             => $u->full_name,
                    'division'              => $u->department,
                    'employment_type'       => $u->employment_type,
                    'previous_loan_balance' => $previous,
                    'balance'               => round($balance, 2),
                    'loans'                 => $loans,
                ];
            })
            ->filter(fn ($m) => $m['loans']->isNotEmpty())
            ->values();

        $allLoans = $members->flatMap(fn ($m) => $m['loans']);
        $summary = [
            'member_count'      => $members->count(),
            'loan_count'        => $allLoans->count(),
            'total_principal'   => round($allLoans->sum('principal'), 2),
            'total_payable'     => round($allLoans->sum('total'), 2),
            'total_paid'        => round($allLoans->sum('total_paid'), 2),
            'total_outstanding' => round($allLoans->sum('remaining'), 2),
            'total_previous'    => round($members->sum('previous_loan_balance'), 2),
            'total_balance'     => round($members->sum('balance'), 2),
        ];

        return $this->success([
            'members' => $members,
            'summary' => $summary,
        ]);
    }

    /**
     * Format a single loan as a ledger block: header figures + payment
     * schedule with a running balance (total payable minus each payment).
     */
    private function formatLedgerLoan($loan): array
    {
        $principal = (float) $loan->amount;
        $interest  = round($principal * ((float) $loan->interest_rate / 100) * (float) $loan->term_months, 2);
        $total     = $loan->total_payable; // principal + interest (exact)

        $running = $total;
        $rows = $loan->payments->sortBy('payment_date')->values()->map(function ($p) use (&$running) {
            $running = round($running - (float) $p->amount, 2);
            return [
                'date'      => $p->payment_date?->format('Y-m-d'),
                'or_number' => $p->or_number,
                'amount'    => (float) $p->amount,
                'balance'   => max(0, $running),
                'method'    => $p->payment_method,
            ];
        });

        $totalPaid = (float) $loan->payments->sum('amount');

        return [
            'reference_no'        => $loan->reference_no,
            'loan_no'             => $loan->id,
            'loan_type'           => $loan->loan_type,
            'co_maker'            => $loan->coMaker?->full_name,
            'term_months'         => $loan->term_months,
            'interest_rate'       => (float) $loan->interest_rate,
            'principal'           => round($principal, 2),
            'interest'            => $interest,
            'total'               => $total,
            'monthly_amortization'=> (float) $loan->monthly_amortization,
            // Semi-monthly (per-payday) deduction — payroll runs twice a month.
            'semi_monthly'        => round((float) $loan->monthly_amortization / 2, 2),
            'status'              => $loan->status,
            'released_at'         => $loan->released_at?->format('Y-m-d'),
            'payments'            => $rows,
            'total_paid'          => round($totalPaid, 2),
            'remaining'           => max(0, round($total - $totalPaid, 2)),
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function formatLoan($loan): array
    {
        $totalPayable = $loan->amount + ($loan->amount * ($loan->interest_rate / 100) * $loan->term_months);
        $totalPaid    = $loan->payments->sum('amount');
        return [
            'id'                  => $loan->id,
            'applicant'           => $loan->user->last_name . ', ' . $loan->user->first_name,
            'employee_id'         => $loan->user->employee_id,
            'employment_type'     => $loan->user->employment_type,
            'loan_type'           => $loan->loan_type,
            'amount'              => $loan->amount,
            'interest_rate'       => $loan->interest_rate,
            'term_months'         => $loan->term_months,
            'monthly_amortization'=> $loan->monthly_amortization,
            'total_payable'       => round($totalPayable, 2),
            'total_paid'          => round($totalPaid, 2),
            'remaining_balance'   => round($totalPayable - $totalPaid, 2),
            'status'              => $loan->status,
            'applied_at'          => $loan->applied_at?->format('Y-m-d'),
            'released_at'         => $loan->released_at?->format('Y-m-d'),
        ];
    }

    private function formatPayment($payment): array
    {
        return [
            'id'             => $payment->id,
            'or_number'      => $payment->or_number,
            'payment_date'   => $payment->payment_date->format('Y-m-d'),
            'borrower'       => $payment->loan->user->last_name . ', ' . $payment->loan->user->first_name,
            'employee_id'    => $payment->loan->user->employee_id,
            'employment_type'=> $payment->loan->user->employment_type,
            'loan_id'        => $payment->loan_id,
            'loan_type'      => $payment->loan->loan_type,
            'amount'         => $payment->amount,
            'payment_method' => $payment->payment_method,
            'recorder'       => $payment->recorder?->full_name ?? 'System',
        ];
    }

    private function formatMember($member): array
    {
        return [
            'id'              => $member->id,
            'employee_id'     => $member->employee_id,
            'name'            => $member->last_name . ', ' . $member->first_name . ' ' . $member->middle_name,
            'email'           => $member->email,
            'employment_type' => $member->employment_type,
            'department'      => $member->department,
            'role'            => $member->role,
            'status'          => $member->status ?? 'active',
            'loans_count'     => $member->loans_count,
            'active_loans'    => $member->loans->whereNotIn('status', ['completed', 'disapproved'])->count(),
            'joined_at'       => $member->created_at->format('Y-m-d'),
        ];
    }

    private function describeFilters(Request $request, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if ($request->filled($key)) {
                $result[$key] = $request->input($key);
            }
        }
        return $result;
    }

    // ── Notice of Deduction Report ────────────────────────────────
    //
    // Per-division, per-cutoff payroll deduction notice. One row per active
    // salary loan whose term overlaps the selected cutoff — a member with two
    // active loans shows twice.

    public function divisions(): JsonResponse
    {
        $rows = User::query()
            ->whereNotNull('department')
            ->where('department', '<>', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->map(fn ($d) => ['value' => $d, 'label' => $d])
            ->values();

        return $this->success($rows);
    }

    public function noticeOfDeduction(Request $request): JsonResponse
    {
        return $this->success($this->buildNoticeOfDeduction($request));
    }

    public function noticeOfDeductionPdf(Request $request)
    {
        // Public route (no auth:sanctum) — resolve user from ?token= like the
        // other new-tab PDF endpoints. Prepared-by would be blank otherwise.
        $this->resolveTokenUser($request);

        $data = $this->buildNoticeOfDeduction($request);

        $pdf = Pdf::loadView('pdf.report-notice-of-deduction', $data);
        $pdf->setPaper('a4', 'portrait');

        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $data['division'] ?: 'division'));
        return $pdf->stream("notice-of-deduction-{$slug}-{$data['cutoff']['year']}-{$data['cutoff']['month']}-{$data['cutoff']['half']}.pdf");
    }

    /**
     * Attach a user to $request based on the ?token= query param (used by the
     * new-tab PDF endpoints that live outside auth:sanctum). Silently no-ops
     * if no token, no matching PAT, or auth already resolved via header.
     */
    private function resolveTokenUser(Request $request): void
    {
        if ($request->user()) return;
        $token = $request->query('token');
        if (!$token) return;

        $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        $user = $pat?->tokenable;
        if ($user) {
            $request->setUserResolver(fn () => $user);
        }
    }

    /**
     * Gathers the rows + header fields the JSON preview and the PDF share.
     */
    private function buildNoticeOfDeduction(Request $request): array
    {
        $division = trim((string) $request->input('division', ''));
        $year     = (int) $request->input('year', now()->year);
        $month    = max(1, min(12, (int) $request->input('month', now()->month)));
        $half     = (int) $request->input('half', now()->day <= 15 ? 1 : 2) === 2 ? 2 : 1;

        $cutoffStart = \Illuminate\Support\Carbon::create($year, $month, $half === 1 ? 1 : 16)->startOfDay();
        $cutoffEnd   = $half === 1
            ? \Illuminate\Support\Carbon::create($year, $month, 15)->endOfDay()
            : $cutoffStart->copy()->endOfMonth();

        $rows = collect();

        if ($division !== '') {
            $loans = Loan::query()
                ->whereIn('status', ['released', 'completed'])
                ->whereNotNull('released_at')
                ->whereHas('user', fn ($q) => $q->where('department', $division))
                ->with('user')
                ->get();

            $rows = $loans
                ->filter(function (Loan $loan) use ($cutoffStart, $cutoffEnd) {
                    $start = $loan->released_at->copy()->startOfDay();
                    $end   = $start->copy()->addMonths((int) $loan->term_months);
                    return $start->lte($cutoffEnd) && $end->gt($cutoffStart);
                })
                ->sortBy(fn (Loan $l) => strtolower($l->user->last_name . ' ' . $l->user->first_name))
                ->values()
                ->map(function (Loan $loan, int $i) {
                    $mi = $loan->user->middle_name ? ' ' . strtoupper(substr($loan->user->middle_name, 0, 1)) . '.' : '';
                    $start = $loan->released_at->copy();
                    $end   = $start->copy()->addMonths((int) $loan->term_months);
                    return [
                        'row_no'       => $i + 1,
                        'loan_id'      => $loan->id,
                        'name'         => $loan->user->last_name . ', ' . $loan->user->first_name . $mi,
                        'semi_monthly' => round((float) $loan->monthly_amortization / 2, 2),
                        'remarks'      => $start->format('M') . ' to ' . $end->format('F j, Y'),
                    ];
                });
        }

        return [
            'division'    => $division,
            'cutoff'      => [
                'year'  => $year,
                'month' => $month,
                'half'  => $half,
                'start' => $cutoffStart->format('Y-m-d'),
                'end'   => $cutoffEnd->format('Y-m-d'),
                'label' => $cutoffStart->format('F') . ' ' . $cutoffStart->day . '-' . $cutoffEnd->day . ', ' . $year,
            ],
            'rows'        => $rows,
            'prepared_by' => $request->user()?->full_name ?? '',
            'generated_at'=> now()->format('m/d/Y'),
        ];
    }
}
