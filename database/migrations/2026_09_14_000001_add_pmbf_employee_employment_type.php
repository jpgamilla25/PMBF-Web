<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the 'PMBF Employee' employment type.
 *
 * The other three types are all sourced from PhilRice HRIS — Permanent,
 * COS-Enrolled ('Contract of Service') and COS-Non-Enrolled ('Non-Member'). A
 * PMBF Employee is staff of the fund itself: no PhilRice employment record to
 * look up, so they are created by an admin in Member Management and carry a
 * locally-issued member ID rather than an HRIS employee ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only MySQL stores this as an enum; sqlite keeps it as a plain string,
        // so there is no constraint to widen there.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY employment_type ENUM('Contract of Service', 'Permanent', 'Non-Member', 'PMBF Employee') NOT NULL DEFAULT 'Permanent'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Narrowing the enum would silently rewrite these people's type, so
        // refuse rather than guess where they belong.
        $staff = DB::table('users')->where('employment_type', 'PMBF Employee')->count();

        if ($staff > 0) {
            throw new RuntimeException(
                "Cannot roll back: {$staff} user(s) are still PMBF Employees. "
                . 'Reassign or remove them first.'
            );
        }

        DB::statement("ALTER TABLE users MODIFY employment_type ENUM('Contract of Service', 'Permanent', 'Non-Member') NOT NULL DEFAULT 'Permanent'");
    }
};
