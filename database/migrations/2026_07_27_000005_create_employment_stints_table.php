<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local mirror of the HRIS employment-stint history
 * (/api/v2/hris/employees/{id}/employment). Keyed on employee_id (string) so
 * both `users.employee_id` and `fmis_share_contributions.employee_id` can look
 * up "what type was this employee at (year, month)?" without a users join.
 * `type` is normalized at the boundary — HRIS uses 'permanent'/'cos', we
 * store 'share'/'premium' to match `share_capitals.type`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_stints', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->index();
            $table->enum('type', ['share', 'premium']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamp('hris_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_stints');
    }
};
