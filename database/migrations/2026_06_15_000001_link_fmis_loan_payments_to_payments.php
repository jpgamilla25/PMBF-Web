<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('fmis_dv_number', 100)->nullable()->after('or_number');
            $table->boolean('auto_linked')->default(false)->after('fmis_dv_number');
            $table->index('fmis_dv_number');
        });

        Schema::table('fmis_loan_payments', function (Blueprint $table) {
            $table->timestamp('linked_at')->nullable()->after('fmis_updated_at');
            $table->index('linked_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['fmis_dv_number']);
            $table->dropColumn(['fmis_dv_number', 'auto_linked']);
        });

        Schema::table('fmis_loan_payments', function (Blueprint $table) {
            $table->dropIndex(['linked_at']);
            $table->dropColumn('linked_at');
        });
    }
};
