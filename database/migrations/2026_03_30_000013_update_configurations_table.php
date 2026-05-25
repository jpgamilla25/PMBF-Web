<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->string('type', 20)->default('text')->after('value'); // text, number, decimal, boolean, select, json
            $table->text('options')->nullable()->after('type'); // JSON for select options
            $table->string('suffix', 20)->nullable()->after('options'); // %, PHP, months, etc.
            $table->integer('sort_order')->default(0)->after('suffix');
        });
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn(['type', 'options', 'suffix', 'sort_order']);
        });
    }
};
