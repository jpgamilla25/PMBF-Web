<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Writes the year-end report to an .xlsx laid out like the office template
 * ("report template.xlsx"): one sheet per schedule, totals as live formulas.
 */
class EquityReportExcel
{
    private const MONEY = '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)';
    private const DATE  = 'dd-mmm-yy';
    private const HEAD_FILL = 'FFE2EFDA';

    public const STATION = 'PHILRICE-MALIGAYA';

    public function build(array $report): Spreadsheet
    {
        $book = new Spreadsheet();
        $book->getProperties()->setCreator('PMBF')->setTitle('PMBF Year-End Report ' . $report['year']);

        $this->equitySheet($book->getActiveSheet(), $report);
        $this->medicalSheet($book->createSheet(), $report);
        $this->dependentsSheet($book->createSheet(), $report);

        $book->setActiveSheetIndex(0);

        return $book;
    }

    // ── EQUITY & LOANS ────────────────────────────────────────────

    private function equitySheet(Worksheet $ws, array $report): void
    {
        $ws->setTitle('EQUITY & LOANS');
        $year = $report['year'];
        $loanKeys = array_keys($report['loan_columns']);

        $ws->setCellValue('A1', 'PHILRICE MUTUAL BENEFIT FUND');
        $ws->setCellValue('A2', 'SUMMARY SCHEDULE OF MEMBERS EQUITY AND LOANS');
        $ws->setCellValue('A3', 'YEAR ENDING DECEMBER 31, ' . $year);
        $ws->getStyle('A1:A3')->getFont()->setBold(true);

        // Columns: A no, B name, C personal, D counterpart, E total equity,
        // loans from F (one per type), then loan TOTAL, then NET EQUITY.
        $firstLoan = 6;
        $lastLoan = $firstLoan + count($loanKeys) - 1;
        $totalCol = $lastLoan + 1;
        $netCol = $totalCol + 1;
        $L = fn (int $i) => Coordinate::stringFromColumnIndex($i);

        $ws->setCellValue('B5', 'NAME');
        $ws->setCellValue('C5', 'PERSONAL CONTRIBUTION');
        $ws->setCellValue('D5', 'PHILRICE');
        $ws->setCellValue('D6', 'COUNTERPART');
        $ws->setCellValue('E5', 'TOTAL EQUITY');
        $ws->setCellValue($L($firstLoan) . '5', 'LOANS');
        $ws->setCellValue($L($netCol) . '5', 'NET EQUITY (OBLIGATION)');
        foreach ($loanKeys as $i => $key) {
            $ws->setCellValue($L($firstLoan + $i) . '6', $report['loan_columns'][$key]);
        }
        $ws->setCellValue($L($totalCol) . '6', 'TOTAL');

        foreach (['A5:A6', 'B5:B6', 'C5:C6', 'E5:E6'] as $range) {
            $ws->mergeCells($range);
        }
        $ws->mergeCells($L($firstLoan) . '5:' . $L($totalCol) . '5');
        $ws->mergeCells($L($netCol) . '5:' . $L($netCol) . '6');
        $this->header($ws, 'A5:' . $L($netCol) . '6');

        $r = 7;
        foreach ($report['equity']['rows'] as $row) {
            $ws->setCellValue("A{$r}", $row['no']);
            $ws->setCellValue("B{$r}", $row['name']);
            $ws->setCellValue("C{$r}", $row['personal']);
            $ws->setCellValue("D{$r}", $row['counterpart']);
            $ws->setCellValue("E{$r}", "=SUM(C{$r}:D{$r})");
            foreach ($loanKeys as $i => $key) {
                $v = $row['loans'][$key];
                if ($v != 0) {
                    $ws->setCellValue($L($firstLoan + $i) . $r, $v);
                }
            }
            $ws->setCellValue($L($totalCol) . $r, '=SUM(' . $L($firstLoan) . "{$r}:" . $L($lastLoan) . "{$r})");
            $ws->setCellValue($L($netCol) . $r, "=E{$r}-" . $L($totalCol) . $r);
            $r++;
        }
        $lastData = max(7, $r - 1);

        $totalRow = $r + 1;
        $ws->setCellValue("B{$totalRow}", 'TOTAL');
        for ($c = 3; $c <= $netCol; $c++) {
            $ws->setCellValue($L($c) . $totalRow, '=SUM(' . $L($c) . '7:' . $L($c) . $lastData . ')');
        }
        $ws->getStyle("A{$totalRow}:" . $L($netCol) . $totalRow)->getFont()->setBold(true);
        $ws->getStyle("A{$totalRow}:" . $L($netCol) . $totalRow)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);

