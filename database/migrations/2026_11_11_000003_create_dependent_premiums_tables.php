<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Health & medical assistance premium owed for a dependent in a year.
        Schema::create('dependent_premiums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependent_id')->constrained()->cascadeOnDelete();
            $table->integer('year');
            $table->decimal('premium', 12, 2)->default(0);
            $table->decimal('monthly_deduction', 12, 2)->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['dependent_id', 'year']);
        });

        // What was actually deducted toward that premium, month by month.
        Schema::create('dependent_premium_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependent_id')->constrained()->cascadeOnDelete();
            $table->integer('year');
            $table->integer('month'); // 1-12
            $table->decimal('amount', 12, 2)->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['dependent_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependent_premium_payments');
        Schema::dropIfExists('dependent_premiums');
    }
};
