<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_exemption_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['below_minimum_pay', 'exceed_max_amount', 'extend_term']);
            $table->string('loan_type');
            $table->decimal('current_value', 12, 2);
            $table->decimal('required_value', 12, 2);
            $table->decimal('requested_value', 12, 2)->nullable();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_remarks')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_exemption_requests');
    }
};
