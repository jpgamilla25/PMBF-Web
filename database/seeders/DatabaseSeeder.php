<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // HRIS and FMIS data now comes from the api-center APIs, so the
        // seeders that populated the local hris/fmis databases are gone.
        $this->call([
            ConfigurationSeeder::class,
            DefaultUsersSeeder::class,
        ]);
    }
}
