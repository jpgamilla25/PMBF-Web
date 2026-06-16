<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\FmisEmployeeSalary;
use App\Models\HrisEmployee;

class FmisService
{
    public function __construct(private readonly HrisService $hrisService) {}

    /**
     * Get the employee's salary as a non-persisted FmisEmployeeSalary, sourced
     * from the HRIS API. Field names are preserved so existing callers
     * (`$salary->monthly_salary`, `$salary->net_take_home`, etc.) keep working.
     */
    public function getSalary(string $employeeId): ?FmisEmployeeSalary
    {
        $emp = $this->hrisService->findByEmployeeId($employeeId);
        if (!$emp) {
            return null;
        }

        return $this->hydrateSalaryFrom($emp);
    }

    /**
     * Calculate max loan amount for SC based on base pay × contract months.
     *
     * @return array{monthly_salary: float, contract_months: int, remaining_contract_months: int, max_loan_amount: float, contract_start: string|null, contract_end: string|null, extended_max: float}
     */
    public function calculateScMaxLoan(string $employeeId): array
    {
        $emp = $this->hrisService->findByEmployeeId($employeeId);

        $monthlySalary = $emp ? (float) $emp->base_pay : 0.0;
        $contractStart = $emp?->contract_start;
        $contractEnd = $emp?->contract_end;

        $contractMonths = 0;
        if ($contractStart && $contractEnd) {
            $contractMonths = max(1, (int) round($contractStart->floatDiffInMonths($contractEnd)));
        }

        // Remaining contract months from today — what's left to deduct against.
        // Floor at zero (an expired contract should yield no eligible term).
        $remainingContractMonths = 0;
        if ($contractEnd) {
            $remainingContractMonths = max(0, (int) round(now()->floatDiffInMonths($contractEnd, false)));
        }

        // SC max loan = a percentage of the value of the REMAINING contract.
        // Default 50% — i.e. half of what the member will earn before contract ends.
        $percentage = Configuration::getDecimal('sc_max_loan_percentage', 50) / 100;

        return [
            'monthly_salary' => $monthlySalary,
            'contract_months' => $contractMonths,
            'remaining_contract_months' => $remainingContractMonths,
            'max_loan_amount' => $monthlySalary * $remainingContractMonths * $percentage,
            'contract_start' => $contractStart?->toDateString(),
            'contract_end' => $contractEnd?->toDateString(),
            'extended_max' => $monthlySalary * max($remainingContractMonths, 12) * $percentage,
        ];
    }

    /**
     * Check if employee meets minimum take-home pay requirement.
     *
     * @return array{meets_requirement: bool, required_amount: float, actual_amount: float, field_checked: string}
     */
    public function meetsMinimumPay(string $employeeId, string $employmentType): array
    {
        $emp = $this->hrisService->findByEmployeeId($employeeId);

        $configKey = $employmentType === 'Contract of Service'
            ? 'sc_min_take_home_pay'
            : 'permanent_min_take_home_pay';

        $requiredAmount = Configuration::getDecimal($configKey);
        $actualAmount = $emp ? (float) $emp->take_home_pay : 0.0;

        return [
            'meets_requirement' => $actualAmount >= $requiredAmount,
            'required_amount' => $requiredAmount,
            'actual_amount' => $actualAmount,
            'field_checked' => 'net_take_home',
        ];
    }

    private function hydrateSalaryFrom(HrisEmployee $emp): FmisEmployeeSalary
    {
        $salary = new FmisEmployeeSalary([
            'employee_id' => $emp->employee_id,
            'monthly_salary' => $emp->base_pay,
            'base_pay' => $emp->base_pay,
            'net_take_home' => $emp->take_home_pay,
            'status' => $emp->status,
        ]);
        $salary->exists = false;

        return $salary;
    }
}
