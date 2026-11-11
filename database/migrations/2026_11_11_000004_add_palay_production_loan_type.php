<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Keep in step with Loan::TYPES. */
    private const TYPES = [
        'Salary Loan',
        'Regular',
        'Consolidated',
        'Multi-Purpose',
        'Emergency',
        'Hospitalization',
        'Palay Production',
        'Temporary',
    ];

    public function up(): void
    {
        $this->setEnum(self::TYPES);
    }

    public function down(): void
    {
        if (DB::table('loans')->where('loan_type', 'Palay Production')->exists()) {
            throw new RuntimeException(
                'Cannot roll back: loans of type "Palay Production" exist. Reassign them first.'
            );
        }

        $this->setEnum(array_values(array_diff(self::TYPES, ['Palay Production'])));
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
