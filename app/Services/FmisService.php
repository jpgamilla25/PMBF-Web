<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\FmisEmployeeSalary;
use App\Models\HrisEmployee;

class FmisService
{
    /**
     * Get latest salary record for an employee.
     */
    public function getSalary(string $employeeId): ?FmisEmployeeSalary
    {
        return FmisEmployeeSalary::where('employee_id', $employeeId)
            ->where('status', 'Active')
            ->orderByDesc('effective_date')
            ->first();
    }

    /**
     * Calculate max loan amount for SC based on salary x contract months.
     * Gets salary from FMIS, contract dates from HRIS.
     *
     * @return array{monthly_salary: float, contract_months: int, max_loan_amount: float, contract_start: string|null, contract_end: string|null, extended_max: float}
     */
    public function calculateScMaxLoan(string $employeeId): array
    {
        $salary = $this->getSalary($employeeId);
        $hrisEmployee = HrisEmployee::where('employee_id', $employeeId)->first();

        $monthlySalary = $salary ? (float) $salary->monthly_salary : 0.0;
        $contractStart = $hrisEmployee?->contract_start;
        $contractEnd = $hrisEmployee?->contract_end;

        // Contract duration in months (full contract period)
        $contractMonths = 0;
        if ($contractStart && $contractEnd) {
            $contractMonths = max(1, (int) round($contractStart->floatDiffInMonths($contractEnd)));
        }

        return [
            'monthly_salary' => $monthlySalary,
            'contract_months' => $contractMonths,
            'max_loan_amount' => $monthlySalary * $contractMonths,
            'contract_start' => $contractStart?->toDateString(),
            'contract_end' => $contractEnd?->toDateString(),
            'extended_max' => $monthlySalary * max($contractMonths, 12),
        ];
    }

    /**
     * Check if employee meets minimum pay requirement.
     *
     * @return array{meets_requirement: bool, required_amount: float, actual_amount: float, field_checked: string}
     */
    public function meetsMinimumPay(string $employeeId, string $employmentType): array
    {
        $salary = $this->getSalary($employeeId);

        if ($employmentType === 'SC') {
            $requiredAmount = Configuration::getDecimal('sc_min_take_home_pay');
            $actualAmount = $salary ? (float) $salary->net_take_home : 0.0;
            $fieldChecked = 'net_take_home';
        } else {
            $requiredAmount = Configuration::getDecimal('permanent_min_take_home_pay');
            $actualAmount = $salary ? (float) $salary->net_take_home : 0.0;
            $fieldChecked = 'net_take_home';
        }

        return [
            'meets_requirement' => $actualAmount >= $requiredAmount,
            'required_amount' => $requiredAmount,
            'actual_amount' => $actualAmount,
            'field_checked' => $fieldChecked,
        ];
    }
}
