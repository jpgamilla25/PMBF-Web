<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanImportBatch;
use App\Models\User;
use App\Services\Concerns\ParsesSpreadsheets;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bulk import of legacy (pre-system) loans: download the template, fill it in,
 * upload, review the preview, then proceed.
 *
 * A duplicated loan silently doubles what a member appears to owe, so the same
 * guards as the payment importer apply: the file's hash per batch and a UNIQUE
 * per-row fingerprint over (member, type, principal, application date). A
 * member genuinely holding two identical loans opts in with allow_duplicate.
 *
 * Nothing is written until the operator confirms the preview.
 */
class LoanImportService
{
    use ParsesSpreadsheets;

    private const STAGING_DIR = 'loan-imports';

    public const COLUMNS = [
        'employee_id',
        'loan_type',
        'amount',
        'interest_rate',
        'interest_method',
        'term_months',
        'monthly_amortization',
        'total_payable',
        'status',
        'applied_at',
        'remarks',
        'allow_duplicate',
    ];

    /** Cached enum options so the template and the validator cannot drift. */
    private array $enums = [];

    public function allowed(string $column): array
    {
        if (!isset($this->enums[$column])) {
            $row = DB::selectOne('SHOW COLUMNS FROM loans WHERE Field = ?', [$column]);
            preg_match_all("/'([^']+)'/", $row->Type ?? '', $matches);
            $this->enums[$column] = $matches[1] ?? [];
        }

        return $this->enums[$column];
    }

