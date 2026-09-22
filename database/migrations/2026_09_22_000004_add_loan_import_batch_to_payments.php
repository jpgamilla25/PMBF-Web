<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A legacy loan usually arrives part-paid, so the loan importer writes an
 * opening payment for whatever was already collected. That payment is a real
 * ledger row — it has to be, or the member's balance would show the whole loan
 * still owing — which means the importer must be able to tell its own opening
 * entries apart from payments posted since.
 *
 * Without this column, rollback could not distinguish "this loan has payments
 * I created" from "this loan has payments someone posted afterwards", and
 * would refuse to undo any part-paid import.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('loan_import_batch_id')
                ->nullable()
                ->after('import_batch_id')
                ->constrained('loan_import_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_import_batch_id');
        });
    }
};
