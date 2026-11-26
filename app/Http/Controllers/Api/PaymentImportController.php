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
    public function downloadWorklist(Request $request): StreamedResponse
    {
        $type = $request->input('employment_type');
        $type = ($type && $type !== 'all') ? (string) $type : null;
        $loans = $this->service->outstandingLoans($type);

        // The payroll cutoff COS rows are cut for: 1st quincena = 1st-15th
        // (paid on the 15th), 2nd = 16th-end (paid on the last day).
        $today = now();
        $year = (int) $request->input('year', $today->year);
        $month = (int) $request->input('month', $today->month);
        $quincena = (int) $request->input('quincena', $today->day <= 15 ? 1 : 2);
        if ($year < 2000 || $year > 2100) {
            $year = $today->year;
        }
        if ($month < 1 || $month > 12) {
            $month = $today->month;
        }
        if (!in_array($quincena, [1, 2], true)) {
            $quincena = 1;
        }

        $periodStart = \Carbon\Carbon::create($year, $month, 1);
        $cutoffDate = $quincena === 1 ? $periodStart->copy()->day(15) : $periodStart->copy()->endOfMonth();
        $quincenaLabel = ($quincena === 1 ? '1st' : '2nd') . ' (' . $periodStart->format('M') . ' '
            . ($quincena === 1 ? '1-15' : '16-' . $cutoffDate->day) . ', ' . $year . ')';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payments');

        $headers = PaymentImportService::COLUMNS;
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;

        $hasQuincena = false;

        foreach ($loans as $loan) {
            // COS rows carry the half-month figure and the cutoff date; the
            // operator still types the amount actually deducted.
            $isCos = in_array($loan->user->employment_type ?? null, PaymentImportService::QUINCENA_TYPES, true);
            $hasQuincena = $hasQuincena || $isCos;

            $sheet->fromArray([
                $loan->id,
                $loan->user->employee_id ?? '',
                trim(($loan->user->first_name ?? '') . ' ' . ($loan->user->last_name ?? '')),
                $loan->loan_type,
                round($loan->monthly_amortization, 2),
                $isCos ? round($loan->monthly_amortization / 2, 2) : null,
                round($loan->remaining_balance, 2),
                $isCos ? $quincenaLabel : null,
                null, // amount — the operator fills this in
                null, // or_number
                $isCos ? $cutoffDate->toDateString() : null, // payment_date
                PaymentImportService::DEFAULT_METHOD,
                null, // remarks
                null, // allow_duplicate
            ], null, "A{$row}");
            $row++;
        }

        $lastRow = $row - 1;

        $sheet->getStyle('A1:N1')->getFont()->setBold(true);
        $sheet->getStyle('A1:N1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E2F3');
        $sheet->getStyle('A1:N1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->freezePane('A2');

        // With no outstanding loans there is no data range to style, and
        // "A2:F1" would be an invalid range rather than an empty one.
        if ($lastRow >= 2) {
            // Reference columns shaded, entry columns left white, so it is
            // obvious at a glance which cells are meant to be typed in.
            $sheet->getStyle("A2:H{$lastRow}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F2F2F2');
            $sheet->getStyle("E2:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("I2:I{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("K2:K{$lastRow}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        }

        foreach (range('A', 'N') as $column) {
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
            ['COS (quincena)', 'COS loans are deducted per quincena: 1st = 1st-15th (paid on the 15th), 2nd = 16th-end of month (paid on the last day).'],
            ['', 'This worklist is for the ' . $quincenaLabel . ' quincena: quincena_amortization is half the monthly amortization, and payment_date is pre-filled with the cutoff date.'],
            ['', 'Type the amount actually deducted in "amount". Download a new worklist for each quincena — the 1st and 2nd are posted separately, never twice.'],
            [''],
            ['Duplicates', 'The same loan + date + amount + OR number is only ever posted once, and re-uploading the same file is blocked.'],
            ['', 'If a member genuinely paid twice on one day for the same amount, type "yes" in "allow_duplicate" on the second row.'],
        ], null, 'A1');
        $notes->getStyle('A1')->getFont()->setBold(true);
        $notes->getColumnDimension('A')->setAutoSize(true);
        $notes->getColumnDimension('B')->setWidth(110);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = $hasQuincena
            ? sprintf('payment_worklist_%d-%02d_%s_quincena.xlsx', $year, $month, $quincena === 1 ? '1st' : '2nd')
            : 'payment_worklist_' . now()->format('Y-m-d') . '.xlsx';

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
