<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk loan import, mirroring the payment importer.
 *
 * Importing the same legacy file twice would otherwise create a second copy of
 * every loan — and a duplicated loan quietly doubles what a member appears to
 * owe. Same two guards as payments: the file's hash per batch, and a UNIQUE
 * per-row fingerprint over (member, type, principal, application date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('file_hash', 64);
            $table->string('file_path')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->decimal('amount_total', 14, 2)->default(0);
            $table->enum('status', ['committed', 'rolled_back'])->default('committed');
            $table->foreignId('rolled_back_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->index('file_hash');
            $table->index(['status', 'created_at']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('import_batch_id')
                ->nullable()
                ->after('user_id')
                ->constrained('loan_import_batches')
                ->nullOnDelete();
            $table->string('dedupe_hash', 64)->nullable()->after('remarks');
        });

        // Backfill so loans already in the system take part in duplicate
        // detection the first time a legacy file is uploaded.
        DB::statement("
            UPDATE loans
               SET dedupe_hash = SHA2(
                   CONCAT_WS('|',
                       user_id,
                       loan_type,
                       CAST(amount AS DECIMAL(12,2)),
                       DATE_FORMAT(applied_at, '%Y-%m-%d')
                   ), 256)
        ");

        // Genuine repeat loans (same member, type, amount and day) already
        // exist in the data, so keep the earliest on the canonical hash and
        // salt the rest — otherwise the UNIQUE index cannot be created.
        DB::statement("
            UPDATE loans l
              JOIN (
                    SELECT id
                      FROM (
                            SELECT id,
                                   ROW_NUMBER() OVER (
                                       PARTITION BY dedupe_hash ORDER BY id
                                   ) AS rn
                              FROM loans
                           ) ranked
                     WHERE ranked.rn > 1
                   ) dupes ON dupes.id = l.id
               SET l.dedupe_hash = SHA2(CONCAT(l.dedupe_hash, '|dup:', l.id), 256)
        ");

        Schema::table('loans', function (Blueprint $table) {
            $table->unique('dedupe_hash', 'loans_dedupe_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropUnique('loans_dedupe_hash_unique');
            $table->dropConstrainedForeignId('import_batch_id');
            $table->dropColumn('dedupe_hash');
        });

        Schema::dropIfExists('loan_import_batches');
    }
};
