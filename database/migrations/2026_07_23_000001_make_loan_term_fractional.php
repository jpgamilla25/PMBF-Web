<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Loan terms were whole months only. Contract-of-Service contracts can run
     * a half-month (e.g. 5.5), so the term becomes a decimal. Existing whole
     * values (5, 12) simply become 5.00, 12.00.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('term_months', 5, 2)->default(12)->change();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Any fractional term would round on the way back to an integer
            // column; whole-month loans are unaffected.
            $table->integer('term_months')->default(12)->change();
        });
    }
};
