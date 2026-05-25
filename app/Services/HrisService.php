<?php

namespace App\Services;

use App\Models\HrisEmployee;

class HrisService
{
    /**
     * Find an HRIS employee by their employee ID.
     */
    public function findByEmployeeId(string $id): ?HrisEmployee
    {
        return HrisEmployee::where('employee_id', $id)->first();
    }

    /**
     * Validate that an employee exists in HRIS and is eligible for registration.
     *
     * @return array{valid: bool, employee: ?HrisEmployee, message: string}
     */
    public function validateEmployee(string $id): array
    {
        $employee = $this->findByEmployeeId($id);

        if (!$employee) {
            return [
                'valid' => false,
                'employee' => null,
                'message' => 'Employee not found in HRIS records.',
            ];
        }

        if ($employee->status !== 'Active' && $employee->status !== 'active') {
            return [
                'valid' => false,
                'employee' => $employee,
                'message' => 'Employee is not currently active in HRIS.',
            ];
        }

        return [
            'valid' => true,
            'employee' => $employee,
            'message' => 'Employee validated successfully.',
        ];
    }
}
