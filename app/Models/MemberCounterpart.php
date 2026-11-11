<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** PhilRice counterpart contribution to a member's equity for one month. */
class MemberCounterpart extends Model
{
    protected $fillable = ['user_id', 'year', 'month', 'amount', 'updated_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
