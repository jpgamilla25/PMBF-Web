<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // COS premium contributions per payroll half-month (quincena). The two
        // halves roll up into the month's share_capitals (type='premium') row,
        // which every premium screen and report already reads.
        Schema::create('premium_quincenas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('year');
            $table->integer('month');    // 1-12
            $table->tinyInteger('quincena'); // 1 = 1st-15th, 2 = 16th-end
            $table->decimal('amount', 12, 2)->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'year', 'month', 'quincena']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('premium_quincenas');
    }
};
