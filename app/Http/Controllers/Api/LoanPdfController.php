<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Loan;
use App\Services\LoanService;
use App\Traits\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class LoanPdfController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly LoanService $loanService,
    ) {}

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
            'schedule' => $this->loanService->amortizationSchedule($loan)['schedule'],
            'totalPayable' => $totalPayable,
            'totalPaid' => $totalPaid,
            'remaining' => $remaining,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream("Loan-{$loan->reference_no}-Payment-Breakdown.pdf");
    }

    /**
     * Printable Contract-of-Service loan agreement (promissory note).
     * Only available for COS borrowers; pre-fills applicant, co-maker, amount
     * (numeric + words), amortization, and term. Signature dates, IDs and
     * notary docket stay blank for pen-and-paper completion at signing.
     */
    public function agreement(Request $request, Loan $loan)
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

        $loan->load(['user', 'coMaker']);

        if ($loan->user?->employment_type !== 'Contract of Service') {
            abort(404, 'Loan agreement is only available for Contract of Service borrowers.');
        }

        $pdf = Pdf::loadView('pdf.loan-agreement', [
            'loan'              => $loan,
            'amountInWords'     => $this->amountInWords((float) $loan->amount),
            'applicantIssuedOn' => $this->idIssuedOn($loan->user?->employee_id),
            'coMakerIssuedOn'   => $this->idIssuedOn($loan->coMaker?->employee_id),
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream("Loan-{$loan->reference_no}-Agreement.pdf");
    }

    /**
     * Peso amount → English words, e.g. 12345.50 → "TWELVE THOUSAND THREE
     * HUNDRED FORTY-FIVE PESOS AND 50/100". Handles up to 9,999,999.99.
     */
    /**
     * PhilRice employee IDs encode the ID-issue date: "YY-MMDD" (e.g. "19-0922"
     * → 22 September 2019). Years ≥ 50 are treated as 19YY, else 20YY.
     * Returns a formatted date string, or '' if the ID doesn't match the shape.
     */
    private function idIssuedOn(?string $employeeId): string
    {
        if (!$employeeId || !preg_match('/^(\d{2})-(\d{2})(\d{2})$/', $employeeId, $m)) {
            return '';
        }

        [$_, $yy, $mm, $dd] = $m;
        $year = (int) $yy;
        $year += $year >= 50 ? 1900 : 2000;

        try {
            return \Illuminate\Support\Carbon::create($year, (int) $mm, (int) $dd)->format('M d, Y');
        } catch (\Throwable) {
            return '';
        }
    }

    private function amountInWords(float $amount): string
    {
        $pesos = (int) floor($amount);
        $cents = (int) round(($amount - $pesos) * 100);

        $words = strtoupper($this->intToWords($pesos));

        if ($cents === 0) {
            return "{$words} PESOS";
        }

        $centStr = str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
        return "{$words} PESOS AND {$centStr}/100";
    }

    private function intToWords(int $n): string
    {
        if ($n === 0) return 'zero';

        $ones = ['', 'one','two','three','four','five','six','seven','eight','nine',
                 'ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen',
                 'seventeen','eighteen','nineteen'];
        $tens = ['', '', 'twenty','thirty','forty','fifty','sixty','seventy','eighty','ninety'];

        $chunk = function (int $x) use (&$chunk, $ones, $tens): string {
            if ($x < 20) return $ones[$x];
            if ($x < 100) {
                $t = intdiv($x, 10); $o = $x % 10;
                return $tens[$t] . ($o ? '-' . $ones[$o] : '');
            }
            $h = intdiv($x, 100); $r = $x % 100;
            return $ones[$h] . ' hundred' . ($r ? ' ' . $chunk($r) : '');
        };

        $parts = [];
        $millions = intdiv($n, 1_000_000);
        $thousands = intdiv($n % 1_000_000, 1_000);
        $rest = $n % 1_000;

        if ($millions)  $parts[] = $chunk($millions) . ' million';
        if ($thousands) $parts[] = $chunk($thousands) . ' thousand';
        if ($rest)      $parts[] = $chunk($rest);

        return implode(' ', $parts);
    }
}
