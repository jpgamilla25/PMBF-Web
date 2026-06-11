<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FmisShareContribution extends Model
{
    protected $fillable = [
        'employee_id',
        'year',
        'month',
        'amount',
        'dv_number',
        'dv_date',
        'fund',
        'voided',
        'fmis_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'voided' => 'boolean',
            'dv_date' => 'date',
            'fmis_updated_at' => 'datetime',
        ];
    }

    /**
     * Belongs-to inferred at query time via employee_id (no FK column —
     * employees may register/unregister independently of FMIS data).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'employee_id', 'employee_id');
    }
}
