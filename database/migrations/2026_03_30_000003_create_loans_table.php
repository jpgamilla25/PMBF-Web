<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('loan_type', [
                'Salary Loan',
                'Consolidated',
                'Multi-Purpose',
                'Emergency',
                'Hospitalization',
                'Temporary'
            ]);
            $table->decimal('amount', 12, 2);
            $table->text('purpose')->nullable();
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->integer('term_months')->default(12);
            $table->decimal('monthly_amortization', 12, 2)->default(0);
            $table->foreignId('co_maker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', [
                'pending',
                'receiver_approved',
                'committee_approved',
                'chairperson_approved',
                'approved',
                'released',
                'disapproved',
                'temporary_submission',
                'board_review',
                'board_approved',
                'completed',
                'defaulted'
            ])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('applied_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('loan_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
