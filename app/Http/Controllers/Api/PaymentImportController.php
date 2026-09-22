<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentImportBatch;
use App\Services\PaymentImportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk payment posting — download the worklist, fill in amounts, upload,
 * review the preview, then commit. See PaymentImportService for the
 * matching and duplicate rules.
 */
class PaymentImportController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PaymentImportService $service)
    {
    }

    /**
     * The worklist: every outstanding loan, one row each, already carrying
     * its loan_ref so the returned file needs no guessing to match.
     */
    public function downloadWorklist(): StreamedResponse
    {
        $loans = $this->service->outstandingLoans();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payments');

        $headers = PaymentImportService::COLUMNS;
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;

        foreach ($loans as $loan) {
            $sheet->fromArray([
                $loan->id,
                $loan->user->employee_id ?? '',
                trim(($loan->user->first_name ?? '') . ' ' . ($loan->user->last_name ?? '')),
                $loan->loan_type,
                round($loan->monthly_amortization, 2),
                round($loan->remaining_balance, 2),
                null, // amount — the operator fills this in
                null, // or_number
                null, // payment_date
                PaymentImportService::DEFAULT_METHOD,
                null, // remarks
                null, // allow_duplicate
            ], null, "A{$row}");
            $row++;
        }

        $lastRow = max($row - 1, 1);

        // Reference columns shaded, entry columns left white, so it is obvious
        // at a glance which cells are meant to be typed in.
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        $sheet->getStyle('A1:L1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E2F3');
        $sheet->getStyle("A2:F{$lastRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F2F2F2');
        $sheet->getStyle('A1:L1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E2:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("I2:I{$lastRow}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->freezePane('A2');

        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $notes = $spreadsheet->createSheet();
        $notes->setTitle('How to use');
        $notes->fromArray([
            ['Bulk payment posting'],
            [''],
            ['1.', 'Fill in "amount" for the loans being paid. Leave a row blank to skip it — blank rows post nothing.'],
            ['2.', 'Fill in "payment_date" (YYYY-MM-DD). It is required: it is part of how a duplicate payment is recognised.'],
            ['3.', 'Add "or_number" where there is one. It makes duplicate detection exact.'],
            ['4.', 'payment_method must be one of: ' . implode(', ', PaymentImportService::PAYMENT_METHODS) . '.'],
            [''],
            ['Do not edit', 'loan_ref, employee_id, member_name, loan_type — these identify the loan being paid.'],
            [''],
            ['Duplicates', 'The same loan + date + amount + OR number is only ever posted once, and re-uploading the same file is blocked.'],
            ['', 'If a member genuinely paid twice on one day for the same amount, type "yes" in "allow_duplicate" on the second row.'],
        ], null, 'A1');
        $notes->getStyle('A1')->getFont()->setBold(true);
        $notes->getColumnDimension('A')->setAutoSize(true);
        $notes->getColumnDimension('B')->setWidth(110);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'payment_worklist_' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Dry run. Nothing is written — returns a per-row verdict plus a warning
     * if this exact file was imported before.
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
            "{$s['total']} rows read — {$s['ok']} ready, {$s['warning']} with warnings, {$s['duplicate']} duplicates, {$s['error']} errors. Nothing has been posted yet."
        );
    }

    /**
     * Post a previewed file.
     */
    public function commit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string',
            // Only ever sent after the operator confirms the "already
            // imported" warning shown by preview().
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
            "{$result['imported']} payments posted (batch #{$result['batch_id']}), totalling " . number_format($result['amount_total'], 2) . '.'
        );
    }

    /**
     * Import history, newest first — each row is undoable.
     */
    public function batches(Request $request): JsonResponse
    {
        $batches = PaymentImportBatch::with(['uploader:id,first_name,last_name', 'rollbackUser:id,first_name,last_name'])
            ->latest('id')
            ->paginate(min((int) $request->input('per_page', 15), 100));

        return $this->success($batches);
    }

    /**
     * Undo a batch: its payments are deleted and any loan it closed re-opens.
     */
    public function rollback(Request $request, PaymentImportBatch $batch): JsonResponse
    {
        $result = $this->service->rollback($batch, $request->user());

        if (isset($result['error'])) {
            return $this->error($result['error'], 409);
        }

        return $this->success($result, "Batch #{$batch->id} rolled back — {$result['deleted']} payments removed.");
    }
}