    /**
     * Stage an upload: keep the file, analyse every row, return the preview.
     * Writes nothing.
     */
    public function stage(UploadedFile $file, User $user): array
    {
        $hash = hash_file('sha256', $file->getRealPath());
        $token = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');

        $priorBatch = LoanImportBatch::where('file_hash', $hash)
            ->where('status', 'committed')
            ->with('uploader:id,first_name,last_name')
            ->latest('id')
            ->first();

        $rows = $this->parse($file->getRealPath(), $extension);

        if (empty($rows)) {
            return ['error' => 'The file is empty, or its first row is not a header. Use the downloaded template.'];
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
     * Create the loans from a staged file, re-read from the stored copy so the
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
            $prior = LoanImportBatch::where('file_hash', $meta['file_hash'])
                ->where('status', 'committed')
                ->latest('id')
                ->first();

            if ($prior) {
                return [
                    'error' => "This exact file was already imported on {$prior->created_at?->toDayDateTimeString()} (batch #{$prior->id}). Nothing was created.",
                ];
            }
        }

        $rows = $this->parse(Storage::path($path), $meta['extension']);
        $analysis = $this->analyse($rows);
        $creatable = array_filter($analysis['rows'], fn ($r) => in_array($r['status_verdict'], ['ok', 'warning'], true));

        if (empty($creatable)) {
            return ['error' => 'No importable rows in this file — every row was a duplicate or an error.'];
        }

        $imported = 0;
        $skipped = 0;
        $amountTotal = 0.0;

        $batch = DB::transaction(function () use ($meta, $user, $path, $analysis, $creatable, &$imported, &$skipped, &$amountTotal) {
            $batch = LoanImportBatch::create([
                'file_name' => $meta['file_name'],
                'file_hash' => $meta['file_hash'],
                'file_path' => $path,
                'uploaded_by' => $user->id,
                'rows_total' => count($analysis['rows']),
            ]);

            foreach ($creatable as $row) {
                $hash = $row['allow_duplicate']
                    ? hash('sha256', $row['dedupe_hash'] . '|dup:' . Str::uuid())
                    : $row['dedupe_hash'];

                try {
                    Loan::create([
                        'user_id' => $row['user_id'],
                        'import_batch_id' => $batch->id,
                        'loan_type' => $row['loan_type'],
                        'amount' => $row['amount'],
                        'interest_rate' => $row['interest_rate'],
                        'interest_method' => $row['interest_method'],
                        'term_months' => $row['term_months'],
                        'monthly_amortization' => $row['monthly_amortization'],
                        // Stored explicitly: without it the balance falls back
                        // to the flat formula, which is not what a legacy loan
                        // actually owes.
                        'total_payable' => $row['total_payable'],
                        'status' => $row['status'],
                        'applied_at' => $row['applied_at'],
                        'remarks' => $row['remarks'] ?: "Imported (batch #{$batch->id})",
                        'dedupe_hash' => $hash,
                    ]);

                    $imported++;
                    $amountTotal += $row['amount'];
                } catch (QueryException $e) {
                    if ($this->isUniqueViolation($e)) {
                        $skipped++;
                        continue;
                    }

                    throw $e;
                }
            }

            $batch->update([
                'rows_imported' => $imported,
                'rows_skipped' => $skipped + count(array_filter($analysis['rows'], fn ($r) => $r['status_verdict'] === 'duplicate')),
                'rows_failed' => count(array_filter($analysis['rows'], fn ($r) => $r['status_verdict'] === 'error')),
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
        ];
    }

    /**
     * Undo a batch. A loan that has already received payments is left alone —
     * deleting it would cascade those payments away with it.
     */
    public function rollback(LoanImportBatch $batch, User $user): array
    {
        if ($batch->status === 'rolled_back') {
            return ['error' => 'That batch was already rolled back.'];
        }

        $result = DB::transaction(function () use ($batch, $user) {
            $withPayments = $batch->loans()->has('payments')->pluck('id');
            $deleted = $batch->loans()->doesntHave('payments')->delete();

            $batch->update([
                'status' => 'rolled_back',
                'rolled_back_by' => $user->id,
                'rolled_back_at' => now(),
            ]);

            return ['deleted' => $deleted, 'kept' => $withPayments->count()];
        });

        return $result + ['batch_id' => $batch->id];
    }

    /**
     * Per-row verdict: which member it belongs to, what it will owe, and
     * whether the same loan is already on file.
     */
    private function analyse(array $rows): array
    {
        $seenInFile = [];
        $out = [];
        $types = $this->allowed('loan_type');
        $statuses = $this->allowed('status');
        $methods = $this->allowed('interest_method');

        foreach ($rows as $index => $raw) {
            $rowNumber = $index + 2;
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $raw);

            $entry = [
                'row' => $rowNumber,
                'employee_id' => (string) ($row['employee_id'] ?? ''),
                'member_name' => '',
                'user_id' => null,
                'loan_type' => (string) ($row['loan_type'] ?? ''),
                'amount' => 0.0,
                'interest_rate' => (float) ($this->toAmount($row['interest_rate'] ?? '') ?? 0),
                'interest_method' => strtolower((string) ($row['interest_method'] ?? '')) ?: 'flat',
                'term_months' => (int) ($row['term_months'] ?? 0),
                'monthly_amortization' => 0.0,
                'total_payable' => 0.0,
                'status' => strtolower((string) ($row['status'] ?? '')) ?: 'released',
                'applied_at' => '',
                'remarks' => (string) ($row['remarks'] ?? ''),
                'allow_duplicate' => $this->isTruthy($row['allow_duplicate'] ?? ''),
                'dedupe_hash' => null,
                'status_verdict' => 'ok',
                'message' => '',
            ];

            // A blank row in a part-filled template is simply not a loan.
            if ($entry['employee_id'] === '' && ($row['amount'] ?? '') === '') {
                continue;
            }

            if ($entry['employee_id'] === '') {
                $out[] = $this->fail($entry, 'Employee ID is required.');
                continue;
            }

            $member = User::where('employee_id', $entry['employee_id'])->first();

            if (!$member) {
                $out[] = $this->fail($entry, "No member with employee ID {$entry['employee_id']}.");
                continue;
            }

            $entry['user_id'] = $member->id;
            $entry['member_name'] = trim("{$member->first_name} {$member->last_name}");

            if (!in_array($entry['loan_type'], $types, true)) {
                $out[] = $this->fail($entry, "Unknown loan type '{$entry['loan_type']}'. Use one of: " . implode(', ', $types) . '.');
                continue;
            }

            if (!in_array($entry['status'], $statuses, true)) {
                $out[] = $this->fail($entry, "Unknown status '{$entry['status']}'. Use one of: " . implode(', ', $statuses) . '.');
                continue;
            }

            if (!in_array($entry['interest_method'], $methods, true)) {
                $out[] = $this->fail($entry, "Interest method must be one of: " . implode(', ', $methods) . '.');
                continue;
            }

            $amount = $this->toAmount($row['amount'] ?? '');

            if ($amount === null || $amount <= 0) {
                $out[] = $this->fail($entry, 'Amount must be greater than zero.');
                continue;
            }

            $entry['amount'] = $amount;

            if ($entry['term_months'] <= 0) {
                $out[] = $this->fail($entry, 'Term (months) must be greater than zero.');
                continue;
            }

            $monthly = $this->toAmount($row['monthly_amortization'] ?? '');

            if ($monthly === null || $monthly <= 0) {
                $out[] = $this->fail($entry, 'Monthly amortization must be greater than zero.');
                continue;
            }

            $entry['monthly_amortization'] = $monthly;

            // Dating a legacy loan "today" would be wrong on the ledger and
            // would also make the same row hash differently tomorrow.
            $applied = $this->toDate($row['applied_at'] ?? '');

            if (!$applied) {
                $out[] = $this->fail($entry, 'Application date is required (YYYY-MM-DD).');
                continue;
            }

            $entry['applied_at'] = $applied;

            // What the member actually owes over the life of a legacy loan is
            // the schedule they are being deducted on: monthly × term.
            $stated = $this->toAmount($row['total_payable'] ?? '');
            $computed = round($monthly * $entry['term_months'], 2);
            $entry['total_payable'] = $stated !== null && $stated > 0 ? $stated : $computed;

            $entry['dedupe_hash'] = hash('sha256', implode('|', [
                $member->id,
                $entry['loan_type'],
                number_format($amount, 2, '.', ''),
                $applied,
            ]));

            if (!$entry['allow_duplicate']) {
                if (isset($seenInFile[$entry['dedupe_hash']])) {
                    $entry['status_verdict'] = 'duplicate';
                    $entry['message'] = "Same member, type, amount and date as row {$seenInFile[$entry['dedupe_hash']]} in this file.";
                    $out[] = $entry;
                    continue;
                }

                if (Loan::where('dedupe_hash', $entry['dedupe_hash'])->exists()) {
                    $entry['status_verdict'] = 'duplicate';
                    $entry['message'] = 'This loan is already on file. Tick allow_duplicate if the member really holds two identical loans.';
                    $out[] = $entry;
                    continue;
                }
            }

            $seenInFile[$entry['dedupe_hash']] = $rowNumber;

            if ($stated !== null && $stated > 0 && abs($stated - $computed) > 1) {
                $entry['status_verdict'] = 'warning';
                $entry['message'] = 'Total payable (' . number_format($stated, 2) . ') does not match monthly × term ('
                    . number_format($computed, 2) . '). The stated figure will be used.';
            } elseif ($entry['allow_duplicate']) {
                $entry['message'] = 'Marked as an intentional duplicate.';
            }

            $out[] = $entry;
        }

        return [
            'rows' => $out,
            'summary' => [
                'total' => count($out),
                'ok' => count(array_filter($out, fn ($r) => $r['status_verdict'] === 'ok')),
                'warning' => count(array_filter($out, fn ($r) => $r['status_verdict'] === 'warning')),
                'duplicate' => count(array_filter($out, fn ($r) => $r['status_verdict'] === 'duplicate')),
                'error' => count(array_filter($out, fn ($r) => $r['status_verdict'] === 'error')),
                'amount' => round(array_sum(array_map(
                    fn ($r) => in_array($r['status_verdict'], ['ok', 'warning'], true) ? $r['amount'] : 0,
                    $out
                )), 2),
            ],
        ];
    }

    private function fail(array $entry, string $message): array
    {
        $entry['status_verdict'] = 'error';
        $entry['message'] = $message;

        return $entry;
    }

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

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062 || $e->getCode() === '23000';
    }
}
