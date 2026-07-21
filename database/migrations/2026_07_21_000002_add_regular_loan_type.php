<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The enum values the loans table has always allowed, plus 'Regular'.
     * 'Temporary' is retained even though no code path offers it — dropping a
     * value that existing rows might use is not this migration's job.
     */
    private const TYPES = [
        'Salary Loan',
        'Regular',
        'Consolidated',
        'Multi-Purpose',
        'Emergency',
        'Hospitalization',
        'Temporary',
    ];

    public function up(): void
    {
        $this->setEnum(self::TYPES);
    }

    public function down(): void
    {
        // Refuse to silently destroy data: an existing Regular loan would be
        // truncated to an empty string by a narrowing ALTER.
        if (DB::table('loans')->where('loan_type', 'Regular')->exists()) {
            throw new RuntimeException(
                'Cannot roll back: loans of type "Regular" exist. Reassign them first.'
            );
        }

        $this->setEnum(array_values(array_diff(self::TYPES, ['Regular'])));
    }

    private function setEnum(array $types): void
    {
        // SQLite (test suite) stores enums as plain strings — nothing to alter.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $list = implode(', ', array_map(fn ($t) => "'" . addslashes($t) . "'", $types));

        DB::statement("ALTER TABLE loans MODIFY COLUMN loan_type ENUM({$list}) NOT NULL");
    }
};
