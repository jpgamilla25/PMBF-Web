<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One month's deduction toward a dependent's premium. */
class DependentPremiumPayment extends Model
{
    protected $fillable = ['dependent_id', 'year', 'month', 'amount', 'updated_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function dependent()
    {
        return $this->belongsTo(Dependent::class);
    }
}
