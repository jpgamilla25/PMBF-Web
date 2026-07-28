<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move every row from the premiums table back into share_capitals with
 * type='premium', then drop the premiums table. This unifies storage while
 * preserving the historical share-vs-premium distinction on each row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('premiums')) {
            $rows = DB::table('premiums')->get();

            if ($rows->isNotEmpty()) {
                DB::table('share_capitals')->insertOrIgnore($rows->map(fn ($r) => [
                    'user_id'    => $r->user_id,
                    'amount'     => $r->amount,
                    'type'       => 'premium',
                    'year'       => $r->year,
                    'month'      => $r->month,
                    'remarks'    => $r->remarks,
                    'updated_by' => $r->updated_by,
                    'created_at' => $r->created_at,
                    'updated_at' => $r->updated_at,
                ])->all());
            }

            Schema::dropIfExists('premiums');
        }
    }

    public function down(): void
    {
        Schema::create('premiums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 12, 2)->default(0);
            $table->integer('year');
            $table->integer('month');
            $table->text('remarks')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'year', 'month']);
            $table->index(['year', 'month']);
        });

        $premiumRows = DB::table('share_capitals')->where('type', 'premium')->get();

        if ($premiumRows->isNotEmpty()) {
            DB::table('premiums')->insertOrIgnore($premiumRows->map(fn ($r) => [
                'user_id'    => $r->user_id,
                'amount'     => $r->amount,
                'year'       => $r->year,
                'month'      => $r->month,
                'remarks'    => $r->remarks,
                'updated_by' => $r->updated_by,
                'created_at' => $r->created_at,
                'updated_at' => $r->updated_at,
            ])->all());

            DB::table('share_capitals')->where('type', 'premium')->delete();
        }
    }
};
