<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * api-center now emits one row per DV_Details deduction line (with an added
 * `line_no` field 1-based per employee+DV+fund). Two lines under the same DV
 * — e.g. current period + arrears, or two fund books contributing to the
 * same DV — used to collide on the old `(employee_id, dv_number)` unique.
 *
 * Add a `line_no` column defaulting to 1 (so existing rows backfill cleanly
 * against the first line each returning sync produces) and swap the unique
 * to `(employee_id, dv_number, fund, line_no)`. `fund` was already stored
 * but not part of the key; including it disambiguates a DV_Header shared
 * across fund books.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fmis_share_contributions', function (Blueprint $table) {
            $table->unsignedInteger('line_no')->default(1)->after('fund');
            $table->dropUnique('fmis_share_contributions_employee_id_dv_number_unique');
        });

        Schema::table('fmis_share_contributions', function (Blueprint $table) {
            $table->unique(['employee_id', 'dv_number', 'fund', 'line_no'], 'fmis_shares_employee_dv_fund_line_unique');
        });

        Schema::table('fmis_loan_payments', function (Blueprint $table) {
            $table->unsignedInteger('line_no')->default(1)->after('fund');
            $table->dropUnique('fmis_loan_payments_employee_dv_unique');
        });

        Schema::table('fmis_loan_payments', function (Blueprint $table) {
            $table->unique(['employee_id', 'dv_number', 'fund', 'line_no'], 'fmis_payments_employee_dv_fund_line_unique');
        });
    }

    public function down(): void
    {
        Schema::table('fmis_share_contributions', function (Blueprint $table) {
            $table->dropUnique('fmis_shares_employee_dv_fund_line_unique');
        });

        // A restore of the old key would fail if the table now has genuine
        // multi-line/multi-fund duplicates. Collapse them into the first line
        // per (employee, dv_number) before re-adding the tighter unique.
        DB::statement("
            DELETE s1 FROM fmis_share_contributions s1
            INNER JOIN fmis_share_contributions s2
              ON s1.employee_id = s2.employee_id
             AND s1.dv_number = s2.dv_number
             AND s1.id > s2.id
        ");

        Schema::table('fmis_share_contributions', function (Blueprint $table) {
            $table->dropColumn('line_no');
        });

        Schema::table('fmis_share_contributions', function (Blueprint $table) {
            $table->unique(['employee_id', 'dv_number'], 'fmis_share_contributions_employee_id_dv_number_unique');
        });

        Schema::table('fmis_loan_payments', function (Blueprint $table) {
            $table->dropUnique('fmis_payments_employee_dv_fund_line_unique');
        });

        DB::statement("
            DELETE p1 FROM fmis_loan_payments p1
            INNER JOIN fmis_loan_payments p2
              ON p1.employee_id = p2.employee_id
             AND p1.dv_number = p2.dv_number
             AND p1.id > p2.id
        ");

        Schema::table('fmis_loan_payments', function (Blueprint $table) {
            $table->dropColumn('line_no');
        });

        Schema::table('fmis_loan_payments', function (Blueprint $table) {
            $table->unique(['employee_id', 'dv_number'], 'fmis_loan_payments_employee_dv_unique');
        });
    }
};
