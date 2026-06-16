<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FMIS now returns one row per DV (a single month can have multiple
        // payroll DVs — typically 1st half + 2nd half), so the natural unique
        // key shifts from (employee_id, year, month) to (employee_id, dv_number).
        Schema::table('fmis_loan_payments', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'year', 'month']);
            $table->unique(['employee_id', 'dv_number'], 'fmis_loan_payments_employee_dv_unique');
        });
    }

    public function down(): void
    {
        Schema::table('fmis_loan_payments', function (Blueprint $table) {
            $table->dropUnique('fmis_loan_payments_employee_dv_unique');
            $table->unique(['employee_id', 'year', 'month']);
        });
    }
};
