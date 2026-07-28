<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // How interest is charged. 'flat' (constant interest on the full
            // principal) or 'diminishing' (interest on the reducing balance,
            // EMI-style). Stored per loan so a rate/method config change never
            // rewrites an existing loan's terms.
            $table->enum('interest_method', ['flat', 'diminishing'])->default('flat')->after('interest_rate');

            // The exact total payable, computed at creation from the schedule.
            // For diminishing loans there is no simple closed form after
            // rounding, so it is stored rather than recomputed.
            $table->decimal('total_payable', 14, 2)->nullable()->after('monthly_amortization');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['interest_method', 'total_payable']);
        });
    }
};
