<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A per-annum rate divides by 12 (8% p.a. → 0.6667%/month), so the stored
// monthly rate needs more than two decimals or it rounds back to 0.67.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('interest_rate', 7, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 2)->default(0)->change();
        });
    }
};
