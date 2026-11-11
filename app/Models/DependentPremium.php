<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Health & medical assistance premium owed for a dependent in one year. */
class DependentPremium extends Model
{
    // Laravel would pluralise this to "dependent_premia".
    protected $table = 'dependent_premiums';

    protected $fillable = ['dependent_id', 'year', 'premium', 'monthly_deduction', 'updated_by'];

    protected function casts(): array
    {
        return [
            'premium' => 'decimal:2',
            'monthly_deduction' => 'decimal:2',
        ];
    }

    public function dependent()
    {
        return $this->belongsTo(Dependent::class);
    }
}
