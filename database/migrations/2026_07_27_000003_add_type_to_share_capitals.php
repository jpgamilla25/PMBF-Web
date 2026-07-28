<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a `type` discriminator to share_capitals so shares (Permanent equity)
 * and premiums (Contract of Service coverage) can live in the same table
 * while keeping their semantics distinct. Existing rows default to 'share'
 * since the earlier backfill already moved every COS row out to the premiums
 * table — the follow-up migration merges those back in with type='premium'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('share_capitals', function (Blueprint $table) {
            $table->enum('type', ['share', 'premium'])->default('share')->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('share_capitals', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
