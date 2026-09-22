<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentImportBatch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Bulk payment posting: worklist out, filled sheet back in.
 *
 * Manual loans carry no reference number, so matching a payroll row to a loan
 * by (employee id, loan type) is ambiguous the moment a member holds two
 * active loans of the same type. Rather than guess, the operator downloads a
 * *worklist* — every outstanding loan, one row each, already carrying its
 * `loan_ref` — types in the amounts, and uploads it back. The row then names
 * the loan outright. A hand-made sheet still works; rows that match more than
 * one loan are reported instead of silently posted to whichever came first.
 *
 * Nothing is written until the operator confirms a preview.
 */
class PaymentImportService
{
    /** Loan statuses a payment may be posted against. */
    public const POSTABLE_STATUSES = ['released', 'chairperson_approved'];

    /** Mirrors the payments.payment_method enum. */
    public const PAYMENT_METHODS = ['cash', 'payroll_deduction', 'bank_transfer'];

    public const DEFAULT_METHOD = 'payroll_deduction';

    private const STAGING_DIR = 'payment-imports';

    public const COLUMNS = [
        'loan_ref',
        'employee_id',
        'member_name',
        'loan_type',
        'monthly_amortization',
        'outstanding_balance',
        'amount',
        'or_number',
        'payment_date',
        'payment_method',
        'remarks',
        'allow_duplicate',
    ];

