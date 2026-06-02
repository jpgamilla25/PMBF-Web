<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris';

    public function up(): void
    {
        Schema::connection('hris')->create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();
            $table->string('email')->unique();
            $table->string('mobile')->nullable();
            $table->enum('employment_type', ['Contract of Service', 'Permanent', 'Non-Member'])->default('Permanent');
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->decimal('base_pay', 12, 2)->default(0);
            $table->decimal('take_home_pay', 12, 2)->default(0);
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->enum('status', ['Active', 'Inactive', 'Resigned'])->default('Active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('hris')->dropIfExists('employees');
    }
};
