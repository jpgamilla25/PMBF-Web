<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * COS-Non Enrolled ("Non-Member") was removed as a member type, so its
     * loan rules and interest settings go with it.
     */
    public function up(): void
    {
        DB::table('configurations')->where('key', 'like', '%non_member%')->delete();
    }

    public function down(): void
    {
        // Nothing to restore: the settings are not used any more. Re-running
        // the ConfigurationSeeder from an older release would bring them back.
    }
};
