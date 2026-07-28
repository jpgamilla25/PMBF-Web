<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dependents', function (Blueprint $table) {
            // Marks a dependent as a designated beneficiary for benefit claims.
            $table->boolean('is_beneficiary')->default(false)->after('coverage_type');
        });
    }

    public function down(): void
    {
        Schema::table('dependents', function (Blueprint $table) {
            $table->dropColumn('is_beneficiary');
        });
    }
};
