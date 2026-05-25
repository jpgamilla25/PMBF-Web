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
            $table->string('co_maker_token', 64)->nullable()->unique()->after('co_maker_id');
            $table->enum('co_maker_status', ['pending', 'approved', 'declined'])->nullable()->after('co_maker_token');
            $table->timestamp('co_maker_acted_at')->nullable()->after('co_maker_status');
        });

        // Add new statuses to enum
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

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['co_maker_token', 'co_maker_status', 'co_maker_acted_at']);
        });

        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM(
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
