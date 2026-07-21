<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Note: employee & salary data now comes from the HRIS/FMIS APIs, not
        // seeded DB tables. Only the app's own tables are seeded here.
        $this->call([
            ConfigurationSeeder::class,
            DefaultUsersSeeder::class,
        ]);
    }
}
