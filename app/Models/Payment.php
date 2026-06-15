<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'loan_id',
        'recorded_by',
        'amount',
        'or_number',
        'payment_method',
        'payment_date',
        'remarks',
        'receipt_path',
        'fmis_dv_number',
        'auto_linked',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'auto_linked' => 'boolean',
        ];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
