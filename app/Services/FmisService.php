<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\FmisEmployeeSalary;
use App\Models\HrisEmployee;
use App\Models\User;
use Carbon\Carbon;

class FmisService
{
    public function __construct(private readonly HrisService $hrisService) {}

    /**
     * Express a raw month count as a usable loan term.
     *
     * Any part of a month counts as a half-month, and never rounds up to the
     * next whole one:
     *
     *   5.0  → 5.0     exactly five whole months
     *   5.2  → 5.5     part-way into month six
     *   5.9  → 5.5     still only part-way into month six
     *   6.0  → 6.0
     *
     * Rounding to the nearest half instead would turn 5.9 into 6.0 and offer
     * a full six-month term the contract cannot cover.
     */
    public static function usableTermMonths(float $months): float
    {
        $whole = floor($months);

        return $months > $whole ? $whole + 0.5 : $whole;
    }

    /**
     * Resolve an employee's profile (employment type, salary, contract dates)
     * from the HRIS API. Falls back to the member's local registration snapshot
     * when the API is unavailable, so loan eligibility doesn't break to zero
     * during an outage. The HRIS API remains the source of truth when reachable.
     */
    public function resolveEmployee(string $employeeId): ?HrisEmployee
    {
        $emp = $this->hrisService->findByEmployeeId($employeeId);
        if ($emp) {
            return $emp;
        }

        $user = User::where('employee_id', $employeeId)->first();
        if (!$user) {
            return null;
        }

        return new HrisEmployee([
            'employee_id'     => $user->employee_id,
            'employment_type' => $user->employment_type,
            'base_pay'        => $user->base_pay,
            'take_home_pay'   => $user->take_home_pay,
            'contract_start'  => $user->contract_start?->toDateString(),
            'contract_end'    => $user->contract_end?->toDateString(),
            'status'          => $user->status === 'active' ? 'Active' : $user->status,
        ]);
    }

    /**
     * Employment type from HRIS (with local fallback).
     */
    public function getEmploymentType(string $employeeId): ?string
    {
        return $this->resolveEmployee($employeeId)?->employment_type;
    }

    /**
     * Contract end date from HRIS (with local fallback).
     */
    public function getContractEnd(string $employeeId): ?Carbon
    {
        return $this->resolveEmployee($employeeId)?->contract_end;
    }

    /**
     * Get the employee's salary as a non-persisted FmisEmployeeSalary, sourced
     * from the HRIS API (with local fallback). Field names are preserved so
     * callers (`$salary->monthly_salary`, `$salary->net_take_home`) keep working.
     */
    public function getSalary(string $employeeId): ?FmisEmployeeSalary
    {
        $emp = $this->resolveEmployee($employeeId);
        if (!$emp) {
            return null;
        }

        return $this->hydrateSalaryFrom($emp);
    }

    /**
     * Calculate max loan amount for SC based on base pay × contract months.
     *
     * @return array{monthly_salary: float, contract_months: float, remaining_contract_months: float, max_loan_amount: float, contract_start: string|null, contract_end: string|null, extended_max: float}
     */
    public function calculateScMaxLoan(string $employeeId): array
    {
        $emp = $this->resolveEmployee($employeeId);

        $monthlySalary = $emp ? (float) $emp->base_pay : 0.0;
        $contractStart = $emp?->contract_start;
        $contractEnd = $emp?->contract_end;

        // Expressed in half-months: a part-month counts as a half, so a
        // contract with 5.2 months left supports a 5.5-month term but never
        // a 6-month one.
        $contractMonths = 0;
        if ($contractStart && $contractEnd) {
            $contractMonths = max(0.5, self::usableTermMonths($contractStart->floatDiffInMonths($contractEnd)));
        }

        // Remaining contract months from today — what's left to deduct against.
        // Floor at zero (an expired contract should yield no eligible term).
        $remainingContractMonths = 0;
        if ($contractEnd) {
            $remainingContractMonths = max(0, self::usableTermMonths(now()->floatDiffInMonths($contractEnd, false)));
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
        $emp = $this->resolveEmployee($employeeId);

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