    /**
     * Every loan that can still receive a payment, newest first, with the
     * figures the operator needs to fill the sheet.
     */
    public function outstandingLoans(): \Illuminate\Support\Collection
    {
        // Payments are eager-loaded (not withSum) because Loan::total_paid
        // sums the loaded relation when it has one — that keeps the balance
        // column to two queries instead of one per loan.
        return Loan::with(['user:id,employee_id,first_name,last_name', 'payments:id,loan_id,amount'])
            ->whereIn('status', self::POSTABLE_STATUSES)
            ->orderBy('user_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (Loan $loan) => $loan->remaining_balance > 0.005)
            ->values();
    }

    /**
     * Stage an upload: keep the file, analyse every row, return the preview.
     * Writes nothing to the ledger.
     */
    public function stage(UploadedFile $file, User $user): array
    {
        $hash = hash_file('sha256', $file->getRealPath());
        $token = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');

        $priorBatch = PaymentImportBatch::where('file_hash', $hash)
            ->where('status', 'committed')
            ->with('uploader:id,first_name,last_name')
            ->latest('id')
            ->first();

        $rows = $this->parse($file->getRealPath(), $extension);

        if (empty($rows)) {
            return [
                'error' => 'The file is empty, or its first row is not a header. Use the downloaded worklist.',
            ];
        }

        Storage::put(self::STAGING_DIR . "/{$token}.{$extension}", file_get_contents($file->getRealPath()));
        Storage::put(self::STAGING_DIR . "/{$token}.json", json_encode([
            'file_name' => $file->getClientOriginalName(),
            'file_hash' => $hash,
            'extension' => $extension,
            'staged_by' => $user->id,
            'staged_at' => now()->toIso8601String(),
        ]));

        $analysis = $this->analyse($rows);

        return [
            'token' => $token,
            'file_name' => $file->getClientOriginalName(),
            'file_hash' => $hash,
            'already_imported' => $priorBatch ? [
                'batch_id' => $priorBatch->id,
                'imported_at' => $priorBatch->created_at?->toDateTimeString(),
                'uploaded_by' => trim(($priorBatch->uploader->first_name ?? '') . ' ' . ($priorBatch->uploader->last_name ?? '')) ?: null,
                'rows_imported' => $priorBatch->rows_imported,
            ] : null,
            'rows' => $analysis['rows'],
            'summary' => $analysis['summary'],
        ];
    }

    /**
     * Post a staged file. Re-reads and re-analyses from the stored copy so the
     * decision is made against the same bytes the preview described.
     */
    public function commit(string $token, User $user, bool $force = false): array
    {
        $meta = $this->readStagedMeta($token);

        if (!$meta) {
            return ['error' => 'That upload has expired. Please upload the file again.'];
        }

        $path = self::STAGING_DIR . "/{$token}.{$meta['extension']}";

        if (!Storage::exists($path)) {
            return ['error' => 'That upload has expired. Please upload the file again.'];
        }

        if (!$force) {
            $prior = PaymentImportBatch::where('file_hash', $meta['file_hash'])
                ->where('status', 'committed')
                ->latest('id')
                ->first();

            if ($prior) {
                return [
                    'error' => "This exact file was already imported on {$prior->created_at?->toDayDateTimeString()} (batch #{$prior->id}). Nothing was posted.",
                ];
            }
        }

        $rows = $this->parse(Storage::path($path), $meta['extension']);
        $analysis = $this->analyse($rows);
        $postable = array_filter($analysis['rows'], fn ($r) => in_array($r['status'], ['ok', 'warning'], true));

        if (empty($postable)) {
            return ['error' => 'No postable rows in this file — every row was a duplicate or an error.'];
        }

        $imported = 0;
        $skipped = 0;
        $amountTotal = 0.0;
        $results = [];

        $batch = DB::transaction(function () use ($meta, $user, $path, $analysis, $postable, &$imported, &$skipped, &$amountTotal, &$results) {
            $batch = PaymentImportBatch::create([
                'file_name' => $meta['file_name'],
                'file_hash' => $meta['file_hash'],
                'file_path' => $path,
                'uploaded_by' => $user->id,
                'rows_total' => count($analysis['rows']),
            ]);

            $touchedLoans = [];

            foreach ($postable as $row) {
                // An intentional duplicate gets a nonce mixed in so it clears
                // the UNIQUE index — the operator asked for it explicitly.
                $hash = $row['allow_duplicate']
                    ? hash('sha256', $row['dedupe_hash'] . '|dup:' . Str::uuid())
                    : $row['dedupe_hash'];

                try {
                    Payment::create([
                        'loan_id' => $row['loan_id'],
                        'recorded_by' => $user->id,
                        'import_batch_id' => $batch->id,
                        'amount' => $row['amount'],
                        'or_number' => $row['or_number'] ?: null,
                        'payment_method' => $row['payment_method'],
                        'payment_date' => $row['payment_date'],
                        'remarks' => $row['remarks'] ?: "Bulk import (batch #{$batch->id})",
                        'dedupe_hash' => $hash,
                    ]);

                    $imported++;
                    $amountTotal += $row['amount'];
                    $touchedLoans[$row['loan_id']] = true;
                    $results[] = ['row' => $row['row'], 'status' => 'imported'];
                } catch (QueryException $e) {
                    // 23000/1062 — the UNIQUE index caught a duplicate that
                    // slipped in between preview and commit (a second admin
                    // posting the same file at the same moment).
                    if ($this->isUniqueViolation($e)) {
                        $skipped++;
                        $results[] = ['row' => $row['row'], 'status' => 'skipped', 'message' => 'Already posted.'];
                        continue;
                    }

                    throw $e;
                }
            }

            // Close out any loan the batch finished paying.
            foreach (array_keys($touchedLoans) as $loanId) {
                $loan = Loan::find($loanId);

                if ($loan && $loan->status !== 'completed' && $loan->total_paid >= $loan->total_payable) {
                    $loan->update(['status' => 'completed']);
                }
            }

            $batch->update([
                'rows_imported' => $imported,
                'rows_skipped' => $skipped + count(array_filter($analysis['rows'], fn ($r) => $r['status'] === 'duplicate')),
                'rows_failed' => count(array_filter($analysis['rows'], fn ($r) => $r['status'] === 'error')),
                'amount_total' => $amountTotal,
            ]);

            return $batch;
        });

        return [
            'batch_id' => $batch->id,
            'imported' => $imported,
            'skipped' => $batch->rows_skipped,
            'failed' => $batch->rows_failed,
            'amount_total' => round($amountTotal, 2),
            'results' => $results,
        ];
    }

    /**
     * Undo a batch: delete its payments and re-open any loan those payments
     * had closed.
     */
    public function rollback(PaymentImportBatch $batch, User $user): array
    {
        if ($batch->status === 'rolled_back') {
            return ['error' => 'That batch was already rolled back.'];
        }

        $deleted = DB::transaction(function () use ($batch, $user) {
            $loanIds = $batch->payments()->pluck('loan_id')->unique();
            $deleted = $batch->payments()->delete();

            foreach ($loanIds as $loanId) {
                $loan = Loan::find($loanId);

                if ($loan && $loan->status === 'completed' && $loan->fresh()->remaining_balance > 0.005) {
                    $loan->update(['status' => 'released']);
                }
            }

            $batch->update([
                'status' => 'rolled_back',
                'rolled_back_by' => $user->id,
                'rolled_back_at' => now(),
            ]);

            return $deleted;
        });

        return ['deleted' => $deleted, 'batch_id' => $batch->id];
    }

    /**
     * Turn parsed rows into a per-row verdict: which loan it hits, what the
     * balance becomes, and whether it is a duplicate.
     */
    private function analyse(array $rows): array
    {
        $seenInFile = [];
        // Running balances, so two rows against one loan project correctly and
        // the second one's overpayment warning is honest.
        $projected = [];
        $out = [];

        foreach ($rows as $index => $raw) {
            $rowNumber = $index + 2;
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $raw);

            $entry = [
                'row' => $rowNumber,
                'employee_id' => (string) ($row['employee_id'] ?? ''),
                'member_name' => (string) ($row['member_name'] ?? ''),
                'loan_ref' => (string) ($row['loan_ref'] ?? ''),
                'loan_type' => (string) ($row['loan_type'] ?? ''),
                'amount' => 0.0,
                'or_number' => (string) ($row['or_number'] ?? ''),
                'payment_method' => strtolower((string) ($row['payment_method'] ?? '')) ?: self::DEFAULT_METHOD,
                'payment_date' => '',
                'remarks' => (string) ($row['remarks'] ?? ''),
                'allow_duplicate' => $this->isTruthy($row['allow_duplicate'] ?? ''),
                'loan_id' => null,
                'balance_before' => null,
                'balance_after' => null,
                'dedupe_hash' => null,
                'status' => 'ok',
                'message' => '',
            ];

            $amount = $this->toAmount($row['amount'] ?? '');

            // A worklist comes back with a row for every outstanding loan;
            // the ones the operator left blank are simply not being paid.
            if ($amount === null || $amount == 0.0) {
                continue;
            }

            $entry['amount'] = $amount;

            if ($amount < 0) {
                $out[] = $this->fail($entry, 'Amount must be greater than zero.');
                continue;
            }

            $message = null;
            $loan = $this->matchLoan($entry, $message);

            if (!$loan) {
                $out[] = $this->fail($entry, $message);
                continue;
            }

            $entry['loan_id'] = $loan->id;
            $entry['loan_type'] = $loan->loan_type;
            $entry['employee_id'] = $loan->user->employee_id ?? $entry['employee_id'];
            $entry['member_name'] = $entry['member_name'] ?: trim(($loan->user->first_name ?? '') . ' ' . ($loan->user->last_name ?? ''));

            if (!in_array($entry['payment_method'], self::PAYMENT_METHODS, true)) {
                $out[] = $this->fail($entry, "Invalid payment method '{$entry['payment_method']}'. Use: " . implode(', ', self::PAYMENT_METHODS) . '.');
                continue;
            }

            $date = $this->toDate($row['payment_date'] ?? '');

            if (!$date) {
                // Defaulting to today would make the same row hash differently
                // tomorrow, which would defeat duplicate detection outright.
                $out[] = $this->fail($entry, 'Payment date is required (YYYY-MM-DD).');
                continue;
            }

            $entry['payment_date'] = $date;

            $before = $projected[$loan->id] ?? $loan->remaining_balance;
            $entry['balance_before'] = round($before, 2);
            $entry['balance_after'] = round($before - $amount, 2);

            $entry['dedupe_hash'] = Payment::dedupeHash($loan->id, $date, $amount, $entry['or_number']);

            if (!$entry['allow_duplicate']) {
                if (isset($seenInFile[$entry['dedupe_hash']])) {
                    $entry['status'] = 'duplicate';
                    $entry['message'] = "Same loan, date and amount as row {$seenInFile[$entry['dedupe_hash']]} in this file. Tick allow_duplicate if both are real.";
                    $out[] = $entry;
                    continue;
                }

                if (Payment::where('dedupe_hash', $entry['dedupe_hash'])->exists()) {
                    $entry['status'] = 'duplicate';
                    $entry['message'] = 'Already posted to this loan. Tick allow_duplicate if this is a second, genuine payment.';
                    $out[] = $entry;
                    continue;
                }
            }

            $seenInFile[$entry['dedupe_hash']] = $rowNumber;
            $projected[$loan->id] = $before - $amount;

            if ($amount > $before + 0.01) {
                $entry['status'] = 'warning';
                $entry['message'] = 'Amount exceeds the outstanding balance of ' . number_format($before, 2) . '. It will still post, leaving the loan overpaid.';
            } elseif ($entry['allow_duplicate']) {
                $entry['message'] = 'Marked as an intentional duplicate.';
            }

            $out[] = $entry;
        }

        return [
            'rows' => $out,
            'summary' => [
                'total' => count($out),
                'ok' => count(array_filter($out, fn ($r) => $r['status'] === 'ok')),
                'warning' => count(array_filter($out, fn ($r) => $r['status'] === 'warning')),
                'duplicate' => count(array_filter($out, fn ($r) => $r['status'] === 'duplicate')),
                'error' => count(array_filter($out, fn ($r) => $r['status'] === 'error')),
                'amount' => round(array_sum(array_map(
                    fn ($r) => in_array($r['status'], ['ok', 'warning'], true) ? $r['amount'] : 0,
                    $out
                )), 2),
            ],
        ];
    }

