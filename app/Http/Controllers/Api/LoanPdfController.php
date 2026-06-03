<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Loan;
use App\Traits\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class LoanPdfController extends Controller
{
    use ApiResponse;

    /**
     * Generate PDF summary for a loan.
     * Accepts auth via Bearer header OR ?token= query param (for new tab).
     */
    public function generate(Request $request, Loan $loan)
    {
        // Check if PDF generation is enabled
        if (!Configuration::getBool('enable_loan_pdf', true)) {
            abort(403, 'PDF generation is currently disabled.');
        }

        // Resolve user from Bearer header or ?token= query param
        $user = $request->user();
        if (!$user && $request->query('token')) {
            $accessToken = PersonalAccessToken::findToken($request->query('token'));
            $user = $accessToken?->tokenable;
        }

        if (!$user) {
            abort(401, 'Unauthorized.');
        }

        // Only owner, co-maker, staff, or admin can view
        if ($loan->user_id !== $user->id && $loan->co_maker_id !== $user->id && !$user->isStaff()) {
            abort(403, 'Unauthorized.');
        }

        $loan->load(['user', 'coMaker', 'approvals.approver', 'payments']);

        $totalPayable = $loan->total_payable;
        $totalPaid = $loan->payments->sum('amount');
        $remaining = max(0, $totalPayable - $totalPaid);

        $pdf = Pdf::loadView('pdf.loan-summary', [
            'loan' => $loan,
            'totalPayable' => $totalPayable,
            'totalPaid' => $totalPaid,
            'remaining' => $remaining,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream("Loan-{$loan->reference_no}-Summary.pdf");
    }

    /**
     * Generate a per-month payment breakdown (amortization schedule) for a loan,
     * with totals (payable / paid / remaining) at the bottom.
     */
    public function breakdown(Request $request, Loan $loan)
    {
        if (!Configuration::getBool('enable_loan_pdf', true)) {
            abort(403, 'PDF generation is currently disabled.');
        }

        $user = $request->user();
        if (!$user && $request->query('token')) {
            $accessToken = PersonalAccessToken::findToken($request->query('token'));
            $user = $accessToken?->tokenable;
        }

        if (!$user) {
            abort(401, 'Unauthorized.');
        }

        if ($loan->user_id !== $user->id && $loan->co_maker_id !== $user->id && !$user->isStaff()) {
            abort(403, 'Unauthorized.');
        }

        $loan->load(['user', 'coMaker', 'payments']);

        $totalPayable = $loan->total_payable;
        $totalPaid = $loan->payments->sum('amount');
        $remaining = max(0, $totalPayable - $totalPaid);

        $pdf = Pdf::loadView('pdf.loan-payment-breakdown', [
            'loan' => $loan,
            'schedule' => $this->buildSchedule($loan, (float) $totalPaid),
            'totalPayable' => $totalPayable,
            'totalPaid' => $totalPaid,
            'remaining' => $remaining,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream("Loan-{$loan->reference_no}-Payment-Breakdown.pdf");
    }

    /**
     * Build the flat-interest amortization schedule. Interest is fixed each month
     * (principal × rate%); the final installment absorbs the rounding so the schedule
     * sums exactly to the total payable.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSchedule(Loan $loan, float $totalPaid): array
    {
        $amount = (float) $loan->amount;
        $rate = (float) $loan->interest_rate;
        $term = max(1, (int) $loan->term_months);
        $monthly = (float) $loan->monthly_amortization;
        $totalPayable = $loan->total_payable;

        $interestPerMonth = round($amount * ($rate / 100), 2);
        $base = $loan->released_at ?? $loan->applied_at ?? $loan->created_at;

        $isReleased = (bool) $loan->released_at;
        $today = now()->startOfDay();

        $rows = [];
        $cumulative = 0.0;

        for ($i = 1; $i <= $term; $i++) {
            // Final installment absorbs the per-month rounding.
            $installment = $i < $term
                ? $monthly
                : round($totalPayable - ($monthly * ($term - 1)), 2);

            $cumulativeBefore = $cumulative;
            $cumulative += $installment;
            $balance = max(0, round($totalPayable - $cumulative, 2));
            $dueDate = $base ? $base->copy()->addMonths($i) : null;

            // Apply total paid against installments in order: fully covered → paid,
            // partially covered → partial. This reflects payments even before the
            // loan is released. Otherwise it's a projection (scheduled), or
            // upcoming/overdue once released.
            if ($totalPaid + 0.01 >= $cumulative) {
                $status = 'paid';
            } elseif ($totalPaid > $cumulativeBefore + 0.01) {
                $status = 'partial';
            } elseif (!$isReleased) {
                $status = 'scheduled';
            } elseif ($dueDate && $dueDate->lt($today)) {
                $status = 'overdue';
            } else {
                $status = 'upcoming';
            }

            $rows[] = [
                'no' => $i,
                'due_date' => $dueDate,
                'amortization' => $installment,
                'principal' => round($installment - $interestPerMonth, 2),
                'interest' => $interestPerMonth,
                'balance' => $balance,
                'status' => $status,
            ];
        }

        return $rows;
    }
}
