<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fmis_loan_payments', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 50);
            $table->integer('year');
            $table->integer('month');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('dv_number', 100)->nullable();
            $table->date('dv_date')->nullable();
            $table->string('fund', 50)->nullable();
            $table->boolean('voided')->default(false);
            $table->timestamp('fmis_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'month']);
            $table->index('employee_id');
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fmis_loan_payments');
    }
};