    /**
     * loan_ref names the loan outright; without it we fall back to
     * (employee id, loan type) and refuse to guess when that is ambiguous.
     */
    private function matchLoan(array $entry, ?string &$message): ?Loan
    {
        $message = null;

        if ($entry['loan_ref'] !== '' && ctype_digit(ltrim($entry['loan_ref'], '#'))) {
            $loan = Loan::with('user:id,employee_id,first_name,last_name')
                ->find((int) ltrim($entry['loan_ref'], '#'));

            if (!$loan) {
                $message = "No loan with reference {$entry['loan_ref']}.";
                return null;
            }

            // Guard against a sheet whose rows were sorted or shifted apart
            // from their reference column.
            if ($entry['employee_id'] !== '' && $loan->user?->employee_id !== $entry['employee_id']) {
                $message = "Loan {$entry['loan_ref']} belongs to {$loan->user?->employee_id}, not {$entry['employee_id']}. Re-download the worklist.";
                return null;
            }

            if (!in_array($loan->status, self::POSTABLE_STATUSES, true)) {
                $message = "Loan {$entry['loan_ref']} is '{$loan->status}' and cannot take payments.";
                return null;
            }

            return $loan;
        }

        if ($entry['employee_id'] === '') {
            $message = 'Employee ID is required when loan_ref is blank.';
            return null;
        }

        $user = User::where('employee_id', $entry['employee_id'])->first();

        if (!$user) {
            $message = "No member with employee ID {$entry['employee_id']}.";
            return null;
        }

        $candidates = Loan::with('user:id,employee_id,first_name,last_name')
            ->where('user_id', $user->id)
            ->whereIn('status', self::POSTABLE_STATUSES)
            ->when($entry['loan_type'] !== '', fn ($q) => $q->where('loan_type', $entry['loan_type']))
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            $type = $entry['loan_type'] !== '' ? "'{$entry['loan_type']}' " : '';
            $message = "No active {$type}loan for {$entry['employee_id']}.";
            return null;
        }

