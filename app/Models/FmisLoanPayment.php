<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FmisLoanPayment extends Model
{
    protected $fillable = [
        'employee_id',
        'year',
        'month',
        'amount',
        'dv_number',
        'dv_date',
        'fund',
        'line_no',
        'voided',
        'fmis_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'voided' => 'boolean',
            'line_no' => 'integer',
            'dv_date' => 'date',
            'fmis_updated_at' => 'datetime',
        ];
    }

    /**
     * Joined at query time via employee_id — FMIS payments exist independently
     * of PMBF registration.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'employee_id', 'employee_id');
    }
}
