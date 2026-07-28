<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FmisLoanPayment;
use App\Models\Loan;
use App\Models\User;
use App\Traits\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Member payment statement — a member's own payment history across all of
 * their loans, as JSON (for the on-screen preview) or PDF (for download).
 *
 * Payment rows come from `Payment` (Loan::payments), which is the authoritative
 * per-loan source and therefore agrees with the balances shown elsewhere in the
 * app. FMIS payroll deductions are keyed by employee/year/month with no loan_id,
 * so they can never be attributed to a specific loan — they are surfaced only as
 * a separate informational "payroll deduction history" section.
 */
class MemberStatementController extends Controller
{
    use ApiResponse;

    /** Loan statuses that never form part of a payment statement. */
    private const EXCLUDED_STATUSES = ['cancelled', 'disapproved', 'co_maker_declined'];

    /**
     * JSON version — used by the UI to render an on-screen preview.
     * GET /api/v1/member/statement
     */
    public function data(Request $request): JsonResponse
    {
        $target = $this->resolveTarget($request, $request->user());

        if (!$target instanceof User) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->success(
            $this->buildStatement($target, $request),
            'Payment statement retrieved.'
        );
    }

    /**
     * PDF version.
     * Accepts auth via Bearer header OR ?token= query param (for new tab).
     * GET /api/v1/member/statement/pdf
     */
    public function pdf(Request $request)
    {
        $user = $request->user();
        if (!$user && $request->query('token')) {
            $accessToken = PersonalAccessToken::findToken($request->query('token'));
            $user = $accessToken?->tokenable;
        }

        if (!$user) {
            abort(401, 'Unauthorized.');
        }

        $target = $this->resolveTarget($request, $user);

        if (!$target instanceof User) {
            abort(403, 'Unauthorized.');
        }

        $statement = $this->buildStatement($target, $request);

        $pdf = Pdf::loadView('pdf.member-statement', ['s' => $statement]);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream(
            'Payment-Statement-' . ($target->employee_id ?: $target->id) . '-' . now()->format('Y-m-d') . '.pdf'
        );
    }

    // ── Internals ──────────────────────────────────────────────────

    /**
     * Whose statement is being generated?
     * A member may only ever generate their own. Staff/admin may pass ?user_id=
     * to generate one for a specified member. Returns null when not allowed.
     */
    private function resolveTarget(Request $request, User $user): ?User
    {
        $requestedId = $request->input('user_id');

        if (!$requestedId || (int) $requestedId === (int) $user->id) {
            return $user;
        }

        if (!$user->isStaff()) {
            return null;
        }

        return User::find($requestedId);
    }

    private function buildStatement(User $member, Request $request): array
    {
        $from   = $request->filled('from') ? $request->input('from') : null;
        $to     = $request->filled('to') ? $request->input('to') : null;
        $loanId = $request->filled('loan_id') ? (int) $request->input('loan_id') : null;

        $query = Loan::where('user_id', $member->id)
            ->whereNotIn('status', self::EXCLUDED_STATUSES)
            ->with(['payments', 'coMaker'])
            ->orderBy('created_at');

        if ($loanId) {
            $query->where('id', $loanId);
        }

        $loans = $query->get()->map(fn (Loan $loan) => $this->formatLoan($loan, $from, $to))->values();

        $summary = [
            'total_borrowed'     => round($loans->sum('principal'), 2),
            'total_payable'      => round($loans->sum('total_payable'), 2),
            'total_paid'         => round($loans->sum('total_paid'), 2),
            'total_paid_in_range'=> round($loans->sum('paid_in_range'), 2),
            'total_outstanding'  => round($loans->sum('remaining'), 2),
            'active_loans'       => $loans->filter(fn ($l) => $l['is_active'])->count(),
            'loan_count'         => $loans->count(),
            'payment_count'      => $loans->sum(fn ($l) => count($l['payments'])),
        ];

        $payroll = $this->payrollDeductions($member, $from, $to);

        return [
            'member' => [
                'id'              => $member->id,
                'employee_id'     => $member->employee_id,
                'full_name'       => $member->full_name,
                'employment_type' => $member->employment_type,
                'department'      => $member->department,
                'position'        => $member->position,
                'email'           => $member->email,
            ],
            'filters' => [
                'from'    => $from,
                'to'      => $to,
                'loan_id' => $loanId,
            ],
            'loans'              => $loans,
            'summary'            => $summary,
            'payroll_deductions' => $payroll['rows'],
            'payroll_total'      => $payroll['total'],
            'generated_at'       => now()->format('M d, Y h:i A'),
        ];
    }

