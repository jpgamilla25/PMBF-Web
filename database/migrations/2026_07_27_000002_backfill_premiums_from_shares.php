<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Move every Contract-of-Service member's share_capitals row into premiums.
 * COS members pay premiums (non-refundable coverage), not shares. Idempotent
 * via insertOrIgnore on the (user_id, year, month) unique key.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('share_capitals')
            ->join('users', 'users.id', '=', 'share_capitals.user_id')
            ->where('users.employment_type', 'Contract of Service')
            ->select(
                'share_capitals.user_id',
                'share_capitals.amount',
                'share_capitals.year',
                'share_capitals.month',
                'share_capitals.remarks',
                'share_capitals.updated_by',
                'share_capitals.created_at',
                'share_capitals.updated_at',
            )
            ->get();

        if ($rows->isEmpty()) return;

        DB::table('premiums')->insertOrIgnore($rows->map(fn ($r) => (array) $r)->all());

        DB::table('share_capitals')
            ->whereIn('user_id', function ($q) {
                $q->select('id')->from('users')->where('employment_type', 'Contract of Service');
            })
            ->delete();
    }

    public function down(): void
    {
        $rows = DB::table('premiums')->get();
        if ($rows->isEmpty()) return;

        DB::table('share_capitals')->insertOrIgnore($rows->map(fn ($r) => [
            'user_id'    => $r->user_id,
            'amount'     => $r->amount,
            'year'       => $r->year,
            'month'      => $r->month,
            'remarks'    => $r->remarks,
            'updated_by' => $r->updated_by,
            'created_at' => $r->created_at,
            'updated_at' => $r->updated_at,
        ])->all());

        DB::table('premiums')->truncate();
    }
};
