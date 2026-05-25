<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE loan_approvals MODIFY COLUMN level ENUM('admin', 'receiver', 'loan_committee', 'chairperson', 'board', 'release') NOT NULL");
        DB::statement("ALTER TABLE loan_approvals MODIFY COLUMN status ENUM('pending', 'approved', 'disapproved', 'released') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE loan_approvals MODIFY COLUMN level ENUM('receiver', 'loan_committee', 'chairperson', 'board') NOT NULL");
        DB::statement("ALTER TABLE loan_approvals MODIFY COLUMN status ENUM('pending', 'approved', 'disapproved') NOT NULL DEFAULT 'pending'");
    }
};
