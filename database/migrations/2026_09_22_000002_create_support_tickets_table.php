<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support tickets raised from the PMBF Assistant chat widget.
 *
 * The widget is reachable before sign-in, so a ticket stands on its own: the
 * employee id and email are captured on the form rather than assumed from a
 * session. `user_id` is linked when the employee id matches a member, which is
 * what lets the admin list show a real name next to the ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_id', 50);
            $table->string('email');
            $table->string('subject');
            $table->text('message');
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            // Whether the "your ticket is resolved" mail actually went out —
            // a mail failure must not silently look like the member was told.
            $table->boolean('resolution_emailed')->default(false);
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