        $ws->getStyle('C7:' . $L($netCol) . $totalRow)->getNumberFormat()->setFormatCode(self::MONEY);
        $this->grid($ws, 'A5:' . $L($netCol) . $lastData);

        $widths = [5, 30, 20, 20, 20];
        foreach ($widths as $i => $w) {
            $ws->getColumnDimension($L($i + 1))->setWidth($w);
        }
        for ($c = $firstLoan; $c <= $netCol; $c++) {
            $ws->getColumnDimension($L($c))->setWidth(16);
        }
        $ws->getColumnDimension($L($netCol))->setWidth(20);
        $ws->freezePane('C7');
        $this->landscape($ws);
    }

    // ── MEDICAL FOR DEP ───────────────────────────────────────────

    private function medicalSheet(Worksheet $ws, array $report): void
    {
        $ws->setTitle('MEDICAL FOR DEP');
        $medical = $report['medical'];
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $ws->setCellValue('A1', 'Philrice Mutual Benefit Fund, Inc. (PMBF)');
        $ws->setCellValue('A2', "Health and Medical Assistance Benefit for Regulars' Dependents");
        $ws->setCellValue('A3', $report['year']);
        $ws->getStyle('A1:A3')->getFont()->setBold(true);
        $ws->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $ws->setCellValue('D5', 'PREMIUM PAYMENT');
        $ws->mergeCells('D5:S5');
        $ws->setCellValue('A6', 'PMBF MEMBER');
        $ws->setCellValue('B6', 'Name of dependent');
        $ws->setCellValue('C6', 'Relationship');
        $ws->setCellValue('D6', 'Premium (P' . number_format($medical['default_premium']) . '/DEPENDENT)');
        $ws->setCellValue('E6', 'Monthly deduction');
        foreach ($months as $i => $m) {
            $ws->setCellValue(Coordinate::stringFromColumnIndex(6 + $i) . '6', $m);
        }
        $ws->setCellValue('R6', 'Total');
        $ws->setCellValue('S6', 'Balance');
        $this->header($ws, 'A5:S6');

        $r = 7;
        $previousMember = null;
        foreach ($medical['rows'] as $row) {
            // Member name once, on their first dependent — as in the template.
            if ($row['user_id'] !== $previousMember) {
                $ws->setCellValue("A{$r}", $row['member']);
                $previousMember = $row['user_id'];
            }
            $ws->setCellValue("B{$r}", $row['dependent']);
            $ws->setCellValue("C{$r}", ucfirst((string) $row['relationship']));
            $ws->setCellValue("D{$r}", $row['premium']);
            $ws->setCellValue("E{$r}", $row['monthly_deduction']);
            for ($m = 1; $m <= 12; $m++) {
                if ($row['months'][$m] != 0) {
                    $ws->setCellValue(Coordinate::stringFromColumnIndex(5 + $m) . $r, $row['months'][$m]);
                }
            }
            $ws->setCellValue("R{$r}", "=SUM(F{$r}:Q{$r})");
            $ws->setCellValue("S{$r}", "=D{$r}-R{$r}");
            $r++;
        }
        $lastData = max(7, $r - 1);

        $totalRow = $r + 1;
        $ws->setCellValue("A{$totalRow}", 'TOTAL');
        foreach (range('D', 'S') as $col) {
            $ws->setCellValue("{$col}{$totalRow}", "=SUM({$col}7:{$col}{$lastData})");
        }
        $ws->getStyle("A{$totalRow}:S{$totalRow}")->getFont()->setBold(true);
        $ws->getStyle("A{$totalRow}:S{$totalRow}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);

        $ws->getStyle("D7:S{$totalRow}")->getNumberFormat()->setFormatCode(self::MONEY);
        $this->grid($ws, "A5:S{$lastData}");

        $ws->getColumnDimension('A')->setWidth(28);
        $ws->getColumnDimension('B')->setWidth(26);
        $ws->getColumnDimension('C')->setWidth(14);
        $ws->getColumnDimension('D')->setWidth(18);
        $ws->getColumnDimension('E')->setWidth(12);
        foreach (range('F', 'S') as $col) {
            $ws->getColumnDimension($col)->setWidth(11.5);
        }
        $ws->getStyle('D6:E6')->getAlignment()->setWrapText(true);
        $ws->freezePane('D7');
        $this->landscape($ws);
    }

    // ── DEPENDENTS & BENEFICIARIES ────────────────────────────────

    private function dependentsSheet(Worksheet $ws, array $report): void
    {
        $ws->setTitle('DEPENDENTS & BENEFICIARIES');

        $ws->setCellValue('A1', 'PHILRICE MUTUAL BENEFIT FUND, INC. (PMBF)');
        $ws->setCellValue('A2', 'LIST OF REGULAR MEMBERS DECLARED DEPENDENTS & BENEFICIARIES');
        $ws->setCellValue('A3', self::STATION);
        $ws->getStyle('A1:A3')->getFont()->setBold(true);

        $headers = ['NO.', 'NAME', 'BIRTHDAY', 'ADDRESS', 'DATE STARTED', "DEPENDENTS' NAME", 'RELATIONSHIP', 'BENEFICIARIES', 'RELATIONSHIP', 'UPDATED'];
        foreach ($headers as $i => $h) {
            $ws->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . '5', $h);
        }
        $this->header($ws, 'A5:J5');

        $r = 6;
        foreach ($report['dependents'] as $member) {
            $span = max(1, count($member['dependents']), count($member['beneficiaries']));

            $ws->setCellValue("A{$r}", $member['no']);
            $ws->setCellValue("B{$r}", $member['name']);
            $ws->getStyle("B{$r}")->getFont()->setBold(true);
            $this->date($ws, "C{$r}", $member['birth_date']);
            $ws->setCellValue("D{$r}", $member['address']);
            $this->date($ws, "E{$r}", $member['date_started']);
            $this->date($ws, "J{$r}", $member['updated']);

            for ($i = 0; $i < $span; $i++) {
                $row = $r + $i;
                if ($d = $member['dependents'][$i] ?? null) {
                    $ws->setCellValue("F{$row}", $d['name']);
                    $ws->setCellValue("G{$row}", ucfirst((string) $d['relationship']));
                }
                if ($b = $member['beneficiaries'][$i] ?? null) {
                    $ws->setCellValue("H{$row}", $b['name']);
                    $ws->setCellValue("I{$row}", ucfirst((string) $b['relationship']));
                }
            }

            // Separate members with a rule so multi-row blocks read as one.
            $ws->getStyle("A{$r}:J{$r}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
            $r += $span;
        }
        $lastData = max(6, $r - 1);

        $ws->getStyle("A5:J{$lastData}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle("A6:J{$lastData}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $ws->getStyle("D6:D{$lastData}")->getAlignment()->setWrapText(true);

        foreach (['A' => 6, 'B' => 32, 'C' => 11, 'D' => 44, 'E' => 12, 'F' => 26, 'G' => 16, 'H' => 26, 'I' => 16, 'J' => 12] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
        $ws->freezePane('C6');
        $this->landscape($ws);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function header(Worksheet $ws, string $range): void
    {
        $style = $ws->getStyle($range);
        $style->getFont()->setBold(true);
        $style->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEAD_FILL);
        $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function grid(Worksheet $ws, string $range): void
    {
        $ws->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function date(Worksheet $ws, string $cell, ?string $date): void
    {
        if (!$date) {
            return;
        }
        $ws->setCellValue($cell, ExcelDate::PHPToExcel(new \DateTime($date)));
        $ws->getStyle($cell)->getNumberFormat()->setFormatCode(self::DATE);
    }

    private function landscape(Worksheet $ws): void
    {
        $ws->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LEGAL)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
    }
}
