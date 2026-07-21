<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DefaultUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Employee data used to come from the HRIS DB connection. That DB is no
        // longer used (HRIS/FMIS are now API-driven), so the default/test
        // accounts are seeded self-contained here into the app's own users table.
        $staffUsers = [
            [
                'employee_id' => '15-0312',
                'first_name' => 'Jayson',
                'middle_name' => 'P.',
                'last_name' => 'Gamilla',
                'suffix' => null,
                'email' => 'lazada.gamilla@gmail.com',
                'mobile' => '09171234567',
                'employment_type' => 'Permanent',
                'position' => 'System Administrator',
                'department' => 'IT',
                'base_pay' => 45000.00,
                'take_home_pay' => 36000.00,
                'contract_start' => '2015-03-12',
                'contract_end' => null,
                'role' => 'admin',
            ],
            [
                'employee_id' => '15-0313',
                'first_name' => 'Art',
                'middle_name' => null,
                'last_name' => 'Arocena',
                'suffix' => null,
                'email' => 'lazada.gamilla+art@gmail.com',
                'mobile' => '09181234568',
                'employment_type' => 'Permanent',
                'position' => 'Senior Analyst',
                'department' => 'Finance',
                'base_pay' => 35000.00,
                'take_home_pay' => 28000.00,
                'contract_start' => '2015-03-13',
                'contract_end' => null,
                'role' => 'member',
            ],
            [
                'employee_id' => '15-0314',
                'first_name' => 'Ronnie',
                'middle_name' => null,
                'last_name' => 'Rimando',
                'suffix' => null,
                'email' => 'lazada.gamilla+ronnie@gmail.com',
                'mobile' => '09191234569',
                'employment_type' => 'Permanent',
                'position' => 'Loan Receiver',
                'department' => 'Finance',
                'base_pay' => 32000.00,
                'take_home_pay' => 25600.00,
                'contract_start' => '2015-03-14',
                'contract_end' => null,
                'role' => 'receiver',
            ],
            [
                'employee_id' => '15-0315',
                'first_name' => 'Timothy',
                'middle_name' => null,
                'last_name' => 'Rivera',
                'suffix' => null,
                'email' => 'lazada.gamilla+timothy@gmail.com',
                'mobile' => '09201234570',
                'employment_type' => 'Permanent',
                'position' => 'Loan Committee Head',
                'department' => 'Finance',
                'base_pay' => 42000.00,
                'take_home_pay' => 33600.00,
                'contract_start' => '2015-03-15',
                'contract_end' => null,
                'role' => 'loan_committee',
            ],
            [
                'employee_id' => '15-0316',
                'first_name' => 'PMBF',
                'middle_name' => null,
                'last_name' => 'Chair',
                'suffix' => null,
                'email' => 'lazada.gamilla+myline@gmail.com',
                'mobile' => '09211234571',
                'employment_type' => 'Permanent',
                'position' => 'Chairperson',
                'department' => 'Executive',
                'base_pay' => 60000.00,
                'take_home_pay' => 48000.00,
                'contract_start' => '2015-03-16',
                'contract_end' => null,
                'role' => 'chairperson',
            ],

            // SC members (pre-registered for testing)
            [
                'employee_id' => '25-0601',  // Carlo Bautista — SC, 18k salary, 12mo contract
                'first_name' => 'Carlo',
                'middle_name' => 'Torres',
                'last_name' => 'Bautista',
                'suffix' => null,
                'email' => 'lazada.gamilla+carlo@gmail.com',
                'mobile' => '09251234575',
                'employment_type' => 'Contract of Service',
                'position' => 'Project Assistant',
                'department' => 'Operations',
                'base_pay' => 18000.00,
                'take_home_pay' => 15000.00,
                'contract_start' => '2026-01-01',
                'contract_end' => '2026-12-31',
                'role' => 'member',
            ],
            [
                'employee_id' => '26-0121',  // Mark Gonzales — SC, 12k salary, take-home 4500 (below 5k min!)
                'first_name' => 'Mark',
                'middle_name' => 'Aquino',
                'last_name' => 'Gonzales',
                'suffix' => null,
                'email' => 'lazada.gamilla+mark@gmail.com',
                'mobile' => '09271234577',
                'employment_type' => 'Contract of Service',
                'position' => 'Field Worker',
                'department' => 'Operations',
                'base_pay' => 12000.00,
                'take_home_pay' => 4500.00,
                'contract_start' => '2026-01-21',
                'contract_end' => '2026-12-31',
                'role' => 'member',
            ],

            // Non-Members (pre-registered for testing)
            [
                'employee_id' => '25-0901',  // Luis Tan — Non-Member, 25k
                'first_name' => 'Luis',
                'middle_name' => 'Ramos',
                'last_name' => 'Tan',
                'suffix' => null,
                'email' => 'lazada.gamilla+luistan@gmail.com',
                'mobile' => '09281234578',
                'employment_type' => 'Non-Member',
                'position' => 'Consultant',
                'department' => 'External',
                'base_pay' => 25000.00,
                'take_home_pay' => 22000.00,
                'contract_start' => '2025-09-01',
                'contract_end' => '2026-06-30',
                'role' => 'member',
            ],
            [
                'employee_id' => '25-0932',  // Grace Lim — Non-Member, 10k
                'first_name' => 'Grace',
                'middle_name' => 'Pascual',
                'last_name' => 'Lim',
                'suffix' => null,
                'email' => 'lazada.gamilla+grace@gmail.com',
                'mobile' => '09291234579',
                'employment_type' => 'Non-Member',
                'position' => 'Part-time Staff',
                'department' => 'Admin',
                'base_pay' => 10000.00,
                'take_home_pay' => 9000.00,
                'contract_start' => '2025-09-15',
                'contract_end' => '2026-08-31',
                'role' => 'member',
            ],
        ];

        foreach ($staffUsers as $staff) {
            DB::table('users')->updateOrInsert(
                ['employee_id' => $staff['employee_id']],
                [
                    'first_name' => $staff['first_name'],
                    'middle_name' => $staff['middle_name'],
                    'last_name' => $staff['last_name'],
                    'suffix' => $staff['suffix'],
                    'email' => $staff['email'],
                    'mobile' => $staff['mobile'],
                    'employment_type' => $staff['employment_type'],
                    'position' => $staff['position'],
                    'department' => $staff['department'],
                    'base_pay' => $staff['base_pay'],
                    'take_home_pay' => $staff['take_home_pay'],
                    'contract_start' => $staff['contract_start'],
                    'contract_end' => $staff['contract_end'],
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
