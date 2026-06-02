<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen enum first (MySQL keeps existing 'SC' rows valid while we backfill).
        DB::statement("ALTER TABLE users MODIFY employment_type ENUM('SC', 'Contract of Service', 'Permanent', 'Non-Member') NOT NULL DEFAULT 'Permanent'");

        DB::table('users')->where('employment_type', 'SC')->update(['employment_type' => 'Contract of Service']);

        // Drop the old value.
        DB::statement("ALTER TABLE users MODIFY employment_type ENUM('Contract of Service', 'Permanent', 'Non-Member') NOT NULL DEFAULT 'Permanent'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY employment_type ENUM('SC', 'Contract of Service', 'Permanent', 'Non-Member') NOT NULL DEFAULT 'Permanent'");
        DB::table('users')->where('employment_type', 'Contract of Service')->update(['employment_type' => 'SC']);
        DB::statement("ALTER TABLE users MODIFY employment_type ENUM('SC', 'Permanent', 'Non-Member') NOT NULL DEFAULT 'Permanent'");
    }
};