    /**
     * One loan block: header figures + the payments that fall inside the range,
     * each carrying the running balance. The running balance is computed over
     * ALL of the loan's payments in date order so that a date-filtered view
     * still shows true balances; `opening_balance` is the balance immediately
     * before the first displayed payment.
     */
    private function formatLoan(Loan $loan, ?string $from, ?string $to): array
    {
        $totalPayable = $loan->total_payable;
        $principal    = (float) $loan->amount;
        $interest     = round($totalPayable - $principal, 2);

        $running  = $totalPayable;
        $opening  = $totalPayable;
        $seenAny  = false;
        $rows     = [];
        $inRange  = 0.0;

        foreach ($loan->payments->sortBy('payment_date')->values() as $payment) {
            $date    = $payment->payment_date;
            $dateStr = $date?->format('Y-m-d');

            $before  = $running;
            $running = round($running - (float) $payment->amount, 2);

            $include = true;
            if ($from && (!$dateStr || $dateStr < $from)) $include = false;
            if ($to && (!$dateStr || $dateStr > $to))     $include = false;

            if (!$include) {
                continue;
            }

            if (!$seenAny) {
                $opening = $before;
                $seenAny = true;
            }

            $inRange += (float) $payment->amount;

            $rows[] = [
                'id'        => $payment->id,
                'date'      => $dateStr,
                'reference' => $payment->or_number,
                'method'    => $payment->payment_method,
                'amount'    => round((float) $payment->amount, 2),
                'balance'   => max(0, $running),
                'remarks'   => $payment->remarks,
            ];
        }

        $totalPaid = round((float) $loan->payments->sum('amount'), 2);
        // A renewed loan is closed — its balance was rolled into the renewal —
        // and a completed loan is fully paid, so neither is still outstanding.
        $isClosed = in_array($loan->status, ['renewed', 'completed'], true);
        $remaining = $isClosed ? 0.0 : max(0, round($totalPayable - $totalPaid, 2));

        return [
            'loan_id'              => $loan->id,
            'reference_no'         => $loan->reference_no,
            'loan_type'            => $loan->loan_type,
            'status'               => $loan->status,
            'principal'            => round($principal, 2),
            'interest'             => $interest,
            'interest_rate'        => (float) $loan->interest_rate,
            'term_months'          => (int) $loan->term_months,
            'monthly_amortization' => round((float) $loan->monthly_amortization, 2),
            'total_payable'        => $totalPayable,
            'co_maker'             => $loan->coMaker?->full_name,
            'applied_at'           => $loan->applied_at?->format('Y-m-d'),
            'released_at'          => $loan->released_at?->format('Y-m-d'),
            'opening_balance'      => max(0, round($opening, 2)),
            'payments'             => $rows,
            'paid_in_range'        => round($inRange, 2),
            'total_paid'           => $totalPaid,
            'remaining'            => $remaining,
            'is_active'            => !$isClosed && $remaining > 0.01,
        ];
    }

    /**
     * FMIS payroll deductions for this employee — informational only.
     * Keyed by employee/year/month with no loan_id, so these are NEVER mixed
     * into per-loan totals or balances.
     */
    private function payrollDeductions(User $member, ?string $from, ?string $to): array
    {
        if (!$member->employee_id) {
            return ['rows' => [], 'total' => 0.0];
        }

        $query = FmisLoanPayment::where('employee_id', $member->employee_id)
            ->where('voided', false)
            ->orderBy('year')
            ->orderBy('month');

        // Deduction periods are year/month; compare on the period's first day so
        // rows without a dv_date are still filtered correctly.
        if ($from) {
            $f = \Illuminate\Support\Carbon::parse($from);
            $query->whereRaw('(year * 100 + month) >= ?', [$f->year * 100 + $f->month]);
        }
        if ($to) {
            $t = \Illuminate\Support\Carbon::parse($to);
            $query->whereRaw('(year * 100 + month) <= ?', [$t->year * 100 + $t->month]);
        }

        $rows = $query->get()->map(fn (FmisLoanPayment $p) => [
            'year'      => (int) $p->year,
            'month'     => (int) $p->month,
            'period'    => \Illuminate\Support\Carbon::create((int) $p->year, (int) $p->month, 1)->format('M Y'),
            'dv_number' => $p->dv_number,
            'dv_date'   => $p->dv_date?->format('Y-m-d'),
            'fund'      => $p->fund,
            'amount'    => round((float) $p->amount, 2),
        ])->values();

        return [
            'rows'  => $rows,
            'total' => round($rows->sum('amount'), 2),
        ];
    }
}
