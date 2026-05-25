<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'fmis';

    public function up(): void
    {
        Schema::connection('fmis')->create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id');
            $table->string('salary_grade')->nullable();
            $table->integer('step')->nullable();
            $table->decimal('monthly_salary', 12, 2);
            $table->decimal('base_pay', 12, 2);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_take_home', 12, 2);
            $table->date('effective_date');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('fmis')->dropIfExists('employee_salaries');
    }
};
