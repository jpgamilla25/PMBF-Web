<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DefaultUsersSeeder extends Seeder
{
    public function run(): void
    {
        $staffUsers = [
            [
                'employee_id' => '15-0312',
                'role' => 'admin',
            ],
            [
                'employee_id' => '15-0313',
                'role' => 'member',
            ],
            [
                'employee_id' => '15-0314',
                'role' => 'receiver',
            ],
            [
                'employee_id' => '15-0315',
                'role' => 'loan_committee',
            ],
            [
                'employee_id' => '15-0316',
                'role' => 'chairperson',
            ],

            // SC members (pre-registered for testing)
            [
                'employee_id' => '25-0601',  // Carlo Bautista — SC, 18k salary, 6mo contract
                'role' => 'member',
            ],
            [
                'employee_id' => '26-0121',  // Mark Gonzales — SC, 12k salary, take-home 4500 (below 5k min!)
                'role' => 'member',
            ],

            // Non-Members (pre-registered for testing)
            [
                'employee_id' => '25-0901',  // Luis Tan — Non-Member, 25k
                'role' => 'member',
            ],
            [
                'employee_id' => '25-0932',  // Grace Lim — Non-Member, 10k
                'role' => 'member',
            ],
        ];

        foreach ($staffUsers as $staff) {
            $hrisEmployee = DB::connection('hris')
                ->table('employees')
                ->where('employee_id', $staff['employee_id'])
                ->first();

            if (!$hrisEmployee) {
                continue;
            }

            DB::table('users')->updateOrInsert(
                ['employee_id' => $staff['employee_id']],
                [
                    'first_name' => $hrisEmployee->first_name,
                    'middle_name' => $hrisEmployee->middle_name,
                    'last_name' => $hrisEmployee->last_name,
                    'suffix' => $hrisEmployee->suffix,
                    'email' => $hrisEmployee->email,
                    'mobile' => $hrisEmployee->mobile,
                    'employment_type' => $hrisEmployee->employment_type,
                    'position' => $hrisEmployee->position,
                    'department' => $hrisEmployee->department,
                    'base_pay' => $hrisEmployee->base_pay,
                    'take_home_pay' => $hrisEmployee->take_home_pay,
                    'contract_start' => $hrisEmployee->contract_start,
                    'contract_end' => $hrisEmployee->contract_end,
                    'role' => $staff['role'],
                    'password' => Hash::make('password123'),
                    'email_verified_at' => now(),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
