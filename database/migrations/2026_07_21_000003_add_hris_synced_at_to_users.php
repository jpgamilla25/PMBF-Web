<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // When this member's pay/employment snapshot was last refreshed
            // from HRIS. Distinct from updated_at, which moves for unrelated
            // reasons and so can't honestly answer "how current is this?".
            $table->timestamp('hris_synced_at')->nullable()->after('take_home_pay');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('hris_synced_at');
        });
    }
};
