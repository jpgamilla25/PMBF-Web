<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('device_fingerprint', 128);
            $table->string('device_name')->nullable(); // e.g. "Chrome on Windows"
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('trusted_until');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_fingerprint']);
            $table->index(['device_fingerprint', 'trusted_until']);
        });

        // Add device_fingerprint to personal_access_tokens for session tracking
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('device_fingerprint', 128)->nullable()->after('abilities');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('device_fingerprint');
        });
        Schema::dropIfExists('trusted_devices');
    }
};
