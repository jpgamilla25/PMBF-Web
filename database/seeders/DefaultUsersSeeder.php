<?php

namespace Database\Seeders;

use App\Models\HrisEmployee;
use App\Services\HrisService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DefaultUsersSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password123';

    /**
     * Seed the default accounts. Employee details come from the HRIS API when
     * it is reachable; each entry carries a local fallback so a fresh install
     * always ends up with working logins, even offline or without API creds.
     */
    public function run(): void
    {
        $hris = app(HrisService::class);
        $seeded = 0;
        $fromFallback = 0;

        foreach ($this->defaultUsers() as $staff) {
            $employee = $hris->findByEmployeeId($staff['employee_id']);

            if (!$employee) {
                $employee = new HrisEmployee($staff['fallback']);
                $fromFallback++;
            }

            DB::table('users')->updateOrInsert(
                ['employee_id' => $staff['employee_id']],
                [
                    'first_name' => $employee->first_name ?? $staff['fallback']['first_name'],
                    'middle_name' => $employee->middle_name,
                    'last_name' => $employee->last_name ?? $staff['fallback']['last_name'],
                    'suffix' => $employee->suffix,
                    'email' => $employee->email ?: $staff['fallback']['email'],
                    'mobile' => $employee->mobile,
                    'employment_type' => $this->normalizeEmploymentType(
                        $employee->employment_type ?? $staff['fallback']['employment_type']
                    ),
                    'position' => $employee->position,
                    'department' => $employee->department,
                    'base_pay' => $employee->base_pay ?? $staff['fallback']['base_pay'],
                    'take_home_pay' => $employee->take_home_pay ?? $staff['fallback']['take_home_pay'],
                    'contract_start' => $employee->contract_start,
                    'contract_end' => $employee->contract_end,
                    'role' => $staff['role'],
                    'password' => Hash::make(self::DEFAULT_PASSWORD),
                    'email_verified_at' => now(),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $seeded++;
        }

        $this->command?->info("Seeded {$seeded} default users (password: " . self::DEFAULT_PASSWORD . ').');

        if ($fromFallback > 0) {
            $this->command?->warn(
                "{$fromFallback} of {$seeded} used local fallback data — HRIS API was unreachable "
                . 'or returned no record. Logins still work; details may differ from HRIS.'
            );
        }
    }

    /**
     * The users table constrains employment_type to a three-value enum, so any
     * unexpected HRIS value is coerced rather than allowed to fail the insert.
     * HRIS still reports service contracts as "SC"; the 2026_06_01 migration
     * renamed that enum value to "Contract of Service".
     */
    private function normalizeEmploymentType(?string $type): string
    {
        $type = match (trim((string) $type)) {
            'SC', 'Service Contract' => 'Contract of Service',
            default => trim((string) $type),
        };

        return in_array($type, ['Contract of Service', 'Permanent', 'Non-Member'], true)
            ? $type
            : 'Permanent';
    }

    private function defaultUsers(): array
    {
        return [
            [
                'employee_id' => '15-0312',
                'role' => 'admin',
                'fallback' => $this->profile('Admin', 'User', 'Permanent', 45000, 30000),
            ],
            [
                'employee_id' => '15-0313',
                'role' => 'member',
                'fallback' => $this->profile('Member', 'User', 'Permanent', 35000, 22000),
            ],
            [
                'employee_id' => '15-0314',
                'role' => 'receiver',
                'fallback' => $this->profile('Receiver', 'User', 'Permanent', 35000, 22000),
            ],
            [
                'employee_id' => '15-0315',
                'role' => 'loan_committee',
                'fallback' => $this->profile('Committee', 'User', 'Permanent', 40000, 26000),
            ],
            [
                'employee_id' => '15-0316',
                'role' => 'chairperson',
                'fallback' => $this->profile('Chairperson', 'User', 'Permanent', 50000, 33000),
            ],

            // SC members (pre-registered for testing)
            [
                // Carlo Bautista — SC, 18k salary, 6mo contract
                'employee_id' => '25-0601',
                'role' => 'member',
                'fallback' => $this->profile('Carlo', 'Bautista', 'SC', 18000, 14000),
            ],
            [
                // Mark Gonzales — SC, 12k salary, take-home 4500 (below 5k min!)
                'employee_id' => '26-0121',
                'role' => 'member',
                'fallback' => $this->profile('Mark', 'Gonzales', 'SC', 12000, 4500),
            ],

            // Non-Members (pre-registered for testing)
            [
                // Luis Tan — Non-Member, 25k
                'employee_id' => '25-0901',
                'role' => 'member',
                'fallback' => $this->profile('Luis', 'Tan', 'Non-Member', 25000, 18000),
            ],
            [
                // Grace Lim — Non-Member, 10k
                'employee_id' => '25-0932',
                'role' => 'member',
                'fallback' => $this->profile('Grace', 'Lim', 'Non-Member', 10000, 7000),
            ],
        ];
    }

    private function profile(
        string $firstName,
        string $lastName,
        string $employmentType,
        float $basePay,
        float $takeHomePay
    ): array {
        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower($firstName . '.' . $lastName) . '@pmbf.local',
            'employment_type' => $employmentType,
            'base_pay' => $basePay,
            'take_home_pay' => $takeHomePay,
        ];
    }
}
