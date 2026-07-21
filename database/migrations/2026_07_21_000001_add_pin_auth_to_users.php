<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The original column was varchar(6) — a bcrypt hash needs 60.
            $table->string('pin', 255)->nullable()->change();
            $table->timestamp('pin_set_at')->nullable()->after('pin');
            $table->unsignedTinyInteger('pin_attempts')->default(0)->after('pin_set_at');
            $table->timestamp('pin_locked_until')->nullable()->after('pin_attempts');
        });

        // Any PIN written before this migration would be plaintext — discard it.
        DB::table('users')->whereNotNull('pin')->update(['pin' => null]);

        // Widening an ENUM needs raw DDL. SQLite (used by the test suite) has
        // no ENUM type at all — the column is already a plain string there,
        // so there is nothing to widen.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE otp_codes MODIFY COLUMN type
                 ENUM('registration', 'login', 'loan_application', 'pin_reset')
                 NOT NULL DEFAULT 'registration'"
            );
        }
    }

    public function down(): void
    {
        DB::table('users')->update(['pin' => null]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pin_set_at', 'pin_attempts', 'pin_locked_until']);
            $table->string('pin', 6)->nullable()->change();
        });

        DB::table('otp_codes')->where('type', 'pin_reset')->delete();

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE otp_codes MODIFY COLUMN type
                 ENUM('registration', 'login', 'loan_application')
                 NOT NULL DEFAULT 'registration'"
            );
        }
    }
};
