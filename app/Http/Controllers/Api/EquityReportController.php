<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Dependent;
use App\Models\DependentPremium;
use App\Models\DependentPremiumPayment;
use App\Services\EquityReportExcel;
use App\Services\EquityReportService;
use App\Traits\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Year-end report for Permanent members (equity & loans, dependents' medical
 * premiums, dependents & beneficiaries), plus the admin entry points for
 * dependent premiums. PhilRice counterpart is entered with the shares
 * (Share Capital page and Import Shares).
 */
class EquityReportController extends Controller
{
    use ApiResponse;

    public function __construct(private EquityReportService $service) {}

    public function show(Request $request): JsonResponse
    {
        return $this->success($this->service->build($this->year($request)));
    }

    public function excel(Request $request): StreamedResponse
    {
        $year = $this->year($request);
        $book = (new EquityReportExcel())->build($this->service->build($year));

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, "pmbf-year-end-report-{$year}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function pdf(Request $request)
    {
        $year = $this->year($request);
        $report = $this->service->build($year);

        $pdf = Pdf::loadView('pdf.report-equity', [
            'report'  => $report,
            'station' => EquityReportExcel::STATION,
        ]);
        $pdf->setPaper('legal', 'landscape');

        return $pdf->download("pmbf-year-end-report-{$year}.pdf");
    }

    // ── Data entry ────────────────────────────────────────────────

    public function savePremium(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dependent_id'      => 'required|exists:dependents,id',
            'year'              => 'required|integer|min:2000|max:2100',
            'premium'           => 'required|numeric|min:0|max:99999999',
            'monthly_deduction' => 'nullable|numeric|min:0|max:99999999',
        ]);

        $record = DependentPremium::updateOrCreate(
            ['dependent_id' => $data['dependent_id'], 'year' => $data['year']],
            [
                'premium'           => $data['premium'],
                'monthly_deduction' => $data['monthly_deduction']
                    ?? round($data['premium'] / $this->service->installments(), 2),
                'updated_by'        => $request->user()->id,
            ]
        );

        return $this->success($record, 'Premium saved.');
    }

    public function savePremiumPayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dependent_id' => 'required|exists:dependents,id',
            'year'         => 'required|integer|min:2000|max:2100',
            'month'        => 'required|integer|min:1|max:12',
            'amount'       => 'nullable|numeric|min:0|max:99999999',
        ]);

        $key = ['dependent_id' => $data['dependent_id'], 'year' => $data['year'], 'month' => $data['month']];

        // Blank or zero clears the month.
        if (empty($data['amount'])) {
            DependentPremiumPayment::where($key)->delete();
            return $this->success(null, 'Deduction cleared.');
        }

        $this->ensurePremium((int) $data['dependent_id'], (int) $data['year'], $request->user()->id);

        $record = DependentPremiumPayment::updateOrCreate($key, [
            'amount'     => $data['amount'],
            'updated_by' => $request->user()->id,
        ]);

        return $this->success($record, 'Deduction saved.');
    }

    /**
     * Record one month's deduction for every covered dependent who still owes
     * a balance and has nothing recorded for that month yet. Never overwrites.
     */
    public function postMonth(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year'  => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $posted = 0;
        foreach ($this->service->medical($data['year'])['rows'] as $row) {
            if ($row['months'][$data['month']] > 0 || $row['balance'] <= 0) {
                continue;
            }

            $this->ensurePremium($row['dependent_id'], $data['year'], $request->user()->id, $row);

            DependentPremiumPayment::create([
                'dependent_id' => $row['dependent_id'],
                'year'         => $data['year'],
                'month'        => $data['month'],
                'amount'       => min($row['monthly_deduction'], $row['balance']),
                'updated_by'   => $request->user()->id,
            ]);
            $posted++;
        }

        ActivityLog::record([
            'admin_id'    => $request->user()->id,
            'action'      => 'dependent_premiums_posted',
            'subject'     => sprintf('%d-%02d', $data['year'], $data['month']),
            'description' => "Posted dependent premium deductions for {$posted} dependent(s)",
        ]);

        return $this->success(['posted' => $posted], "Posted deductions for {$posted} dependent(s).");
    }

    // ── Helpers ───────────────────────────────────────────────────

    /** Pin the default premium on first payment, so a later config change doesn't rewrite history. */
    private function ensurePremium(int $dependentId, int $year, int $adminId, ?array $row = null): void
    {
        $default = $row['premium'] ?? $this->service->defaultPremium();

        DependentPremium::firstOrCreate(
            ['dependent_id' => $dependentId, 'year' => $year],
            [
                'premium'           => $default,
                'monthly_deduction' => $row['monthly_deduction'] ?? round($default / $this->service->installments(), 2),
                'updated_by'        => $adminId,
            ]
        );
    }

    private function year(Request $request): int
    {
        $year = (int) $request->input('year', now()->year);

        return ($year >= 2000 && $year <= 2100) ? $year : (int) now()->year;
    }
}
