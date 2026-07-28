<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUSES = [
        'co_maker_pending', 'co_maker_declined', 'admin_pending', 'pending',
        'receiver_approved', 'committee_approved', 'chairperson_approved',
        'approved', 'released', 'disapproved', 'cancelled',
        'temporary_submission', 'board_review', 'board_approved',
        'completed', 'defaulted',
        // A loan whose remaining balance was rolled into a renewal loan.
        'renewed',
    ];

    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('renewed_from_loan_id')->nullable()->after('start_date')
                ->constrained('loans')->nullOnDelete();
        });

        $this->setStatusEnum(self::STATUSES);
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('renewed_from_loan_id');
        });

        $this->setStatusEnum(array_values(array_diff(self::STATUSES, ['renewed'])));
    }

    private function setStatusEnum(array $statuses): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $list = implode(', ', array_map(fn ($s) => "'{$s}'", $statuses));
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM({$list}) NOT NULL DEFAULT 'pending'");
    }
};
