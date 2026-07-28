<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * COS-Enrolled loans may name up to two co-makers (at least one). The
     * first stays in the existing co_maker_* columns for backward
     * compatibility; the second lives here. Both must consent before the loan
     * proceeds.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('co_maker_id_2')->nullable()->after('co_maker_id')
                ->constrained('users')->nullOnDelete();
            $table->string('co_maker_token_2')->nullable()->after('co_maker_token');
            $table->string('co_maker_status_2')->nullable()->after('co_maker_status');
            $table->timestamp('co_maker_acted_at_2')->nullable()->after('co_maker_acted_at');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('co_maker_id_2');
            $table->dropColumn(['co_maker_token_2', 'co_maker_status_2', 'co_maker_acted_at_2']);
        });
    }
};
