<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Benefit;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    use ApiResponse;

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