        if ($candidates->count() > 1) {
            $refs = $candidates->pluck('id')->implode(', ');
            $message = "{$entry['employee_id']} has {$candidates->count()} active loans (refs: {$refs}). Use the downloaded worklist so the row carries loan_ref.";
            return null;
        }

        return $candidates->first();
    }

    private function fail(array $entry, ?string $message): array
    {
        $entry['status'] = 'error';
        $entry['message'] = $message ?: 'Could not match this row to a loan.';

        return $entry;
    }

    /** Read a staged upload's sidecar metadata. */
    private function readStagedMeta(string $token): ?array
    {
        if (!Str::isUuid($token)) {
            return null;
        }

        $metaPath = self::STAGING_DIR . "/{$token}.json";

        if (!Storage::exists($metaPath)) {
            return null;
        }

        return json_decode(Storage::get($metaPath), true) ?: null;
    }

    /**
     * Parse CSV or a real spreadsheet into rows keyed by normalised header.
     */
    public function parse(string $path, string $extension): array
    {
        return in_array($extension, ['xlsx', 'xls'], true)
            ? $this->parseSpreadsheet($path)
            : $this->parseCsv($path);
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);
            return [];
        }

        $headers = array_map(fn ($h) => $this->normaliseHeader($h), $headers);

        while (($data = fgetcsv($handle)) !== false) {
            if (count(array_filter($data, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];

            foreach ($headers as $i => $header) {
                if ($header !== '') {
                    $row[$header] = $data[$i] ?? '';
                }
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function parseSpreadsheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();

        $rows = [];
        $headers = [];

        foreach ($sheet->getRowIterator() as $rowIndex => $sheetRow) {
            $cells = [];
            $iterator = $sheetRow->getCellIterator();
            $iterator->setIterateOnlyExistingCells(false);

            foreach ($iterator as $cell) {
                // Dates arrive as Excel serial numbers; keep them as such and
                // let toDate() convert, so a real date cell is not read as 45678.
                $value = $cell->getValue();

                if (ExcelDate::isDateTime($cell) && is_numeric($value)) {
                    $value = ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
                }

                $cells[] = is_string($value) ? trim($value) : $value;
            }

            if ($rowIndex === 1) {
                $headers = array_map(fn ($h) => $this->normaliseHeader((string) $h), $cells);
                continue;
            }

            if (count(array_filter($cells, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];

            foreach ($headers as $i => $header) {
                if ($header !== '') {
                    $row[$header] = $cells[$i] ?? '';
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function normaliseHeader(?string $header): string
    {
        return str_replace([' ', '-'], '_', strtolower(trim((string) $header)));
    }

    /** "₱1,250.00 " and "1250" both mean 1250.00; blank means "not paying". */
    private function toAmount(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($clean) ? round((float) $clean, 2) : null;
    }

    private function toDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'y', 'yes', 'true', 'x', 'ok'], true);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062 || $e->getCode() === '23000';
    }
}
