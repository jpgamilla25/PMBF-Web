<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('action', 60);         // config_updated | beginning_balance_updated | role_assigned
            $table->string('subject')->nullable(); // config key, employee_id, etc.
            $table->text('description')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('employment_type', 30)->nullable(); // admin context when change was made
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['action', 'created_at']);
            $table->index('employment_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
