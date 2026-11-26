<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MemberCounterpart;
use App\Models\PremiumQuincena;
use App\Models\ShareCapital;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    use ApiResponse;

    /**
     * Import monthly shares from CSV, one row per member per month. Each row
     * sets that member's Members Contribution (shown on their Shares page)
     * and, optionally, PhilRice's counterpart for the same month; the two
     * together are the member's Total Equity. Re-importing a
     * month overwrites it rather than adding to it.
     */
    public function importShares(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $rows = $this->parseCsv($request->file('file')->getRealPath());

        if (empty($rows)) {
            return $this->error('The file is empty or could not be parsed.');
        }

        // "share_amount" was the column name before it became Members
        // Contribution; older downloaded templates still import.
        if (!array_key_exists('members_contribution', $rows[0]) && array_key_exists('share_amount', $rows[0])) {
            $rows = array_map(fn ($r) => $r + ['members_contribution' => $r['share_amount']], $rows);
        }

        $headers = array_keys($rows[0]);
        foreach (['employee_id', 'year', 'month', 'members_contribution'] as $header) {
            if (!in_array($header, $headers, true)) {
                return $this->error("Missing required column: {$header}. Please use the provided template.");
            }
        }

        $adminId = $request->user()->id;
        $users = User::whereIn('employee_id', array_filter(array_map(fn ($r) => trim($r['employee_id'] ?? ''), $rows)))
            ->get()
            ->keyBy('employee_id');

        $successCount = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $row = array_map('trim', $row);
            $employeeId = $row['employee_id'] ?? '';
            $fail = function (string $message) use (&$errors, $rowNumber, $employeeId) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => $message];
            };

            if ($employeeId === '') { $fail('Employee ID is required.'); continue; }

            $user = $users->get($employeeId);
            if (!$user) { $fail("Employee not found: {$employeeId}"); continue; }
            if ($user->employment_type !== 'Permanent') {
                $fail("Shares apply to Permanent members only ({$user->employment_type}).");
                continue;
            }

            $year = filter_var($row['year'] ?? '', FILTER_VALIDATE_INT);
            if ($year === false || $year < 2000 || $year > 2100) { $fail('Year must be a 4-digit year.'); continue; }

            $month = $this->parseMonth($row['month'] ?? '');
            if (!$month) { $fail('Month must be 1-12 or a month name (e.g. Jan).'); continue; }

            $share = $this->parseAmount($row['members_contribution'] ?? '');
            if ($share === null) { $fail('Members contribution must be a number of 0 or more.'); continue; }

            $counterpartRaw = $row['philrice_counterpart'] ?? '';
            $counterpart = $counterpartRaw === '' ? null : $this->parseAmount($counterpartRaw);
            if ($counterpartRaw !== '' && $counterpart === null) {
                $fail('PhilRice counterpart must be a number of 0 or more.');
                continue;
            }

            DB::transaction(function () use ($user, $year, $month, $share, $counterpart, $adminId) {
                ShareCapital::updateOrCreate(
                    ['user_id' => $user->id, 'year' => $year, 'month' => $month],
                    ['amount' => $share, 'type' => 'share', 'remarks' => 'Imported', 'updated_by' => $adminId]
                );

                if ($counterpart !== null) {
                    MemberCounterpart::updateOrCreate(
                        ['user_id' => $user->id, 'year' => $year, 'month' => $month],
                        ['amount' => $counterpart, 'updated_by' => $adminId]
                    );
                }
            });
            $successCount++;
        }

        if ($successCount > 0) {
            ActivityLog::record([
                'admin_id'        => $adminId,
                'action'          => 'shares_imported',
                'subject'         => $request->file('file')->getClientOriginalName(),
                'description'     => "Imported {$successCount} share row(s)" . (count($errors) ? ', ' . count($errors) . ' failed' : ''),
                'employment_type' => 'Permanent',
            ]);
        }

        return $this->success([
            'imported' => $successCount,
            'failed' => count($errors),
            'errors' => $errors,
        ], "{$successCount} share rows imported successfully." . (count($errors) > 0 ? ' ' . count($errors) . ' rows failed.' : ''));
    }

    /**
     * Download share import CSV template.
     */
    public function downloadShareTemplate(): StreamedResponse
    {
        // total_equity is a convenience formula for whoever fills the sheet in
        // (Excel evaluates it on open). The import ignores it and always
        // derives Total Equity from contribution + counterpart.
        $headers = ['employee_id', 'year', 'month', 'members_contribution', 'philrice_counterpart', 'total_equity'];
        $year = now()->year;

        return $this->streamCsvTemplate('share_import_template.csv', $headers, [
            ['EMP-001', $year, '1', '500', '500', '=D2+E2'],
            ['EMP-001', $year, '2', '500', '500', '=D3+E3'],
        ]);
    }

    /**
     * Import COS-Enrolled premium contributions per quincena (payroll
     * half-month): one row per member per month per half. Each half is kept
     * on its own, so the 1st and 2nd cutoffs can arrive in separate files;
     * the month's premium is always the sum of its two halves. Re-importing a
     * half replaces it.
     */
    public function importPremiums(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $rows = $this->parseCsv($request->file('file')->getRealPath());

        if (empty($rows)) {
            return $this->error('The file is empty or could not be parsed.');
        }

        $headers = array_keys($rows[0]);
        foreach (['employee_id', 'year', 'month', 'quincena', 'premium_contribution'] as $header) {
            if (!in_array($header, $headers, true)) {
                return $this->error("Missing required column: {$header}. Please use the provided template.");
            }
        }

        $adminId = $request->user()->id;
        $users = User::whereIn('employee_id', array_filter(array_map(fn ($r) => trim($r['employee_id'] ?? ''), $rows)))
            ->get()
            ->keyBy('employee_id');

        $successCount = 0;
        $errors = [];
        $touchedMonths = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $row = array_map('trim', $row);
            $employeeId = $row['employee_id'] ?? '';
            $fail = function (string $message) use (&$errors, $rowNumber, $employeeId) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => $message];
            };

            if ($employeeId === '') { $fail('Employee ID is required.'); continue; }

            $user = $users->get($employeeId);
            if (!$user) { $fail("Employee not found: {$employeeId}"); continue; }
            if ($user->employment_type !== 'Contract of Service') {
                $fail("Premium contributions apply to COS-Enrolled members only ({$user->employment_type}).");
                continue;
            }

            $year = filter_var($row['year'] ?? '', FILTER_VALIDATE_INT);
            if ($year === false || $year < 2000 || $year > 2100) { $fail('Year must be a 4-digit year.'); continue; }

            $month = $this->parseMonth($row['month'] ?? '');
            if (!$month) { $fail('Month must be 1-12 or a month name (e.g. Jan).'); continue; }

            $quincena = $this->parseQuincena($row['quincena'] ?? '');
            if (!$quincena) { $fail('Quincena must be 1 (1st-15th) or 2 (16th-end).'); continue; }

            $amount = $this->parseAmount($row['premium_contribution'] ?? '');
            if ($amount === null) { $fail('Premium contribution must be a number of 0 or more.'); continue; }

            PremiumQuincena::updateOrCreate(
                ['user_id' => $user->id, 'year' => $year, 'month' => $month, 'quincena' => $quincena],
                ['amount' => $amount, 'updated_by' => $adminId]
            );
            $touchedMonths["{$user->id}-{$year}-{$month}"] = [$user->id, $year, $month];
            $successCount++;
        }

        // Roll each touched month up into the monthly premium row.
        DB::transaction(function () use ($touchedMonths, $adminId) {
            foreach ($touchedMonths as [$userId, $year, $month]) {
                $halves = PremiumQuincena::where(['user_id' => $userId, 'year' => $year, 'month' => $month])->get();
                ShareCapital::updateOrCreate(
                    ['user_id' => $userId, 'year' => $year, 'month' => $month],
                    [
                        'amount'     => $halves->sum('amount'),
                        'type'       => 'premium',
                        'remarks'    => 'Imported (' . $halves->sortBy('quincena')->map(fn ($h) => $h->quincena === 1 ? '1st' : '2nd')->implode(' + ') . ' quincena)',
                        'updated_by' => $adminId,
                    ]
                );
            }
        });

        if ($successCount > 0) {
            ActivityLog::record([
                'admin_id'        => $adminId,
                'action'          => 'premiums_imported',
                'subject'         => $request->file('file')->getClientOriginalName(),
                'description'     => "Imported {$successCount} premium quincena row(s)" . (count($errors) ? ', ' . count($errors) . ' failed' : ''),
                'employment_type' => 'Contract of Service',
            ]);
        }

        return $this->success([
            'imported' => $successCount,
            'failed' => count($errors),
            'errors' => $errors,
        ], "{$successCount} premium rows imported successfully." . (count($errors) > 0 ? ' ' . count($errors) . ' rows failed.' : ''));
    }

    /**
     * Download COS premium (per quincena) import CSV template.
     */
    public function downloadPremiumTemplate(): StreamedResponse
    {
        $headers = ['employee_id', 'year', 'month', 'quincena', 'premium_contribution'];
        $year = now()->year;

        return $this->streamCsvTemplate('premium_quincena_import_template.csv', $headers, [
            ['EMP-001', $year, '1', '1', '250'],
            ['EMP-001', $year, '1', '2', '250'],
            ['EMP-001', $year, '2', '1', '250'],
            ['EMP-001', $year, '2', '2', '250'],
        ]);
    }

    /** 1 / "1st" / "first" → 1; 2 / "2nd" / "second" → 2. */
    private function parseQuincena(string $value): ?int
    {
        $v = strtolower(trim($value));

        return match (true) {
            in_array($v, ['1', '1st', 'first'], true) => 1,
            in_array($v, ['2', '2nd', 'second'], true) => 2,
            default => null,
        };
    }

    private function parseMonth(string $value): ?int
    {
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            $m = (int) $value;
            return ($m >= 1 && $m <= 12) ? $m : null;
        }

        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        $i = array_search(strtolower(substr($value, 0, 3)), $months, true);

        return $i === false ? null : $i + 1;
    }

    private function parseAmount(string $value): ?float
    {
        $clean = str_replace([',', '₱', ' '], '', $value);
        if ($clean === '' || !is_numeric($clean) || (float) $clean < 0) {
            return null;
        }

        return round((float) $clean, 2);
    }

    /**
     * Parse CSV file into an associative array.
     */
    private function parseCsv(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return [];
        }

        // Read BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        while (($data = fgetcsv($handle)) !== false) {
            // Skip completely empty rows
            if (count(array_filter($data, fn($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = $data[$i] ?? '';
            }
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Stream a CSV template download.
     */
    private function streamCsvTemplate(string $filename, array $headers, array $sampleRows = []): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $sampleRows) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            foreach ($sampleRows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
