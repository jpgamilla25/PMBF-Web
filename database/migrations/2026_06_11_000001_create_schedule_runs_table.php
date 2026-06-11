<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command', 200);
            $table->string('expression', 100)->nullable();
            $table->enum('status', ['running', 'success', 'failed', 'skipped'])->default('running');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->integer('exit_code')->nullable();
            $table->text('output_excerpt')->nullable();
            $table->boolean('manual')->default(false);
            $table->timestamps();

            $table->index(['command', 'started_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_runs');
    }
};
