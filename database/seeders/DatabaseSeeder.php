<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            HrisEmployeeSeeder::class,
            FmisEmployeeSalarySeeder::class,
            ConfigurationSeeder::class,
            DefaultUsersSeeder::class,
        ]);
    }
}
