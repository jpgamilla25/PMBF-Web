<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->boolean('requires_admin_approval')->default(false)->after('co_maker_acted_at');
        });

        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM(
            'co_maker_pending',
            'co_maker_declined',
            'admin_pending',
            'pending',
            'receiver_approved',
            'committee_approved',
            'chairperson_approved',
            'approved',
            'released',
            'disapproved',
            'cancelled',
            'temporary_submission',
            'board_review',
            'board_approved',
            'completed',
            'defaulted'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('requires_admin_approval');
        });

        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM(
            'co_maker_pending',
            'co_maker_declined',
            'pending',
            'receiver_approved',
            'committee_approved',
            'chairperson_approved',
            'approved',
            'released',
            'disapproved',
            'cancelled',
            'temporary_submission',
            'board_review',
            'board_approved',
            'completed',
            'defaulted'
        ) NOT NULL DEFAULT 'pending'");
    }
};
