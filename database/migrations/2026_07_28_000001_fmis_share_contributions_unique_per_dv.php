<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll deductions for shares/premiums are grouped by DV — the March 1-15
 * cutoff and the March 16-31 cutoff each issue a separate Disbursement
 * Voucher covering many employees. Under the old unique (employee_id, year,
 * month) key, the second DV silently overwrote the first, halving the
 * recorded contribution for split-cutoff months. Same shape as
 * fmis_loan_payments — one row per (employee, DV).
 *
 * share_capitals.amount is now the aggregate: SUM of every DV a member has
 * for a given (year, month). SyncSharesFromFmis handles the recompute.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fmis_share_contributions', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'year', 'month']);
        });

        Schema::table('fmis_share_contributions', function (Blueprint $table) {
            $table->unique(['employee_id', 'dv_number']);
            $table->index(['employee_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::table('fmis_share_contributions', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'dv_number']);
            $table->dropIndex(['employee_id', 'year', 'month']);
        });

        Schema::table('fmis_share_contributions', function (Blueprint $table) {
            $table->unique(['employee_id', 'year', 'month']);
        });
    }
};
