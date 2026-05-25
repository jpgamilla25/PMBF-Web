<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending','receiver_approved','committee_approved','chairperson_approved','approved','released','disapproved','cancelled','temporary_submission','board_review','board_approved','completed','defaulted') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending','receiver_approved','committee_approved','chairperson_approved','approved','released','disapproved','temporary_submission','board_review','board_approved','completed','defaulted') DEFAULT 'pending'");
    }
};
