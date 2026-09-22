<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk payment posting.
 *
 * Manual (pre-system) loans carry no reference number — only the member's
 * employee id and an amount — so a re-uploaded payroll file would otherwise
 * post every payment a second time with nothing to catch it. Two guards:
 *
 *   • `payment_import_batches.file_hash` — the SHA-256 of the uploaded bytes,
 *     so the very same file is recognised on the way in.
 *   • `payments.dedupe_hash` — a per-row fingerprint with a UNIQUE index, so
 *     even a re-typed or re-cut file cannot post the same payment twice, and
 *     two admins uploading at once still can't race past each other.
 *
 * The row fingerprint is SHA-256 over
 *     loan_id|payment_date|amount|OR-NUMBER
 * which is exactly what "the same payment" means for a loan. A genuine second
 * payment of the same amount on the same day (no OR to tell them apart) is
 * still postable — the operator ticks `allow_duplicate` in the sheet and the
 * row gets a nonce mixed into its fingerprint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_import_batches', function (Blueprint $table) {
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

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('import_batch_id')
                ->nullable()
                ->after('recorded_by')
                ->constrained('payment_import_batches')
                ->nullOnDelete();
            $table->string('dedupe_hash', 64)->nullable()->after('receipt_path');
        });

        // Backfill existing payments so history participates in duplicate
        // detection from day one — otherwise the first import of a file
        // covering already-posted months would sail straight through.
        //
        // CAST to DECIMAL(12,2) renders "5000.00" (FORMAT() would inject a
        // thousands separator); PHP mirrors it with number_format($x, 2, '.', '').
        DB::statement("
            UPDATE payments
               SET dedupe_hash = SHA2(
                   CONCAT_WS('|',
                       loan_id,
                       DATE_FORMAT(payment_date, '%Y-%m-%d'),
                       CAST(amount AS DECIMAL(12,2)),
                       UPPER(TRIM(COALESCE(or_number, '')))
                   ), 256)
        ");

        // Rows that are already duplicates of each other under that key must
        // not block the UNIQUE index. Keep the earliest row on the canonical
        // hash and push the rest onto id-salted ones — they stay in the ledger,
        // and future imports still collide against the row that matters.
        DB::statement("
            UPDATE payments p
              JOIN (
                    SELECT id
                      FROM (
                            SELECT id,
                                   ROW_NUMBER() OVER (
                                       PARTITION BY dedupe_hash ORDER BY id
                                   ) AS rn
                              FROM payments
                           ) ranked
                     WHERE ranked.rn > 1
                   ) dupes ON dupes.id = p.id
               SET p.dedupe_hash = SHA2(CONCAT(p.dedupe_hash, '|dup:', p.id), 256)
        ");

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('dedupe_hash', 'payments_dedupe_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_dedupe_hash_unique');
            $table->dropConstrainedForeignId('import_batch_id');
            $table->dropColumn('dedupe_hash');
        });

        Schema::dropIfExists('payment_import_batches');
    }
};
