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

        $totalPayable = $loan->monthly_amortization * $loan->term_months;
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
}
