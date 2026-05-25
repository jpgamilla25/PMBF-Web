<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Benefit;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    use ApiResponse;

    /**
     * Import existing loans from CSV file.
     */
    public function importLoans(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        $rows = $this->parseCsv($file->getRealPath());

        if (empty($rows)) {
            return $this->error('The file is empty or could not be parsed.');
        }

        $requiredHeaders = ['employee_id', 'loan_type', 'amount', 'interest_rate', 'term_months', 'monthly_amortization'];
        $headers = array_map(fn($h) => strtolower(trim($h)), array_keys($rows[0]));

        foreach ($requiredHeaders as $header) {
            if (!in_array($header, $headers)) {
                return $this->error("Missing required column: {$header}. Please use the provided template.");
            }
        }

        $successCount = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 because row 1 is header, data starts at row 2
            $row = array_map('trim', $row);

            $employeeId = $row['employee_id'] ?? '';
            if (empty($employeeId)) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => 'Employee ID is required.'];
                continue;
            }

            $user = User::where('employee_id', $employeeId)->first();
            if (!$user) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => "Employee not found: {$employeeId}"];
                continue;
            }

            $amount = (float) ($row['amount'] ?? 0);
            $interestRate = (float) ($row['interest_rate'] ?? 0);
            $termMonths = (int) ($row['term_months'] ?? 0);
            $monthlyAmortization = (float) ($row['monthly_amortization'] ?? 0);

            if ($amount <= 0) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => 'Amount must be greater than zero.'];
                continue;
            }

            if ($termMonths <= 0) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => 'Term months must be greater than zero.'];
                continue;
            }

            if ($monthlyAmortization <= 0) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => 'Monthly amortization must be greater than zero.'];
                continue;
            }

            try {
                Loan::create([
                    'user_id' => $user->id,
                    'loan_type' => $row['loan_type'] ?? 'Salary Loan',
                    'amount' => $amount,
                    'interest_rate' => $interestRate,
                    'term_months' => $termMonths,
                    'monthly_amortization' => $monthlyAmortization,
                    'status' => $row['status'] ?? 'released',
                    'applied_at' => !empty($row['applied_at']) ? $row['applied_at'] : now(),
                    'remarks' => $row['remarks'] ?? null,
                ]);
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => 'Failed to create loan: ' . $e->getMessage()];
            }
        }

        return $this->success([
            'imported' => $successCount,
            'failed' => count($errors),
            'errors' => $errors,
        ], "{$successCount} loans imported successfully." . (count($errors) > 0 ? " " . count($errors) . " rows failed." : ''));
    }

    /**
     * Import benefits from CSV file.
     */
    public function importBenefits(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        $rows = $this->parseCsv($file->getRealPath());

        if (empty($rows)) {
            return $this->error('The file is empty or could not be parsed.');
        }

        $requiredHeaders = ['employee_id', 'benefit_type', 'amount'];
        $headers = array_map(fn($h) => strtolower(trim($h)), array_keys($rows[0]));

        foreach ($requiredHeaders as $header) {
            if (!in_array($header, $headers)) {
                return $this->error("Missing required column: {$header}. Please use the provided template.");
            }
        }

        $successCount = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $row = array_map('trim', $row);

            $employeeId = $row['employee_id'] ?? '';
            if (empty($employeeId)) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => 'Employee ID is required.'];
                continue;
            }

            $user = User::where('employee_id', $employeeId)->first();
            if (!$user) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => "Employee not found: {$employeeId}"];
                continue;
            }

            $amount = (float) ($row['amount'] ?? 0);

            try {
                Benefit::create([
                    'user_id' => $user->id,
                    'benefit_type' => $row['benefit_type'] ?? '',
                    'description' => $row['description'] ?? null,
                    'amount' => $amount,
                    'share_capital' => (float) ($row['share_capital'] ?? 0),
                ]);
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => 'Failed to create benefit: ' . $e->getMessage()];
            }
        }

        return $this->success([
            'imported' => $successCount,
            'failed' => count($errors),
            'errors' => $errors,
        ], "{$successCount} benefits imported successfully." . (count($errors) > 0 ? " " . count($errors) . " rows failed." : ''));
    }

    /**
     * Import payments from CSV file.
     */
    public function importPayments(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        $rows = $this->parseCsv($file->getRealPath());

        if (empty($rows)) {
            return $this->error('The file is empty or could not be parsed.');
        }

        $requiredHeaders = ['employee_id', 'loan_type', 'amount', 'payment_date', 'payment_method'];
        $headers = array_map(fn($h) => strtolower(trim($h)), array_keys($rows[0]));

        foreach ($requiredHeaders as $header) {
            if (!in_array($header, $headers)) {
                return $this->error("Missing required column: {$header}. Please use the provided template.");
            }
        }

        $successCount = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $row = array_map('trim', $row);

            $employeeId = $row['employee_id'] ?? '';
            if (empty($employeeId)) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => 'Employee ID is required.'];
                continue;
            }

            $user = User::where('employee_id', $employeeId)->first();
            if (!$user) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => "Employee not found: {$employeeId}"];
                continue;
            }

            $loanType = $row['loan_type'] ?? '';
            $loan = $user->loans()
                ->where('loan_type', $loanType)
                ->whereIn('status', ['released', 'chairperson_approved'])
                ->first();

            if (!$loan) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => "No active released '{$loanType}' loan found for this employee."];
                continue;
            }

            $amount = (float) ($row['amount'] ?? 0);
            if ($amount <= 0) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => 'Amount must be greater than zero.'];
                continue;
            }

            $paymentMethod = $row['payment_method'] ?? 'cash';
            if (!in_array($paymentMethod, ['cash', 'payroll_deduction', 'bank_transfer', 'check'])) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => "Invalid payment method: {$paymentMethod}"];
                continue;
            }

            try {
                Payment::create([
                    'loan_id' => $loan->id,
                    'recorded_by' => $request->user()->id,
                    'amount' => $amount,
                    'or_number' => $row['or_number'] ?? null,
                    'payment_method' => $paymentMethod,
                    'payment_date' => !empty($row['payment_date']) ? $row['payment_date'] : now()->toDateString(),
                    'remarks' => $row['remarks'] ?? 'Imported via CSV',
                ]);

                // Check if loan is fully paid
                $totalPaid = $loan->payments()->sum('amount');
                $totalPayable = $loan->monthly_amortization * $loan->term_months;
                if ($totalPaid >= $totalPayable) {
                    $loan->update(['status' => 'completed']);
                }

                $successCount++;
            } catch (\Exception $e) {
                $errors[] = ['row' => $rowNumber, 'employee_id' => $employeeId, 'error' => 'Failed to create payment: ' . $e->getMessage()];
            }
        }

        return $this->success([
            'imported' => $successCount,
            'failed' => count($errors),
            'errors' => $errors,
        ], "{$successCount} payments imported successfully." . (count($errors) > 0 ? " " . count($errors) . " rows failed." : ''));
    }

    /**
     * Download payment import CSV template.
     */
    public function downloadPaymentTemplate(): StreamedResponse
    {
        $headers = ['employee_id', 'loan_type', 'amount', 'or_number', 'payment_method', 'payment_date', 'remarks'];

        return $this->streamCsvTemplate('payment_import_template.csv', $headers, [
            ['15-0313', 'Hospitalization', '5000', 'OR-2026-001', 'cash', '2026-03-15', 'March payment'],
            ['15-0313', 'Hospitalization', '5000', 'OR-2026-002', 'payroll_deduction', '2026-04-15', 'April payment'],
        ]);
    }

    /**
     * Download loan import CSV template.
     */
    public function downloadLoanTemplate(): StreamedResponse
    {
        $headers = ['employee_id', 'loan_type', 'amount', 'interest_rate', 'term_months', 'monthly_amortization', 'status', 'applied_at', 'remarks'];

        return $this->streamCsvTemplate('loan_import_template.csv', $headers, [
            ['EMP-001', 'Salary Loan', '50000', '6', '24', '2250', 'released', '2025-01-15', 'Existing loan'],
        ]);
    }

    /**
     * Download benefit import CSV template.
     */
    public function downloadBenefitTemplate(): StreamedResponse
    {
        $headers = ['employee_id', 'benefit_type', 'description', 'amount', 'share_capital'];

        return $this->streamCsvTemplate('benefit_import_template.csv', $headers, [
            ['EMP-001', 'Dividend', 'Annual dividend 2025', '5000', '10000'],
        ]);
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
