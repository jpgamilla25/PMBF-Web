<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A COS member's premium contribution for one payroll half-month. */
class PremiumQuincena extends Model
{
    protected $fillable = ['user_id', 'year', 'month', 'quincena', 'amount', 'updated_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
