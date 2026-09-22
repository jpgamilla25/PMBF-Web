<?php

namespace App\Services\Concerns;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Reading an uploaded CSV or spreadsheet into rows keyed by normalised header,
 * shared by the payment and loan importers so both accept the same files and
 * read "₱1,250.00", "1250" and an Excel date cell the same way.
 */
trait ParsesSpreadsheets
{
    public function parse(string $path, string $extension): array
    {
        return in_array($extension, ['xlsx', 'xls'], true)
            ? $this->parseSpreadsheet($path)
            : $this->parseCsv($path);
    }

    protected function parseCsv(string $path): array
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

    protected function parseSpreadsheet(string $path): array
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
                // Dates arrive as Excel serial numbers; convert here so a real
                // date cell is not read as 45678.
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

    protected function normaliseHeader(?string $header): string
    {
        return str_replace([' ', '-'], '_', strtolower(trim((string) $header)));
    }

    /** "₱1,250.00 " and "1250" both mean 1250.00; blank returns null. */
    protected function toAmount(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($clean) ? round((float) $clean, 2) : null;
    }

    protected function toDate(mixed $value): ?string
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

    protected function isTruthy(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'y', 'yes', 'true', 'x', 'ok'], true);
    }
}
