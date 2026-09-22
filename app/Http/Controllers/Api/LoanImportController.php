<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoanImportBatch;
use App\Services\LoanImportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk import of legacy loans: template out, filled sheet back in, preview,
 * then proceed. See LoanImportService for the validation and duplicate rules.
 */
class LoanImportController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly LoanImportService $service)
    {
    }

    /**
     * Blank template, with the valid loan types and statuses spelled out on a
     * second sheet so the file comes back with values the system accepts.
     */
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Loans');

        $sheet->fromArray(LoanImportService::COLUMNS, null, 'A1');
        $sheet->fromArray([
            ['15-0313', 'Salary Loan', 50000, 1, 'flat', 24, 2250, 54000, 'released', '2025-01-15', 13500, 6, '2025-07-15', 'Existing loan', ''],
        ], null, 'A2');

        $sheet->getStyle('A1:O1')->getFont()->setBold(true);
        $sheet->getStyle('A1:O1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E2F3');
        $sheet->getStyle('A1:O1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C2:H2')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('K2')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->freezePane('A2');

        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $notes = $spreadsheet->createSheet();
        $notes->setTitle('How to use');
        $notes->fromArray([
            ['Importing existing loans'],
            [''],
            ['1.', 'Row 2 is an example — replace it with real data or delete it.'],
            ['2.', 'employee_id must match a member already in the system.'],
            ['3.', 'applied_at is required (YYYY-MM-DD). It is the loan\'s original application date, and part of how a duplicate is recognised.'],
            ['4.', 'total_payable may be left blank — it is then taken as monthly_amortization × term_months, which is what a legacy loan actually owes.'],
            [''],
            ['Already paid', 'amount_paid is how much has been collected on the loan so far; months_paid is how many months were deducted.'],
            ['', 'Give either one. With only months_paid, the amount is taken as months_paid × monthly_amortization.'],
            ['', 'It is imported as a real payment against the loan, so it shows in the payments list, the ledger and the member statement, and the balance is the loan less what was paid.'],
            ['', 'paid_as_of is the date that payment is recorded under (the month paid up to). Left blank, the application date is used.'],
            ['', 'A loan whose payments cover the whole amount is imported as completed.'],
            [''],
            ['loan_type', implode(', ', $this->service->allowed('loan_type'))],
            ['status', implode(', ', $this->service->allowed('status')) . '  (use "released" for a live legacy loan)'],
            ['interest_method', implode(', ', $this->service->allowed('interest_method'))],
            [''],
            ['Duplicates', 'The same member + loan type + amount + application date is only ever imported once, and re-uploading the same file is blocked.'],
            ['', 'If a member really holds two identical loans, type "yes" in allow_duplicate on the second row.'],
        ], null, 'A1');
        $notes->getStyle('A1')->getFont()->setBold(true);
        $notes->getColumnDimension('A')->setAutoSize(true);
        $notes->getColumnDimension('B')->setWidth(110);

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'loan_import_template_' . now()->format('Y-m-d') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Dry run — nothing is created.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $result = $this->service->stage($request->file('file'), $request->user());

        if (isset($result['error'])) {
            return $this->error($result['error']);
        }

        $s = $result['summary'];

        return $this->success(
            $result,
            "{$s['total']} rows read — {$s['ok']} ready, {$s['warning']} with warnings, {$s['duplicate']} duplicates, {$s['error']} errors. Nothing has been imported yet."
        );
    }

    public function commit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string',
            'force' => 'sometimes|boolean',
        ]);

        $result = $this->service->commit(
            $data['token'],
            $request->user(),
            (bool) ($data['force'] ?? false)
        );

        if (isset($result['error'])) {
            return $this->error($result['error'], 409);
        }

        return $this->success(
            $result,
            "{$result['imported']} loans imported (batch #{$result['batch_id']}), totalling "
            . number_format($result['amount_total'], 2) . '.'
            . ($result['opening_payments'] > 0
                ? " {$result['opening_payments']} of them opened with payments already made, totalling "
                  . number_format($result['opening_paid_total'], 2) . '.'
                : '')
        );
    }

    public function batches(Request $request): JsonResponse
    {
        $batches = LoanImportBatch::with(['uploader:id,first_name,last_name', 'rollbackUser:id,first_name,last_name'])
            ->latest('id')
            ->paginate(min((int) $request->input('per_page', 15), 100));

        return $this->success($batches);
    }

    /**
     * Undo a batch. Loans that already carry payments are kept.
     */
    public function rollback(Request $request, LoanImportBatch $batch): JsonResponse
    {
        $result = $this->service->rollback($batch, $request->user());

        if (isset($result['error'])) {
            return $this->error($result['error'], 409);
        }

        $message = "Batch #{$batch->id} rolled back — {$result['deleted']} loans removed.";

        if ($result['kept'] > 0) {
            $message .= " {$result['kept']} kept because payments have already been posted against them.";
        }

        return $this->success($result, $message);
    }
}
